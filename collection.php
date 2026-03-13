<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/store_service.php';
require_once __DIR__ . '/includes/site_ui.php';

storeEnsureSchema();

$slug = trim((string) ($_GET['slug'] ?? ''));
$collectionId = (int) ($_GET['id'] ?? 0);

$collection = $slug !== '' ? storeGetCollectionBySlug($slug) : null;
if (!$collection && $collectionId > 0) {
    $collection = storeGetCollectionById($collectionId);
}

if (!$collection) {
    renderPageStart('Collection Not Found', 'catalog-page collection-page');
    renderStoreHeader('shop');
    ?>
    <main class="catalog-main">
        <section class="page-shell">
            <div class="container">
                <div class="empty-state">
                    <h3>Collection not found</h3>
                    <p>This collection does not exist yet. Choose another collection from the homepage.</p>
                    <a href="index.php#collections" class="cta-btn">Back to collections</a>
                </div>
            </div>
        </section>
    </main>
    <?php
    renderStoreFooter();
    exit;
}

$resolvedCategory = storeResolveCollectionCategory($collection);
$products = storeGetCollectionProducts($collection, ['only_active' => true]);
$currentUser = storeCurrentUser();
$collectionSlug = trim((string) ($collection['slug'] ?? ''));
$productCount = count($products);
$productCountLabel = $productCount === 1 ? '1 product' : $productCount . ' products';

renderPageStart((string) ($collection['name'] ?? 'Collection'), 'catalog-page collection-page');
renderStoreHeader('shop');
?>

<main class="catalog-main">
    <section class="page-shell">
        <div class="container">
            <section class="collection-hero tone-<?= esc((string) ($collection['tone'] ?? 'rose')); ?>">
                <div class="collection-hero-head">
                    <div class="collection-hero-copy">
                        <p class="product-breadcrumb"><a href="index.php">Home</a> / <a href="shop-all.php">Shop all</a> / Collection</p>
                        <span class="collection-hero-badge"><?= esc((string) ($collection['tag'] ?? 'NEW')); ?></span>
                        <h1><?= esc((string) ($collection['name'] ?? 'Collection')); ?></h1>
                        <p>
                            Browse the products linked to this collection and place an order instantly. Login is optional for direct checkout.
                        </p>
                        <div class="collection-hero-meta">
                            <span class="admin-chip"><?= esc($productCountLabel); ?></span>
                            <?php if ($collectionSlug !== ''): ?>
                                <span class="admin-chip">/<?= esc($collectionSlug); ?></span>
                            <?php endif; ?>
                            <?php if ($resolvedCategory): ?>
                                <a class="mini-link" href="shop-all.php?category=<?= esc((string) ($resolvedCategory['slug'] ?? '')); ?>">
                                    Open <?= esc((string) ($resolvedCategory['name'] ?? 'Category')); ?>
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="collection-hero-mark" aria-hidden="true">
                        <span class="collection-hero-symbol"><?= esc((string) ($collection['symbol'] ?? '*')); ?></span>
                    </div>
                </div>
            </section>
        </div>
    </section>

    <section class="page-shell compact-top">
        <div class="container">
            <?php if (!$currentUser): ?>
                <p class="guest-order-note">Guest checkout is enabled. Use <strong>Order Now</strong> on any product below.</p>
            <?php endif; ?>

            <?php if ($products === []): ?>
                <div class="empty-state">
                    <h3>No products in this collection</h3>
                    <p>This collection is being updated. Please browse the full catalog or check back again shortly.</p>
                    <a href="shop-all.php" class="cta-btn">Browse shop</a>
                </div>
            <?php else: ?>
                <div class="catalog-products-grid">
                    <?php foreach ($products as $product): ?>
                        <?php
                        $productSlug = (string) ($product['slug'] ?? '');
                        $productUrl = $productSlug !== '' ? 'product.php?slug=' . rawurlencode($productSlug) : 'product.php?id=' . (int) ($product['id'] ?? 0);
                        $buyNowUrl = $productSlug !== '' ? 'buy-now.php?product=' . rawurlencode($productSlug) : 'buy-now.php?id=' . (int) ($product['id'] ?? 0);
                        $isAvailable = (string) ($product['availability'] ?? 'out_of_stock') === 'in_stock' && (int) ($product['stock_qty'] ?? 0) > 0;
                        ?>
                        <article class="catalog-product-card tone-<?= esc((string) ($product['tone'] ?? 'rose')); ?>">
                            <a href="<?= esc($productUrl); ?>" class="catalog-product-media">
                                <?php renderImageOrFallback($product['image_path'] ?? null, (string) ($product['name'] ?? 'Product'), (string) ($product['short_name'] ?? 'Item')); ?>
                                <span class="image-tag"><?= esc((string) ($product['tag'] ?? 'NEW')); ?></span>
                                <?php if (!$isAvailable): ?>
                                    <span class="sold-out"><?= esc((string) ($product['stock_label'] ?? 'Out of Stock')); ?></span>
                                <?php endif; ?>
                            </a>
                            <h3><?= esc((string) ($product['name'] ?? 'Product')); ?></h3>
                            <p class="product-meta"><?= esc((string) ($product['category_name'] ?? 'Uncategorized')); ?></p>
                            <p class="product-meta">Stock: <?= (int) ($product['stock_qty'] ?? 0); ?></p>
                            <p class="price">
                                Rs. <?= number_format((float) ($product['price'] ?? 0), 0); ?>
                                <?php if (($product['compare_price'] ?? null) !== null && (float) $product['compare_price'] > (float) ($product['price'] ?? 0)): ?>
                                    <span class="compare-price">Rs. <?= number_format((float) $product['compare_price'], 0); ?></span>
                                <?php endif; ?>
                            </p>
                            <div class="card-actions">
                                <a href="<?= esc($productUrl); ?>" class="mini-link">View details</a>
                                <?php if ($isAvailable): ?>
                                    <a href="<?= esc($buyNowUrl); ?>" class="mini-link order-now-link">Order now</a>
                                <?php endif; ?>
                                <?php if ($currentUser && $isAvailable): ?>
                                    <form method="post" action="cart.php" class="inline-form">
                                        <input type="hidden" name="action" value="add">
                                        <input type="hidden" name="product_id" value="<?= (int) ($product['id'] ?? 0); ?>">
                                        <input type="hidden" name="quantity" value="1">
                                        <button type="submit" class="mini-link">Add to cart</button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </section>
</main>

<?php renderStoreFooter(); ?>
