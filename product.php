<?php
require_once __DIR__ . '/app.php';

$productId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
$product = $productId === false || $productId === null ? null : product_by_id((int) $productId);
if ($product === null || !(bool) $product['is_active']) {
    http_response_code(404);
    site_shell_start('A good find, gone for now', 'product-page');
    ?>
    <section class="empty-state cart-empty">
        <span class="empty-symbol" aria-hidden="true">✳</span>
        <h1>That find has moved on.</h1>
        <p>It may be taking a little break. There are plenty of other lovely things to discover.</p>
        <a class="button button-dark" href="index.php#shop">Browse the collection <span aria-hidden="true">↗</span></a>
    </section>
    <?php
    site_shell_end();
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $user = require_user();
    if (($_POST['action'] ?? '') === 'wishlist') {
        $exists = db()->one(
            'SELECT id FROM wishlist_items WHERE user_id = ? AND product_id = ?',
            [(int) $user['id'], (int) $product['id']]
        );
        if ($exists === null) {
            db()->insert('wishlist_items', [
                'user_id' => (int) $user['id'],
                'product_id' => (int) $product['id'],
            ]);
            set_flash('success', 'Saved to your favourites.');
        } else {
            db()->run('DELETE FROM wishlist_items WHERE user_id = ? AND product_id = ?', [(int) $user['id'], (int) $product['id']]);
            set_flash('info', 'Removed from your favourites.');
        }
        redirect('product.php?id=' . (int) $product['id']);
    }
}

$user = current_user();
$isSaved = $user !== null && db()->one(
    'SELECT id FROM wishlist_items WHERE user_id = ? AND product_id = ?',
    [(int) $user['id'], (int) $product['id']]
) !== null;
$recommendations = array_values(array_filter(
    products(['category' => (string) $product['category']]),
    static fn(array $item): bool => (int) $item['id'] !== (int) $product['id']
));
site_shell_start((string) $product['name'], 'product-page');
?>
    <nav class="breadcrumbs" aria-label="Breadcrumb">
        <a href="index.php">Home</a><span aria-hidden="true">/</span>
        <a href="index.php#shop">Shop</a><span aria-hidden="true">/</span>
        <span aria-current="page"><?= e($product['name']) ?></span>
    </nav>
    <section class="product-detail" aria-labelledby="product-title">
        <div class="detail-art">
            <img src="<?= e($product['image']) ?>" alt="<?= e($product['name']) ?>">
            <?php if ((bool) $product['is_featured']): ?><span class="product-badge">A CloudCart favourite</span><?php endif; ?>
        </div>
        <div class="detail-copy">
            <p class="section-eyebrow"><?= e($product['category']) ?> · THOUGHTFULLY CHOSEN</p>
            <h1 id="product-title"><?= e($product['name']) ?><span class="title-dot">.</span></h1>
            <p class="detail-price"><?= money((float) $product['price']) ?></p>
            <p class="detail-description"><?= e($product['description']) ?></p>
            <p class="detail-availability"><span aria-hidden="true">✳</span> <?= (int) $product['stock'] < 1 ? 'Taking a little rest right now.' : ((int) $product['stock'] < 5 ? 'Only a few left to find a home.' : 'In stock and ready for its next home.') ?></p>
            <form action="cart.php" method="post" class="detail-actions">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="add">
                <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
                <button class="button button-dark" type="submit" <?= (int) $product['stock'] < 1 ? 'disabled' : '' ?>>Add to your bag <span aria-hidden="true">↗</span></button>
            </form>
            <?php if ($user !== null): ?>
                <form action="product.php?id=<?= (int) $product['id'] ?>" method="post" class="wishlist-form">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="wishlist">
                    <button class="wishlist-button" type="submit" aria-pressed="<?= $isSaved ? 'true' : 'false' ?>">
                        <span aria-hidden="true"><?= $isSaved ? '♥' : '♡' ?></span> <?= $isSaved ? 'In your favourites' : 'Save to favourites' ?>
                    </button>
                </form>
            <?php else: ?>
                <a class="wishlist-button" href="login.php"><span aria-hidden="true">♡</span> Sign in to save this find</a>
            <?php endif; ?>
            <div class="detail-promises">
                <p><span aria-hidden="true">↗</span> Free delivery over £100</p>
                <p><span aria-hidden="true">✳</span> 30 days to find your fit</p>
                <p><span aria-hidden="true">✓</span> Secure, simulated checkout</p>
            </div>
        </div>
    </section>
    <section class="recommendations">
        <div class="section-heading">
            <div><p class="section-eyebrow">MORE TO FALL FOR</p><h2>In the same good company.</h2></div>
            <a class="text-link" href="index.php#shop">See everything <span aria-hidden="true">↗</span></a>
        </div>
        <div class="recommendation-row">
            <?php foreach (array_slice($recommendations, 0, 3) as $related): ?>
                <a class="recommendation-card" href="product.php?id=<?= (int) $related['id'] ?>">
                    <img src="<?= e($related['image']) ?>" alt="" loading="lazy">
                    <span><strong><?= e($related['name']) ?></strong><small><?= money((float) $related['price']) ?></small></span>
                    <span aria-hidden="true">↗</span>
                </a>
            <?php endforeach; ?>
        </div>
    </section>
<?php site_shell_end(); ?>
