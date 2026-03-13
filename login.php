<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/store_service.php';
require_once __DIR__ . '/includes/site_ui.php';

storeEnsureSchema();

if (storeCurrentUserId() !== null) {
    header('Location: account.php');
    exit;
}

$next = trim((string) ($_GET['next'] ?? ''));
if ($next === '') {
    $next = 'account.php';
}
if (!preg_match('/^[a-zA-Z0-9._\\/-]+(\\?[a-zA-Z0-9=&_%\\-]*)?$/', $next)) {
    $next = 'account.php';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = storeLoginUser((string) ($_POST['email'] ?? ''), (string) ($_POST['password'] ?? ''));
    if (!empty($result['success'])) {
        header('Location: ' . $next);
        exit;
    }
    storeFlashSet('auth_login', $result);
    header('Location: login.php?next=' . rawurlencode($next));
    exit;
}

$flash = storeFlashPull('auth_login');

renderPageStart('Login', 'catalog-page auth-page');
renderStoreHeader('');
?>

<main class="catalog-main">
    <section class="page-shell">
        <div class="container narrow-container">
            <h1 class="page-title">Login</h1>

            <?php if (is_array($flash)): ?>
                <div class="flash flash-error"><?= esc((string) ($flash['message'] ?? '')); ?></div>
            <?php endif; ?>

            <section class="admin-card auth-card">
                <form method="post" action="login.php?next=<?= esc(rawurlencode($next)); ?>" class="admin-form">
                    <label>
                        <span>Email</span>
                        <input type="email" name="email" required>
                    </label>
                    <label>
                        <span>Password</span>
                        <input type="password" name="password" required>
                    </label>
                    <button type="submit" class="cta-btn">Login</button>
                </form>
                <p class="hint-text">New here? <a href="register.php">Create account</a></p>
            </section>
        </div>
    </section>
</main>

<?php renderStoreFooter(); ?>
