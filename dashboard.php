<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/store_service.php';
require_once __DIR__ . '/includes/site_ui.php';

function dashboardStatusClass(string $status): string
{
    $normalized = strtolower(trim(str_replace('_', ' ', $status)));

    if (in_array($normalized, ['in stock', 'confirmed', 'processing', 'shipped', 'delivered'], true)) {
        return 'status-live';
    }

    if (in_array($normalized, ['sold out', 'out of stock', 'cancelled'], true)) {
        return 'status-out';
    }

    return 'status-wait';
}

storeEnsureSchema();
storeEnsureSession();

if (isset($_GET['logout_admin'])) {
    storeLogoutAdmin();
    header('Location: dashboard.php?admin_login=1');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = (string) ($_POST['action'] ?? '');
    $result = ['success' => false, 'message' => 'Unknown action.'];

    if ($action === 'admin_login') {
        $email = (string) ($_POST['email'] ?? '');
        $password = (string) ($_POST['password'] ?? '');
        $result = storeLoginAdmin($email, $password);
        storeFlashSet('dashboard', $result);
        header('Location: dashboard.php');
        exit;
    }

    storeRequireAdmin();
    $adminId = storeCurrentAdminId();

    if ($action === 'add_category') {
        $result = createCatalogCategory($_POST);
    } elseif ($action === 'update_category') {
        $result = storeUpdateCategory((int) ($_POST['category_id'] ?? 0), $_POST);
    } elseif ($action === 'delete_category') {
        $result = deleteCatalogCategory((int) ($_POST['category_id'] ?? 0));
    } elseif ($action === 'add_collection') {
        $result = storeCreateCollection($_POST);
    } elseif ($action === 'update_collection') {
        $result = storeUpdateCollection((int) ($_POST['collection_id'] ?? 0), $_POST);
    } elseif ($action === 'delete_collection') {
        $result = storeDeleteCollection((int) ($_POST['collection_id'] ?? 0));
    } elseif ($action === 'add_product') {
        $result = storeCreateProduct($_POST, $_FILES, $adminId);
    } elseif ($action === 'update_product') {
        $result = storeUpdateProduct((int) ($_POST['product_id'] ?? 0), $_POST, $_FILES, $adminId);
    } elseif ($action === 'delete_product') {
        $result = storeDeleteProduct((int) ($_POST['product_id'] ?? 0));
    } elseif ($action === 'update_order_status') {
        $result = storeOrderStatusUpdate((int) ($_POST['order_id'] ?? 0), (string) ($_POST['status'] ?? 'pending'));
    }

    storeFlashSet('dashboard', $result);
    $tab = (string) ($_POST['tab'] ?? 'overview');
    header('Location: dashboard.php?tab=' . urlencode($tab));
    exit;
}

$flash = storeFlashPull('dashboard');
$admin = storeCurrentAdmin();
$showAdminLogin = isset($_GET['admin_login']) || $admin === null;

if ($showAdminLogin && $admin === null) {
    renderPageStart('Admin Login', 'catalog-page dashboard-page');
    renderStoreHeader('');
    ?>
    <main class="catalog-main">
        <section class="page-shell">
            <div class="container narrow-container">
                <h1 class="page-title">Admin Login</h1>

                <?php if (is_array($flash)): ?>
                    <div class="flash <?= !empty($flash['success']) ? 'flash-success' : 'flash-error'; ?>">
                        <?= esc((string) ($flash['message'] ?? '')); ?>
                    </div>
                <?php endif; ?>

                <section class="admin-card">
                    <form method="post" action="dashboard.php" class="admin-form">
                        <input type="hidden" name="action" value="admin_login">
                        <label>
                            <span>Email</span>
                            <input type="email" name="email" value="admin@store.pk" required>
                        </label>
                        <label>
                            <span>Password</span>
                            <input type="password" name="password" value="admin123" required>
                        </label>
                        <button type="submit" class="cta-btn">Login as Admin</button>
                    </form>
                    <p class="hint-text">Default admin: <code>admin@store.pk</code> / <code>admin123</code></p>
                </section>
            </div>
        </section>
    </main>
    <?php
    renderStoreFooter();
    exit;
}

storeRequireAdmin();

$tab = (string) ($_GET['tab'] ?? 'overview');
$validTabs = ['overview', 'collections', 'categories', 'products', 'orders', 'reports'];
if (!in_array($tab, $validTabs, true)) {
    $tab = 'overview';
}

$stats = storeAdminStats();
$collections = storeGetCollections();
$categories = storeGetCategories();
$products = storeGetProducts();

$orderFilters = [
    'status' => (string) ($_GET['status'] ?? ''),
    'search' => (string) ($_GET['search'] ?? ''),
    'date_from' => (string) ($_GET['date_from'] ?? ''),
    'date_to' => (string) ($_GET['date_to'] ?? ''),
];
$orders = storeAdminOrders($orderFilters);

$reportFrom = (string) ($_GET['report_from'] ?? date('Y-m-01'));
$reportTo = (string) ($_GET['report_to'] ?? date('Y-m-d'));
$salesReport = storeSalesReport($reportFrom, $reportTo);
$purchaseReport = storePurchaseReport($reportFrom, $reportTo);

$editProductId = (int) ($_GET['edit_product'] ?? 0);
$editCollectionId = (int) ($_GET['edit_collection'] ?? 0);
$editCategoryId = (int) ($_GET['edit_category'] ?? 0);
$editProduct = $editProductId > 0 ? storeGetProductById($editProductId) : null;
$editCollection = $editCollectionId > 0 ? storeGetCollectionById($editCollectionId) : null;
$editCategory = $editCategoryId > 0 ? getCatalogCategoryById($editCategoryId) : null;

renderPageStart('Admin Dashboard', 'catalog-page dashboard-page');
renderStoreHeader('');
?>

<main class="catalog-main">
    <section class="page-shell">
        <div class="container">
            <div class="section-head dashboard-head">
                <h1 class="page-title">Admin Dashboard</h1>
                <div class="dashboard-head-actions">
                    <span class="admin-chip">Signed in as <?= esc((string) ($admin['full_name'] ?? 'Admin')); ?></span>
                    <a class="cta-btn" href="dashboard.php?logout_admin=1">Logout Admin</a>
                </div>
            </div>

            <?php if (is_array($flash)): ?>
                <div class="flash <?= !empty($flash['success']) ? 'flash-success' : 'flash-error'; ?>">
                    <?= esc((string) ($flash['message'] ?? '')); ?>
                </div>
            <?php endif; ?>

            <section class="admin-card dashboard-hero">
                <div class="dashboard-hero-copy">
                    <p class="dashboard-kicker">Store Control Center</p>
                    <h2>Manage homepage collections, catalog products, orders, and reports from one place.</h2>
                    <p class="dashboard-intro">
                        This dashboard now includes collection management for the homepage, full product controls, order handling,
                        and reporting in a layout designed to stay usable on mobile, tablet, and desktop.
                    </p>
                </div>
                <div class="dashboard-hero-actions">
                    <a class="mini-link" href="dashboard.php?tab=collections">Collections</a>
                    <a class="mini-link" href="dashboard.php?tab=products">Products</a>
                    <a class="mini-link" href="dashboard.php?tab=orders">Orders</a>
                    <a class="mini-link" href="dashboard.php?tab=reports">Reports</a>
                </div>
            </section>

            <div class="admin-tabs">
                <?php foreach ($validTabs as $tabName): ?>
                    <a href="dashboard.php?tab=<?= esc($tabName); ?>" class="<?= $tab === $tabName ? 'is-active' : ''; ?>">
                        <?= esc(ucfirst($tabName)); ?>
                    </a>
                <?php endforeach; ?>
            </div>

            <?php if ($tab === 'overview'): ?>
                <div class="admin-stats-grid admin-stats-grid-wide">
                    <article class="admin-stat-card"><h3><?= (int) ($stats['products'] ?? 0); ?></h3><p>Total Products</p></article>
                    <article class="admin-stat-card"><h3><?= (int) ($stats['categories'] ?? 0); ?></h3><p>Total Categories</p></article>
                    <article class="admin-stat-card"><h3><?= (int) ($stats['collections'] ?? 0); ?></h3><p>Homepage Collections</p></article>
                    <article class="admin-stat-card"><h3><?= (int) ($stats['orders'] ?? 0); ?></h3><p>Total Orders</p></article>
                    <article class="admin-stat-card"><h3><?= (int) ($stats['pending_orders'] ?? 0); ?></h3><p>Pending Orders</p></article>
                    <article class="admin-stat-card"><h3><?= (int) ($stats['users'] ?? 0); ?></h3><p>Registered Users</p></article>
                    <article class="admin-stat-card"><h3>Rs. <?= number_format((float) ($stats['revenue'] ?? 0), 0); ?></h3><p>Total Revenue</p></article>
                </div>

                <section class="admin-card table-card">
                    <div class="section-head section-head-tight">
                        <h2>Recent Orders</h2>
                        <a class="mini-link" href="dashboard.php?tab=orders">Open Orders</a>
                    </div>
                    <div class="admin-table-wrap">
                        <table class="admin-table admin-table-responsive">
                            <thead><tr><th>Order</th><th>Customer</th><th>Status</th><th>Total</th><th>Date</th></tr></thead>
                            <tbody>
                                <?php $recentOrders = array_slice($orders, 0, 10); ?>
                                <?php if ($recentOrders === []): ?>
                                    <tr>
                                        <td colspan="5"><p class="table-empty">No orders have been placed yet.</p></td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($recentOrders as $order): ?>
                                        <tr>
                                            <td data-label="Order"><a href="order-details.php?order=<?= esc((string) $order['order_number']); ?>"><?= esc((string) $order['order_number']); ?></a></td>
                                            <td data-label="Customer"><?= esc((string) $order['recipient_name']); ?></td>
                                            <td data-label="Status"><span class="status-chip <?= dashboardStatusClass((string) $order['status']); ?>"><?= esc((string) $order['status']); ?></span></td>
                                            <td data-label="Total">Rs. <?= number_format((float) $order['total'], 0); ?></td>
                                            <td data-label="Date"><?= esc((string) substr((string) $order['created_at'], 0, 10)); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            <?php endif; ?>

            <?php if ($tab === 'collections'): ?>
                <div class="dashboard-layout dashboard-layout-admin">
                    <section class="admin-card">
                        <div class="section-head section-head-tight">
                            <h2><?= $editCollection ? 'Edit Collection' : 'Add Collection'; ?></h2>
                        </div>
                        <p class="admin-section-copy">
                            Collections control the cards shown in the homepage "Shop by Collection" section and can now link directly to a product category page.
                        </p>
                        <form method="post" action="dashboard.php?tab=collections" class="admin-form">
                            <input type="hidden" name="tab" value="collections">
                            <input type="hidden" name="action" value="<?= $editCollection ? 'update_collection' : 'add_collection'; ?>">
                            <?php if ($editCollection): ?>
                                <input type="hidden" name="collection_id" value="<?= (int) $editCollection['id']; ?>">
                            <?php endif; ?>

                            <div class="admin-grid">
                                <label><span>Collection Name</span><input type="text" name="name" value="<?= esc((string) ($editCollection['name'] ?? '')); ?>" required></label>
                                <label><span>Slug</span><input type="text" name="slug" value="<?= esc((string) ($editCollection['slug'] ?? '')); ?>" placeholder="auto from name"></label>
                                <label><span>Tag</span><input type="text" name="tag" maxlength="30" value="<?= esc((string) ($editCollection['tag'] ?? 'NEW')); ?>"></label>
                                <label><span>Symbol</span><input type="text" name="symbol" maxlength="10" value="<?= esc((string) ($editCollection['symbol'] ?? '*')); ?>"></label>
                                <label><span>Sort Order</span><input type="number" name="sort_order" value="<?= esc((string) ($editCollection['sort_order'] ?? 0)); ?>"></label>
                                <label>
                                    <span>Linked Category</span>
                                    <select name="category_id">
                                        <option value="">Auto detect / none</option>
                                        <?php foreach ($categories as $category): ?>
                                            <option value="<?= (int) $category['id']; ?>" <?= ($editCollection && (int) ($editCollection['category_id'] ?? 0) === (int) $category['id']) ? 'selected' : ''; ?>>
                                                <?= esc((string) $category['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>
                                <label>
                                    <span>Tone</span>
                                    <select name="tone">
                                        <?php foreach (storeAllowedTones() as $tone): ?>
                                            <option value="<?= esc($tone); ?>" <?= ($editCollection && $editCollection['tone'] === $tone) ? 'selected' : ''; ?>><?= esc(ucfirst($tone)); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>
                            </div>

                            <div class="collection-preview-card tone-<?= esc((string) ($editCollection['tone'] ?? 'rose')); ?>">
                                <span class="collection-preview-badge"><?= esc((string) ($editCollection['tag'] ?? 'NEW')); ?></span>
                                <span class="collection-preview-symbol"><?= esc((string) ($editCollection['symbol'] ?? '*')); ?></span>
                                <strong><?= esc((string) ($editCollection['name'] ?? 'Homepage Collection')); ?></strong>
                                <p><?= esc((string) ($editCollection['slug'] ?? 'auto-generated-slug')); ?><?= !empty($editCollection['category_name']) ? ' / ' . esc((string) $editCollection['category_name']) : ''; ?></p>
                            </div>

                            <button type="submit" class="cta-btn"><?= $editCollection ? 'Update Collection' : 'Create Collection'; ?></button>
                        </form>
                    </section>

                    <section class="admin-card">
                        <div class="section-head section-head-tight">
                            <h2>Collection List</h2>
                            <span class="admin-chip"><?= count($collections); ?> items</span>
                        </div>
                        <div class="admin-table-wrap">
                            <table class="admin-table admin-table-responsive">
                                <thead><tr><th>Preview</th><th>Name</th><th>Slug</th><th>Category</th><th>Products</th><th>Sort</th><th>Actions</th></tr></thead>
                                <tbody>
                                    <?php if ($collections === []): ?>
                                        <tr>
                                            <td colspan="7"><p class="table-empty">No collections available. Add one to show it on the homepage.</p></td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($collections as $collection): ?>
                                            <?php
                                            $collectionPath = !empty($collection['slug'])
                                                ? 'collection.php?slug=' . rawurlencode((string) $collection['slug'])
                                                : 'collection.php?id=' . (int) ($collection['id'] ?? 0);
                                            ?>
                                            <tr>
                                                <td data-label="Preview">
                                                    <div class="collection-swatch tone-<?= esc((string) $collection['tone']); ?>">
                                                        <span><?= esc((string) $collection['symbol']); ?></span>
                                                    </div>
                                                </td>
                                                <td data-label="Name">
                                                    <div class="admin-item-copy">
                                                        <strong><?= esc((string) $collection['name']); ?></strong>
                                                        <span><?= esc((string) $collection['tag']); ?></span>
                                                    </div>
                                                </td>
                                                <td data-label="Slug"><code><?= esc((string) ($collection['slug'] ?? '')); ?></code></td>
                                                <td data-label="Category"><?= esc((string) (($collection['category_name'] ?? '') !== '' ? $collection['category_name'] : 'Not linked')); ?></td>
                                                <td data-label="Products"><?= (int) ($collection['product_count'] ?? 0); ?></td>
                                                <td data-label="Sort"><?= (int) ($collection['sort_order'] ?? 0); ?></td>
                                                <td data-label="Actions">
                                                    <div class="table-actions">
                                                        <a class="mini-link" href="<?= esc($collectionPath); ?>">View</a>
                                                        <a class="mini-link" href="dashboard.php?tab=collections&edit_collection=<?= (int) $collection['id']; ?>">Edit</a>
                                                        <form method="post" action="dashboard.php?tab=collections" data-confirm="Delete this collection?" class="inline-form">
                                                            <input type="hidden" name="tab" value="collections">
                                                            <input type="hidden" name="action" value="delete_collection">
                                                            <input type="hidden" name="collection_id" value="<?= (int) $collection['id']; ?>">
                                                            <button type="submit" class="table-delete">Delete</button>
                                                        </form>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </section>
                </div>
            <?php endif; ?>

            <?php if ($tab === 'categories'): ?>
                <div class="dashboard-layout dashboard-layout-admin">
                    <section class="admin-card">
                        <div class="section-head section-head-tight">
                            <h2><?= $editCategory ? 'Edit Category' : 'Add Category'; ?></h2>
                        </div>
                        <p class="admin-section-copy">
                            Categories organize products across the catalog, search, and detail pages.
                        </p>
                        <form method="post" action="dashboard.php?tab=categories" class="admin-form">
                            <input type="hidden" name="tab" value="categories">
                            <input type="hidden" name="action" value="<?= $editCategory ? 'update_category' : 'add_category'; ?>">
                            <?php if ($editCategory): ?>
                                <input type="hidden" name="category_id" value="<?= (int) $editCategory['id']; ?>">
                            <?php endif; ?>
                            <label><span>Category Name</span><input type="text" name="name" value="<?= esc((string) ($editCategory['name'] ?? '')); ?>" required></label>
                            <label><span>Slug</span><input type="text" name="slug" value="<?= esc((string) ($editCategory['slug'] ?? '')); ?>"></label>
                            <label>
                                <span>Tone</span>
                                <select name="tone">
                                    <?php foreach (storeAllowedTones() as $tone): ?>
                                        <option value="<?= esc($tone); ?>" <?= ($editCategory && $editCategory['tone'] === $tone) ? 'selected' : ''; ?>><?= esc(ucfirst($tone)); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </label>
                            <label><span>Sort Order</span><input type="number" name="sort_order" value="<?= esc((string) ($editCategory['sort_order'] ?? 0)); ?>"></label>
                            <button type="submit" class="cta-btn"><?= $editCategory ? 'Update Category' : 'Create Category'; ?></button>
                        </form>
                    </section>

                    <section class="admin-card">
                        <div class="section-head section-head-tight">
                            <h2>Category List</h2>
                            <span class="admin-chip"><?= count($categories); ?> items</span>
                        </div>
                        <div class="admin-table-wrap">
                            <table class="admin-table admin-table-responsive">
                                <thead><tr><th>Name</th><th>Slug</th><th>Tone</th><th>Products</th><th>Actions</th></tr></thead>
                                <tbody>
                                    <?php if ($categories === []): ?>
                                        <tr>
                                            <td colspan="5"><p class="table-empty">No categories available.</p></td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($categories as $category): ?>
                                            <tr>
                                                <td data-label="Name"><?= esc((string) $category['name']); ?></td>
                                                <td data-label="Slug"><?= esc((string) $category['slug']); ?></td>
                                                <td data-label="Tone"><span class="admin-tone"><?= esc(ucfirst((string) ($category['tone'] ?? 'rose'))); ?></span></td>
                                                <td data-label="Products"><?= (int) ($category['product_count'] ?? 0); ?></td>
                                                <td data-label="Actions">
                                                    <div class="table-actions">
                                                        <a class="mini-link" href="dashboard.php?tab=categories&edit_category=<?= (int) $category['id']; ?>">Edit</a>
                                                        <form method="post" action="dashboard.php?tab=categories" data-confirm="Delete this category?" class="inline-form">
                                                            <input type="hidden" name="tab" value="categories">
                                                            <input type="hidden" name="action" value="delete_category">
                                                            <input type="hidden" name="category_id" value="<?= (int) $category['id']; ?>">
                                                            <button type="submit" class="table-delete">Delete</button>
                                                        </form>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </section>
                </div>
            <?php endif; ?>

            <?php if ($tab === 'products'): ?>
                <div class="dashboard-layout dashboard-layout-admin">
                    <section class="admin-card">
                        <div class="section-head section-head-tight">
                            <h2><?= $editProduct ? 'Edit Product' : 'Add Product'; ?></h2>
                        </div>
                        <p class="admin-section-copy">
                            Create products, manage stock, update pricing, and control sort order for storefront listing.
                        </p>
                        <form method="post" class="admin-form" action="dashboard.php?tab=products" enctype="multipart/form-data">
                            <input type="hidden" name="tab" value="products">
                            <input type="hidden" name="action" value="<?= $editProduct ? 'update_product' : 'add_product'; ?>">
                            <?php if ($editProduct): ?>
                                <input type="hidden" name="product_id" value="<?= (int) $editProduct['id']; ?>">
                            <?php endif; ?>

                            <div class="admin-grid">
                                <label><span>Name</span><input type="text" name="name" value="<?= esc((string) ($editProduct['name'] ?? '')); ?>" required></label>
                                <label><span>Short Name</span><input type="text" name="short_name" value="<?= esc((string) ($editProduct['short_name'] ?? '')); ?>"></label>
                                <label>
                                    <span>Category</span>
                                    <select name="category_id" required>
                                        <option value="">Select category</option>
                                        <?php foreach ($categories as $category): ?>
                                            <option value="<?= (int) $category['id']; ?>" <?= ($editProduct && (int) $editProduct['category_id'] === (int) $category['id']) ? 'selected' : ''; ?>>
                                                <?= esc((string) $category['name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>
                                <label><span>Price</span><input type="number" name="price" step="0.01" min="0" value="<?= esc((string) ($editProduct['price'] ?? '0')); ?>" required></label>
                                <label><span>Compare Price</span><input type="number" name="compare_price" step="0.01" min="0" value="<?= esc((string) ($editProduct['compare_price'] ?? '0')); ?>"></label>
                                <label><span>Cost Price</span><input type="number" name="cost_price" step="0.01" min="0" value="<?= esc((string) ($editProduct['cost_price'] ?? '0')); ?>"></label>
                                <label><span>Stock Qty</span><input type="number" name="stock_qty" min="0" value="<?= esc((string) ($editProduct['stock_qty'] ?? 0)); ?>" required></label>
                                <label>
                                    <span>Availability</span>
                                    <select name="availability">
                                        <?php foreach (storeAvailabilityStatuses() as $option): ?>
                                            <option value="<?= esc($option); ?>" <?= ($editProduct && $editProduct['availability'] === $option) ? 'selected' : ''; ?>>
                                                <?= esc(str_replace('_', ' ', ucfirst($option))); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>
                                <label>
                                    <span>Tone</span>
                                    <select name="tone">
                                        <?php foreach (storeAllowedTones() as $tone): ?>
                                            <option value="<?= esc($tone); ?>" <?= ($editProduct && $editProduct['tone'] === $tone) ? 'selected' : ''; ?>><?= esc(ucfirst($tone)); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </label>
                                <label><span>Tag</span><input type="text" name="tag" maxlength="30" value="<?= esc((string) ($editProduct['tag'] ?? 'NEW')); ?>"></label>
                                <label><span>Sort Order</span><input type="number" name="sort_order" value="<?= esc((string) ($editProduct['sort_order'] ?? 0)); ?>"></label>
                                <label><span>Image <?= $editProduct ? '(optional)' : ''; ?></span><input type="file" name="image" accept="image/*" <?= $editProduct ? '' : 'required'; ?>></label>
                            </div>

                            <label><span>Description</span><textarea name="description" rows="4"><?= esc((string) ($editProduct['description'] ?? '')); ?></textarea></label>
                            <label class="checkbox-row"><input type="checkbox" name="is_active" value="1" <?= (!$editProduct || !empty($editProduct['is_active'])) ? 'checked' : ''; ?>><span>Active product</span></label>

                            <button type="submit" class="cta-btn"><?= $editProduct ? 'Update Product' : 'Add Product'; ?></button>
                        </form>
                    </section>

                    <section class="admin-card">
                        <div class="section-head section-head-tight">
                            <h2>Products</h2>
                            <span class="admin-chip"><?= count($products); ?> items</span>
                        </div>
                        <div class="admin-table-wrap">
                            <table class="admin-table admin-table-responsive">
                                <thead><tr><th>Image</th><th>Product</th><th>Category</th><th>Sort</th><th>Stock</th><th>Status</th><th>Price</th><th>Actions</th></tr></thead>
                                <tbody>
                                    <?php if ($products === []): ?>
                                        <tr>
                                            <td colspan="8"><p class="table-empty">No products found.</p></td>
                                        </tr>
                                    <?php else: ?>
                                        <?php foreach ($products as $product): ?>
                                            <tr>
                                                <td data-label="Image"><div class="table-thumb"><?php renderImageOrFallback($product['image_path'] ?? null, (string) $product['name'], 'Img'); ?></div></td>
                                                <td data-label="Product">
                                                    <div class="admin-item-copy">
                                                        <strong><?= esc((string) $product['name']); ?></strong>
                                                        <span><?= esc((string) ($product['short_name'] ?? '')); ?></span>
                                                    </div>
                                                </td>
                                                <td data-label="Category"><?= esc((string) $product['category_name']); ?></td>
                                                <td data-label="Sort"><?= (int) ($product['sort_order'] ?? 0); ?></td>
                                                <td data-label="Stock"><?= (int) $product['stock_qty']; ?></td>
                                                <td data-label="Status"><span class="status-chip <?= dashboardStatusClass((string) $product['stock_label']); ?>"><?= esc((string) $product['stock_label']); ?></span></td>
                                                <td data-label="Price">Rs. <?= number_format((float) $product['price'], 0); ?></td>
                                                <td data-label="Actions">
                                                    <div class="table-actions">
                                                        <a class="mini-link" href="dashboard.php?tab=products&edit_product=<?= (int) $product['id']; ?>">Edit</a>
                                                        <form method="post" action="dashboard.php?tab=products" data-confirm="Delete this product?" class="inline-form">
                                                            <input type="hidden" name="tab" value="products">
                                                            <input type="hidden" name="action" value="delete_product">
                                                            <input type="hidden" name="product_id" value="<?= (int) $product['id']; ?>">
                                                            <button type="submit" class="table-delete">Delete</button>
                                                        </form>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </section>
                </div>
            <?php endif; ?>

            <?php if ($tab === 'orders'): ?>
                <section class="admin-card">
                    <div class="section-head section-head-tight">
                        <h2>Orders</h2>
                        <span class="admin-chip"><?= count($orders); ?> matches</span>
                    </div>
                    <form method="get" action="dashboard.php" class="admin-filters">
                        <input type="hidden" name="tab" value="orders">
                        <label><span>Search</span><input type="text" name="search" placeholder="Order / customer / phone" value="<?= esc((string) $orderFilters['search']); ?>"></label>
                        <label>
                            <span>Status</span>
                            <select name="status">
                                <option value="">All statuses</option>
                                <?php foreach (storeOrderStatuses() as $status): ?>
                                    <option value="<?= esc($status); ?>" <?= $orderFilters['status'] === $status ? 'selected' : ''; ?>><?= esc(ucfirst($status)); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </label>
                        <label><span>From</span><input type="date" name="date_from" value="<?= esc((string) $orderFilters['date_from']); ?>"></label>
                        <label><span>To</span><input type="date" name="date_to" value="<?= esc((string) $orderFilters['date_to']); ?>"></label>
                        <button type="submit" class="cta-btn">Filter</button>
                    </form>

                    <div class="admin-table-wrap">
                        <table class="admin-table admin-table-responsive">
                            <thead><tr><th>Order</th><th>Customer</th><th>Total</th><th>Date</th><th>Status</th><th>Action</th></tr></thead>
                            <tbody>
                                <?php if ($orders === []): ?>
                                    <tr>
                                        <td colspan="6"><p class="table-empty">No orders matched the current filters.</p></td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($orders as $order): ?>
                                        <tr>
                                            <td data-label="Order"><a href="order-details.php?order=<?= esc((string) $order['order_number']); ?>"><?= esc((string) $order['order_number']); ?></a></td>
                                            <td data-label="Customer"><?= esc((string) $order['recipient_name']); ?></td>
                                            <td data-label="Total">Rs. <?= number_format((float) $order['total'], 0); ?></td>
                                            <td data-label="Date"><?= esc((string) substr((string) $order['created_at'], 0, 10)); ?></td>
                                            <td data-label="Status"><span class="status-chip <?= dashboardStatusClass((string) $order['status']); ?>"><?= esc((string) $order['status']); ?></span></td>
                                            <td data-label="Action">
                                                <form method="post" action="dashboard.php?tab=orders" class="inline-form">
                                                    <input type="hidden" name="tab" value="orders">
                                                    <input type="hidden" name="action" value="update_order_status">
                                                    <input type="hidden" name="order_id" value="<?= (int) $order['id']; ?>">
                                                    <select name="status">
                                                        <?php foreach (storeOrderStatuses() as $status): ?>
                                                            <option value="<?= esc($status); ?>" <?= $order['status'] === $status ? 'selected' : ''; ?>><?= esc(ucfirst($status)); ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                    <button type="submit" class="mini-link">Save</button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </section>
            <?php endif; ?>

            <?php if ($tab === 'reports'): ?>
                <section class="admin-card">
                    <h2>Sales & Purchase Reports</h2>
                    <form method="get" action="dashboard.php" class="admin-filters">
                        <input type="hidden" name="tab" value="reports">
                        <label><span>From</span><input type="date" name="report_from" value="<?= esc((string) $reportFrom); ?>"></label>
                        <label><span>To</span><input type="date" name="report_to" value="<?= esc((string) $reportTo); ?>"></label>
                        <button type="submit" class="cta-btn">Load Report</button>
                    </form>

                    <div class="admin-stats-grid">
                        <article class="admin-stat-card"><h3><?= (int) ($salesReport['summary']['orders'] ?? 0); ?></h3><p>Orders</p></article>
                        <article class="admin-stat-card"><h3><?= (int) ($salesReport['summary']['units'] ?? 0); ?></h3><p>Units Sold</p></article>
                        <article class="admin-stat-card"><h3>Rs. <?= number_format((float) ($salesReport['summary']['revenue'] ?? 0), 0); ?></h3><p>Sales Revenue</p></article>
                        <article class="admin-stat-card"><h3>Rs. <?= number_format((float) ($salesReport['summary']['profit'] ?? 0), 0); ?></h3><p>Gross Profit</p></article>
                        <article class="admin-stat-card"><h3><?= (int) ($purchaseReport['summary']['qty'] ?? 0); ?></h3><p>Purchased Qty</p></article>
                        <article class="admin-stat-card"><h3>Rs. <?= number_format((float) ($purchaseReport['summary']['cost'] ?? 0), 0); ?></h3><p>Purchase Cost</p></article>
                    </div>

                    <div class="dashboard-layout dashboard-layout-admin">
                        <section class="admin-card">
                            <h2>Top Selling Products</h2>
                            <div class="admin-table-wrap">
                                <table class="admin-table admin-table-responsive">
                                    <thead><tr><th>Product</th><th>Units</th><th>Revenue</th></tr></thead>
                                    <tbody>
                                        <?php if (($salesReport['top_products'] ?? []) === []): ?>
                                            <tr>
                                                <td colspan="3"><p class="table-empty">No sales data is available for this range.</p></td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach (($salesReport['top_products'] ?? []) as $row): ?>
                                                <tr>
                                                    <td data-label="Product"><?= esc((string) $row['product_name']); ?></td>
                                                    <td data-label="Units"><?= (int) $row['units']; ?></td>
                                                    <td data-label="Revenue">Rs. <?= number_format((float) $row['revenue'], 0); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </section>

                        <section class="admin-card">
                            <h2>Purchase by Product</h2>
                            <div class="admin-table-wrap">
                                <table class="admin-table admin-table-responsive">
                                    <thead><tr><th>Product</th><th>Qty</th><th>Cost</th></tr></thead>
                                    <tbody>
                                        <?php if (($purchaseReport['products'] ?? []) === []): ?>
                                            <tr>
                                                <td colspan="3"><p class="table-empty">No purchase data is available for this range.</p></td>
                                            </tr>
                                        <?php else: ?>
                                            <?php foreach (($purchaseReport['products'] ?? []) as $row): ?>
                                                <tr>
                                                    <td data-label="Product"><?= esc((string) $row['product_name']); ?></td>
                                                    <td data-label="Qty"><?= (int) $row['qty']; ?></td>
                                                    <td data-label="Cost">Rs. <?= number_format((float) $row['cost'], 0); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </section>
                    </div>
                </section>
            <?php endif; ?>
        </div>
    </section>
</main>

<?php renderStoreFooter(); ?>
