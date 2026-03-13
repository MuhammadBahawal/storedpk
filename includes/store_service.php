<?php

declare(strict_types=1);

require_once __DIR__ . '/store_auth.php';

function storeGetCategories(): array
{
    storeEnsureSchema();
    return getCatalogCategories();
}

function storeUpdateCategory(int $categoryId, array $input): array
{
    storeEnsureSchema();
    $db = db();
    if (!$db || $categoryId <= 0) {
        return ['success' => false, 'message' => 'Invalid category selected.'];
    }

    $existing = getCatalogCategoryById($categoryId);
    if (!$existing) {
        return ['success' => false, 'message' => 'Category not found.'];
    }

    $name = trim((string) ($input['name'] ?? $existing['name']));
    $slugInput = trim((string) ($input['slug'] ?? $existing['slug']));
    $tone = trim((string) ($input['tone'] ?? $existing['tone']));
    $sortOrder = (int) ($input['sort_order'] ?? $existing['sort_order']);

    if ($name === '') {
        return ['success' => false, 'message' => 'Category name is required.'];
    }

    $slug = slugify($slugInput !== '' ? $slugInput : $name);
    if (!in_array($tone, storeAllowedTones(), true)) {
        $tone = 'rose';
    }

    $stmt = $db->prepare('UPDATE categories SET name = ?, slug = ?, tone = ?, sort_order = ? WHERE id = ?');
    if (!$stmt) {
        return ['success' => false, 'message' => 'Unable to update category.'];
    }

    $stmt->bind_param('sssii', $name, $slug, $tone, $sortOrder, $categoryId);
    $ok = $stmt->execute();
    $errorNo = $stmt->errno;
    $stmt->close();

    if (!$ok) {
        if ($errorNo === 1062) {
            return ['success' => false, 'message' => 'Category name or slug already exists.'];
        }
        return ['success' => false, 'message' => 'Unable to update category.'];
    }

    $updateProducts = $db->prepare('UPDATE products SET section_slug = ? WHERE category_id = ?');
    if ($updateProducts) {
        $updateProducts->bind_param('si', $slug, $categoryId);
        $updateProducts->execute();
        $updateProducts->close();
    }

    return ['success' => true, 'message' => 'Category updated.'];
}

function storeBuildCollectionSelect(): string
{
    return "SELECT
                c.id,
                c.name,
                c.slug,
                c.tone,
                c.tag,
                c.symbol,
                c.sort_order,
                c.category_id,
                cat.name AS category_name,
                (
                    SELECT COUNT(*)
                    FROM products p_count
                    WHERE p_count.category_id = c.category_id
                      AND p_count.is_active = 1
                ) AS product_count,
                (
                    SELECT p_preview.image_path
                    FROM products p_preview
                    WHERE p_preview.category_id = c.category_id
                      AND p_preview.is_active = 1
                    ORDER BY p_preview.sort_order ASC, p_preview.id DESC
                    LIMIT 1
                ) AS preview_image
            FROM collections c
            LEFT JOIN categories cat ON cat.id = c.category_id";
}

function storeResolveCollectionCategoryId(string $name, int $requestedCategoryId = 0): int
{
    if ($requestedCategoryId > 0) {
        return getCatalogCategoryById($requestedCategoryId) ? $requestedCategoryId : 0;
    }

    $db = db();
    if (!$db) {
        return 0;
    }

    $guessedCategoryId = catalogGuessCollectionCategoryId($db, $name);
    return $guessedCategoryId !== null && $guessedCategoryId > 0 ? $guessedCategoryId : 0;
}

function storeGenerateCollectionSlug(string $value, int $excludeId = 0): string
{
    storeEnsureSchema();
    $db = db();
    if (!$db) {
        return catalogNormalizeSlug($value, 'collection');
    }

    return catalogGenerateCollectionSlug($db, $value, $excludeId);
}

function storeMapCollection(array $row): array
{
    $tone = (string) ($row['tone'] ?? 'rose');
    if (!in_array($tone, storeAllowedTones(), true)) {
        $tone = 'rose';
    }

    return [
        'id' => (int) ($row['id'] ?? 0),
        'name' => (string) ($row['name'] ?? ''),
        'slug' => (string) ($row['slug'] ?? catalogNormalizeSlug((string) ($row['name'] ?? ''), 'collection')),
        'tone' => $tone,
        'tag' => (string) ($row['tag'] ?? 'NEW'),
        'symbol' => (string) ($row['symbol'] ?? '*'),
        'sort_order' => (int) ($row['sort_order'] ?? 0),
        'category_id' => $row['category_id'] !== null ? (int) $row['category_id'] : null,
        'category_name' => (string) ($row['category_name'] ?? ''),
        'product_count' => (int) ($row['product_count'] ?? 0),
        'preview_image' => $row['preview_image'] !== null ? (string) $row['preview_image'] : null,
    ];
}

function storeGetCollections(): array
{
    storeEnsureSchema();
    $db = db();
    if (!$db) {
        return [];
    }

    $result = $db->query(storeBuildCollectionSelect() . ' ORDER BY c.sort_order ASC, c.id ASC');
    if (!$result) {
        return [];
    }

    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = storeMapCollection($row);
    }

    return $rows;
}

function storeGetCollectionById(int $collectionId): ?array
{
    storeEnsureSchema();
    $db = db();
    if (!$db || $collectionId <= 0) {
        return null;
    }

    $stmt = $db->prepare(storeBuildCollectionSelect() . ' WHERE c.id = ? LIMIT 1');
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('i', $collectionId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return $row ? storeMapCollection($row) : null;
}

function storeGetCollectionBySlug(string $slug): ?array
{
    storeEnsureSchema();
    $db = db();
    $slug = trim($slug);
    if (!$db || $slug === '') {
        return null;
    }

    $stmt = $db->prepare(storeBuildCollectionSelect() . ' WHERE c.slug = ? LIMIT 1');
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('s', $slug);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return $row ? storeMapCollection($row) : null;
}

function storeResolveCollectionCategory(array $collection): ?array
{
    $categoryId = (int) ($collection['category_id'] ?? 0);
    if ($categoryId > 0) {
        return getCatalogCategoryById($categoryId);
    }

    $slug = trim((string) ($collection['slug'] ?? ''));
    if ($slug !== '') {
        $matchedCategory = getCatalogCategoryBySlug($slug);
        if ($matchedCategory) {
            return $matchedCategory;
        }
    }

    $db = db();
    if (!$db) {
        return null;
    }

    $guessedCategoryId = catalogGuessCollectionCategoryId($db, (string) ($collection['name'] ?? ''));
    return $guessedCategoryId !== null && $guessedCategoryId > 0
        ? getCatalogCategoryById($guessedCategoryId)
        : null;
}

function storeGetCollectionProducts(array $collection, array $filters = []): array
{
    $onlyActive = (bool) ($filters['only_active'] ?? true);
    $limit = (int) ($filters['limit'] ?? 0);
    $resolvedCategory = storeResolveCollectionCategory($collection);

    if ($resolvedCategory) {
        $categoryProducts = storeGetProducts([
            'category_id' => (int) ($resolvedCategory['id'] ?? 0),
            'only_active' => $onlyActive,
            'limit' => $limit,
        ]);

        return $categoryProducts;
    }

    $name = trim((string) ($collection['name'] ?? ''));
    $slugLabel = trim(str_replace('-', ' ', (string) ($collection['slug'] ?? '')));
    $searchTerms = [];

    if ($name !== '') {
        $searchTerms[] = $name;
    }
    if ($slugLabel !== '' && !in_array($slugLabel, $searchTerms, true)) {
        $searchTerms[] = $slugLabel;
    }

    $tokens = preg_split('/[\s-]+/', strtolower($name . ' ' . $slugLabel)) ?: [];
    foreach ($tokens as $token) {
        if (strlen($token) < 4 || in_array($token, $searchTerms, true)) {
            continue;
        }
        $searchTerms[] = $token;
    }

    $products = [];
    $seenProductIds = [];
    foreach ($searchTerms as $term) {
        $matches = storeGetProducts([
            'search' => $term,
            'only_active' => $onlyActive,
        ]);

        foreach ($matches as $match) {
            $productId = (int) ($match['id'] ?? 0);
            if ($productId <= 0 || isset($seenProductIds[$productId])) {
                continue;
            }

            $seenProductIds[$productId] = true;
            $products[] = $match;
            if ($limit > 0 && count($products) >= $limit) {
                return array_slice($products, 0, $limit);
            }
        }
    }

    return $products;
}

function storeCreateCollection(array $input): array
{
    storeEnsureSchema();
    $db = db();
    if (!$db) {
        return ['success' => false, 'message' => 'Database connection is not available.'];
    }

    $name = trim((string) ($input['name'] ?? ''));
    $slugInput = trim((string) ($input['slug'] ?? ''));
    $tone = trim((string) ($input['tone'] ?? 'rose'));
    $tag = strtoupper(trim((string) ($input['tag'] ?? 'NEW')));
    $symbol = trim((string) ($input['symbol'] ?? '*'));
    $sortOrder = (int) ($input['sort_order'] ?? 0);
    $categoryId = storeResolveCollectionCategoryId($name, (int) ($input['category_id'] ?? 0));

    if ($name === '') {
        return ['success' => false, 'message' => 'Collection name is required.'];
    }

    if (!in_array($tone, storeAllowedTones(), true)) {
        $tone = 'rose';
    }
    if ($tag === '') {
        $tag = 'NEW';
    }
    if ($symbol === '') {
        $symbol = '*';
    }

    $tag = substr($tag, 0, 30);
    $symbol = substr($symbol, 0, 10);
    $slug = storeGenerateCollectionSlug($slugInput !== '' ? $slugInput : $name);

    $stmt = $db->prepare(
        'INSERT INTO collections (name, slug, tone, tag, symbol, sort_order, category_id)
         VALUES (?, ?, ?, ?, ?, ?, NULLIF(?, 0))'
    );
    if (!$stmt) {
        return ['success' => false, 'message' => 'Unable to save collection.'];
    }

    $stmt->bind_param('sssssii', $name, $slug, $tone, $tag, $symbol, $sortOrder, $categoryId);
    $ok = $stmt->execute();
    $errorNo = $stmt->errno;
    $stmt->close();

    if (!$ok) {
        if ($errorNo === 1062) {
            return ['success' => false, 'message' => 'Collection name or slug already exists.'];
        }
        return ['success' => false, 'message' => 'Unable to save collection.'];
    }

    return ['success' => true, 'message' => 'Collection created successfully.'];
}

function storeUpdateCollection(int $collectionId, array $input): array
{
    storeEnsureSchema();
    $db = db();
    if (!$db || $collectionId <= 0) {
        return ['success' => false, 'message' => 'Invalid collection selected.'];
    }

    $existing = storeGetCollectionById($collectionId);
    if (!$existing) {
        return ['success' => false, 'message' => 'Collection not found.'];
    }

    $name = trim((string) ($input['name'] ?? $existing['name']));
    $slugInput = trim((string) ($input['slug'] ?? $existing['slug']));
    $tone = trim((string) ($input['tone'] ?? $existing['tone']));
    $tag = strtoupper(trim((string) ($input['tag'] ?? $existing['tag'])));
    $symbol = trim((string) ($input['symbol'] ?? $existing['symbol']));
    $sortOrder = (int) ($input['sort_order'] ?? $existing['sort_order']);
    $categoryId = storeResolveCollectionCategoryId($name, (int) ($input['category_id'] ?? ($existing['category_id'] ?? 0)));

    if ($name === '') {
        return ['success' => false, 'message' => 'Collection name is required.'];
    }

    if (!in_array($tone, storeAllowedTones(), true)) {
        $tone = 'rose';
    }
    if ($tag === '') {
        $tag = 'NEW';
    }
    if ($symbol === '') {
        $symbol = '*';
    }

    $tag = substr($tag, 0, 30);
    $symbol = substr($symbol, 0, 10);
    $slug = storeGenerateCollectionSlug($slugInput !== '' ? $slugInput : $name, $collectionId);

    $stmt = $db->prepare(
        'UPDATE collections
         SET name = ?, slug = ?, tone = ?, tag = ?, symbol = ?, sort_order = ?, category_id = NULLIF(?, 0)
         WHERE id = ?'
    );
    if (!$stmt) {
        return ['success' => false, 'message' => 'Unable to update collection.'];
    }

    $stmt->bind_param('sssssiii', $name, $slug, $tone, $tag, $symbol, $sortOrder, $categoryId, $collectionId);
    $ok = $stmt->execute();
    $errorNo = $stmt->errno;
    $stmt->close();

    if (!$ok) {
        if ($errorNo === 1062) {
            return ['success' => false, 'message' => 'Collection name or slug already exists.'];
        }
        return ['success' => false, 'message' => 'Unable to update collection.'];
    }

    return ['success' => true, 'message' => 'Collection updated.'];
}

function storeDeleteCollection(int $collectionId): array
{
    storeEnsureSchema();
    $db = db();
    if (!$db || $collectionId <= 0) {
        return ['success' => false, 'message' => 'Invalid collection selected.'];
    }

    $stmt = $db->prepare('DELETE FROM collections WHERE id = ?');
    if (!$stmt) {
        return ['success' => false, 'message' => 'Unable to delete collection.'];
    }

    $stmt->bind_param('i', $collectionId);
    $stmt->execute();
    $deleted = $stmt->affected_rows > 0;
    $stmt->close();

    if (!$deleted) {
        return ['success' => false, 'message' => 'Collection not found or could not be deleted.'];
    }

    return ['success' => true, 'message' => 'Collection deleted successfully.'];
}

function storeMapProduct(array $row): array
{
    $availability = (string) ($row['availability'] ?? 'in_stock');
    $stockQty = (int) ($row['stock_qty'] ?? 0);

    return [
        'id' => (int) ($row['id'] ?? 0),
        'category_id' => $row['category_id'] !== null ? (int) $row['category_id'] : null,
        'category_name' => (string) ($row['category_name'] ?? 'Uncategorized'),
        'category_slug' => (string) ($row['category_slug'] ?? ''),
        'section_slug' => (string) ($row['section_slug'] ?? ''),
        'name' => (string) ($row['name'] ?? ''),
        'slug' => (string) ($row['slug'] ?? ''),
        'short_name' => (string) ($row['short_name'] ?? ''),
        'description' => (string) ($row['description'] ?? ''),
        'tag' => (string) ($row['tag'] ?? 'NEW'),
        'tone' => (string) ($row['tone'] ?? 'rose'),
        'price' => (float) ($row['price'] ?? 0),
        'compare_price' => ($row['compare_price'] !== null && (float) $row['compare_price'] > 0) ? (float) $row['compare_price'] : null,
        'cost_price' => (float) ($row['cost_price'] ?? 0),
        'stock_qty' => $stockQty,
        'availability' => $availability,
        'stock_label' => storeStockLabel($availability, $stockQty),
        'is_active' => (int) ($row['is_active'] ?? 1) === 1,
        'image_path' => $row['image_path'] !== null ? (string) $row['image_path'] : null,
        'sort_order' => (int) ($row['sort_order'] ?? 0),
        'created_at' => (string) ($row['created_at'] ?? ''),
        'updated_at' => (string) ($row['updated_at'] ?? ''),
    ];
}

function storeGetProducts(array $filters = []): array
{
    storeEnsureSchema();
    $db = db();
    if (!$db) {
        return [];
    }

    $categoryId = (int) ($filters['category_id'] ?? 0);
    $search = trim((string) ($filters['search'] ?? ''));
    $onlyActive = (bool) ($filters['only_active'] ?? false);
    $onlyAvailable = (bool) ($filters['only_available'] ?? false);
    $limit = (int) ($filters['limit'] ?? 0);

    $conditions = [];
    if ($categoryId > 0) {
        $conditions[] = 'p.category_id = ' . $categoryId;
    }
    if ($onlyActive) {
        $conditions[] = 'p.is_active = 1';
    }
    if ($onlyAvailable) {
        $conditions[] = "p.availability = 'in_stock' AND p.stock_qty > 0";
    }
    if ($search !== '') {
        $escaped = $db->real_escape_string($search);
        $conditions[] = "(p.name LIKE '%{$escaped}%' OR p.short_name LIKE '%{$escaped}%' OR p.tag LIKE '%{$escaped}%' OR c.name LIKE '%{$escaped}%')";
    }

    $where = $conditions !== [] ? ' WHERE ' . implode(' AND ', $conditions) : '';

    $sql = "SELECT
                p.id,
                p.category_id,
                p.section_slug,
                p.name,
                p.slug,
                p.short_name,
                p.description,
                p.tag,
                p.tone,
                p.price,
                p.compare_price,
                p.cost_price,
                p.stock_qty,
                p.availability,
                p.image_path,
                p.is_active,
                p.sort_order,
                p.created_at,
                p.updated_at,
                c.name AS category_name,
                c.slug AS category_slug
            FROM products p
            LEFT JOIN categories c ON c.id = p.category_id" . $where .
            ' ORDER BY p.sort_order ASC, p.id DESC';

    if ($limit > 0) {
        $sql .= ' LIMIT ' . $limit;
    }

    $result = $db->query($sql);
    if (!$result) {
        return [];
    }

    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = storeMapProduct($row);
    }

    return $rows;
}

function storeGetProductById(int $productId): ?array
{
    storeEnsureSchema();
    $db = db();
    if (!$db || $productId <= 0) {
        return null;
    }

    $stmt = $db->prepare(
        "SELECT
            p.id,
            p.category_id,
            p.section_slug,
            p.name,
            p.slug,
            p.short_name,
            p.description,
            p.tag,
            p.tone,
            p.price,
            p.compare_price,
            p.cost_price,
            p.stock_qty,
            p.availability,
            p.image_path,
            p.is_active,
            p.sort_order,
            p.created_at,
            p.updated_at,
            c.name AS category_name,
            c.slug AS category_slug
        FROM products p
        LEFT JOIN categories c ON c.id = p.category_id
        WHERE p.id = ?
        LIMIT 1"
    );
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('i', $productId);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return $row ? storeMapProduct($row) : null;
}

function storeGetProductBySlug(string $slug): ?array
{
    storeEnsureSchema();
    $db = db();
    $slug = trim($slug);
    if (!$db || $slug === '') {
        return null;
    }

    $stmt = $db->prepare(
        "SELECT
            p.id,
            p.category_id,
            p.section_slug,
            p.name,
            p.slug,
            p.short_name,
            p.description,
            p.tag,
            p.tone,
            p.price,
            p.compare_price,
            p.cost_price,
            p.stock_qty,
            p.availability,
            p.image_path,
            p.is_active,
            p.sort_order,
            p.created_at,
            p.updated_at,
            c.name AS category_name,
            c.slug AS category_slug
        FROM products p
        LEFT JOIN categories c ON c.id = p.category_id
        WHERE p.slug = ?
        LIMIT 1"
    );
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param('s', $slug);
    $stmt->execute();
    $result = $stmt->get_result();
    $row = $result ? $result->fetch_assoc() : null;
    $stmt->close();

    return $row ? storeMapProduct($row) : null;
}

function storeUploadImage($file, bool $optional = false): array
{
    return uploadProductImage($file, $optional);
}

function storeRecordPurchase(int $productId, int $quantity, float $costPrice, string $note = '', ?int $adminId = null): void
{
    if ($productId <= 0 || $quantity <= 0 || $costPrice <= 0) {
        return;
    }

    storeEnsureSchema();
    $db = db();
    if (!$db) {
        return;
    }

    $totalCost = $quantity * $costPrice;
    $admin = $adminId !== null && $adminId > 0 ? $adminId : null;

    $stmt = $db->prepare('INSERT INTO purchase_entries (product_id, quantity, cost_price, total_cost, note, admin_id) VALUES (?, ?, ?, ?, ?, ?)');
    if (!$stmt) {
        return;
    }

    $stmt->bind_param('iidssi', $productId, $quantity, $costPrice, $totalCost, $note, $admin);
    $stmt->execute();
    $stmt->close();
}

function storeCreateProduct(array $post, array $files, ?int $adminId = null): array
{
    storeEnsureSchema();
    $db = db();
    if (!$db) {
        return ['success' => false, 'message' => 'Database connection is not available.'];
    }

    $name = trim((string) ($post['name'] ?? ''));
    $shortName = trim((string) ($post['short_name'] ?? ''));
    $categoryId = (int) ($post['category_id'] ?? 0);
    $description = trim((string) ($post['description'] ?? ''));
    $tag = strtoupper(trim((string) ($post['tag'] ?? 'NEW')));
    $tone = trim((string) ($post['tone'] ?? 'rose'));
    $price = (float) ($post['price'] ?? 0);
    $comparePrice = (float) ($post['compare_price'] ?? 0);
    $costPrice = (float) ($post['cost_price'] ?? 0);
    $stockQty = (int) ($post['stock_qty'] ?? 0);
    $sortOrder = (int) ($post['sort_order'] ?? 0);
    $availability = storeNormalizeAvailability(trim((string) ($post['availability'] ?? 'in_stock')), $stockQty);
    $isActive = isset($post['is_active']) ? 1 : 0;

    if ($name === '' || $categoryId <= 0) {
        return ['success' => false, 'message' => 'Product name and category are required.'];
    }
    if ($price < 0 || $comparePrice < 0 || $costPrice < 0 || $stockQty < 0) {
        return ['success' => false, 'message' => 'Price, cost, and stock cannot be negative.'];
    }

    if ($shortName === '') {
        $shortName = substr($name, 0, 26);
    }
    if ($tag === '') {
        $tag = 'NEW';
    }
    $tag = substr($tag, 0, 30);
    if (!in_array($tone, storeAllowedTones(), true)) {
        $tone = 'rose';
    }

    $category = getCatalogCategoryById($categoryId);
    if (!$category) {
        return ['success' => false, 'message' => 'Selected category does not exist.'];
    }

    $uploadResult = storeUploadImage($files['image'] ?? null, false);
    if (!$uploadResult['success']) {
        return $uploadResult;
    }
    $imagePath = (string) ($uploadResult['path'] ?? '');

    $slug = storeGenerateProductSlug($name);
    $sectionSlug = (string) ($category['slug'] ?? '');
    $isSoldOut = $availability === 'sold_out' ? 1 : 0;

    $stmt = $db->prepare(
        'INSERT INTO products (
            category_id, section_slug, name, slug, short_name, description, tag, tone,
            price, compare_price, cost_price, stock_qty, availability, image_path,
            is_sold_out, is_active, sort_order
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    if (!$stmt) {
        cleanupUploadedFile($imagePath);
        return ['success' => false, 'message' => 'Unable to save product.'];
    }

    $stmt->bind_param(
        'isssssssdddissiii',
        $categoryId,
        $sectionSlug,
        $name,
        $slug,
        $shortName,
        $description,
        $tag,
        $tone,
        $price,
        $comparePrice,
        $costPrice,
        $stockQty,
        $availability,
        $imagePath,
        $isSoldOut,
        $isActive,
        $sortOrder
    );

    $ok = $stmt->execute();
    $productId = (int) $stmt->insert_id;
    $stmt->close();

    if (!$ok) {
        cleanupUploadedFile($imagePath);
        return ['success' => false, 'message' => 'Unable to save product.'];
    }

    if ($stockQty > 0 && $costPrice > 0) {
        storeRecordPurchase($productId, $stockQty, $costPrice, 'Initial stock', $adminId);
    }

    return ['success' => true, 'message' => 'Product added successfully.'];
}

function storeUpdateProduct(int $productId, array $post, array $files, ?int $adminId = null): array
{
    $existing = storeGetProductById($productId);
    if (!$existing) {
        return ['success' => false, 'message' => 'Product not found.'];
    }

    $db = db();
    if (!$db) {
        return ['success' => false, 'message' => 'Database connection is not available.'];
    }

    $name = trim((string) ($post['name'] ?? $existing['name']));
    $shortName = trim((string) ($post['short_name'] ?? $existing['short_name']));
    $categoryId = (int) ($post['category_id'] ?? $existing['category_id']);
    $description = trim((string) ($post['description'] ?? $existing['description']));
    $tag = strtoupper(trim((string) ($post['tag'] ?? $existing['tag'])));
    $tone = trim((string) ($post['tone'] ?? $existing['tone']));
    $price = isset($post['price']) ? (float) $post['price'] : (float) $existing['price'];
    $comparePrice = isset($post['compare_price']) ? (float) $post['compare_price'] : (float) ($existing['compare_price'] ?? 0);
    $costPrice = isset($post['cost_price']) ? (float) $post['cost_price'] : (float) $existing['cost_price'];
    $stockQty = isset($post['stock_qty']) ? (int) $post['stock_qty'] : (int) $existing['stock_qty'];
    $sortOrder = isset($post['sort_order']) ? (int) $post['sort_order'] : (int) ($existing['sort_order'] ?? 0);
    $availability = storeNormalizeAvailability(trim((string) ($post['availability'] ?? $existing['availability'])), $stockQty);
    $isActive = isset($post['is_active']) ? 1 : 0;

    if ($name === '' || $categoryId <= 0) {
        return ['success' => false, 'message' => 'Product name and category are required.'];
    }
    if ($price < 0 || $comparePrice < 0 || $costPrice < 0 || $stockQty < 0) {
        return ['success' => false, 'message' => 'Price, cost, and stock cannot be negative.'];
    }

    if ($shortName === '') {
        $shortName = substr($name, 0, 26);
    }
    if (!in_array($tone, storeAllowedTones(), true)) {
        $tone = 'rose';
    }
    if ($tag === '') {
        $tag = 'NEW';
    }
    $tag = substr($tag, 0, 30);

    $category = getCatalogCategoryById($categoryId);
    if (!$category) {
        return ['success' => false, 'message' => 'Selected category does not exist.'];
    }

    $imagePath = (string) ($existing['image_path'] ?? '');
    $uploadResult = storeUploadImage($files['image'] ?? null, true);
    if (!$uploadResult['success']) {
        return $uploadResult;
    }
    if (!empty($uploadResult['path'])) {
        $newPath = (string) $uploadResult['path'];
        if ($imagePath !== '' && $imagePath !== $newPath) {
            cleanupUploadedFile($imagePath);
        }
        $imagePath = $newPath;
    }

    $slug = storeGenerateProductSlug($name, $productId);
    $sectionSlug = (string) ($category['slug'] ?? '');
    $isSoldOut = $availability === 'sold_out' ? 1 : 0;

    $stmt = $db->prepare(
        'UPDATE products
         SET category_id = ?, section_slug = ?, name = ?, slug = ?, short_name = ?, description = ?, tag = ?, tone = ?,
             price = ?, compare_price = ?, cost_price = ?, stock_qty = ?, availability = ?, image_path = ?,
             is_sold_out = ?, is_active = ?, sort_order = ?
         WHERE id = ?'
    );
    if (!$stmt) {
        return ['success' => false, 'message' => 'Unable to update product.'];
    }

    $stmt->bind_param(
        'isssssssdddissiiii',
        $categoryId,
        $sectionSlug,
        $name,
        $slug,
        $shortName,
        $description,
        $tag,
        $tone,
        $price,
        $comparePrice,
        $costPrice,
        $stockQty,
        $availability,
        $imagePath,
        $isSoldOut,
        $isActive,
        $sortOrder,
        $productId
    );
    $ok = $stmt->execute();
    $stmt->close();

    if (!$ok) {
        return ['success' => false, 'message' => 'Unable to update product.'];
    }

    $addedQty = $stockQty - (int) $existing['stock_qty'];
    if ($addedQty > 0 && $costPrice > 0) {
        storeRecordPurchase($productId, $addedQty, $costPrice, 'Stock restock', $adminId);
    }

    return ['success' => true, 'message' => 'Product updated successfully.'];
}

function storeDeleteProduct(int $productId): array
{
    storeEnsureSchema();
    $db = db();
    if (!$db || $productId <= 0) {
        return ['success' => false, 'message' => 'Invalid product selected.'];
    }

    $product = storeGetProductById($productId);
    if (!$product) {
        return ['success' => false, 'message' => 'Product not found.'];
    }

    $db->query('DELETE FROM cart_items WHERE product_id = ' . $productId);
    $db->query('DELETE FROM purchase_entries WHERE product_id = ' . $productId);

    $stmt = $db->prepare('DELETE FROM products WHERE id = ?');
    if (!$stmt) {
        return ['success' => false, 'message' => 'Unable to delete product.'];
    }
    $stmt->bind_param('i', $productId);
    $stmt->execute();
    $deleted = $stmt->affected_rows > 0;
    $stmt->close();

    if (!$deleted) {
        return ['success' => false, 'message' => 'Product could not be deleted.'];
    }

    $imagePath = (string) ($product['image_path'] ?? '');
    cleanupUploadedFile($imagePath);

    return ['success' => true, 'message' => 'Product deleted.'];
}

function storeCartCount(?int $userId = null): int
{
    storeEnsureSchema();
    $db = db();
    if (!$db) {
        return 0;
    }
    if ($userId === null) {
        $userId = storeCurrentUserId();
    }
    if ($userId === null || $userId <= 0) {
        return 0;
    }

    $stmt = $db->prepare('SELECT COALESCE(SUM(quantity), 0) AS total FROM cart_items WHERE user_id = ?');
    if (!$stmt) {
        return 0;
    }
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : ['total' => 0];
    $stmt->close();

    return (int) ($row['total'] ?? 0);
}

function storeCartAdd(int $userId, int $productId, int $qty = 1): array
{
    if ($userId <= 0 || $productId <= 0 || $qty <= 0) {
        return ['success' => false, 'message' => 'Invalid cart request.'];
    }

    $product = storeGetProductById($productId);
    if (!$product || !$product['is_active']) {
        return ['success' => false, 'message' => 'Product is unavailable.'];
    }
    if ((string) ($product['availability'] ?? 'out_of_stock') !== 'in_stock' || (int) ($product['stock_qty'] ?? 0) <= 0) {
        return ['success' => false, 'message' => 'This product is currently unavailable.'];
    }

    $db = db();
    if (!$db) {
        return ['success' => false, 'message' => 'Database connection is not available.'];
    }

    $currentQty = 0;
    $stmt = $db->prepare('SELECT quantity FROM cart_items WHERE user_id = ? AND product_id = ? LIMIT 1');
    if ($stmt) {
        $stmt->bind_param('ii', $userId, $productId);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();
        $currentQty = (int) ($row['quantity'] ?? 0);
    }

    $newQty = $currentQty + $qty;
    if ($newQty > (int) ($product['stock_qty'] ?? 0)) {
        return ['success' => false, 'message' => 'Only ' . (int) ($product['stock_qty'] ?? 0) . ' units are available.'];
    }

    $save = $db->prepare(
        'INSERT INTO cart_items (user_id, product_id, quantity)
         VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE quantity = VALUES(quantity), updated_at = CURRENT_TIMESTAMP'
    );
    if (!$save) {
        return ['success' => false, 'message' => 'Unable to update cart.'];
    }

    $save->bind_param('iii', $userId, $productId, $newQty);
    $ok = $save->execute();
    $save->close();

    if (!$ok) {
        return ['success' => false, 'message' => 'Unable to update cart.'];
    }

    return ['success' => true, 'message' => 'Product added to cart.'];
}

function storeCartItems(int $userId): array
{
    storeEnsureSchema();
    $db = db();
    if (!$db || $userId <= 0) {
        return [];
    }

    $stmt = $db->prepare(
        "SELECT
            c.product_id,
            c.quantity,
            p.name,
            p.slug,
            p.short_name,
            p.price,
            p.compare_price,
            p.image_path,
            p.stock_qty,
            p.availability,
            p.is_active,
            p.tag,
            p.tone,
            cat.name AS category_name
         FROM cart_items c
         INNER JOIN products p ON p.id = c.product_id
         LEFT JOIN categories cat ON cat.id = p.category_id
         WHERE c.user_id = ?
         ORDER BY c.updated_at DESC"
    );

    if (!$stmt) {
        return [];
    }

    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    $rows = [];
    while ($result && ($row = $result->fetch_assoc())) {
        $qty = (int) ($row['quantity'] ?? 0);
        $price = (float) ($row['price'] ?? 0);
        $rows[] = [
            'product_id' => (int) ($row['product_id'] ?? 0),
            'quantity' => $qty,
            'name' => (string) ($row['name'] ?? ''),
            'slug' => (string) ($row['slug'] ?? ''),
            'short_name' => (string) ($row['short_name'] ?? ''),
            'price' => $price,
            'compare_price' => ($row['compare_price'] !== null && (float) $row['compare_price'] > 0) ? (float) $row['compare_price'] : null,
            'image_path' => $row['image_path'] !== null ? (string) $row['image_path'] : null,
            'stock_qty' => (int) ($row['stock_qty'] ?? 0),
            'availability' => (string) ($row['availability'] ?? 'in_stock'),
            'is_active' => (int) ($row['is_active'] ?? 1) === 1,
            'tag' => (string) ($row['tag'] ?? 'NEW'),
            'tone' => (string) ($row['tone'] ?? 'rose'),
            'category_name' => (string) ($row['category_name'] ?? 'Uncategorized'),
            'line_total' => $qty * $price,
        ];
    }
    $stmt->close();

    return $rows;
}

function storeCartTotals(array $items): array
{
    $subtotal = 0.0;
    foreach ($items as $item) {
        $subtotal += (float) ($item['line_total'] ?? 0);
    }
    $shipping = $subtotal >= 3000 ? 0.0 : ($subtotal > 0 ? 250.0 : 0.0);
    $discount = 0.0;
    $total = max(0.0, $subtotal + $shipping - $discount);

    return [
        'subtotal' => $subtotal,
        'shipping' => $shipping,
        'discount' => $discount,
        'total' => $total,
    ];
}

function storeCartUpdate(int $userId, int $productId, int $qty): array
{
    storeEnsureSchema();
    $db = db();
    if (!$db || $userId <= 0 || $productId <= 0) {
        return ['success' => false, 'message' => 'Invalid cart request.'];
    }

    if ($qty <= 0) {
        return storeCartRemove($userId, $productId);
    }

    $product = storeGetProductById($productId);
    if (!$product || !$product['is_active']) {
        return ['success' => false, 'message' => 'Product is unavailable.'];
    }
    if ($qty > (int) ($product['stock_qty'] ?? 0)) {
        return ['success' => false, 'message' => 'Only ' . (int) ($product['stock_qty'] ?? 0) . ' units are available.'];
    }

    $stmt = $db->prepare('UPDATE cart_items SET quantity = ? WHERE user_id = ? AND product_id = ?');
    if (!$stmt) {
        return ['success' => false, 'message' => 'Unable to update cart.'];
    }
    $stmt->bind_param('iii', $qty, $userId, $productId);
    $stmt->execute();
    $stmt->close();

    return ['success' => true, 'message' => 'Cart updated.'];
}

function storeCartRemove(int $userId, int $productId): array
{
    storeEnsureSchema();
    $db = db();
    if (!$db || $userId <= 0 || $productId <= 0) {
        return ['success' => false, 'message' => 'Invalid cart request.'];
    }

    $stmt = $db->prepare('DELETE FROM cart_items WHERE user_id = ? AND product_id = ?');
    if (!$stmt) {
        return ['success' => false, 'message' => 'Unable to remove cart item.'];
    }
    $stmt->bind_param('ii', $userId, $productId);
    $stmt->execute();
    $stmt->close();

    return ['success' => true, 'message' => 'Item removed from cart.'];
}

function storeCartClear(int $userId): void
{
    storeEnsureSchema();
    $db = db();
    if (!$db || $userId <= 0) {
        return;
    }

    $stmt = $db->prepare('DELETE FROM cart_items WHERE user_id = ?');
    if (!$stmt) {
        return;
    }
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $stmt->close();
}

function storeGuestCheckoutEmail(): string
{
    return 'guest@store.pk';
}

function storeGuestUserId(): int
{
    storeEnsureSchema();
    $db = db();
    if (!$db) {
        return 0;
    }

    $guestEmail = storeGuestCheckoutEmail();
    $stmt = $db->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    if ($stmt) {
        $stmt->bind_param('s', $guestEmail);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result ? $result->fetch_assoc() : null;
        $stmt->close();

        if ($row) {
            return (int) ($row['id'] ?? 0);
        }
    }

    try {
        $passwordSeed = bin2hex(random_bytes(16));
    } catch (Throwable $exception) {
        $passwordSeed = (string) mt_rand(100000000, 999999999);
    }

    $fullName = 'Guest Checkout';
    $phone = '';
    $passwordHash = password_hash($passwordSeed, PASSWORD_DEFAULT);
    $insert = $db->prepare('INSERT INTO users (full_name, email, phone, password_hash, is_active) VALUES (?, ?, ?, ?, 0)');
    if (!$insert) {
        return 0;
    }

    $insert->bind_param('ssss', $fullName, $guestEmail, $phone, $passwordHash);
    $ok = $insert->execute();
    $insertId = (int) $insert->insert_id;
    $insert->close();

    if ($ok && $insertId > 0) {
        return $insertId;
    }

    $retry = $db->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
    if (!$retry) {
        return 0;
    }

    $retry->bind_param('s', $guestEmail);
    $retry->execute();
    $retryResult = $retry->get_result();
    $retryRow = $retryResult ? $retryResult->fetch_assoc() : null;
    $retry->close();

    return (int) ($retryRow['id'] ?? 0);
}

function storeRememberGuestOrder(string $orderNumber): void
{
    $orderNumber = trim($orderNumber);
    if ($orderNumber === '') {
        return;
    }

    storeEnsureSession();
    if (!isset($_SESSION['guest_orders']) || !is_array($_SESSION['guest_orders'])) {
        $_SESSION['guest_orders'] = [];
    }

    $_SESSION['guest_orders'][$orderNumber] = time();
    if (count($_SESSION['guest_orders']) > 20) {
        $_SESSION['guest_orders'] = array_slice($_SESSION['guest_orders'], -20, null, true);
    }
}

function storeGuestCanViewOrder(string $orderNumber): bool
{
    $orderNumber = trim($orderNumber);
    if ($orderNumber === '') {
        return false;
    }

    storeEnsureSession();
    return isset($_SESSION['guest_orders'][$orderNumber]);
}

function storeNormalizeCheckoutPayload(array $payload): array
{
    return [
        'recipient_name' => trim((string) ($payload['recipient_name'] ?? '')),
        'phone' => trim((string) ($payload['phone'] ?? '')),
        'email' => strtolower(trim((string) ($payload['email'] ?? ''))),
        'address_line' => trim((string) ($payload['address_line'] ?? '')),
        'city' => trim((string) ($payload['city'] ?? '')),
        'province' => trim((string) ($payload['province'] ?? '')),
        'postal_code' => trim((string) ($payload['postal_code'] ?? '')),
        'notes' => trim((string) ($payload['notes'] ?? '')),
    ];
}

function storeValidateCheckoutPayload(array $payload): ?string
{
    if (
        $payload['recipient_name'] === ''
        || $payload['phone'] === ''
        || $payload['email'] === ''
        || $payload['address_line'] === ''
        || $payload['city'] === ''
    ) {
        return 'Please complete all required checkout fields.';
    }

    if (!filter_var((string) $payload['email'], FILTER_VALIDATE_EMAIL)) {
        return 'Please enter a valid email address.';
    }

    return null;
}

function storeValidateCheckoutItems(array $items): ?string
{
    if ($items === []) {
        return 'No products are available for checkout.';
    }

    foreach ($items as $item) {
        if ((int) ($item['product_id'] ?? 0) <= 0 || (int) ($item['quantity'] ?? 0) <= 0) {
            return 'One or more selected products are invalid.';
        }
        if (isset($item['is_active']) && !$item['is_active']) {
            return 'Some products are unavailable.';
        }
        if ((string) ($item['availability'] ?? 'out_of_stock') !== 'in_stock') {
            return 'Some products are unavailable.';
        }
        if ((int) ($item['quantity'] ?? 0) > (int) ($item['stock_qty'] ?? 0)) {
            return 'Stock is insufficient for one or more products.';
        }
    }

    return null;
}

function storeGenerateOrderNumber(mysqli $db): string
{
    do {
        $candidate = 'SBA' . date('ymd') . str_pad((string) random_int(1, 99999), 5, '0', STR_PAD_LEFT);
        $stmt = $db->prepare('SELECT COUNT(*) AS total FROM orders WHERE order_number = ?');
        if (!$stmt) {
            return $candidate;
        }
        $stmt->bind_param('s', $candidate);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : ['total' => 0];
        $stmt->close();
    } while ((int) ($row['total'] ?? 0) > 0);

    return $candidate;
}

function storePlacePreparedOrder(int $userId, array $items, array $payload, bool $clearCartAfterCheckout = false): array
{
    storeEnsureSchema();
    $db = db();
    if (!$db || $userId <= 0) {
        return ['success' => false, 'message' => 'Unable to place order right now.'];
    }

    $normalizedPayload = storeNormalizeCheckoutPayload($payload);
    $payloadError = storeValidateCheckoutPayload($normalizedPayload);
    if ($payloadError !== null) {
        return ['success' => false, 'message' => $payloadError];
    }

    $itemsError = storeValidateCheckoutItems($items);
    if ($itemsError !== null) {
        return ['success' => false, 'message' => $itemsError];
    }

    foreach ($items as $index => $item) {
        if (!isset($items[$index]['line_total'])) {
            $items[$index]['line_total'] = (float) ($item['price'] ?? 0) * (int) ($item['quantity'] ?? 0);
        }
    }

    $recipientName = (string) $normalizedPayload['recipient_name'];
    $phone = (string) $normalizedPayload['phone'];
    $email = (string) $normalizedPayload['email'];
    $address = (string) $normalizedPayload['address_line'];
    $city = (string) $normalizedPayload['city'];
    $province = (string) $normalizedPayload['province'];
    $postalCode = (string) $normalizedPayload['postal_code'];
    $notes = (string) $normalizedPayload['notes'];

    $totals = storeCartTotals($items);
    $db->begin_transaction();

    try {
        $orderNumber = storeGenerateOrderNumber($db);
        $status = 'pending';
        $paymentMethod = 'cod';
        $paymentStatus = 'unpaid';
        $subtotal = (float) ($totals['subtotal'] ?? 0);
        $shipping = (float) ($totals['shipping'] ?? 0);
        $discount = (float) ($totals['discount'] ?? 0);
        $total = (float) ($totals['total'] ?? 0);

        $orderStmt = $db->prepare(
            'INSERT INTO orders (
                user_id, order_number, status, payment_method, payment_status,
                subtotal, shipping_fee, discount_amount, total,
                recipient_name, phone, email, address_line, city, province, postal_code, notes
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        if (!$orderStmt) {
            throw new RuntimeException('Unable to create order.');
        }

        $orderStmt->bind_param(
            'issssddddssssssss',
            $userId,
            $orderNumber,
            $status,
            $paymentMethod,
            $paymentStatus,
            $subtotal,
            $shipping,
            $discount,
            $total,
            $recipientName,
            $phone,
            $email,
            $address,
            $city,
            $province,
            $postalCode,
            $notes
        );
        if (!$orderStmt->execute()) {
            $orderStmt->close();
            throw new RuntimeException('Unable to create order.');
        }
        $orderId = (int) $orderStmt->insert_id;
        $orderStmt->close();

        $itemStmt = $db->prepare(
            'INSERT INTO order_items (
                order_id, product_id, product_name, category_name, quantity,
                unit_price, cost_price, line_total, image_path
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stockStmt = $db->prepare('UPDATE products SET stock_qty = stock_qty - ? WHERE id = ?');

        if (!$itemStmt || !$stockStmt) {
            throw new RuntimeException('Unable to process order items.');
        }

        foreach ($items as $item) {
            $productId = (int) ($item['product_id'] ?? 0);
            $qty = (int) ($item['quantity'] ?? 0);
            $productName = (string) ($item['name'] ?? 'Product');
            $categoryName = (string) ($item['category_name'] ?? '');
            $unitPrice = (float) ($item['price'] ?? 0);
            $costPrice = 0.0;
            $lineTotal = $unitPrice * $qty;
            $imagePath = (string) ($item['image_path'] ?? '');

            $costStmt = $db->prepare('SELECT cost_price FROM products WHERE id = ? LIMIT 1');
            if ($costStmt) {
                $costStmt->bind_param('i', $productId);
                $costStmt->execute();
                $costRes = $costStmt->get_result();
                $costRow = $costRes ? $costRes->fetch_assoc() : null;
                $costPrice = (float) ($costRow['cost_price'] ?? 0);
                $costStmt->close();
            }

            $itemStmt->bind_param(
                'iissiddds',
                $orderId,
                $productId,
                $productName,
                $categoryName,
                $qty,
                $unitPrice,
                $costPrice,
                $lineTotal,
                $imagePath
            );
            if (!$itemStmt->execute()) {
                throw new RuntimeException('Unable to save order items.');
            }

            $stockStmt->bind_param('ii', $qty, $productId);
            if (!$stockStmt->execute()) {
                throw new RuntimeException('Unable to update stock.');
            }

            $availabilityStmt = $db->prepare(
                "UPDATE products
                 SET availability = CASE
                    WHEN stock_qty <= 0 AND availability = 'in_stock' THEN 'out_of_stock'
                    ELSE availability
                 END
                 WHERE id = ?"
            );
            if ($availabilityStmt) {
                $availabilityStmt->bind_param('i', $productId);
                $availabilityStmt->execute();
                $availabilityStmt->close();
            }
        }

        $itemStmt->close();
        $stockStmt->close();

        if ($clearCartAfterCheckout) {
            storeCartClear($userId);
        }
        $db->commit();

        return [
            'success' => true,
            'message' => 'Order placed successfully.',
            'order_number' => $orderNumber,
        ];
    } catch (Throwable $exception) {
        $db->rollback();
        return ['success' => false, 'message' => $exception->getMessage() ?: 'Unable to place order right now.'];
    }
}

function storePlaceOrder(int $userId, array $payload): array
{
    if ($userId <= 0) {
        return ['success' => false, 'message' => 'Unable to place order right now.'];
    }

    $items = storeCartItems($userId);
    if ($items === []) {
        return ['success' => false, 'message' => 'Your cart is empty.'];
    }

    return storePlacePreparedOrder($userId, $items, $payload, true);
}

function storePlaceDirectOrder(?int $userId, int $productId, int $quantity, array $payload): array
{
    $product = storeGetProductById($productId);
    if (!$product || !$product['is_active']) {
        return ['success' => false, 'message' => 'Product is unavailable.'];
    }

    $quantity = max(1, $quantity);
    if ((string) ($product['availability'] ?? 'out_of_stock') !== 'in_stock' || (int) ($product['stock_qty'] ?? 0) <= 0) {
        return ['success' => false, 'message' => 'This product is currently unavailable.'];
    }
    if ($quantity > (int) ($product['stock_qty'] ?? 0)) {
        return ['success' => false, 'message' => 'Only ' . (int) ($product['stock_qty'] ?? 0) . ' units are available.'];
    }

    $isGuestOrder = $userId === null || $userId <= 0;
    $checkoutUserId = $isGuestOrder ? storeGuestUserId() : $userId;
    if ($checkoutUserId === null || $checkoutUserId <= 0) {
        return ['success' => false, 'message' => 'Unable to place order right now.'];
    }

    $item = [
        'product_id' => (int) ($product['id'] ?? 0),
        'quantity' => $quantity,
        'name' => (string) ($product['name'] ?? 'Product'),
        'slug' => (string) ($product['slug'] ?? ''),
        'short_name' => (string) ($product['short_name'] ?? ''),
        'price' => (float) ($product['price'] ?? 0),
        'compare_price' => ($product['compare_price'] ?? null) !== null ? (float) $product['compare_price'] : null,
        'image_path' => $product['image_path'] !== null ? (string) $product['image_path'] : null,
        'stock_qty' => (int) ($product['stock_qty'] ?? 0),
        'availability' => (string) ($product['availability'] ?? 'in_stock'),
        'is_active' => (bool) ($product['is_active'] ?? true),
        'tag' => (string) ($product['tag'] ?? 'NEW'),
        'tone' => (string) ($product['tone'] ?? 'rose'),
        'category_name' => (string) ($product['category_name'] ?? 'Uncategorized'),
        'line_total' => (float) ($product['price'] ?? 0) * $quantity,
    ];

    $result = storePlacePreparedOrder((int) $checkoutUserId, [$item], $payload, false);
    if (!empty($result['success']) && $isGuestOrder) {
        storeRememberGuestOrder((string) ($result['order_number'] ?? ''));
    }

    return $result;
}

function storeUserOrders(int $userId): array
{
    storeEnsureSchema();
    $db = db();
    if (!$db || $userId <= 0) {
        return [];
    }

    $stmt = $db->prepare(
        'SELECT id, order_number, status, payment_method, payment_status, total, created_at
         FROM orders
         WHERE user_id = ?
         ORDER BY id DESC'
    );
    if (!$stmt) {
        return [];
    }
    $stmt->bind_param('i', $userId);
    $stmt->execute();
    $result = $stmt->get_result();

    $rows = [];
    while ($result && ($row = $result->fetch_assoc())) {
        $rows[] = [
            'id' => (int) ($row['id'] ?? 0),
            'order_number' => (string) ($row['order_number'] ?? ''),
            'status' => (string) ($row['status'] ?? 'pending'),
            'payment_method' => (string) ($row['payment_method'] ?? 'cod'),
            'payment_status' => (string) ($row['payment_status'] ?? 'unpaid'),
            'total' => (float) ($row['total'] ?? 0),
            'created_at' => (string) ($row['created_at'] ?? ''),
        ];
    }
    $stmt->close();

    return $rows;
}

function storeOrderDetail(string $orderNumber, ?int $userId = null): ?array
{
    storeEnsureSchema();
    $db = db();
    $orderNumber = trim($orderNumber);
    if (!$db || $orderNumber === '') {
        return null;
    }

    $sql = 'SELECT * FROM orders WHERE order_number = ?';
    if ($userId !== null && $userId > 0) {
        $sql .= ' AND user_id = ' . $userId;
    }
    $sql .= ' LIMIT 1';

    $stmt = $db->prepare($sql);
    if (!$stmt) {
        return null;
    }
    $stmt->bind_param('s', $orderNumber);
    $stmt->execute();
    $res = $stmt->get_result();
    $order = $res ? $res->fetch_assoc() : null;
    $stmt->close();

    if (!$order) {
        return null;
    }

    $orderId = (int) ($order['id'] ?? 0);
    $itemStmt = $db->prepare('SELECT * FROM order_items WHERE order_id = ? ORDER BY id ASC');
    if (!$itemStmt) {
        return null;
    }
    $itemStmt->bind_param('i', $orderId);
    $itemStmt->execute();
    $itemRes = $itemStmt->get_result();

    $items = [];
    while ($itemRes && ($row = $itemRes->fetch_assoc())) {
        $items[] = [
            'id' => (int) ($row['id'] ?? 0),
            'product_id' => $row['product_id'] !== null ? (int) $row['product_id'] : null,
            'product_name' => (string) ($row['product_name'] ?? ''),
            'category_name' => (string) ($row['category_name'] ?? ''),
            'quantity' => (int) ($row['quantity'] ?? 0),
            'unit_price' => (float) ($row['unit_price'] ?? 0),
            'cost_price' => (float) ($row['cost_price'] ?? 0),
            'line_total' => (float) ($row['line_total'] ?? 0),
            'image_path' => $row['image_path'] !== null ? (string) $row['image_path'] : null,
        ];
    }
    $itemStmt->close();

    $order['items'] = $items;
    return $order;
}

function storeAdminOrders(array $filters = []): array
{
    storeEnsureSchema();
    $db = db();
    if (!$db) {
        return [];
    }

    $status = trim((string) ($filters['status'] ?? ''));
    $search = trim((string) ($filters['search'] ?? ''));
    $dateFrom = trim((string) ($filters['date_from'] ?? ''));
    $dateTo = trim((string) ($filters['date_to'] ?? ''));

    $conditions = [];

    if ($status !== '' && in_array($status, storeOrderStatuses(), true)) {
        $safe = $db->real_escape_string($status);
        $conditions[] = "o.status = '{$safe}'";
    }
    if ($search !== '') {
        $safe = $db->real_escape_string($search);
        $conditions[] = "(o.order_number LIKE '%{$safe}%' OR o.recipient_name LIKE '%{$safe}%' OR o.phone LIKE '%{$safe}%' OR o.email LIKE '%{$safe}%')";
    }
    if ($dateFrom !== '') {
        $safe = $db->real_escape_string($dateFrom);
        $conditions[] = "DATE(o.created_at) >= '{$safe}'";
    }
    if ($dateTo !== '') {
        $safe = $db->real_escape_string($dateTo);
        $conditions[] = "DATE(o.created_at) <= '{$safe}'";
    }

    $where = $conditions !== [] ? ' WHERE ' . implode(' AND ', $conditions) : '';

    $sql = "SELECT
                o.id,
                o.order_number,
                o.status,
                o.payment_method,
                o.payment_status,
                o.total,
                o.recipient_name,
                o.phone,
                o.email,
                o.city,
                o.created_at,
                u.full_name AS user_name
            FROM orders o
            LEFT JOIN users u ON u.id = o.user_id" . $where .
            ' ORDER BY o.id DESC';

    $result = $db->query($sql);
    if (!$result) {
        return [];
    }

    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = [
            'id' => (int) ($row['id'] ?? 0),
            'order_number' => (string) ($row['order_number'] ?? ''),
            'status' => (string) ($row['status'] ?? 'pending'),
            'payment_method' => (string) ($row['payment_method'] ?? 'cod'),
            'payment_status' => (string) ($row['payment_status'] ?? 'unpaid'),
            'total' => (float) ($row['total'] ?? 0),
            'recipient_name' => (string) ($row['recipient_name'] ?? ''),
            'phone' => (string) ($row['phone'] ?? ''),
            'email' => (string) ($row['email'] ?? ''),
            'city' => (string) ($row['city'] ?? ''),
            'created_at' => (string) ($row['created_at'] ?? ''),
            'user_name' => (string) ($row['user_name'] ?? ''),
        ];
    }

    return $rows;
}

function storeOrderStatusUpdate(int $orderId, string $status): array
{
    storeEnsureSchema();
    $db = db();
    if (!$db || $orderId <= 0) {
        return ['success' => false, 'message' => 'Invalid order selected.'];
    }
    if (!in_array($status, storeOrderStatuses(), true)) {
        return ['success' => false, 'message' => 'Invalid status selected.'];
    }

    $stmt = $db->prepare('SELECT status FROM orders WHERE id = ? LIMIT 1');
    if (!$stmt) {
        return ['success' => false, 'message' => 'Order not found.'];
    }
    $stmt->bind_param('i', $orderId);
    $stmt->execute();
    $res = $stmt->get_result();
    $row = $res ? $res->fetch_assoc() : null;
    $stmt->close();
    if (!$row) {
        return ['success' => false, 'message' => 'Order not found.'];
    }

    $oldStatus = (string) ($row['status'] ?? 'pending');
    $db->begin_transaction();

    try {
        if ($oldStatus !== 'cancelled' && $status === 'cancelled') {
            $itemsStmt = $db->prepare('SELECT product_id, quantity FROM order_items WHERE order_id = ?');
            if ($itemsStmt) {
                $itemsStmt->bind_param('i', $orderId);
                $itemsStmt->execute();
                $itemRes = $itemsStmt->get_result();
                while ($itemRes && ($item = $itemRes->fetch_assoc())) {
                    $productId = (int) ($item['product_id'] ?? 0);
                    $qty = (int) ($item['quantity'] ?? 0);
                    if ($productId <= 0 || $qty <= 0) {
                        continue;
                    }

                    $stockStmt = $db->prepare('UPDATE products SET stock_qty = stock_qty + ? WHERE id = ?');
                    if ($stockStmt) {
                        $stockStmt->bind_param('ii', $qty, $productId);
                        $stockStmt->execute();
                        $stockStmt->close();
                    }

                    $availStmt = $db->prepare(
                        "UPDATE products
                         SET availability = CASE
                            WHEN availability = 'out_of_stock' AND stock_qty > 0 THEN 'in_stock'
                            ELSE availability
                         END
                         WHERE id = ?"
                    );
                    if ($availStmt) {
                        $availStmt->bind_param('i', $productId);
                        $availStmt->execute();
                        $availStmt->close();
                    }
                }
                $itemsStmt->close();
            }
        }

        $updateStmt = $db->prepare('UPDATE orders SET status = ? WHERE id = ?');
        if (!$updateStmt) {
            throw new RuntimeException('Unable to update order status.');
        }
        $updateStmt->bind_param('si', $status, $orderId);
        if (!$updateStmt->execute()) {
            $updateStmt->close();
            throw new RuntimeException('Unable to update order status.');
        }
        $updateStmt->close();

        $db->commit();
        return ['success' => true, 'message' => 'Order status updated.'];
    } catch (Throwable $exception) {
        $db->rollback();
        return ['success' => false, 'message' => 'Unable to update order status.'];
    }
}

function storeAdminStats(): array
{
    storeEnsureSchema();
    $db = db();
    if (!$db) {
        return [
            'products' => 0,
            'categories' => 0,
            'collections' => 0,
            'orders' => 0,
            'pending_orders' => 0,
            'users' => 0,
            'revenue' => 0.0,
        ];
    }

    $guestEmail = $db->real_escape_string(storeGuestCheckoutEmail());
    $stats = [
        'products' => 0,
        'categories' => 0,
        'collections' => 0,
        'orders' => 0,
        'pending_orders' => 0,
        'users' => 0,
        'revenue' => 0.0,
    ];

    $queries = [
        'products' => 'SELECT COUNT(*) AS total FROM products',
        'categories' => 'SELECT COUNT(*) AS total FROM categories',
        'collections' => 'SELECT COUNT(*) AS total FROM collections',
        'orders' => 'SELECT COUNT(*) AS total FROM orders',
        'pending_orders' => "SELECT COUNT(*) AS total FROM orders WHERE status IN ('pending', 'confirmed', 'processing')",
        'users' => "SELECT COUNT(*) AS total FROM users WHERE email <> '{$guestEmail}'",
        'revenue' => "SELECT COALESCE(SUM(total), 0) AS total FROM orders WHERE status <> 'cancelled'",
    ];

    foreach ($queries as $key => $sql) {
        $res = $db->query($sql);
        if (!$res) {
            continue;
        }
        $row = $res->fetch_assoc();
        if ($key === 'revenue') {
            $stats[$key] = (float) ($row['total'] ?? 0);
        } else {
            $stats[$key] = (int) ($row['total'] ?? 0);
        }
    }

    return $stats;
}

function storeSalesReport(string $from, string $to): array
{
    storeEnsureSchema();
    $db = db();
    if (!$db) {
        return ['summary' => ['orders' => 0, 'revenue' => 0.0, 'units' => 0, 'profit' => 0.0], 'top_products' => []];
    }

    $from = preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $from) ? $from : date('Y-m-01');
    $to = preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $to) ? $to : date('Y-m-d');

    $summary = ['orders' => 0, 'revenue' => 0.0, 'units' => 0, 'profit' => 0.0];

    $stmt = $db->prepare("SELECT COUNT(*) AS orders, COALESCE(SUM(total), 0) AS revenue FROM orders WHERE DATE(created_at) BETWEEN ? AND ? AND status <> 'cancelled'");
    if ($stmt) {
        $stmt->bind_param('ss', $from, $to);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();
        $summary['orders'] = (int) ($row['orders'] ?? 0);
        $summary['revenue'] = (float) ($row['revenue'] ?? 0);
    }

    $itemStmt = $db->prepare(
        "SELECT
            COALESCE(SUM(oi.quantity), 0) AS units,
            COALESCE(SUM(oi.line_total), 0) AS sales,
            COALESCE(SUM(oi.cost_price * oi.quantity), 0) AS cost
         FROM order_items oi
         INNER JOIN orders o ON o.id = oi.order_id
         WHERE DATE(o.created_at) BETWEEN ? AND ? AND o.status <> 'cancelled'"
    );
    if ($itemStmt) {
        $itemStmt->bind_param('ss', $from, $to);
        $itemStmt->execute();
        $res = $itemStmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $itemStmt->close();

        $summary['units'] = (int) ($row['units'] ?? 0);
        $summary['profit'] = (float) ($row['sales'] ?? 0) - (float) ($row['cost'] ?? 0);
    }

    $topProducts = [];
    $topStmt = $db->prepare(
        "SELECT
            oi.product_name,
            COALESCE(SUM(oi.quantity), 0) AS units,
            COALESCE(SUM(oi.line_total), 0) AS revenue
         FROM order_items oi
         INNER JOIN orders o ON o.id = oi.order_id
         WHERE DATE(o.created_at) BETWEEN ? AND ? AND o.status <> 'cancelled'
         GROUP BY oi.product_name
         ORDER BY units DESC, revenue DESC
         LIMIT 10"
    );
    if ($topStmt) {
        $topStmt->bind_param('ss', $from, $to);
        $topStmt->execute();
        $res = $topStmt->get_result();
        while ($res && ($row = $res->fetch_assoc())) {
            $topProducts[] = [
                'product_name' => (string) ($row['product_name'] ?? ''),
                'units' => (int) ($row['units'] ?? 0),
                'revenue' => (float) ($row['revenue'] ?? 0),
            ];
        }
        $topStmt->close();
    }

    return [
        'range' => ['from' => $from, 'to' => $to],
        'summary' => $summary,
        'top_products' => $topProducts,
    ];
}

function storePurchaseReport(string $from, string $to): array
{
    storeEnsureSchema();
    $db = db();
    if (!$db) {
        return ['summary' => ['entries' => 0, 'qty' => 0, 'cost' => 0.0], 'products' => []];
    }

    $from = preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $from) ? $from : date('Y-m-01');
    $to = preg_match('/^\\d{4}-\\d{2}-\\d{2}$/', $to) ? $to : date('Y-m-d');

    $summary = ['entries' => 0, 'qty' => 0, 'cost' => 0.0];
    $stmt = $db->prepare("SELECT COUNT(*) AS entries, COALESCE(SUM(quantity), 0) AS qty, COALESCE(SUM(total_cost), 0) AS cost FROM purchase_entries WHERE DATE(created_at) BETWEEN ? AND ?");
    if ($stmt) {
        $stmt->bind_param('ss', $from, $to);
        $stmt->execute();
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();
        $summary = [
            'entries' => (int) ($row['entries'] ?? 0),
            'qty' => (int) ($row['qty'] ?? 0),
            'cost' => (float) ($row['cost'] ?? 0),
        ];
    }

    $products = [];
    $productStmt = $db->prepare(
        "SELECT
            p.name AS product_name,
            COALESCE(SUM(pe.quantity), 0) AS qty,
            COALESCE(SUM(pe.total_cost), 0) AS cost
         FROM purchase_entries pe
         INNER JOIN products p ON p.id = pe.product_id
         WHERE DATE(pe.created_at) BETWEEN ? AND ?
         GROUP BY p.name
         ORDER BY cost DESC, qty DESC"
    );
    if ($productStmt) {
        $productStmt->bind_param('ss', $from, $to);
        $productStmt->execute();
        $res = $productStmt->get_result();
        while ($res && ($row = $res->fetch_assoc())) {
            $products[] = [
                'product_name' => (string) ($row['product_name'] ?? ''),
                'qty' => (int) ($row['qty'] ?? 0),
                'cost' => (float) ($row['cost'] ?? 0),
            ];
        }
        $productStmt->close();
    }

    return [
        'range' => ['from' => $from, 'to' => $to],
        'summary' => $summary,
        'products' => $products,
    ];
}
