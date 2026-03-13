<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/store_service.php';
require_once __DIR__ . '/includes/site_ui.php';

storeEnsureSchema();

$productSlug = trim((string) ($_GET['product'] ?? $_GET['slug'] ?? ''));
$productId = (int) ($_GET['id'] ?? 0);

$product = $productSlug !== '' ? storeGetProductBySlug($productSlug) : null;
if (!$product && $productId > 0) {
    $product = storeGetProductById($productId);
}

if (!$product || !$product['is_active']) {
    renderPageStart('Product Not Found', 'catalog-page buy-now-page');
    renderStoreHeader('shop');
    ?>
    <main class="catalog-main">
        <section class="page-shell">
            <div class="container">
                <div class="empty-state">
                    <h3>Product not found</h3>
                    <p>This product does not exist or is not available for checkout.</p>
                    <a href="shop-all.php" class="cta-btn">Back to shop</a>
                </div>
            </div>
        </section>
    </main>
    <?php
    renderStoreFooter();
    exit;
}

$currentUser = storeCurrentUser();
$isAvailable = (string) ($product['availability'] ?? 'out_of_stock') === 'in_stock' && (int) ($product['stock_qty'] ?? 0) > 0;
$productUrl = (string) ($product['slug'] ?? '') !== ''
    ? 'product.php?slug=' . rawurlencode((string) $product['slug'])
    : 'product.php?id=' . (int) ($product['id'] ?? 0);
$buyNowUrl = (string) ($product['slug'] ?? '') !== ''
    ? 'buy-now.php?product=' . rawurlencode((string) $product['slug'])
    : 'buy-now.php?id=' . (int) ($product['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (string) ($_POST['action'] ?? '') === 'place_direct_order') {
    $quantity = max(1, (int) ($_POST['quantity'] ?? 1));
    $payload = [
        'recipient_name' => (string) ($_POST['recipient_name'] ?? ''),
        'phone' => (string) ($_POST['phone'] ?? ''),
        'email' => (string) ($_POST['email'] ?? ''),
        'address_line' => (string) ($_POST['address_line'] ?? ''),
        'city' => (string) ($_POST['city'] ?? ''),
        'province' => (string) ($_POST['province'] ?? ''),
        'postal_code' => (string) ($_POST['postal_code'] ?? ''),
        'notes' => (string) ($_POST['notes'] ?? ''),
    ];

    $result = storePlaceDirectOrder($currentUser['id'] ?? null, (int) ($product['id'] ?? 0), $quantity, $payload);
    if (!empty($result['success'])) {
        $orderNumber = (string) ($result['order_number'] ?? '');
        header('Location: order-details.php?order=' . rawurlencode($orderNumber));
        exit;
    }

    storeFlashSet('buy_now', [
        'success' => false,
        'message' => (string) ($result['message'] ?? 'Unable to place order right now.'),
        'form' => $payload,
        'quantity' => $quantity,
    ]);
    header('Location: ' . $buyNowUrl);
    exit;
}

$flash = storeFlashPull('buy_now');
$formDefaults = [
    'recipient_name' => (string) ($currentUser['full_name'] ?? ''),
    'phone' => (string) ($currentUser['phone'] ?? ''),
    'email' => (string) ($currentUser['email'] ?? ''),
    'address_line' => '',
    'city' => '',
    'province' => '',
    'postal_code' => '',
    'notes' => '',
];
$formValues = is_array($flash) && is_array($flash['form'] ?? null)
    ? array_merge($formDefaults, (array) $flash['form'])
    : $formDefaults;
$selectedQuantity = max(
    1,
    min(
        (int) (is_array($flash) ? ($flash['quantity'] ?? 1) : 1),
        max(1, (int) ($product['stock_qty'] ?? 1))
    )
);
$previewItem = [[
    'line_total' => (float) ($product['price'] ?? 0) * $selectedQuantity,
]];
$totals = storeCartTotals($previewItem);

renderPageStart('Order Now', 'catalog-page checkout-page buy-now-page');
renderStoreHeader('shop');
?>

<main class="catalog-main">
    <section class="page-shell">
        <div class="container">
            <div class="section-head explore-head">
                <h1 class="page-title">Quick Checkout</h1>
                <a href="<?= esc($productUrl); ?>" class="mini-link">Back to product</a>
            </div>

            <?php if (is_array($flash)): ?>
                <div class="flash flash-error"><?= esc((string) ($flash['message'] ?? '')); ?></div>
            <?php endif; ?>

            <?php if (!$isAvailable): ?>
                <div class="empty-state">
                    <h3>Currently unavailable</h3>
                    <p>This product is out of stock right now. Please choose another item.</p>
                    <a href="<?= esc($productUrl); ?>" class="cta-btn">View product</a>
                </div>
            <?php else: ?>
                <div class="dashboard-layout cart-layout">
                    <section class="admin-card">
                        <h2>Delivery Details</h2>
                        <p class="guest-order-note">Cash on delivery is enabled. Login is optional for this order.</p>
                        <form method="post" action="<?= esc($buyNowUrl); ?>" class="admin-form">
                            <input type="hidden" name="action" value="place_direct_order">
                            <div class="admin-grid">
                                <label><span>Recipient Name</span><input type="text" name="recipient_name" value="<?= esc((string) $formValues['recipient_name']); ?>" required></label>
                                <label><span>Email</span><input type="email" name="email" value="<?= esc((string) $formValues['email']); ?>" required></label>
                                <label><span>Phone</span><input type="text" name="phone" value="<?= esc((string) $formValues['phone']); ?>" required></label>
                                <label><span>Quantity</span><input class="buy-now-qty" type="number" name="quantity" min="1" max="<?= max(1, (int) ($product['stock_qty'] ?? 1)); ?>" value="<?= $selectedQuantity; ?>" required></label>
                                <label><span>City</span><input type="text" name="city" value="<?= esc((string) $formValues['city']); ?>" required></label>
                                <label><span>Province/State</span><input type="text" name="province" value="<?= esc((string) $formValues['province']); ?>"></label>
                                <label><span>Postal Code</span><input type="text" name="postal_code" value="<?= esc((string) $formValues['postal_code']); ?>"></label>
                            </div>
                            <label><span>Address</span><textarea name="address_line" rows="3" required><?= esc((string) $formValues['address_line']); ?></textarea></label>
                            <label><span>Notes (optional)</span><textarea name="notes" rows="3"><?= esc((string) $formValues['notes']); ?></textarea></label>
                            <button type="submit" class="cta-btn">Place Order (Cash on Delivery)</button>
                        </form>
                    </section>

                    <section class="admin-card cart-summary">
                        <h2>Your Product</h2>
                        <div class="buy-now-product">
                            <a href="<?= esc($productUrl); ?>" class="buy-now-product-media tone-<?= esc((string) ($product['tone'] ?? 'rose')); ?>">
                                <?php renderImageOrFallback($product['image_path'] ?? null, (string) ($product['name'] ?? 'Product'), (string) ($product['short_name'] ?? 'Item')); ?>
                            </a>
                            <div>
                                <h3><?= esc((string) ($product['name'] ?? 'Product')); ?></h3>
                                <p class="product-meta"><?= esc((string) ($product['category_name'] ?? 'Uncategorized')); ?></p>
                                <p class="product-meta">Stock left: <?= (int) ($product['stock_qty'] ?? 0); ?></p>
                                <p class="price">
                                    Rs. <?= number_format((float) ($product['price'] ?? 0), 0); ?>
                                    <?php if (($product['compare_price'] ?? null) !== null && (float) $product['compare_price'] > (float) ($product['price'] ?? 0)): ?>
                                        <span class="compare-price">Rs. <?= number_format((float) $product['compare_price'], 0); ?></span>
                                    <?php endif; ?>
                                </p>
                            </div>
                        </div>
                        <div class="summary-row"><span>Quantity</span><strong><?= $selectedQuantity; ?></strong></div>
                        <div class="summary-row"><span>Subtotal</span><strong>Rs. <?= number_format((float) ($totals['subtotal'] ?? 0), 0); ?></strong></div>
                        <div class="summary-row"><span>Shipping</span><strong>Rs. <?= number_format((float) ($totals['shipping'] ?? 0), 0); ?></strong></div>
                        <div class="summary-row summary-total"><span>Total</span><strong>Rs. <?= number_format((float) ($totals['total'] ?? 0), 0); ?></strong></div>
                    </section>
                </div>
            <?php endif; ?>
        </div>
    </section>
</main>

<?php renderStoreFooter(); ?>
