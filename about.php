<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/site_ui.php';

renderPageStart('About Us', 'catalog-page info-page');
renderStoreHeader('about');
?>

<main class="catalog-main">
    <section class="page-shell">
        <div class="container narrow-container">
            <h1 class="page-title">About Us</h1>

            <article class="about-copy">
                <p>
                    LS Store.pk is a little dream turned into reality, created and run by two sisters with a passion
                    for all things girly, trendy, and fun. What started as a simple love for makeup and accessories has
                    grown into a cozy online space where you will find cute and minimal jewellery, high-end makeup dupes,
                    and everyday essentials to brighten your life.
                </p>
                <p>
                    We believe in bringing a touch of sparkle to your day with products that are not only affordable but
                    also carefully chosen to match your vibe.
                </p>
                <p>
                    Customer care is at the heart of what we do. Every order is packed with attention to detail, every
                    query is answered with patience, and every customer is treated like part of the LS Store.pk family.
                    We are here to make your shopping experience easy, smooth, and exciting.
                </p>
            </article>
        </div>
    </section>
</main>

<?php renderStoreFooter(); ?>
