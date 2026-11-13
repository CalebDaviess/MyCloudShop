<?php
require_once __DIR__ . '/app.php';

$productId = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
$product = $productId === false || $productId === null ? null : product_by_id((int) $productId);
if ($product === null || !(bool) $product['is_active']) {
    http_response_code(404);
    site_shell_start('Product Not Found', 'product-page');
    ?>
    <section class="empty-state cart-empty">
        <span class="empty-symbol" aria-hidden="true">⌕</span>
        <h1>We couldn’t find that product.</h1>
        <p>It may have been archived or moved. Explore our current UK tech deals below.</p>
        <a class="button button-amber" href="home.php#shop">Browse All Tech Deals →</a>
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
            set_flash('success', 'Added to your Saved Wish List.');
        } else {
            db()->run('DELETE FROM wishlist_items WHERE user_id = ? AND product_id = ?', [(int) $user['id'], (int) $product['id']]);
            set_flash('info', 'Removed from your Saved Wish List.');
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
if (count($recommendations) < 4) {
    $more = array_values(array_filter(
        products(),
        static fn(array $item): bool => (int) $item['id'] !== (int) $product['id'] && (string) $item['category'] !== (string) $product['category']
    ));
    $recommendations = array_merge($recommendations, $more);
}

$image = product_image_url((string) $product['image'], (string) $product['name']);
$meta = product_meta($product);
$price = (float) $product['price'];
$stock = (int) $product['stock'];

site_shell_start((string) $product['name'], 'product-page');
?>
    <nav class="breadcrumbs" aria-label="Breadcrumb">
        <a href="home.php">CloudCart.co.uk</a><span aria-hidden="true">›</span>
        <a href="home.php?category=<?= rawurlencode((string) $product['category']) ?>#shop"><?= e($product['category']) ?></a><span aria-hidden="true">›</span>
        <span aria-current="page"><?= e($product['name']) ?></span>
    </nav>

    <section class="product-detail-layout" aria-labelledby="product-title">
        <div class="detail-gallery-col">
            <div class="detail-art-frame">
                <span class="product-badge <?= e($meta['badge_class']) ?>"><?= e($meta['badge']) ?></span>
                <img src="<?= e($image) ?>" alt="<?= e($product['name']) ?>">
            </div>
            <div class="detail-trust-pills">
                <span>🛡️ 2-Year UK Warranty</span>
                <span>↩️ Free 30-Day Returns</span>
                <span>⚡ Verified Genuine Stock</span>
            </div>
        </div>

        <div class="detail-info-col">
            <a class="detail-brand-link" href="home.php?category=<?= rawurlencode((string) $product['category']) ?>#shop">Visit the CloudCart <?= e($product['category']) ?> Store</a>
            <h1 id="product-title"><?= e($product['name']) ?></h1>

            <div class="detail-rating-row">
                <span class="stars-gold">★★★★★</span>
                <strong><?= e($meta['rating']) ?> out of 5</strong>
                <span class="detail-review-count"><?= e($meta['reviews']) ?> global ratings</span>
                <span class="detail-choice-pill">✓ <?= e($meta['badge']) ?></span>
            </div>

            <div class="detail-price-box">
                <div class="detail-deal-headline">
                    <span class="deal-limited-badge">Deal Price</span>
                    <span class="detail-discount-pct">-<?= (int) $meta['discount_pct'] ?>%</span>
                    <span class="detail-price-main"><?= money($price) ?></span>
                </div>
                <p class="detail-rrp-line">RRP: <s><?= money((float) $meta['rrp']) ?></s> · You save <strong><?= money((float) $meta['savings']) ?></strong> (Prices include UK VAT)</p>
                <p class="detail-prime-line"><span class="prime-tick">✓ prime</span> <strong><?= $price >= 100 ? 'FREE Next-Day UK Delivery' : 'Standard UK Delivery £4.95 (FREE over £100)' ?></strong></p>
            </div>

            <div class="detail-specs-block">
                <h2>About this item</h2>
                <p class="detail-description"><?= e($product['description']) ?></p>
                <ul class="detail-spec-list">
                    <?php foreach ($meta['specs'] as $spec): ?>
                        <li><?= e($spec) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>

            <div class="detail-reviews-summary">
                <h3>Verified Customer Ratings (<?= e($meta['rating']) ?> / 5.0)</h3>
                <div class="rating-bars">
                    <div class="rating-bar-row"><span>5 star</span><div class="bar-track"><div class="bar-fill" style="width: 82%"></div></div><span>82%</span></div>
                    <div class="rating-bar-row"><span>4 star</span><div class="bar-track"><div class="bar-fill" style="width: 13%"></div></div><span>13%</span></div>
                    <div class="rating-bar-row"><span>3 star</span><div class="bar-track"><div class="bar-fill" style="width: 4%"></div></div><span>4%</span></div>
                    <div class="rating-bar-row"><span>2 star</span><div class="bar-track"><div class="bar-fill" style="width: 1%"></div></div><span>1%</span></div>
                    <div class="rating-bar-row"><span>1 star</span><div class="bar-track"><div class="bar-fill" style="width: 0%"></div></div><span>0%</span></div>
                </div>
            </div>
        </div>

        <aside class="detail-buy-box" aria-label="Purchase options">
            <div class="buy-box-price"><?= money($price) ?></div>
            <p class="buy-box-prime"><span class="prime-tick">✓ prime</span> &amp; <strong>FREE Returns</strong></p>
            <p class="buy-box-delivery">
                FREE delivery <strong>Tomorrow</strong> on orders over £100. Order within <span data-deal-countdown>4 hrs 42 mins</span>.
            </p>
            <p class="buy-box-location">📍 Delivering to Cardiff &amp; UK Mainland</p>

            <p class="buy-box-stock <?= $stock < 1 ? 'stock-out' : ($stock < 10 ? 'stock-low' : 'stock-ok') ?>">
                <?= $stock < 1 ? 'Temporarily Out of Stock' : ($stock < 10 ? 'Only ' . $stock . ' left in stock — order soon.' : 'In Stock (' . $stock . ' available)') ?>
            </p>

            <form action="cart.php" method="post" class="buy-box-form">
                <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="add">
                <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
                <button class="button-add-amber button-full" type="submit" <?= $stock < 1 ? 'disabled' : '' ?>>
                    <?= $stock < 1 ? 'Currently Unavailable' : 'Add to Basket' ?>
                </button>
            </form>

            <?php if ($user !== null): ?>
                <form action="product.php?id=<?= (int) $product['id'] ?>" method="post" class="wishlist-form">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="wishlist">
                    <button class="wishlist-button button-full" type="submit" aria-pressed="<?= $isSaved ? 'true' : 'false' ?>">
                        <span aria-hidden="true"><?= $isSaved ? '♥' : '♡' ?></span> <?= $isSaved ? 'Saved in Your Wish List' : 'Add to Wish List' ?>
                    </button>
                </form>
            <?php else: ?>
                <a class="wishlist-button button-full" href="login.php"><span aria-hidden="true">♡</span> Sign in to Save to Wish List</a>
            <?php endif; ?>

            <dl class="buy-box-meta">
                <div><dt>Dispatches from</dt><dd>CloudCart.co.uk</dd></div>
                <div><dt>Sold by</dt><dd>CloudCart Official UK</dd></div>
                <div><dt>Returns</dt><dd>Returnable within 30 days</dd></div>
                <div><dt>Payment</dt><dd>Simulated Demo Checkout</dd></div>
            </dl>
        </aside>
    </section>

    <section class="recommendations">
        <div class="section-heading">
            <div>
                <p class="section-eyebrow">CUSTOMERS ALSO VIEWED</p>
                <h2>More High-Performance Tech to Explore</h2>
            </div>
            <a class="text-link" href="home.php#shop">View full catalogue →</a>
        </div>
        <div class="recommendation-row">
            <?php foreach (array_slice($recommendations, 0, 4) as $related): ?>
                <?php
                $relImg = product_image_url((string) $related['image'], (string) $related['name']);
                $relMeta = product_meta($related);
                ?>
                <a class="recommendation-card" href="product.php?id=<?= (int) $related['id'] ?>">
                    <img src="<?= e($relImg) ?>" alt="<?= e($related['name']) ?>" loading="lazy">
                    <div>
                        <small class="rec-cat"><?= e($related['category']) ?></small>
                        <strong><?= e($related['name']) ?></strong>
                        <span class="rec-stars">★★★★★ <?= e($relMeta['rating']) ?></span>
                        <span class="rec-price"><?= money((float) $related['price']) ?></span>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </section>
<?php site_shell_end(); ?>
