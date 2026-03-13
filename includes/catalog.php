<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';

if (!function_exists('esc')) {
    function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

function slugify(string $value): string
{
    $value = strtolower(trim($value));
    $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';
    $value = trim($value, '-');

    return $value !== '' ? $value : 'category';
}

function allowedTones(): array
{
    return ['rose', 'peach', 'sand', 'mist', 'mint'];
}

function catalogNormalizeSlug(string $value, string $fallback = 'collection'): string
{
    $slug = slugify($value);
    if (strlen($slug) > 80) {
        $slug = substr($slug, 0, 80);
        $slug = rtrim($slug, '-');
    }

    return $slug !== '' ? $slug : $fallback;
}

function catalogGenerateCollectionSlug(mysqli $db, string $name, int $excludeId = 0): string
{
    $base = catalogNormalizeSlug($name, 'collection');
    $candidate = $base;
    $counter = 2;

    while (true) {
        $sql = 'SELECT COUNT(*) AS total FROM collections WHERE slug = ?';
        if ($excludeId > 0) {
            $sql .= ' AND id <> ' . $excludeId;
        }

        $stmt = $db->prepare($sql);
        if (!$stmt) {
            return $candidate;
        }

        $stmt->bind_param('s', $candidate);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : ['total' => 0];
        $stmt->close();

        if ((int) ($row['total'] ?? 0) === 0) {
            return $candidate;
        }

        $suffix = '-' . $counter;
        $candidate = substr($base, 0, max(1, 80 - strlen($suffix))) . $suffix;
        $counter++;
    }
}

function catalogGuessCollectionCategoryId(mysqli $db, string $value): ?int
{
    $value = trim($value);
    if ($value === '') {
        return null;
    }

    $candidateSlugs = [catalogNormalizeSlug($value, 'collection')];
    $normalized = strtolower($value);

    $keywordMap = [
        'nails' => ['nail', 'press', 'french', 'salon'],
        'jewelry' => ['jewel', 'ring', 'bracelet', 'chain', 'necklace', 'bangle', 'anklet'],
        'bags' => ['bag', 'pouch'],
        'fragrances' => ['perfume', 'fragrance', 'scent'],
        'makeup' => ['makeup', 'glow', 'skin', 'skincare', 'lotion', 'beauty', 'self-care', 'self care', 'self-love', 'self love'],
        'deals' => ['deal', 'save'],
    ];

    foreach ($keywordMap as $slug => $keywords) {
        foreach ($keywords as $keyword) {
            if (strpos($normalized, $keyword) !== false) {
                $candidateSlugs[] = $slug;
                break;
            }
        }
    }

    $candidateSlugs = array_values(array_unique(array_filter($candidateSlugs)));
    $stmt = $db->prepare('SELECT id FROM categories WHERE slug = ? LIMIT 1');
    if (!$stmt) {
        return null;
    }

    foreach ($candidateSlugs as $slug) {
        $stmt->bind_param('s', $slug);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : null;
        if ($row) {
            $stmt->close();
            return (int) ($row['id'] ?? 0);
        }
    }

    $stmt->close();
    return null;
}

function ensureCatalogSchema(): void
{
    static $done = false;
    if ($done) {
        return;
    }

    $db = db();
    if (!$db) {
        return;
    }

    @$db->query(
        "CREATE TABLE IF NOT EXISTS categories (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(120) NOT NULL,
            slug VARCHAR(80) NOT NULL,
            tone VARCHAR(30) NOT NULL DEFAULT 'rose',
            sort_order INT NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_categories_slug (slug),
            UNIQUE KEY uq_categories_name (name)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    @$db->query(
        "CREATE TABLE IF NOT EXISTS collections (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(120) NOT NULL,
            slug VARCHAR(80) NULL,
            tone VARCHAR(30) NOT NULL DEFAULT 'rose',
            tag VARCHAR(30) NOT NULL DEFAULT 'NEW',
            symbol VARCHAR(10) NOT NULL DEFAULT '*',
            sort_order INT NOT NULL DEFAULT 0,
            category_id INT UNSIGNED NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_collections_name (name),
            UNIQUE KEY uq_collections_slug (slug),
            INDEX idx_collections_category_id (category_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    @$db->query(
        "CREATE TABLE IF NOT EXISTS products (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            category_id INT UNSIGNED NULL,
            section_slug VARCHAR(60) NOT NULL DEFAULT '',
            name VARCHAR(140) NOT NULL,
            short_name VARCHAR(60) NOT NULL DEFAULT '',
            description TEXT NULL,
            tag VARCHAR(30) NOT NULL DEFAULT 'NEW',
            tone VARCHAR(30) NOT NULL DEFAULT 'rose',
            price DECIMAL(10,2) NOT NULL DEFAULT 0,
            compare_price DECIMAL(10,2) NULL,
            image_path VARCHAR(255) NULL,
            is_sold_out TINYINT(1) NOT NULL DEFAULT 0,
            sort_order INT NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_products_category_id (category_id),
            INDEX idx_products_section_slug (section_slug)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    ensureColumnExists($db, 'products', 'category_id', 'INT UNSIGNED NULL AFTER id');
    ensureColumnExists($db, 'products', 'section_slug', "VARCHAR(60) NOT NULL DEFAULT '' AFTER category_id");
    ensureColumnExists($db, 'products', 'short_name', "VARCHAR(60) NOT NULL DEFAULT '' AFTER name");
    ensureColumnExists($db, 'products', 'description', 'TEXT NULL AFTER short_name');
    ensureColumnExists($db, 'products', 'compare_price', 'DECIMAL(10,2) NULL AFTER price');
    ensureColumnExists($db, 'products', 'image_path', 'VARCHAR(255) NULL AFTER compare_price');
    ensureColumnExists($db, 'products', 'is_sold_out', 'TINYINT(1) NOT NULL DEFAULT 0 AFTER image_path');
    ensureColumnExists($db, 'collections', 'slug', 'VARCHAR(80) NULL AFTER name');
    ensureColumnExists($db, 'collections', 'category_id', 'INT UNSIGNED NULL AFTER sort_order');

    ensureIndexExists($db, 'products', 'idx_products_category_id', 'INDEX idx_products_category_id (category_id)');
    ensureIndexExists($db, 'products', 'uq_products_category_name', 'UNIQUE KEY uq_products_category_name (category_id, name(120))');
    ensureIndexExists($db, 'collections', 'uq_collections_slug', 'UNIQUE KEY uq_collections_slug (slug)');
    ensureIndexExists($db, 'collections', 'idx_collections_category_id', 'INDEX idx_collections_category_id (category_id)');

    @$db->query(
        "CREATE TABLE IF NOT EXISTS contact_messages (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            full_name VARCHAR(120) NOT NULL,
            email VARCHAR(160) NOT NULL,
            phone VARCHAR(40) NOT NULL DEFAULT '',
            comment TEXT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_contact_messages_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
    );

    $count = $db->query('SELECT COUNT(*) AS total FROM categories');
    $total = 0;
    if ($count) {
        $row = $count->fetch_assoc();
        $total = (int) ($row['total'] ?? 0);
    }

    if ($total === 0) {
        @$db->query(
            "INSERT INTO categories (name, slug, tone, sort_order)
             VALUES
             ('Anklets', 'anklets', 'sand', 1),
             ('Bags', 'bags', 'rose', 2),
             ('Bangles', 'bangles', 'peach', 3),
             ('Bracelets', 'bracelets', 'sand', 4),
             ('Branded Makeup', 'branded-makeup', 'mist', 5),
             ('Deals', 'deals', 'mint', 6),
             ('Eid Special', 'eid-special', 'peach', 7),
             ('Fragrances', 'fragrances', 'sand', 8),
             ('Hair Accessories', 'hair-accessories', 'rose', 9),
             ('Jewellery', 'jewelry', 'peach', 10),
             ('Jewellery Organizers', 'jewellery-organizers', 'mist', 11),
             ('Makeup', 'makeup', 'peach', 12),
             ('Nails', 'nails', 'rose', 13),
             ('Necklaces', 'necklaces', 'sand', 14),
             ('New Arrivals', 'new-arrivals', 'mist', 15),
             ('Pouches', 'pouches', 'mint', 16),
             ('Printables', 'printables', 'mist', 17),
             ('Ramadan Special', 'ramadan-special', 'peach', 18),
             ('Rings', 'rings', 'sand', 19)
             ON DUPLICATE KEY UPDATE tone = VALUES(tone), sort_order = VALUES(sort_order)"
        );
    }

    $collectionCount = $db->query('SELECT COUNT(*) AS total FROM collections');
    $collectionTotal = 0;
    if ($collectionCount) {
        $row = $collectionCount->fetch_assoc();
        $collectionTotal = (int) ($row['total'] ?? 0);
    }

    if ($collectionTotal === 0) {
        @$db->query(
            "INSERT INTO collections (name, slug, tone, tag, symbol, sort_order)
             VALUES
             ('Nail Art', 'nail-art', 'rose', 'HOT', '*', 1),
             ('Get Ready', 'get-ready', 'sand', 'NEW', '#', 2),
             ('Wedding Special', 'wedding-special', 'peach', 'SAVE', '*', 3),
             ('Jewelry', 'jewelry', 'rose', 'TREND', '+', 4),
             ('Skincare Products', 'skincare-products', 'mist', 'NEW', '*', 5),
             ('Perfume Collection', 'perfume-collection', 'mint', 'TOP', '#', 6),
             ('Self-care Kit', 'self-care-kit', 'peach', 'KIT', '*', 7),
             ('LS Store.pk', 'ls-store-pk', 'sand', 'NEW', '+', 8),
             ('Jewelry Box', 'jewelry-box', 'mist', 'TOP', '#', 9),
             ('Skincare', 'skincare', 'rose', 'SAVE', '*', 10),
             ('Self-love', 'self-love', 'peach', 'NEW', '+', 11),
             ('Nails', 'nails', 'mint', 'BEST', '*', 12),
             ('Makeup Branding', 'makeup-branding', 'sand', 'HOT', '#', 13),
             ('Lotion', 'lotion', 'peach', 'NEW', '*', 14),
             ('Bags', 'bags', 'rose', 'TOP', '+', 15),
             ('Mailing', 'mailing', 'mist', 'NEW', '#', 16),
             ('Warmth', 'warmth', 'sand', 'GLOW', '*', 17)
              ON DUPLICATE KEY UPDATE
                slug = VALUES(slug),
                tone = VALUES(tone),
                tag = VALUES(tag),
                symbol = VALUES(symbol),
                sort_order = VALUES(sort_order)"
        );
    }

    @$db->query(
        "INSERT INTO categories (name, slug, tone, sort_order)
         SELECT s.title, s.slug, 'rose', s.sort_order
         FROM sections s
         ON DUPLICATE KEY UPDATE name = VALUES(name), sort_order = VALUES(sort_order)"
    );

    @$db->query(
        "UPDATE categories
         SET slug = 'jewelry'
         WHERE slug = 'jewellery'
           AND NOT EXISTS (
                SELECT 1
                FROM (SELECT slug FROM categories) c2
                WHERE c2.slug = 'jewelry'
           )"
    );

    @$db->query(
        "UPDATE products p
         INNER JOIN categories c ON c.slug = p.section_slug
         SET p.category_id = c.id
         WHERE p.category_id IS NULL"
    );

    $collectionSlugRows = $db->query("SELECT id, name FROM collections WHERE slug IS NULL OR slug = ''");
    if ($collectionSlugRows) {
        while ($row = $collectionSlugRows->fetch_assoc()) {
            $collectionId = (int) ($row['id'] ?? 0);
            if ($collectionId <= 0) {
                continue;
            }

            $slug = catalogGenerateCollectionSlug($db, (string) ($row['name'] ?? 'collection'), $collectionId);
            $stmt = $db->prepare('UPDATE collections SET slug = ? WHERE id = ?');
            if ($stmt) {
                $stmt->bind_param('si', $slug, $collectionId);
                $stmt->execute();
                $stmt->close();
            }
        }
    }

    $collectionCategoryRows = $db->query('SELECT id, name FROM collections WHERE category_id IS NULL');
    if ($collectionCategoryRows) {
        while ($row = $collectionCategoryRows->fetch_assoc()) {
            $collectionId = (int) ($row['id'] ?? 0);
            if ($collectionId <= 0) {
                continue;
            }

            $categoryId = catalogGuessCollectionCategoryId($db, (string) ($row['name'] ?? ''));
            if ($categoryId === null || $categoryId <= 0) {
                continue;
            }

            $stmt = $db->prepare('UPDATE collections SET category_id = ? WHERE id = ?');
            if ($stmt) {
                $stmt->bind_param('ii', $categoryId, $collectionId);
                $stmt->execute();
                $stmt->close();
            }
        }
    }

    $done = true;
}

function ensureColumnExists(mysqli $db, string $table, string $column, string $definition): void
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

function ensureIndexExists(mysqli $db, string $table, string $indexName, string $indexDefinition): void
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

function getCatalogCategories(): array
{
    ensureCatalogSchema();
    $db = db();

    if (!$db) {
        return [
            ['id' => 0, 'name' => 'Makeup', 'slug' => 'makeup', 'tone' => 'rose', 'product_count' => 0, 'preview_image' => null],
            ['id' => 0, 'name' => 'Nails', 'slug' => 'nails', 'tone' => 'peach', 'product_count' => 0, 'preview_image' => null],
            ['id' => 0, 'name' => 'Jewellery', 'slug' => 'jewelry', 'tone' => 'sand', 'product_count' => 0, 'preview_image' => null],
        ];
    }

    $sql = "SELECT
                c.id,
                c.name,
                c.slug,
                c.tone,
                c.sort_order,
                COUNT(p.id) AS product_count,
                (
                    SELECT p2.image_path
                    FROM products p2
                    WHERE p2.category_id = c.id
                    ORDER BY p2.id DESC
                    LIMIT 1
                ) AS preview_image
            FROM categories c
            LEFT JOIN products p ON p.category_id = c.id
            GROUP BY c.id, c.name, c.slug, c.tone, c.sort_order
            ORDER BY c.sort_order ASC, c.name ASC";

    $result = $db->query($sql);
    if (!$result) {
        return [];
    }

    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = [
            'id' => (int) $row['id'],
            'name' => (string) $row['name'],
            'slug' => (string) $row['slug'],
            'tone' => (string) ($row['tone'] ?? 'rose'),
            'product_count' => (int) ($row['product_count'] ?? 0),
            'preview_image' => $row['preview_image'] !== null ? (string) $row['preview_image'] : null,
        ];
    }

    return $rows;
}

function getCatalogCategoryBySlug(string $slug): ?array
{
    ensureCatalogSchema();
    $db = db();
    if (!$db) {
        return null;
    }

    $stmt = $db->prepare('SELECT id, name, slug, tone FROM categories WHERE slug = ? LIMIT 1');
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('s', $slug);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    if (!$row) {
        return null;
    }

    return [
        'id' => (int) $row['id'],
        'name' => (string) $row['name'],
        'slug' => (string) $row['slug'],
        'tone' => (string) ($row['tone'] ?? 'rose'),
    ];
}

function getCatalogCategoryById(int $categoryId): ?array
{
    ensureCatalogSchema();
    $db = db();
    if (!$db || $categoryId <= 0) {
        return null;
    }

    $stmt = $db->prepare('SELECT id, name, slug, tone, sort_order FROM categories WHERE id = ? LIMIT 1');
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('i', $categoryId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    if (!$row) {
        return null;
    }

    return [
        'id' => (int) ($row['id'] ?? 0),
        'name' => (string) ($row['name'] ?? ''),
        'slug' => (string) ($row['slug'] ?? ''),
        'tone' => (string) ($row['tone'] ?? 'rose'),
        'sort_order' => (int) ($row['sort_order'] ?? 0),
    ];
}

function getCatalogProducts(?int $categoryId = null): array
{
    ensureCatalogSchema();
    $db = db();

    if (!$db) {
        return [];
    }

    $sql = "SELECT
                p.id,
                p.name,
                p.short_name,
                p.description,
                p.tag,
                p.tone,
                p.price,
                p.compare_price,
                p.image_path,
                p.is_sold_out,
                p.created_at,
                c.id AS category_id,
                c.name AS category_name,
                c.slug AS category_slug
            FROM products p
            LEFT JOIN categories c ON c.id = p.category_id";

    if ($categoryId !== null) {
        $sql .= ' WHERE p.category_id = ?';
    }

    $sql .= ' ORDER BY p.id DESC';

    $rows = [];
    if ($categoryId !== null) {
        $stmt = $db->prepare($sql);
        if (!$stmt) {
            return [];
        }

        $stmt->bind_param('i', $categoryId);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $rows[] = mapProductRow($row);
            }
        }
        $stmt->close();

        return $rows;
    }

    $result = $db->query($sql);
    if (!$result) {
        return [];
    }

    while ($row = $result->fetch_assoc()) {
        $rows[] = mapProductRow($row);
    }

    return $rows;
}

function getDashboardProducts(int $limit = 30): array
{
    ensureCatalogSchema();
    $db = db();
    if (!$db) {
        return [];
    }

    $limit = max(1, min(200, $limit));
    $sql = "SELECT
                p.id,
                p.name,
                p.price,
                p.image_path,
                p.is_sold_out,
                p.tag,
                c.name AS category_name,
                c.slug AS category_slug
            FROM products p
            LEFT JOIN categories c ON c.id = p.category_id
            ORDER BY p.id DESC
            LIMIT {$limit}";

    $result = $db->query($sql);
    if (!$result) {
        return [];
    }

    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = [
            'id' => (int) $row['id'],
            'name' => (string) $row['name'],
            'price' => (float) ($row['price'] ?? 0),
            'image_path' => $row['image_path'] !== null ? (string) $row['image_path'] : null,
            'is_sold_out' => (int) ($row['is_sold_out'] ?? 0) === 1,
            'tag' => (string) ($row['tag'] ?? 'NEW'),
            'category_name' => (string) ($row['category_name'] ?? 'Uncategorized'),
            'category_slug' => (string) ($row['category_slug'] ?? ''),
        ];
    }

    return $rows;
}

function mapProductRow(array $row): array
{
    return [
        'id' => (int) ($row['id'] ?? 0),
        'name' => (string) ($row['name'] ?? ''),
        'short_name' => (string) ($row['short_name'] ?? ''),
        'description' => (string) ($row['description'] ?? ''),
        'tag' => (string) ($row['tag'] ?? 'NEW'),
        'tone' => (string) ($row['tone'] ?? 'rose'),
        'price' => (float) ($row['price'] ?? 0),
        'compare_price' => $row['compare_price'] !== null ? (float) $row['compare_price'] : null,
        'image_path' => $row['image_path'] !== null ? (string) $row['image_path'] : null,
        'is_sold_out' => (int) ($row['is_sold_out'] ?? 0) === 1,
        'created_at' => (string) ($row['created_at'] ?? ''),
        'category_id' => $row['category_id'] !== null ? (int) $row['category_id'] : null,
        'category_name' => (string) ($row['category_name'] ?? 'Uncategorized'),
        'category_slug' => (string) ($row['category_slug'] ?? ''),
    ];
}

function createCatalogCategory(array $input): array
{
    ensureCatalogSchema();
    $db = db();
    if (!$db) {
        return ['success' => false, 'message' => 'Database connection is not available.'];
    }

    $name = trim((string) ($input['name'] ?? ''));
    $slugInput = trim((string) ($input['slug'] ?? ''));
    $toneInput = trim((string) ($input['tone'] ?? 'rose'));

    if ($name === '') {
        return ['success' => false, 'message' => 'Category name is required.'];
    }

    $slug = $slugInput !== '' ? slugify($slugInput) : slugify($name);
    if (strlen($slug) > 80) {
        $slug = substr($slug, 0, 80);
        $slug = rtrim($slug, '-');
    }

    $tone = in_array($toneInput, allowedTones(), true) ? $toneInput : 'rose';

    $stmt = $db->prepare('INSERT INTO categories (name, slug, tone, sort_order) VALUES (?, ?, ?, 0)');
    if (!$stmt) {
        return ['success' => false, 'message' => 'Could not prepare category insert query.'];
    }

    $stmt->bind_param('sss', $name, $slug, $tone);
    $success = $stmt->execute();
    $errorNo = $stmt->errno;
    $stmt->close();

    if (!$success) {
        if ($errorNo === 1062) {
            return ['success' => false, 'message' => 'Category name or slug already exists.'];
        }

        return ['success' => false, 'message' => 'Unable to save category.'];
    }

    return ['success' => true, 'message' => 'Category created successfully.'];
}

function deleteCatalogCategory(int $categoryId): array
{
    ensureCatalogSchema();
    $db = db();
    if (!$db || $categoryId <= 0) {
        return ['success' => false, 'message' => 'Invalid category selected.'];
    }

    $usageStmt = $db->prepare('SELECT COUNT(*) AS total FROM products WHERE category_id = ?');
    if (!$usageStmt) {
        return ['success' => false, 'message' => 'Unable to validate category usage.'];
    }
    $usageStmt->bind_param('i', $categoryId);
    $usageStmt->execute();
    $usageRes = $usageStmt->get_result();
    $usageRow = $usageRes ? $usageRes->fetch_assoc() : null;
    $usageStmt->close();

    if ((int) ($usageRow['total'] ?? 0) > 0) {
        return ['success' => false, 'message' => 'Category has products. Reassign or delete products first.'];
    }

    $collectionStmt = $db->prepare('SELECT COUNT(*) AS total FROM collections WHERE category_id = ?');
    if ($collectionStmt) {
        $collectionStmt->bind_param('i', $categoryId);
        $collectionStmt->execute();
        $collectionRes = $collectionStmt->get_result();
        $collectionRow = $collectionRes ? $collectionRes->fetch_assoc() : null;
        $collectionStmt->close();

        if ((int) ($collectionRow['total'] ?? 0) > 0) {
            return ['success' => false, 'message' => 'Category is linked to homepage collections. Reassign those collections first.'];
        }
    }

    $stmt = $db->prepare('DELETE FROM categories WHERE id = ?');
    if (!$stmt) {
        return ['success' => false, 'message' => 'Unable to delete category.'];
    }
    $stmt->bind_param('i', $categoryId);
    $stmt->execute();
    $deleted = $stmt->affected_rows > 0;
    $stmt->close();

    if (!$deleted) {
        return ['success' => false, 'message' => 'Category not found or could not be deleted.'];
    }

    return ['success' => true, 'message' => 'Category deleted successfully.'];
}

function createCatalogProduct(array $post, array $files): array
{
    ensureCatalogSchema();
    $db = db();
    if (!$db) {
        return ['success' => false, 'message' => 'Database connection is not available.'];
    }

    $name = trim((string) ($post['name'] ?? ''));
    $shortName = trim((string) ($post['short_name'] ?? ''));
    $categoryId = (int) ($post['category_id'] ?? 0);
    $priceInput = trim((string) ($post['price'] ?? ''));
    $compareInput = trim((string) ($post['compare_price'] ?? ''));
    $tag = strtoupper(trim((string) ($post['tag'] ?? 'NEW')));
    $toneInput = trim((string) ($post['tone'] ?? 'rose'));
    $description = trim((string) ($post['description'] ?? ''));
    $soldOut = isset($post['is_sold_out']) ? 1 : 0;

    if ($name === '') {
        return ['success' => false, 'message' => 'Product name is required.'];
    }

    if ($shortName === '') {
        $shortName = substr($name, 0, 26);
    }

    if ($categoryId <= 0) {
        return ['success' => false, 'message' => 'Please select a category.'];
    }

    if ($priceInput === '' || !is_numeric($priceInput)) {
        return ['success' => false, 'message' => 'Please enter a valid price.'];
    }

    $price = (float) $priceInput;
    if ($price < 0) {
        return ['success' => false, 'message' => 'Price cannot be negative.'];
    }

    $comparePrice = null;
    if ($compareInput !== '') {
        if (!is_numeric($compareInput)) {
            return ['success' => false, 'message' => 'Compare price must be numeric.'];
        }
        $comparePrice = (float) $compareInput;
    }

    if ($tag === '') {
        $tag = 'NEW';
    }
    $tag = substr($tag, 0, 30);

    $tone = in_array($toneInput, allowedTones(), true) ? $toneInput : 'rose';

    $categoryStmt = $db->prepare('SELECT id, slug FROM categories WHERE id = ? LIMIT 1');
    if (!$categoryStmt) {
        return ['success' => false, 'message' => 'Could not validate selected category.'];
    }

    $categoryStmt->bind_param('i', $categoryId);
    $categoryStmt->execute();
    $categoryResult = $categoryStmt->get_result();
    $category = $categoryResult ? $categoryResult->fetch_assoc() : null;
    $categoryStmt->close();

    if (!$category) {
        return ['success' => false, 'message' => 'Selected category does not exist.'];
    }

    $uploadResult = uploadProductImage($files['image'] ?? null);
    if (!$uploadResult['success']) {
        return $uploadResult;
    }

    $imagePath = (string) ($uploadResult['path'] ?? '');
    $sectionSlug = (string) ($category['slug'] ?? '');

    $sql = 'INSERT INTO products (category_id, section_slug, name, short_name, description, tag, tone, price, compare_price, image_path, is_sold_out, sort_order)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 0)';

    $stmt = $db->prepare($sql);
    if (!$stmt) {
        cleanupUploadedFile($imagePath);
        return ['success' => false, 'message' => 'Could not prepare product insert query.'];
    }

    $stmt->bind_param(
        'issssssddsi',
        $categoryId,
        $sectionSlug,
        $name,
        $shortName,
        $description,
        $tag,
        $tone,
        $price,
        $comparePrice,
        $imagePath,
        $soldOut
    );

    $success = $stmt->execute();
    $errorNo = $stmt->errno;
    $stmt->close();

    if (!$success) {
        cleanupUploadedFile($imagePath);
        if ($errorNo === 1062) {
            return ['success' => false, 'message' => 'This product already exists in the selected category.'];
        }

        return ['success' => false, 'message' => 'Unable to save product.'];
    }

    return ['success' => true, 'message' => 'Product uploaded successfully.'];
}

function uploadProductImage($file, bool $optional = false): array
{
    if (!is_array($file)) {
        if ($optional) {
            return ['success' => true, 'message' => 'No image uploaded.', 'path' => null];
        }
        return ['success' => false, 'message' => 'Please select a product image.'];
    }

    $error = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($optional && $error === UPLOAD_ERR_NO_FILE) {
        return ['success' => true, 'message' => 'No image uploaded.', 'path' => null];
    }
    if ($error !== UPLOAD_ERR_OK) {
        return ['success' => false, 'message' => 'Image upload failed.'];
    }

    $tmpName = (string) ($file['tmp_name'] ?? '');
    if ($tmpName === '' || !is_uploaded_file($tmpName)) {
        return ['success' => false, 'message' => 'Invalid upload source.'];
    }

    $size = (int) ($file['size'] ?? 0);
    if ($size <= 0 || $size > 5 * 1024 * 1024) {
        return ['success' => false, 'message' => 'Image must be less than 5MB.'];
    }

    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime = $finfo ? finfo_file($finfo, $tmpName) : '';
    if ($finfo) {
        finfo_close($finfo);
    }

    $mimeMap = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
        'image/gif' => 'gif',
    ];

    if (!isset($mimeMap[$mime])) {
        return ['success' => false, 'message' => 'Only JPG, PNG, WEBP, or GIF images are allowed.'];
    }

    $ext = $mimeMap[$mime];
    try {
        $suffix = bin2hex(random_bytes(4));
    } catch (Throwable $exception) {
        $suffix = (string) mt_rand(10000000, 99999999);
    }
    $fileName = date('YmdHis') . '-' . $suffix . '.' . $ext;

    $relativeDir = 'uploads/products';
    $absoluteDir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'uploads' . DIRECTORY_SEPARATOR . 'products';

    if (!is_dir($absoluteDir) && !mkdir($absoluteDir, 0775, true) && !is_dir($absoluteDir)) {
        return ['success' => false, 'message' => 'Unable to create upload directory.'];
    }

    $absolutePath = $absoluteDir . DIRECTORY_SEPARATOR . $fileName;
    if (!move_uploaded_file($tmpName, $absolutePath)) {
        return ['success' => false, 'message' => 'Failed to save uploaded image.'];
    }

    return [
        'success' => true,
        'message' => 'Image uploaded.',
        'path' => $relativeDir . '/' . $fileName,
    ];
}

function cleanupUploadedFile(string $relativePath): void
{
    $relativePath = trim($relativePath);
    if ($relativePath === '') {
        return;
    }

    $normalized = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $relativePath);
    $absolute = dirname(__DIR__) . DIRECTORY_SEPARATOR . $normalized;
    if (is_file($absolute)) {
        @unlink($absolute);
    }
}

function deleteCatalogProduct(int $productId): array
{
    ensureCatalogSchema();
    $db = db();
    if (!$db) {
        return ['success' => false, 'message' => 'Database connection is not available.'];
    }

    if ($productId <= 0) {
        return ['success' => false, 'message' => 'Invalid product selected.'];
    }

    $select = $db->prepare('SELECT image_path FROM products WHERE id = ? LIMIT 1');
    if (!$select) {
        return ['success' => false, 'message' => 'Unable to fetch product.'];
    }

    $select->bind_param('i', $productId);
    $select->execute();
    $res = $select->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $select->close();

    if (!$row) {
        return ['success' => false, 'message' => 'Product not found.'];
    }

    $imagePath = (string) ($row['image_path'] ?? '');

    $stmt = $db->prepare('DELETE FROM products WHERE id = ?');
    if (!$stmt) {
        return ['success' => false, 'message' => 'Unable to delete product.'];
    }

    $stmt->bind_param('i', $productId);
    $stmt->execute();
    $deleted = $stmt->affected_rows > 0;
    $stmt->close();

    if ($deleted) {
        cleanupUploadedFile($imagePath);
        return ['success' => true, 'message' => 'Product deleted.'];
    }

    return ['success' => false, 'message' => 'Product could not be deleted.'];
}

function saveContactMessage(array $post): array
{
    ensureCatalogSchema();
    $db = db();
    if (!$db) {
        return ['success' => false, 'message' => 'Database connection is not available.'];
    }

    $name = trim((string) ($post['name'] ?? ''));
    $email = trim((string) ($post['email'] ?? ''));
    $phone = trim((string) ($post['phone'] ?? ''));
    $comment = trim((string) ($post['comment'] ?? ''));

    if ($name === '' || $email === '' || $comment === '') {
        return ['success' => false, 'message' => 'Name, email, and comment are required.'];
    }

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'message' => 'Please enter a valid email address.'];
    }

    $stmt = $db->prepare('INSERT INTO contact_messages (full_name, email, phone, comment) VALUES (?, ?, ?, ?)');
    if (!$stmt) {
        return ['success' => false, 'message' => 'Unable to save your message right now.'];
    }

    $stmt->bind_param('ssss', $name, $email, $phone, $comment);
    $success = $stmt->execute();
    $stmt->close();

    if (!$success) {
        return ['success' => false, 'message' => 'Unable to save your message right now.'];
    }

    return ['success' => true, 'message' => 'Thank you. Your message has been submitted.'];
}
