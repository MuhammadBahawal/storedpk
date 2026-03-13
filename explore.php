<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/store_service.php';
require_once __DIR__ . '/includes/site_ui.php';

$query = trim((string) ($_GET['q'] ?? ''));
$products = storeGetProducts([
    'only_active' => true,
    'search' => $query,
]);

renderPageStart('Explore', 'catalog-page explore-page');
renderStoreHeader('explore');
?>

<main class="catalog-main">
    <section class="page-shell">
        <div class="container">
            <div class="section-head explore-head">
                <h1 class="page-title">Explore</h1>
                <p class="explore-count"><?= count($products); ?> products</p>
            </div>

            <form method="get" class="explore-search" action="explore.php">
                <input
                    type="search"
                    name="q"
                    value="<?= esc($query); ?>"
                    placeholder="Search products, tags, or categories"
                    aria-label="Search products"
                >
                <button type="submit" class="cta-btn">Search</button>
            </form>

            <?php if ($products === []): ?>
                <div class="empty-state">
                    <h3>No products found</h3>
                    <p>Try a different keyword or upload products from dashboard.</p>
                    <a href="dashboard.php" class="cta-btn">Go to dashboard</a>
                </div>
            <?php else: ?>
                <div class="explore-grid">
                    <?php foreach ($products as $product): ?>
                        <article class="explore-card tone-<?= esc((string) ($product['tone'] ?? 'rose')); ?>">
                            <a href="product.php?slug=<?= esc((string) ($product['slug'] ?? '')); ?>" class="explore-image">
                                <?php renderImageOrFallback($product['image_path'] ?? null, (string) $product['name'], (string) ($product['short_name'] ?? 'Item')); ?>
                                <span class="image-tag"><?= esc((string) ($product['tag'] ?? 'NEW')); ?></span>
                                <?php if ((string) ($product['availability'] ?? 'in_stock') !== 'in_stock'): ?>
                                    <span class="sold-out"><?= esc((string) ($product['stock_label'] ?? 'Out of Stock')); ?></span>
                                <?php endif; ?>
                            </a>
                            <h2><?= esc((string) ($product['name'] ?? 'Product')); ?></h2>
                            <p class="price">
                                Rs. <?= number_format((float) ($product['price'] ?? 0), 0); ?>
                                <?php if (($product['compare_price'] ?? null) !== null && (float) $product['compare_price'] > (float) $product['price']): ?>
                                    <span class="compare-price">Rs. <?= number_format((float) $product['compare_price'], 0); ?></span>
                                <?php endif; ?>
                            </p>
                            <div class="card-actions">
                                <a href="product.php?slug=<?= esc((string) ($product['slug'] ?? '')); ?>" class="mini-link">View details</a>
                                <?php if ((string) ($product['availability'] ?? 'out_of_stock') === 'in_stock'): ?>
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
