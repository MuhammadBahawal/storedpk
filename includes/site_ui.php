<?php

declare(strict_types=1);

require_once __DIR__ . '/data.php';
require_once __DIR__ . '/store_service.php';

function assetUrl(string $path): string
{
    $normalized = ltrim(str_replace('\\', '/', $path), '/');
    $fullPath = dirname(__DIR__) . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $normalized);
    $version = @filemtime($fullPath);

    if (!$version) {
        return $normalized;
    }

    return $normalized . '?v=' . $version;
}

function renderPageStart(string $title, string $bodyClass = ''): void
{
    $site = getSiteConfig();
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="description" content="LS Store.pk - curated beauty and lifestyle store.">
        <title><?= esc($title); ?> | <?= esc((string) ($site['title'] ?? 'LS Store.pk')); ?></title>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;500;600;700&family=Nunito:wght@400;500;600;700;800&display=swap" rel="stylesheet">
        <link rel="stylesheet" href="<?= esc(assetUrl('assets/css/style.css')); ?>">
    </head>
    <body class="<?= esc($bodyClass); ?>">
    <?php
}

function renderStoreHeader(string $active = 'home'): void
{
    $site = getSiteConfig();
    $currentUser = storeCurrentUser();
    $cartCount = storeCartCount($currentUser['id'] ?? null);
    ?>
    <div class="announcement-bar">
        <p><?= esc((string) ($site['announcement'] ?? 'Modern and minimal wardrobe')); ?></p>
    </div>

    <header class="site-header">
        <div class="container header-inner">
            <a href="index.php" class="logo" aria-label="LS Store.pk home">
                <img src="<?= esc(assetUrl('images/logo.png')); ?>" alt="LS Store.pk" class="logo-mark">
            </a>

            <button class="menu-toggle" type="button" aria-expanded="false" aria-controls="main-nav">
                <span></span>
                <span></span>
                <span></span>
            </button>

            <nav id="main-nav" class="main-nav" aria-label="Primary navigation">
                <ul>
                    <li><a href="index.php" class="<?= $active === 'home' ? 'active' : ''; ?>">Home</a></li>
                    <li><a href="shop-all.php" class="<?= $active === 'shop' ? 'active' : ''; ?>">Shop All</a></li>
                    <li><a href="explore.php" class="<?= $active === 'explore' ? 'active' : ''; ?>">Explore</a></li>
                    <li><a href="contact.php" class="<?= $active === 'contact' ? 'active' : ''; ?>">Contact Us</a></li>
                    <li><a href="about.php" class="<?= $active === 'about' ? 'active' : ''; ?>">About Us</a></li>
                </ul>
            </nav>

            <div class="header-actions" aria-label="Header actions">
                <button type="button" class="icon-btn" data-search-open aria-label="Search products" aria-haspopup="dialog" aria-controls="header-search-modal" aria-expanded="false">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <circle cx="11" cy="11" r="6"></circle>
                        <path d="M20 20l-4.2-4.2"></path>
                    </svg>
                </button>
                <a href="<?= $currentUser ? 'account.php' : 'login.php'; ?>" class="icon-btn" aria-label="Account">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <circle cx="12" cy="8" r="4"></circle>
                        <path d="M4 20c1.8-3.6 4.5-5.2 8-5.2s6.2 1.6 8 5.2"></path>
                    </svg>
                </a>
                <a href="cart.php" class="icon-btn icon-btn-cart" aria-label="Cart">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M6.5 9h11l-1 10h-9z"></path>
                        <path d="M9 9V7a3 3 0 0 1 6 0v2"></path>
                    </svg>
                    <?php if ($cartCount > 0): ?>
                        <span class="cart-count"><?= (int) $cartCount; ?></span>
                    <?php endif; ?>
                </a>
            </div>
        </div>
    </header>
    <?php renderHeaderSearchModal(); ?>
    <?php
}

function renderHeaderSearchModal(): void
{
    $products = storeGetProducts([
        'only_active' => true,
        'limit' => 12,
    ]);
    ?>
    <div class="search-modal" id="header-search-modal" data-search-modal hidden>
        <button type="button" class="search-modal-backdrop" data-search-close aria-label="Close search panel"></button>
        <div class="search-dialog" role="dialog" aria-modal="true" aria-labelledby="header-search-title">
            <div class="search-dialog-head">
                <form method="get" action="explore.php" class="search-form" data-search-form>
                    <label class="sr-only" for="header-search-input">Search products</label>
                    <span class="search-form-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24">
                            <circle cx="11" cy="11" r="6"></circle>
                            <path d="M20 20l-4.2-4.2"></path>
                        </svg>
                    </span>
                    <input
                        id="header-search-input"
                        class="search-form-input"
                        type="search"
                        name="q"
                        value=""
                        placeholder="Search"
                        autocomplete="off"
                        aria-describedby="header-search-title"
                        data-search-input
                    >
                </form>
                <button type="button" class="search-close-btn" data-search-close aria-label="Close search">
                    <svg viewBox="0 0 24 24" aria-hidden="true">
                        <path d="M6 6l12 12"></path>
                        <path d="M18 6L6 18"></path>
                    </svg>
                </button>
            </div>
            <div class="search-dialog-body">
                <p id="header-search-title" class="search-dialog-title">Products</p>
                <div class="search-results-grid" data-search-results>
                    <?php renderHeaderSearchCards($products); ?>
                </div>
                <p class="search-empty-message" data-search-empty <?= $products === [] ? '' : 'hidden'; ?>>No products found.</p>
            </div>
        </div>
    </div>
    <?php
}

function renderHeaderSearchCards(array $products): void
{
    foreach ($products as $product) {
        $slug = (string) ($product['slug'] ?? '');
        $url = $slug !== '' ? 'product.php?slug=' . rawurlencode($slug) : 'product.php?id=' . (int) ($product['id'] ?? 0);
        ?>
        <article class="search-card tone-<?= esc((string) ($product['tone'] ?? 'rose')); ?>">
            <a href="<?= esc($url); ?>" class="search-card-media tone-<?= esc((string) ($product['tone'] ?? 'rose')); ?>">
                <?php renderImageOrFallback($product['image_path'] ?? null, (string) ($product['name'] ?? 'Product'), (string) ($product['short_name'] ?? 'Item')); ?>
                <?php if ((string) ($product['availability'] ?? 'in_stock') !== 'in_stock'): ?>
                    <span class="sold-out"><?= esc((string) ($product['stock_label'] ?? 'Out of Stock')); ?></span>
                <?php endif; ?>
            </a>
            <h3><a href="<?= esc($url); ?>"><?= esc((string) ($product['name'] ?? 'Product')); ?></a></h3>
            <p class="price">
                Rs. <?= number_format((float) ($product['price'] ?? 0), 0); ?>
                <?php if (($product['compare_price'] ?? null) !== null && (float) $product['compare_price'] > (float) $product['price']): ?>
                    <span class="compare-price">Rs. <?= number_format((float) $product['compare_price'], 0); ?></span>
                <?php endif; ?>
            </p>
        </article>
        <?php
    }
}

function renderStoreFooter(): void
{
    ?>
    <footer class="site-footer">
        <div class="container footer-inner">
            <h2>LS Store.pk</h2>
            <nav aria-label="Footer links">
                <ul>
                    <li><a href="shop-all.php">Shop all</a></li>
                    <li><a href="about.php">About us</a></li>
                    <li><a href="contact.php">Contact us</a></li>
                </ul>
            </nav>
            <p>Copyright <?= date('Y'); ?> LS Store.pk. All rights reserved.</p>
        </div>
    </footer>

    <script src="<?= esc(assetUrl('assets/js/main.js')); ?>"></script>
    </body>
    </html>
    <?php
}

function renderImageOrFallback(?string $imagePath, string $alt, string $fallbackText = 'LS Store.pk'): void
{
    $path = $imagePath !== null ? trim($imagePath) : '';
    if ($path !== '') {
        ?>
        <img src="<?= esc($path); ?>" alt="<?= esc($alt); ?>" loading="lazy">
        <?php
        return;
    }

    ?>
    <div class="media-fallback" aria-hidden="true"><?= esc($fallbackText); ?></div>
    <?php
}
