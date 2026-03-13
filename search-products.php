<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/store_service.php';

header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');

$query = trim((string) ($_GET['q'] ?? ''));
$limit = (int) ($_GET['limit'] ?? 12);
$limit = max(1, min(24, $limit));

$products = storeGetProducts([
    'only_active' => true,
    'search' => $query,
    'limit' => $limit,
]);

$payload = array_map(
    static function (array $product): array {
        $comparePrice = $product['compare_price'] ?? null;
        return [
            'id' => (int) ($product['id'] ?? 0),
            'slug' => (string) ($product['slug'] ?? ''),
            'name' => (string) ($product['name'] ?? ''),
            'short_name' => (string) ($product['short_name'] ?? ''),
            'tone' => (string) ($product['tone'] ?? 'rose'),
            'price' => (float) ($product['price'] ?? 0),
            'compare_price' => $comparePrice !== null ? (float) $comparePrice : null,
            'image_path' => $product['image_path'] !== null ? (string) $product['image_path'] : null,
            'availability' => (string) ($product['availability'] ?? 'in_stock'),
            'stock_label' => (string) ($product['stock_label'] ?? ''),
        ];
    },
    $products
);

$json = json_encode(
    [
        'success' => true,
        'query' => $query,
        'products' => $payload,
    ],
    JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
);

if ($json === false) {
    http_response_code(500);
    echo '{"success":false,"query":"","products":[]}';
    exit;
}

echo $json;

