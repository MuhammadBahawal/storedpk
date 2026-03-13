<?php

declare(strict_types=1);

require_once __DIR__ . '/includes/store_service.php';
require_once __DIR__ . '/includes/site_ui.php';

storeEnsureSchema();

if (storeCurrentUserId() !== null) {
    header('Location: account.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = storeRegisterUser($_POST);
    if (!empty($result['success'])) {
        header('Location: account.php');
        exit;
    }
    storeFlashSet('auth_register', $result);
    header('Location: register.php');
    exit;
}

$flash = storeFlashPull('auth_register');

renderPageStart('Register', 'catalog-page auth-page');
renderStoreHeader('');
?>

<main class="catalog-main">
    <section class="page-shell">
        <div class="container narrow-container">
            <h1 class="page-title">Create Account</h1>

            <?php if (is_array($flash)): ?>
                <div class="flash flash-error"><?= esc((string) ($flash['message'] ?? '')); ?></div>
            <?php endif; ?>

            <section class="admin-card auth-card">
                <form method="post" action="register.php" class="admin-form">
                    <label>
                        <span>Full Name</span>
                        <input type="text" name="full_name" required>
                    </label>
                    <label>
                        <span>Email</span>
                        <input type="email" name="email" required>
                    </label>
                    <label>
                        <span>Phone</span>
                        <input type="text" name="phone" required>
                    </label>
                    <label>
                        <span>Password</span>
                        <input type="password" name="password" required>
                    </label>
                    <label>
                        <span>Confirm Password</span>
                        <input type="password" name="confirm_password" required>
                    </label>
                    <button type="submit" class="cta-btn">Create Account</button>
                </form>
                <p class="hint-text">Already have account? <a href="login.php">Login</a></p>
            </section>
        </div>
    </section>
</main>

<?php renderStoreFooter(); ?>
