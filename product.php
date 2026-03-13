<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/store_service.php';
require_once __DIR__ . '/includes/site_ui.php';

storeEnsureSchema();

$slug = trim((string) ($_GET['slug'] ?? ''));
$productId = (int) ($_GET['id'] ?? 0);

$product = null;
if ($slug !== '') {
    $product = storeGetProductBySlug($slug);
}
if (!$product && $productId > 0) {
    $product = storeGetProductById($productId);
}

if (!$product || !$product['is_active']) {
    renderPageStart('Product Not Found', 'catalog-page');
    renderStoreHeader('');
    ?>
    <main class="catalog-main">
        <section class="page-shell">
            <div class="container">
                <div class="empty-state">
                    <h3>Product not found</h3>
                    <p>This product does not exist or is inactive.</p>
                    <a href="shop-all.php" class="cta-btn">Back to shop</a>
                </div>
            </div>
        </section>
    </main>
    <?php
    renderStoreFooter();
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['action'] ?? '') === 'add_to_cart') {
    $currentUser = storeCurrentUser();
    if (!$currentUser) {
        $nextUrl = 'product.php?slug=' . rawurlencode((string) ($product['slug'] ?? ''));
        header('Location: login.php?next=' . rawurlencode($nextUrl));
        exit;
    }

    $qty = max(1, (int) ($_POST['quantity'] ?? 1));
    $result = storeCartAdd((int) $currentUser['id'], (int) $product['id'], $qty);
    storeFlashSet('product_add_to_cart', $result);
    header('Location: product.php?slug=' . rawurlencode((string) ($product['slug'] ?? '')));
    exit;
}

$currentUser = storeCurrentUser();
$flash = storeFlashPull('product_add_to_cart');

$relatedProducts = storeGetProducts([
    'category_id' => (int) ($product['category_id'] ?? 0),
    'only_active' => true,
    'limit' => 10,
]);
$relatedProducts = array_values(array_filter(
    $relatedProducts,
    static fn(array $item): bool => (int) ($item['id'] ?? 0) !== (int) ($product['id'] ?? 0)
));
$relatedProducts = array_slice($relatedProducts, 0, 6);

$isAvailable = (string) ($product['availability'] ?? 'out_of_stock') === 'in_stock' && (int) ($product['stock_qty'] ?? 0) > 0;
$buyNowUrl = (string) ($product['slug'] ?? '') !== ''
    ? 'buy-now.php?product=' . rawurlencode((string) $product['slug'])
    : 'buy-now.php?id=' . (int) ($product['id'] ?? 0);

renderPageStart((string) $product['name'], 'catalog-page product-page');
renderStoreHeader('');
?>

<main class="catalog-main">
    <section class="page-shell">
        <div class="container">
            <?php if (is_array($flash)): ?>
                <div class="flash <?= !empty($flash['success']) ? 'flash-success' : 'flash-error'; ?>">
                    <?= esc((string) ($flash['message'] ?? '')); ?>
                </div>
            <?php endif; ?>

            <div class="product-detail-grid">
                <div class="product-detail-media tone-<?= esc((string) ($product['tone'] ?? 'rose')); ?>">
                    <?php renderImageOrFallback($product['image_path'] ?? null, (string) $product['name'], (string) ($product['short_name'] ?? 'Item')); ?>
                </div>
                <div class="product-detail-content">
                    <p class="product-breadcrumb"><a href="shop-all.php">Shop all</a> / <?= esc((string) ($product['category_name'] ?? 'Category')); ?></p>
                    <h1><?= esc((string) ($product['name'] ?? 'Product')); ?></h1>
                    <p class="product-status <?= $isAvailable ? 'status-live' : 'status-out'; ?>"><?= esc((string) ($product['stock_label'] ?? 'Out of Stock')); ?></p>
                    <p class="price">
                        Rs. <?= number_format((float) ($product['price'] ?? 0), 0); ?>
                        <?php if (($product['compare_price'] ?? null) !== null && (float) $product['compare_price'] > (float) $product['price']): ?>
                            <span class="compare-price">Rs. <?= number_format((float) $product['compare_price'], 0); ?></span>
                        <?php endif; ?>
                    </p>
                    <p class="product-meta">Tag: <?= esc((string) ($product['tag'] ?? 'NEW')); ?></p>
                    <p class="product-meta">Stock left: <?= (int) ($product['stock_qty'] ?? 0); ?></p>
                    <p class="product-description"><?= nl2br(esc((string) ($product['description'] ?? 'No description available.'))); ?></p>

                    <form method="post" action="product.php?slug=<?= esc(rawurlencode((string) ($product['slug'] ?? ''))); ?>" class="product-cart-form">
                        <input type="hidden" name="action" value="add_to_cart">
                        <label>
                            <span>Quantity</span>
                            <input type="number" name="quantity" min="1" max="<?= max(1, (int) ($product['stock_qty'] ?? 1)); ?>" value="1" <?= $isAvailable ? '' : 'disabled'; ?>>
                        </label>
                        <button type="submit" class="cta-btn" <?= $isAvailable ? '' : 'disabled'; ?>>Add to Cart</button>
                        <?php if ($isAvailable): ?>
                            <a href="<?= esc($buyNowUrl); ?>" class="mini-link order-now-link">Order Now</a>
                        <?php endif; ?>
                    </form>
                    <?php if (!$currentUser && $isAvailable): ?>
                        <p class="hint-text">Login is optional for direct orders. Use <strong>Order Now</strong> to place this order as a guest.</p>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <?php if ($relatedProducts !== []): ?>
        <section class="page-shell compact-top">
            <div class="container">
                <div class="section-head">
                    <h2>Related Products</h2>
                </div>
                <div class="catalog-products-grid">
                    <?php foreach ($relatedProducts as $item): ?>
                        <article class="catalog-product-card tone-<?= esc((string) ($item['tone'] ?? 'rose')); ?>">
                            <a href="product.php?slug=<?= esc((string) ($item['slug'] ?? '')); ?>" class="catalog-product-media">
                                <?php renderImageOrFallback($item['image_path'] ?? null, (string) ($item['name'] ?? 'Product'), (string) ($item['short_name'] ?? 'Item')); ?>
                                <span class="image-tag"><?= esc((string) ($item['tag'] ?? 'NEW')); ?></span>
                            </a>
                            <h3><?= esc((string) ($item['name'] ?? 'Product')); ?></h3>
                            <p class="price">Rs. <?= number_format((float) ($item['price'] ?? 0), 0); ?></p>
                        </article>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
    <?php endif; ?>
</main>

<?php renderStoreFooter(); ?>
