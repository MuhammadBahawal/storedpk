<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/store_service.php';
require_once __DIR__ . '/includes/site_ui.php';

storeEnsureSchema();
storeRequireUser('account.php');

$user = storeCurrentUser();
$orders = storeUserOrders((int) ($user['id'] ?? 0));

renderPageStart('My Account', 'catalog-page account-page');
renderStoreHeader('');
?>

<main class="catalog-main">
    <section class="page-shell">
        <div class="container">
            <div class="section-head dashboard-head">
                <h1 class="page-title">My Account</h1>
                <a href="logout.php" class="cta-btn">Logout</a>
            </div>

            <div class="dashboard-layout">
                <section class="admin-card">
                    <h2>Profile</h2>
                    <p><strong>Name:</strong> <?= esc((string) ($user['full_name'] ?? '')); ?></p>
                    <p><strong>Email:</strong> <?= esc((string) ($user['email'] ?? '')); ?></p>
                    <p><strong>Phone:</strong> <?= esc((string) ($user['phone'] ?? '')); ?></p>
                    <p><strong>Member Since:</strong> <?= esc((string) substr((string) ($user['created_at'] ?? ''), 0, 10)); ?></p>
                </section>

                <section class="admin-card">
                    <h2>My Orders</h2>
                    <?php if ($orders === []): ?>
                        <p class="table-empty">No orders yet.</p>
                    <?php else: ?>
                        <div class="admin-table-wrap">
                            <table class="admin-table">
                                <thead><tr><th>Order</th><th>Status</th><th>Total</th><th>Date</th></tr></thead>
                                <tbody>
                                    <?php foreach ($orders as $order): ?>
                                        <tr>
                                            <td><a href="order-details.php?order=<?= esc((string) $order['order_number']); ?>"><?= esc((string) $order['order_number']); ?></a></td>
                                            <td><?= esc((string) $order['status']); ?></td>
                                            <td>Rs. <?= number_format((float) $order['total'], 0); ?></td>
                                            <td><?= esc((string) substr((string) $order['created_at'], 0, 10)); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </section>
            </div>
        </div>
    </section>
</main>

<?php renderStoreFooter(); ?>
