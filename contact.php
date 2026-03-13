<?php

declare(strict_types=1);

session_start();

require_once __DIR__ . '/includes/catalog.php';
require_once __DIR__ . '/includes/site_ui.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $result = saveContactMessage($_POST);
    $_SESSION['contact_flash'] = $result;
    header('Location: contact.php');
    exit;
}

$flash = $_SESSION['contact_flash'] ?? null;
unset($_SESSION['contact_flash']);

renderPageStart('Contact', 'catalog-page info-page');
renderStoreHeader('contact');
?>

<main class="catalog-main">
    <section class="page-shell">
        <div class="container narrow-container">
            <h1 class="page-title">Contact</h1>

            <?php if (is_array($flash)): ?>
                <div class="flash <?= !empty($flash['success']) ? 'flash-success' : 'flash-error'; ?>">
                    <?= esc((string) ($flash['message'] ?? '')); ?>
                </div>
            <?php endif; ?>

            <form method="post" action="contact.php" class="contact-form">
                <div class="contact-grid">
                    <label>
                        <span>Name</span>
                        <input type="text" name="name" required>
                    </label>

                    <label>
                        <span>Email</span>
                        <input type="email" name="email" required>
                    </label>

                    <label class="full-width">
                        <span>Phone</span>
                        <input type="text" name="phone" required>
                    </label>

                    <label class="full-width">
                        <span>Comment</span>
                        <textarea name="comment" rows="8" required></textarea>
                    </label>
                </div>

                <button type="submit" class="cta-btn">Submit</button>
            </form>
        </div>
    </section>
</main>

<?php renderStoreFooter(); ?>
