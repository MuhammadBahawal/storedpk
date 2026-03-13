<?php

declare(strict_types=1);

require_once __DIR__ . '/catalog.php';

function storeEnsureSession(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
}

function storeFlashSet(string $key, array $payload): void
{
    storeEnsureSession();
    $_SESSION['store_flash'][$key] = $payload;
}

function storeFlashPull(string $key): ?array
{
    storeEnsureSession();
    if (!isset($_SESSION['store_flash'][$key]) || !is_array($_SESSION['store_flash'][$key])) {
        return null;
    }

    $payload = $_SESSION['store_flash'][$key];
    unset($_SESSION['store_flash'][$key]);

    return $payload;
}

function storeAllowedTones(): array
{
    return ['rose', 'peach', 'sand', 'mist', 'mint'];
}

function storeAvailabilityStatuses(): array
{
    return ['in_stock', 'sold_out', 'out_of_stock'];
}

function storeOrderStatuses(): array
{
    return ['pending', 'confirmed', 'processing', 'shipped', 'delivered', 'cancelled'];
}

function storeNormalizeAvailability(string $availability, int $stockQty): string
{
    if (!in_array($availability, storeAvailabilityStatuses(), true)) {
        $availability = $stockQty > 0 ? 'in_stock' : 'out_of_stock';
    }

    if ($availability === 'in_stock' && $stockQty <= 0) {
        return 'out_of_stock';
    }

    return $availability;
}

function storeStockLabel(string $availability, int $stockQty): string
{
    if ($availability === 'sold_out') {
        return 'Sold Out';
    }
    if ($availability === 'out_of_stock' || $stockQty <= 0) {
        return 'Out of Stock';
    }

    return 'In Stock';
}

function storeEnsureColumn(mysqli $db, string $table, string $column, string $definition): void
{
    $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    $safeColumn = preg_replace('/[^a-zA-Z0-9_]/', '', $column);
    if ($safeTable === '' || $safeColumn === '') {
        return;
    }

    $check = @$db->query("SHOW COLUMNS FROM `{$safeTable}` LIKE '{$safeColumn}'");
    if ($check && $check->num_rows > 0) {
        return;
    }

    @$db->query("ALTER TABLE `{$safeTable}` ADD COLUMN `{$safeColumn}` {$definition}");
}

function storeEnsureIndex(mysqli $db, string $table, string $indexName, string $indexDefinition): void
{
    $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
    $safeIndex = preg_replace('/[^a-zA-Z0-9_]/', '', $indexName);
    if ($safeTable === '' || $safeIndex === '') {
        return;
    }

    $check = @$db->query("SHOW INDEX FROM `{$safeTable}` WHERE Key_name = '{$safeIndex}'");
    if ($check && $check->num_rows > 0) {
        return;
    }

    @$db->query("ALTER TABLE `{$safeTable}` ADD {$indexDefinition}");
}

function storeGenerateProductSlug(string $name, int $excludeId = 0): string
{
    storeEnsureSchema();
    $db = db();
    if (!$db) {
        return slugify($name);
    }

    $base = slugify($name);
    $candidate = $base;
    $counter = 1;

    while (true) {
        $sql = 'SELECT COUNT(*) AS total FROM products WHERE slug = ?';
        if ($excludeId > 0) {
            $sql .= ' AND id <> ' . $excludeId;
        }
        $stmt = $db->prepare($sql);
        if (!$stmt) {
            return $candidate;
        }

        $stmt->bind_param('s', $candidate);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : ['total' => 0];
        $stmt->close();

        if ((int) ($row['total'] ?? 0) === 0) {
            return $candidate;
        }

        $counter++;
        $candidate = $base . '-' . $counter;
    }
}

function storeEnsureSchema(): void
{
    static $done = false;
    static $running = false;
    if ($done) {
        return;
    }
    if ($running) {
        return;
    }
    $running = true;

    try {
        ensureCatalogSchema();

        $db = db();
        if (!$db) {
            return;
        }

        storeEnsureColumn($db, 'products', 'slug', 'VARCHAR(170) NULL AFTER name');
        storeEnsureColumn($db, 'products', 'cost_price', 'DECIMAL(10,2) NOT NULL DEFAULT 0 AFTER compare_price');
        storeEnsureColumn($db, 'products', 'stock_qty', 'INT NOT NULL DEFAULT 0 AFTER cost_price');
        storeEnsureColumn($db, 'products', 'availability', "VARCHAR(20) NOT NULL DEFAULT 'in_stock' AFTER stock_qty");
        storeEnsureColumn($db, 'products', 'is_active', 'TINYINT(1) NOT NULL DEFAULT 1 AFTER is_sold_out');
        storeEnsureColumn($db, 'products', 'updated_at', 'TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP AFTER created_at');

        storeEnsureIndex($db, 'products', 'uq_products_slug', 'UNIQUE KEY uq_products_slug (slug)');

        @$db->query(
        "CREATE TABLE IF NOT EXISTS users (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            full_name VARCHAR(120) NOT NULL,
            email VARCHAR(160) NOT NULL,
            phone VARCHAR(40) NOT NULL DEFAULT '',
            password_hash VARCHAR(255) NOT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_users_email (email)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

        @$db->query(
        "CREATE TABLE IF NOT EXISTS admin_users (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            full_name VARCHAR(120) NOT NULL,
            email VARCHAR(160) NOT NULL,
            password_hash VARCHAR(255) NOT NULL,
            is_active TINYINT(1) NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            last_login_at TIMESTAMP NULL DEFAULT NULL,
            UNIQUE KEY uq_admin_users_email (email)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

        @$db->query(
        "CREATE TABLE IF NOT EXISTS cart_items (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            product_id INT UNSIGNED NOT NULL,
            quantity INT NOT NULL DEFAULT 1,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_cart_user_product (user_id, product_id),
            INDEX idx_cart_user_id (user_id),
            INDEX idx_cart_product_id (product_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

        @$db->query(
        "CREATE TABLE IF NOT EXISTS orders (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            order_number VARCHAR(30) NOT NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            payment_method VARCHAR(30) NOT NULL DEFAULT 'cod',
            payment_status VARCHAR(30) NOT NULL DEFAULT 'unpaid',
            subtotal DECIMAL(10,2) NOT NULL DEFAULT 0,
            shipping_fee DECIMAL(10,2) NOT NULL DEFAULT 0,
            discount_amount DECIMAL(10,2) NOT NULL DEFAULT 0,
            total DECIMAL(10,2) NOT NULL DEFAULT 0,
            recipient_name VARCHAR(140) NOT NULL,
            phone VARCHAR(40) NOT NULL,
            email VARCHAR(160) NOT NULL,
            address_line VARCHAR(255) NOT NULL,
            city VARCHAR(80) NOT NULL,
            province VARCHAR(80) NOT NULL DEFAULT '',
            postal_code VARCHAR(20) NOT NULL DEFAULT '',
            notes TEXT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_orders_order_number (order_number),
            INDEX idx_orders_user_id (user_id),
            INDEX idx_orders_status (status),
            INDEX idx_orders_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

        @$db->query(
        "CREATE TABLE IF NOT EXISTS order_items (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            order_id INT UNSIGNED NOT NULL,
            product_id INT UNSIGNED NULL,
            product_name VARCHAR(180) NOT NULL,
            category_name VARCHAR(120) NOT NULL DEFAULT '',
            quantity INT NOT NULL DEFAULT 1,
            unit_price DECIMAL(10,2) NOT NULL DEFAULT 0,
            cost_price DECIMAL(10,2) NOT NULL DEFAULT 0,
            line_total DECIMAL(10,2) NOT NULL DEFAULT 0,
            image_path VARCHAR(255) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_order_items_order_id (order_id),
            INDEX idx_order_items_product_id (product_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

        @$db->query(
        "CREATE TABLE IF NOT EXISTS purchase_entries (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            product_id INT UNSIGNED NOT NULL,
            quantity INT NOT NULL,
            cost_price DECIMAL(10,2) NOT NULL DEFAULT 0,
            total_cost DECIMAL(10,2) NOT NULL DEFAULT 0,
            note VARCHAR(255) NOT NULL DEFAULT '',
            admin_id INT UNSIGNED NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_purchase_entries_product_id (product_id),
            INDEX idx_purchase_entries_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

        $slugRows = $db->query("SELECT id, name FROM products WHERE slug IS NULL OR slug = ''");
        if ($slugRows) {
            while ($row = $slugRows->fetch_assoc()) {
                $id = (int) ($row['id'] ?? 0);
                if ($id <= 0) {
                    continue;
                }

                $slug = storeGenerateProductSlug((string) ($row['name'] ?? 'product'), $id);
                $stmt = $db->prepare('UPDATE products SET slug = ? WHERE id = ?');
                if ($stmt) {
                    $stmt->bind_param('si', $slug, $id);
                    $stmt->execute();
                    $stmt->close();
                }
            }
        }

        @$db->query("UPDATE products SET availability = 'sold_out' WHERE is_sold_out = 1");
        @$db->query("UPDATE products SET availability = 'out_of_stock' WHERE availability = 'in_stock' AND stock_qty <= 0");

        $adminCount = 0;
        $countResult = $db->query('SELECT COUNT(*) AS total FROM admin_users');
        if ($countResult) {
            $row = $countResult->fetch_assoc();
            $adminCount = (int) ($row['total'] ?? 0);
        }

        if ($adminCount === 0) {
            $name = 'Store Admin';
            $email = 'admin@store.pk';
            $passwordHash = password_hash('admin123', PASSWORD_DEFAULT);
            $stmt = $db->prepare('INSERT INTO admin_users (full_name, email, password_hash, is_active) VALUES (?, ?, ?, 1)');
            if ($stmt) {
                $stmt->bind_param('sss', $name, $email, $passwordHash);
                $stmt->execute();
                $stmt->close();
            }
        }

        $done = true;
    } finally {
        $running = false;
    }
}
