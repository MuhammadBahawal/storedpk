<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/data.php';
require_once __DIR__ . '/includes/store_service.php';
require_once __DIR__ . '/includes/site_ui.php';

$site = getSiteConfig();
$collections = getCollections();
$categorySections = getCategorySections();
$faqs = getFaqs();
$stats = getStats();
$currentUser = storeCurrentUser();
$cartCount = storeCartCount($currentUser['id'] ?? null);

function renderProductSection(array $section): void
{
    $id = 'slider-' . preg_replace('/[^a-z0-9]+/i', '-', strtolower((string) ($section['title'] ?? 'section')));
    $products = $section['products'] ?? [];
    ?>
    <section class="product-section" aria-label="<?= esc((string) ($section['title'] ?? 'Products')); ?>">
        <div class="section-head">
            <h2><?= esc((string) ($section['title'] ?? 'Products')); ?></h2>
            <a href="explore.php" class="view-all">View all</a>
        </div>

        <div class="product-slider-wrap">
            <button type="button" class="slider-btn slider-btn-prev" data-slide-prev="<?= esc($id); ?>" aria-label="Previous products">
                <span aria-hidden="true">&larr;</span>
            </button>

            <div class="product-slider" id="<?= esc($id); ?>" data-slider>
                <?php foreach ($products as $product): ?>
                    <article class="product-card">
                        <a href="explore.php" class="product-image tone-<?= esc((string) ($product['tone'] ?? 'rose')); ?>">
                            <span class="image-tag"><?= esc((string) ($product['tag'] ?? 'BESTSELLER')); ?></span>
                            <span class="image-title"><?= esc((string) ($product['short'] ?? 'Beauty')); ?></span>
                        </a>
                        <h3><a href="explore.php"><?= esc((string) ($product['name'] ?? 'Product Name')); ?></a></h3>
                        <p class="price">Rs. <?= number_format((float) ($product['price'] ?? 0), 0); ?></p>
                    </article>
                <?php endforeach; ?>
            </div>

            <button type="button" class="slider-btn slider-btn-next" data-slide-next="<?= esc($id); ?>" aria-label="Next products">
                <span aria-hidden="true">&rarr;</span>
            </button>
        </div>
    </section>
    <?php
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="LS Store.pk - Makeup, nails, and jewelry collections.">
    <title><?= esc($site['title']); ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;500;600;700&family=Nunito:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= esc(assetUrl('assets/css/style.css')); ?>">
</head>
<body>
    <div class="announcement-bar">
        <p><?= esc($site['announcement']); ?></p>
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
                    <li><a href="index.php" class="active">Home</a></li>
                    <li><a href="shop-all.php">Shop All</a></li>
                    <li><a href="explore.php">Explore</a></li>
                    <li><a href="contact.php">Contact Us</a></li>
                    <li><a href="about.php">About Us</a></li>
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

    <main>
        <section class="hero-section">
            <div class="container hero-shell">
                <div class="hero-card">
                    <span class="hero-pill">New Collection</span>
                    <h1>
                        <span class="title-main">LS Store.pk</span>
                    </h1>
                    <p class="hero-copy">is Here</p>
                    <a href="shop-all.php" class="cta-btn">Shop now</a>
                </div>

                <div class="deco deco-1"></div>
                <div class="deco deco-2"></div>
                <div class="deco deco-3"></div>
                <div class="deco deco-4"></div>
                <div class="deco deco-5"></div>
                <div class="deco deco-6"></div>
                <div class="deco deco-7"></div>
                <div class="deco deco-8"></div>
            </div>
        </section>

        <section class="collections" id="collections" aria-label="Shop by collection">
            <div class="container">
                <div class="section-head">
                    <h2>Shop by Collection</h2>
                </div>

                <div class="collection-grid">
                    <?php foreach ($collections as $collection): ?>
                        <?php
                        $collectionSlug = trim((string) ($collection['slug'] ?? ''));
                        $collectionUrl = $collectionSlug !== '' ? 'collection.php?slug=' . rawurlencode($collectionSlug) : 'shop-all.php';
                        ?>
                        <article class="collection-card tone-<?= esc((string) ($collection['tone'] ?? 'rose')); ?>">
                            <a href="<?= esc($collectionUrl); ?>" class="collection-image">
                                <span class="collection-badge"><?= esc((string) ($collection['tag'] ?? 'NEW')); ?></span>
                                <span class="collection-symbol"><?= esc((string) ($collection['symbol'] ?? '*')); ?></span>
                            </a>
                            <h3><a href="<?= esc($collectionUrl); ?>"><?= esc((string) ($collection['name'] ?? 'Collection')); ?></a></h3>
                        </article>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>

        <?php foreach ($categorySections as $section): ?>
            <?php renderProductSection($section); ?>

            <?php if (($section['marquee'] ?? '') !== ''): ?>
                <div class="ticker" aria-hidden="true">
                    <div class="ticker-track">
                        <span><?= esc((string) $section['marquee']); ?></span>
                        <span><?= esc((string) $section['marquee']); ?></span>
                        <span><?= esc((string) $section['marquee']); ?></span>
                    </div>
                </div>
            <?php endif; ?>
        <?php endforeach; ?>

        <section class="promo-block">
            <div class="container promo-grid">
                <div class="promo-image" aria-hidden="true">
                    <div class="promo-face"></div>
                </div>
                <div class="promo-content">
                    <h2>Chic, Cute, Classy</h2>
                    <p>
                        Fresh and trending style edits. Whether you are creating a glam look or an easy everyday routine,
                        discover handpicked beauty and lifestyle essentials in one place.
                    </p>
                    <a href="explore.php" class="cta-btn cta-btn-small">Shop now</a>
                </div>
            </div>
        </section>

        <div class="ticker ticker-alt" aria-hidden="true">
            <div class="ticker-track">
                <span>Free Gift Discount Card with your Order * LS Store.pk *</span>
                <span>Free Gift Discount Card with your Order * LS Store.pk *</span>
                <span>Free Gift Discount Card with your Order * LS Store.pk *</span>
            </div>
        </div>

        <section class="stats-row" aria-label="Store highlights">
            <div class="container stats-grid">
                <?php foreach ($stats as $stat): ?>
                    <article class="stat-item">
                        <span class="stat-icon"><?= esc((string) ($stat['icon'] ?? '*')); ?></span>
                        <h3><?= esc((string) ($stat['value'] ?? '0')); ?></h3>
                        <p><?= esc((string) ($stat['label'] ?? 'Metric')); ?></p>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="faq-section" id="faq" aria-label="Frequently asked questions">
            <div class="container">
                <div class="section-head">
                    <h2>Faqs</h2>
                </div>
                <div class="faq-list" data-accordion>
                    <?php foreach ($faqs as $index => $faq): ?>
                        <article class="faq-item">
                            <h3>
                                <button type="button" class="faq-trigger" aria-expanded="<?= $index === 0 ? 'true' : 'false'; ?>">
                                    <span><?= esc((string) ($faq['question'] ?? 'Question')); ?></span>
                                    <span class="plus" aria-hidden="true">+</span>
                                </button>
                            </h3>
                            <div class="faq-panel" <?= $index === 0 ? '' : 'hidden'; ?>>
                                <p><?= esc((string) ($faq['answer'] ?? 'Answer')); ?></p>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            </div>
        </section>
    </main>

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
