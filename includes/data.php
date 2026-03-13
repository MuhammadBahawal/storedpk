<?php

declare(strict_types=1);

require_once __DIR__ . '/db.php';

if (!function_exists('esc')) {
    function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
    }
}

function getSiteConfig(): array
{
    return [
        'title' => 'LS Store.pk | Beauty Store',
        'announcement' => 'Modern and minimal wardrobe',
    ];
}

function getCollections(): array
{
    if (function_exists('storeGetCollections')) {
        $collections = storeGetCollections();
        if ($collections !== []) {
            return $collections;
        }
    } elseif (function_exists('ensureCatalogSchema')) {
        ensureCatalogSchema();
    }

    $fallback = [
        ['name' => 'Nail Art', 'slug' => 'nail-art', 'tone' => 'rose', 'tag' => 'HOT', 'symbol' => '*'],
        ['name' => 'Get Ready', 'slug' => 'get-ready', 'tone' => 'sand', 'tag' => 'NEW', 'symbol' => '#'],
        ['name' => 'Wedding Special', 'slug' => 'wedding-special', 'tone' => 'peach', 'tag' => 'SAVE', 'symbol' => '*'],
        ['name' => 'Jewelry', 'slug' => 'jewelry', 'tone' => 'rose', 'tag' => 'TREND', 'symbol' => '+'],
        ['name' => 'Skincare Products', 'slug' => 'skincare-products', 'tone' => 'mist', 'tag' => 'NEW', 'symbol' => '*'],
        ['name' => 'Perfume Collection', 'slug' => 'perfume-collection', 'tone' => 'mint', 'tag' => 'TOP', 'symbol' => '#'],
        ['name' => 'Self-care Kit', 'slug' => 'self-care-kit', 'tone' => 'peach', 'tag' => 'KIT', 'symbol' => '*'],
        ['name' => 'LS Store.pk', 'slug' => 'ls-store-pk', 'tone' => 'sand', 'tag' => 'NEW', 'symbol' => '+'],
        ['name' => 'Jewelry Box', 'slug' => 'jewelry-box', 'tone' => 'mist', 'tag' => 'TOP', 'symbol' => '#'],
        ['name' => 'Skincare', 'slug' => 'skincare', 'tone' => 'rose', 'tag' => 'SAVE', 'symbol' => '*'],
        ['name' => 'Self-love', 'slug' => 'self-love', 'tone' => 'peach', 'tag' => 'NEW', 'symbol' => '+'],
        ['name' => 'Nails', 'slug' => 'nails', 'tone' => 'mint', 'tag' => 'BEST', 'symbol' => '*'],
        ['name' => 'Makeup Branding', 'slug' => 'makeup-branding', 'tone' => 'sand', 'tag' => 'HOT', 'symbol' => '#'],
        ['name' => 'Lotion', 'slug' => 'lotion', 'tone' => 'peach', 'tag' => 'NEW', 'symbol' => '*'],
        ['name' => 'Bags', 'slug' => 'bags', 'tone' => 'rose', 'tag' => 'TOP', 'symbol' => '+'],
        ['name' => 'Mailing', 'slug' => 'mailing', 'tone' => 'mist', 'tag' => 'NEW', 'symbol' => '#'],
        ['name' => 'Warmth', 'slug' => 'warmth', 'tone' => 'sand', 'tag' => 'GLOW', 'symbol' => '*'],
    ];

    $db = db();
    if (!$db) {
        return $fallback;
    }

    $result = @$db->query('SELECT name, slug, tone, tag, symbol FROM collections ORDER BY sort_order ASC, id ASC');
    if (!$result) {
        return $fallback;
    }

    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = [
            'name' => (string) ($row['name'] ?? ''),
            'slug' => (string) ($row['slug'] ?? ''),
            'tone' => (string) ($row['tone'] ?? 'rose'),
            'tag' => (string) ($row['tag'] ?? 'NEW'),
            'symbol' => (string) ($row['symbol'] ?? '*'),
        ];
    }

    return $rows !== [] ? $rows : $fallback;
}

function fallbackProductsBySection(): array
{
    return [
        'makeup' => [
            ['name' => 'Flawless Glow Kit', 'short' => 'Glow Kit', 'tag' => 'SALE', 'tone' => 'rose', 'price' => 2450],
            ['name' => 'Soft Matte Set', 'short' => 'Matte Set', 'tag' => 'HOT', 'tone' => 'peach', 'price' => 2990],
            ['name' => 'Pink Blend Palette', 'short' => 'Palette', 'tag' => 'SAVE', 'tone' => 'mist', 'price' => 3290],
            ['name' => 'Mini Lip Tint Pack', 'short' => 'Lip Tint', 'tag' => 'NEW', 'tone' => 'sand', 'price' => 1590],
            ['name' => 'Classic Brush Set', 'short' => 'Brushes', 'tag' => 'TOP', 'tone' => 'mint', 'price' => 2090],
            ['name' => 'Velvet Face Powder', 'short' => 'Powder', 'tag' => 'BEST', 'tone' => 'rose', 'price' => 1390],
            ['name' => 'Hydra Primer', 'short' => 'Primer', 'tag' => 'HOT', 'tone' => 'peach', 'price' => 1690],
        ],
        'nails' => [
            ['name' => 'Press-On Nail Box', 'short' => 'Nail Box', 'tag' => 'HOT', 'tone' => 'sand', 'price' => 1950],
            ['name' => 'French Tips Kit', 'short' => 'French Tips', 'tag' => 'NEW', 'tone' => 'mist', 'price' => 1790],
            ['name' => 'Salon Effect Nails', 'short' => 'Salon Kit', 'tag' => 'SALE', 'tone' => 'rose', 'price' => 2290],
            ['name' => 'Nail Care Essentials', 'short' => 'Care Kit', 'tag' => 'SAVE', 'tone' => 'mint', 'price' => 1290],
            ['name' => 'Velvet Pink Press Set', 'short' => 'Pink Set', 'tag' => 'BEST', 'tone' => 'peach', 'price' => 2490],
            ['name' => 'Daily Nails Combo', 'short' => 'Combo', 'tag' => 'NEW', 'tone' => 'sand', 'price' => 1650],
        ],
        'jewelry' => [
            ['name' => 'Pearl Charm Ring Set', 'short' => 'Ring Set', 'tag' => 'HOT', 'tone' => 'rose', 'price' => 999],
            ['name' => 'Elegant Chain Duo', 'short' => 'Chain Duo', 'tag' => 'NEW', 'tone' => 'peach', 'price' => 1450],
            ['name' => 'Minimal Pearl Necklace', 'short' => 'Necklace', 'tag' => 'SAVE', 'tone' => 'mist', 'price' => 1750],
            ['name' => 'Gold Touch Bracelet', 'short' => 'Bracelet', 'tag' => 'TOP', 'tone' => 'sand', 'price' => 1190],
            ['name' => 'Charming Ear Set', 'short' => 'Ear Set', 'tag' => 'BEST', 'tone' => 'mint', 'price' => 1090],
            ['name' => 'Party Ring Combo', 'short' => 'Party Rings', 'tag' => 'SALE', 'tone' => 'rose', 'price' => 1290],
        ],
    ];
}

function getCategorySections(): array
{
    $fallback = [
        [
            'slug' => 'makeup',
            'title' => 'Makeup',
            'marquee' => '* You Glow Girl * You Glow Girl * You Glow Girl *',
            'products' => fallbackProductsBySection()['makeup'],
        ],
        [
            'slug' => 'nails',
            'title' => 'Nails',
            'marquee' => '',
            'products' => fallbackProductsBySection()['nails'],
        ],
        [
            'slug' => 'jewelry',
            'title' => 'Jewellery',
            'marquee' => '',
            'products' => fallbackProductsBySection()['jewelry'],
        ],
    ];

    $db = db();
    if (!$db) {
        return $fallback;
    }

    $sectionResult = @$db->query('SELECT slug, title, marquee FROM sections ORDER BY sort_order ASC, id ASC');
    if (!$sectionResult) {
        return $fallback;
    }

    $sections = [];
    while ($row = $sectionResult->fetch_assoc()) {
        $slug = (string) ($row['slug'] ?? '');
        if ($slug === '') {
            continue;
        }

        $products = [];
        $stmt = @$db->prepare(
            'SELECT name, short_name, tag, tone, price FROM products WHERE section_slug = ? ORDER BY sort_order ASC, id ASC'
        );

        if ($stmt) {
            $stmt->bind_param('s', $slug);
            $stmt->execute();
            $productResult = $stmt->get_result();
            while ($product = $productResult->fetch_assoc()) {
                $products[] = [
                    'name' => (string) ($product['name'] ?? ''),
                    'short' => (string) ($product['short_name'] ?? 'Item'),
                    'tag' => (string) ($product['tag'] ?? 'NEW'),
                    'tone' => (string) ($product['tone'] ?? 'rose'),
                    'price' => (float) ($product['price'] ?? 0),
                ];
            }
            $stmt->close();
        }

        if ($products === [] && isset(fallbackProductsBySection()[$slug])) {
            $products = fallbackProductsBySection()[$slug];
        }

        $sections[] = [
            'slug' => $slug,
            'title' => (string) ($row['title'] ?? ucfirst($slug)),
            'marquee' => (string) ($row['marquee'] ?? ''),
            'products' => $products,
        ];
    }

    return $sections !== [] ? $sections : $fallback;
}

function getFaqs(): array
{
    $fallback = [
        [
            'question' => 'What are the delivery charges?',
            'answer' => 'Delivery charges are calculated at checkout based on your city and order size.',
        ],
        [
            'question' => 'Is this payment in advance?',
            'answer' => 'You can choose bank transfer, wallet, or cash on delivery where available.',
        ],
        [
            'question' => 'When will I get my order?',
            'answer' => 'Major cities are usually delivered within 2-4 business days and other areas within 4-7 days.',
        ],
        [
            'question' => 'Can I return or exchange my order?',
            'answer' => 'Eligible products can be exchanged within 7 days if they are unused and in original condition.',
        ],
    ];

    $db = db();
    if (!$db) {
        return $fallback;
    }

    $result = @$db->query('SELECT question, answer FROM faqs ORDER BY sort_order ASC, id ASC');
    if (!$result) {
        return $fallback;
    }

    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = [
            'question' => (string) ($row['question'] ?? ''),
            'answer' => (string) ($row['answer'] ?? ''),
        ];
    }

    return $rows !== [] ? $rows : $fallback;
}

function getStats(): array
{
    return [
        ['icon' => '*', 'value' => '8000+', 'label' => 'Order Shipped'],
        ['icon' => '*', 'value' => '5000+', 'label' => 'Customer Reviews'],
        ['icon' => '*', 'value' => '100K+', 'label' => 'Followers'],
    ];
}
