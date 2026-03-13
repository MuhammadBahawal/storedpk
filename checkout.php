<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/store_service.php';
require_once __DIR__ . '/includes/site_ui.php';

storeEnsureSchema();
storeRequireUser('checkout.php');

$user = storeCurrentUser();
$userId = (int) ($user['id'] ?? 0);
$items = storeCartItems($userId);

if ($items === []) {
    storeFlashSet('cart', ['success' => false, 'message' => 'Your cart is empty.']);
    header('Location: cart.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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

    $result = storePlaceOrder($userId, $payload);
    if (!empty($result['success'])) {
        $orderNumber = (string) ($result['order_number'] ?? '');
        header('Location: order-details.php?order=' . rawurlencode($orderNumber));
        exit;
    }

    storeFlashSet('checkout', $result);
    header('Location: checkout.php');
    exit;
}

$flash = storeFlashPull('checkout');
$totals = storeCartTotals($items);

renderPageStart('Checkout', 'catalog-page checkout-page');
renderStoreHeader('');
?>

<main class="catalog-main">
    <section class="page-shell">
        <div class="container">
            <h1 class="page-title">Checkout</h1>

            <?php if (is_array($flash)): ?>
                <div class="flash flash-error"><?= esc((string) ($flash['message'] ?? '')); ?></div>
            <?php endif; ?>

            <div class="dashboard-layout cart-layout">
                <section class="admin-card">
                    <h2>Delivery Details</h2>
                    <form method="post" action="checkout.php" class="admin-form">
                        <div class="admin-grid">
                            <label><span>Recipient Name</span><input type="text" name="recipient_name" value="<?= esc((string) ($user['full_name'] ?? '')); ?>" required></label>
                            <label><span>Email</span><input type="email" name="email" value="<?= esc((string) ($user['email'] ?? '')); ?>" required></label>
                            <label><span>Phone</span><input type="text" name="phone" value="<?= esc((string) ($user['phone'] ?? '')); ?>" required></label>
                            <label><span>City</span><input type="text" name="city" required></label>
                            <label><span>Province/State</span><input type="text" name="province"></label>
                            <label><span>Postal Code</span><input type="text" name="postal_code"></label>
                        </div>
                        <label><span>Address</span><textarea name="address_line" rows="3" required></textarea></label>
                        <label><span>Notes (optional)</span><textarea name="notes" rows="3"></textarea></label>
                        <button type="submit" class="cta-btn">Place Order (Cash on Delivery)</button>
                    </form>
                </section>

                <section class="admin-card cart-summary">
                    <h2>Your Items</h2>
                    <?php foreach ($items as $item): ?>
                        <div class="summary-row">
                            <span><?= esc((string) $item['name']); ?> x <?= (int) $item['quantity']; ?></span>
                            <strong>Rs. <?= number_format((float) $item['line_total'], 0); ?></strong>
                        </div>
                    <?php endforeach; ?>
                    <div class="summary-row"><span>Subtotal</span><strong>Rs. <?= number_format((float) ($totals['subtotal'] ?? 0), 0); ?></strong></div>
                    <div class="summary-row"><span>Shipping</span><strong>Rs. <?= number_format((float) ($totals['shipping'] ?? 0), 0); ?></strong></div>
                    <div class="summary-row summary-total"><span>Total</span><strong>Rs. <?= number_format((float) ($totals['total'] ?? 0), 0); ?></strong></div>
                </section>
            </div>
        </div>
    </section>
</main>

<?php renderStoreFooter(); ?>
