<?php
require_once __DIR__ . '/app.php';
$user = require_user();
$orderId = filter_var($_GET['order'] ?? null, FILTER_VALIDATE_INT);
$order = $orderId === false || $orderId === null
    ? null
    : db()->one(
        'SELECT id, status, total, payment_reference FROM orders WHERE id = ? AND user_id = ?',
        [(int) $orderId, (int) $user['id']]
    );
if ($order === null) {
    redirect('account.php');
}
$items = db()->all(
    'SELECT product_name, quantity FROM order_items WHERE order_id = ?',
    [(int) $order['id']]
);
site_shell_start('Your order is on its way', 'success-page');
?>
    <section class="confirmation-card" aria-labelledby="confirmation-title">
        <span class="confirmation-mark" aria-hidden="true">✓</span>
        <p class="section-eyebrow">ALL NICE AND NEAT</p>
        <h1 id="confirmation-title">It’s in the<br><em>works.</em></h1>
        <p class="confirmation-message">Your demo order is safely recorded. This was a simulated checkout — no payment was taken.</p>
        <div class="confirmation-summary">
            <div><span>Order</span><strong>CC-<?= e(str_pad((string) $order['id'], 6, '0', STR_PAD_LEFT)) ?></strong></div>
            <div><span>What’s on its way</span><strong><?= e(implode(', ', array_map(static fn(array $item): string => (int) $item['quantity'] . ' × ' . $item['product_name'], $items))) ?></strong></div>
            <div><span>Demo total</span><strong><?= money((float) $order['total']) ?></strong></div>
            <div><span>First update</span><strong><?= e($order['status']) ?></strong></div>
        </div>
        <div class="confirmation-actions">
            <a class="button button-dark" href="account.php">Follow your order <span aria-hidden="true">↗</span></a>
            <a class="text-link" href="home.php#shop">Keep exploring <span aria-hidden="true">↗</span></a>
        </div>
    </section>
<?php site_shell_end(); ?>
