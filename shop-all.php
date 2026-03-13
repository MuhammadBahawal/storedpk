<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/store_service.php';
require_once __DIR__ . '/includes/site_ui.php';

$selectedSlug = trim((string) ($_GET['category'] ?? ''));
$categories = storeGetCategories();
$selectedCategory = $selectedSlug !== '' ? getCatalogCategoryBySlug($selectedSlug) : null;
$categoryNotFound = $selectedSlug !== '' && $selectedCategory === null;
$products = $selectedCategory
    ? storeGetProducts(['category_id' => (int) $selectedCategory['id'], 'only_active' => true])
    : [];

renderPageStart('Shop All', 'catalog-page');
renderStoreHeader('shop');
?>

<main class="catalog-main">
    <section class="page-shell">
        <div class="container">
            <h1 class="page-title">Shop by Collection</h1>

            <div class="category-filter-grid">
                <?php foreach ($categories as $category): ?>
                    <?php
                    $isActive = $selectedCategory !== null && $selectedCategory['slug'] === $category['slug'];
                    $countLabel = ((int) $category['product_count']) === 1 ? '1 item' : ((int) $category['product_count']) . ' items';
                    $categoryUrl = 'shop-all.php?category=' . rawurlencode((string) $category['slug']) . '#collection-products';
                    ?>
                    <article class="category-filter-card tone-<?= esc((string) ($category['tone'] ?? 'rose')); ?><?= $isActive ? ' is-active' : ''; ?>">
                        <a href="<?= esc($categoryUrl); ?>" class="category-filter-link" aria-label="Browse <?= esc((string) $category['name']); ?> products">
                            <div class="category-filter-media">
                                <?php renderImageOrFallback($category['preview_image'] ?? null, (string) $category['name'], (string) $category['name']); ?>
                            </div>
                            <h2><?= esc((string) $category['name']); ?></h2>
                            <p><?= esc($countLabel); ?></p>
                        </a>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <section class="page-shell compact-top" id="collection-products">
        <div class="container">
            <?php if (!$selectedCategory): ?>
                <div class="empty-state">
                    <h3><?= $categoryNotFound ? 'Category not found' : 'Select a category to view products'; ?></h3>
                    <p>
                        <?= $categoryNotFound
                            ? 'The selected category does not exist. Choose a category from the list above.'
                            : 'Pick any category above and all uploaded products for that category will appear here.'; ?>
                    </p>
                </div>
            <?php else: ?>
                <div class="section-head">
                    <h2><?= esc((string) $selectedCategory['name']); ?> Products</h2>
                    <a class="view-all" href="shop-all.php">Show all categories</a>
                </div>

                <?php if ($products === []): ?>
                    <div class="empty-state">
                        <h3>No products found</h3>
                        <p>Use the dashboard to upload products for this category.</p>
                        <a href="dashboard.php" class="cta-btn">Go to dashboard</a>
                    </div>
                <?php else: ?>
                    <div class="catalog-products-grid">
                        <?php foreach ($products as $product): ?>
                            <article class="catalog-product-card tone-<?= esc((string) ($product['tone'] ?? 'rose')); ?>">
                                <a href="product.php?slug=<?= esc((string) ($product['slug'] ?? '')); ?>" class="catalog-product-media">
                                    <?php renderImageOrFallback($product['image_path'] ?? null, (string) $product['name'], (string) ($product['short_name'] ?? 'Item')); ?>
                                    <span class="image-tag"><?= esc((string) ($product['tag'] ?? 'NEW')); ?></span>
                                    <?php if ((string) ($product['availability'] ?? 'in_stock') !== 'in_stock'): ?>
                                        <span class="sold-out"><?= esc((string) ($product['stock_label'] ?? 'Out of Stock')); ?></span>
                                    <?php endif; ?>
                                </a>
                                <h3><?= esc((string) ($product['name'] ?? 'Product')); ?></h3>
                                <p class="product-meta"><?= esc((string) ($product['category_name'] ?? 'Uncategorized')); ?></p>
                                <p class="product-meta">Stock: <?= (int) ($product['stock_qty'] ?? 0); ?></p>
                                <p class="price">
                                    Rs. <?= number_format((float) ($product['price'] ?? 0), 0); ?>
                                    <?php if (($product['compare_price'] ?? null) !== null && (float) $product['compare_price'] > (float) $product['price']): ?>
                                        <span class="compare-price">Rs. <?= number_format((float) $product['compare_price'], 0); ?></span>
                                    <?php endif; ?>
                                </p>
                                <a href="product.php?slug=<?= esc((string) ($product['slug'] ?? '')); ?>" class="mini-link">View details</a>
                                <?php if ((string) ($product['availability'] ?? 'out_of_stock') === 'in_stock'): ?>
                                    <form method="post" action="cart.php" class="inline-form">
                                        <input type="hidden" name="action" value="add">
                                        <input type="hidden" name="product_id" value="<?= (int) ($product['id'] ?? 0); ?>">
                                        <input type="hidden" name="quantity" value="1">
                                        <button type="submit" class="mini-link">Add to cart</button>
                                    </form>
                                <?php endif; ?>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </section>
</main>

<?php renderStoreFooter(); ?>
