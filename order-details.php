<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/store_service.php';
require_once __DIR__ . '/includes/site_ui.php';

storeEnsureSchema();

$orderNumber = trim((string) ($_GET['order'] ?? ''));
$admin = storeCurrentAdmin();
$user = storeCurrentUser();
$guestAccess = false;

if ($orderNumber === '') {
    header('Location: ' . ($admin ? 'dashboard.php?tab=orders' : ($user ? 'account.php' : 'index.php')));
    exit;
}

if (!$admin && !$user) {
    $guestAccess = storeGuestCanViewOrder($orderNumber);
    if (!$guestAccess) {
        storeRequireUser('order-details.php?order=' . rawurlencode($orderNumber));
    }
}

$order = $admin
    ? storeOrderDetail($orderNumber, null)
    : ($user
        ? storeOrderDetail($orderNumber, (int) ($user['id'] ?? 0))
        : storeOrderDetail($orderNumber, null));

if ($guestAccess && $order && (int) ($order['user_id'] ?? 0) !== storeGuestUserId()) {
    $order = null;
}

$backUrl = $admin ? 'dashboard.php?tab=orders' : ($user ? 'account.php' : 'index.php');

if (!$order) {
    renderPageStart('Order Not Found', 'catalog-page');
    renderStoreHeader('');
    ?>
    <main class="catalog-main">
        <section class="page-shell">
            <div class="container">
                <div class="empty-state">
                    <h3>Order not found</h3>
                    <p>This order does not exist or you do not have permission to view it.</p>
                    <a href="<?= $backUrl; ?>" class="cta-btn">Go back</a>
                </div>
            </div>
        </section>
    </main>
    <?php
    renderStoreFooter();
    exit;
}

renderPageStart('Order ' . (string) ($order['order_number'] ?? ''), 'catalog-page order-page');
renderStoreHeader('');
?>

<main class="catalog-main">
    <section class="page-shell">
        <div class="container">
            <div class="section-head">
                <h1 class="page-title">Order <?= esc((string) ($order['order_number'] ?? '')); ?></h1>
                <a href="<?= $backUrl; ?>" class="mini-link">Back</a>
            </div>

            <div class="dashboard-layout">
                <section class="admin-card">
                    <h2>Order Info</h2>
                    <p><strong>Status:</strong> <?= esc((string) ($order['status'] ?? 'pending')); ?></p>
                    <p><strong>Payment:</strong> <?= esc((string) ($order['payment_method'] ?? 'cod')); ?> / <?= esc((string) ($order['payment_status'] ?? 'unpaid')); ?></p>
                    <p><strong>Total:</strong> Rs. <?= number_format((float) ($order['total'] ?? 0), 0); ?></p>
                    <p><strong>Date:</strong> <?= esc((string) substr((string) ($order['created_at'] ?? ''), 0, 19)); ?></p>
                </section>

                <section class="admin-card">
                    <h2>Delivery Details</h2>
                    <p><strong>Name:</strong> <?= esc((string) ($order['recipient_name'] ?? '')); ?></p>
                    <p><strong>Phone:</strong> <?= esc((string) ($order['phone'] ?? '')); ?></p>
                    <p><strong>Email:</strong> <?= esc((string) ($order['email'] ?? '')); ?></p>
                    <p><strong>Address:</strong> <?= esc((string) ($order['address_line'] ?? '')); ?></p>
                    <p><strong>City:</strong> <?= esc((string) ($order['city'] ?? '')); ?> <?= esc((string) ($order['province'] ?? '')); ?></p>
                    <?php if (!empty($order['notes'])): ?>
                        <p><strong>Notes:</strong> <?= esc((string) $order['notes']); ?></p>
                    <?php endif; ?>
                </section>
            </div>

            <section class="admin-card table-card">
                <h2>Items</h2>
                <div class="admin-table-wrap">
                    <table class="admin-table">
                        <thead><tr><th>Product</th><th>Category</th><th>Qty</th><th>Unit Price</th><th>Total</th></tr></thead>
                        <tbody>
                            <?php foreach ((array) ($order['items'] ?? []) as $item): ?>
                                <tr>
                                    <td><?= esc((string) ($item['product_name'] ?? '')); ?></td>
                                    <td><?= esc((string) ($item['category_name'] ?? '')); ?></td>
                                    <td><?= (int) ($item['quantity'] ?? 0); ?></td>
                                    <td>Rs. <?= number_format((float) ($item['unit_price'] ?? 0), 0); ?></td>
                                    <td>Rs. <?= number_format((float) ($item['line_total'] ?? 0), 0); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </section>
        </div>
    </section>
</main>

<?php renderStoreFooter(); ?>
