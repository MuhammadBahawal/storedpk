<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/store_service.php';
require_once __DIR__ . '/includes/site_ui.php';

storeEnsureSchema();
storeRequireUser('cart.php');

$user = storeCurrentUser();
$userId = (int) ($user['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    $result = ['success' => false, 'message' => 'Unknown action.'];

    if ($action === 'add') {
        $result = storeCartAdd($userId, (int) ($_POST['product_id'] ?? 0), max(1, (int) ($_POST['quantity'] ?? 1)));
    } elseif ($action === 'update') {
        $result = storeCartUpdate($userId, (int) ($_POST['product_id'] ?? 0), (int) ($_POST['quantity'] ?? 1));
    } elseif ($action === 'remove') {
        $result = storeCartRemove($userId, (int) ($_POST['product_id'] ?? 0));
    } elseif ($action === 'clear') {
        storeCartClear($userId);
        $result = ['success' => true, 'message' => 'Cart cleared.'];
    }

    storeFlashSet('cart', $result);
    header('Location: cart.php');
    exit;
}

$flash = storeFlashPull('cart');
$items = storeCartItems($userId);
$totals = storeCartTotals($items);

renderPageStart('Cart', 'catalog-page cart-page');
renderStoreHeader('');
?>

<main class="catalog-main">
    <section class="page-shell">
        <div class="container">
            <div class="section-head">
                <h1 class="page-title">Your Cart</h1>
                <?php if ($items !== []): ?>
                    <form method="post" action="cart.php" data-confirm="Clear your cart?" class="inline-form">
                        <input type="hidden" name="action" value="clear">
                        <button type="submit" class="mini-link">Clear Cart</button>
                    </form>
                <?php endif; ?>
            </div>

            <?php if (is_array($flash)): ?>
                <div class="flash <?= !empty($flash['success']) ? 'flash-success' : 'flash-error'; ?>">
                    <?= esc((string) ($flash['message'] ?? '')); ?>
                </div>
            <?php endif; ?>

            <?php if ($items === []): ?>
                <div class="empty-state">
                    <h3>Your cart is empty</h3>
                    <p>Add some products to continue checkout.</p>
                    <a href="explore.php" class="cta-btn">Explore products</a>
                </div>
            <?php else: ?>
                <div class="dashboard-layout cart-layout">
                    <section class="admin-card">
                        <?php foreach ($items as $item): ?>
                            <article class="cart-item">
                                <a href="product.php?slug=<?= esc((string) ($item['slug'] ?? '')); ?>" class="cart-thumb tone-<?= esc((string) ($item['tone'] ?? 'rose')); ?>">
                                    <?php renderImageOrFallback($item['image_path'] ?? null, (string) ($item['name'] ?? 'Product'), (string) ($item['short_name'] ?? 'Item')); ?>
                                </a>
                                <div class="cart-meta">
                                    <h3><a href="product.php?slug=<?= esc((string) ($item['slug'] ?? '')); ?>"><?= esc((string) ($item['name'] ?? 'Product')); ?></a></h3>
                                    <p class="product-meta"><?= esc((string) ($item['category_name'] ?? 'Category')); ?></p>
                                    <p class="price">Rs. <?= number_format((float) ($item['price'] ?? 0), 0); ?></p>
                                    <p class="product-meta">Stock: <?= (int) ($item['stock_qty'] ?? 0); ?></p>
                                </div>
                                <form method="post" action="cart.php" class="cart-actions">
                                    <input type="hidden" name="action" value="update">
                                    <input type="hidden" name="product_id" value="<?= (int) ($item['product_id'] ?? 0); ?>">
                                    <input type="number" name="quantity" min="1" max="<?= max(1, (int) ($item['stock_qty'] ?? 1)); ?>" value="<?= (int) ($item['quantity'] ?? 1); ?>">
                                    <button type="submit" class="mini-link">Update</button>
                                </form>
                                <form method="post" action="cart.php" class="cart-actions">
                                    <input type="hidden" name="action" value="remove">
                                    <input type="hidden" name="product_id" value="<?= (int) ($item['product_id'] ?? 0); ?>">
                                    <button type="submit" class="table-delete">Remove</button>
                                </form>
                            </article>
                        <?php endforeach; ?>
                    </section>

                    <section class="admin-card cart-summary">
                        <h2>Order Summary</h2>
                        <div class="summary-row"><span>Subtotal</span><strong>Rs. <?= number_format((float) ($totals['subtotal'] ?? 0), 0); ?></strong></div>
                        <div class="summary-row"><span>Shipping</span><strong>Rs. <?= number_format((float) ($totals['shipping'] ?? 0), 0); ?></strong></div>
                        <div class="summary-row"><span>Discount</span><strong>Rs. <?= number_format((float) ($totals['discount'] ?? 0), 0); ?></strong></div>
                        <div class="summary-row summary-total"><span>Total</span><strong>Rs. <?= number_format((float) ($totals['total'] ?? 0), 0); ?></strong></div>
                        <a href="checkout.php" class="cta-btn">Proceed to Checkout</a>
                    </section>
                </div>
            <?php endif; ?>
        </div>
    </section>
</main>

<?php renderStoreFooter(); ?>
