<?php
require_once __DIR__ . '/app.php';
$user = require_user();
$setupSecret = $_SESSION['pending_totp_secret'] ?? null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = post_string('action');

    if ($action === 'mfa_setup' && !$user['mfa_enabled']) {
        $_SESSION['pending_totp_secret'] = totp_secret();
        unset($_SESSION['totp_setup_attempts']);
        redirect('account.php#security');
    }

    if ($action === 'mfa_confirm' && !$user['mfa_enabled'] && is_string($setupSecret)) {
        $code = post_string('mfa_code');
        $attempts = (int) ($_SESSION['totp_setup_attempts'] ?? 0);
        if ($attempts >= 5) {
            unset($_SESSION['pending_totp_secret'], $_SESSION['totp_setup_attempts']);
            set_flash('error', 'Too many verification attempts. Start MFA setup again.');
            redirect('account.php#security');
        }
        if (is_string($code) && verify_totp($setupSecret, trim($code))) {
            db()->run('UPDATE users SET mfa_secret = ?, mfa_enabled = 1 WHERE id = ?', [$setupSecret, (int) $user['id']]);
            $_SESSION['mfa_verified'] = true;
            unset($_SESSION['pending_totp_secret'], $_SESSION['totp_setup_attempts']);
            set_flash('success', 'Multi-factor authentication is on. Your account has an extra layer of protection.');
        } else {
            $attempts++;
            if ($attempts >= 5) {
                unset($_SESSION['pending_totp_secret'], $_SESSION['totp_setup_attempts']);
                set_flash('error', 'Too many verification attempts. Start MFA setup again.');
            } else {
                $_SESSION['totp_setup_attempts'] = $attempts;
                set_flash('error', 'That code didn’t match. Check your authenticator app and try again.');
            }
        }
        redirect('account.php#security');
    }

    if ($action === 'mfa_disable' && $user['mfa_enabled']) {
        $code = post_string('mfa_code');
        $password = post_string('password');
        $record = db()->one('SELECT password_hash, mfa_secret FROM users WHERE id = ?', [(int) $user['id']]);
        $hasLocalPassword = $record !== null && $password !== '' && password_verify($password, (string) $record['password_hash']);
        $hasAuthenticatorCode = $record !== null && is_string($code) && verify_totp((string) $record['mfa_secret'], trim($code));
        if ($hasLocalPassword || $hasAuthenticatorCode) {
            db()->run('UPDATE users SET mfa_secret = NULL, mfa_enabled = 0 WHERE id = ?', [(int) $user['id']]);
            $_SESSION['mfa_verified'] = true;
            set_flash('info', 'Multi-factor authentication has been turned off.');
        } else {
            set_flash('error', 'Enter your password or a current authenticator code to turn MFA off.');
        }
        redirect('account.php#security');
    }

    if ($action === 'wishlist_remove') {
        $productId = filter_var($_POST['product_id'] ?? null, FILTER_VALIDATE_INT);
        if ($productId !== false && $productId !== null) {
            db()->run('DELETE FROM wishlist_items WHERE user_id = ? AND product_id = ?', [(int) $user['id'], (int) $productId]);
            set_flash('success', 'Removed from your favourites.');
        }
        redirect('account.php#favourites');
    }

    set_flash('error', 'That request couldn’t be completed. Please try again.');
    redirect('account.php');
}

$orderLimit = db()->isSqlServer() ? 'SELECT TOP 30' : 'SELECT';
$orderSuffix = db()->isSqlServer() ? '' : ' LIMIT 30';
$orders = db()->all(
    $orderLimit . ' id, status, total, delivery_fee, payment_reference, created_at, updated_at FROM orders WHERE user_id = ? ORDER BY created_at DESC, id DESC' . $orderSuffix,
    [(int) $user['id']]
);
$orderLines = [];
if ($orders !== []) {
    $orderIds = array_map(static fn(array $order): int => (int) $order['id'], $orders);
    $placeholders = implode(', ', array_fill(0, count($orderIds), '?'));
    foreach (db()->all(
        'SELECT order_id, product_name, quantity, unit_price FROM order_items WHERE order_id IN (' . $placeholders . ')',
        $orderIds
    ) as $line) {
        $orderLines[(int) $line['order_id']][] = $line;
    }
}
$favourites = db()->all(
    'SELECT p.id, p.name, p.category, p.price, p.image, p.is_active FROM wishlist_items w JOIN products p ON p.id = w.product_id WHERE w.user_id = ? ORDER BY w.created_at DESC',
    [(int) $user['id']]
);
$oauthLinked = db()->one('SELECT oauth_subject FROM users WHERE id = ?', [(int) $user['id']]);
$oauthSubject = $oauthLinked['oauth_subject'] ?? null;
site_shell_start('Your account', 'account-page');
?>
    <section class="account-welcome">
        <div>
            <p class="section-eyebrow">YOUR CLOUDCART</p>
            <h1>Good to see you, <?= e(explode(' ', (string) $user['name'])[0]) ?><span class="title-dot">.</span></h1>
            <p>Here’s your little corner of the good stuff.</p>
        </div>
        <span class="account-avatar" aria-hidden="true"><?= e(strtoupper(substr((string) $user['name'], 0, 1))) ?></span>
    </section>

    <section class="account-section" aria-labelledby="orders-title">
        <div class="section-heading account-section-heading">
            <div><p class="section-eyebrow">THE JOURNEY SO FAR</p><h2 id="orders-title">Your orders</h2></div>
            <span class="result-count"><?= count($orders) ?> <?= count($orders) === 1 ? 'order' : 'orders' ?></span>
        </div>
        <?php if ($orders === []): ?>
            <div class="empty-state account-empty">
                <span class="empty-symbol" aria-hidden="true">↗</span>
                <h3>Your next favourite is out there.</h3>
                <p>Once you find it, you’ll be able to follow its journey from here.</p>
                <a class="text-link" href="index.php#shop">Have a look around <span aria-hidden="true">↗</span></a>
            </div>
        <?php else: ?>
            <p class="tracking-live-status" data-tracking-live role="status">Order updates refresh automatically.</p>
            <div class="order-list">
                <?php foreach ($orders as $order): ?>
                    <?php
                    $lines = $orderLines[(int) $order['id']] ?? [];
                    $status = (string) $order['status'];
                    $statusIndex = match ($status) {
                        'Order received' => 0,
                        'Being prepared' => 1,
                        'On its way' => 2,
                        'Delivered' => 3,
                        default => 0,
                    };
                    ?>
                    <article class="order-card" data-order-id="<?= (int) $order['id'] ?>">
                        <div class="order-topline">
                            <div>
                                <p class="product-category">ORDER CC-<?= e(str_pad((string) $order['id'], 6, '0', STR_PAD_LEFT)) ?></p>
                                <h3><?= e(date('j F Y', strtotime((string) $order['created_at']))) ?></h3>
                            </div>
                            <span class="order-status" data-order-status><?= e($status) ?></span>
                        </div>
                        <ol class="order-progress" aria-label="Order progress" data-order-progress>
                            <?php foreach (['Order received', 'Being prepared', 'On its way', 'Delivered'] as $index => $step): ?>
                                <li class="<?= $index <= $statusIndex ? 'is-complete' : '' ?>" data-order-step="<?= e($step) ?>" <?= $index === $statusIndex ? 'aria-current="step"' : '' ?>>
                                    <span class="progress-dot"></span><?= e($step) ?>
                                </li>
                            <?php endforeach; ?>
                        </ol>
                        <div class="order-lines">
                            <?php foreach ($lines as $line): ?>
                                <p><span><?= e($line['product_name']) ?> <small>× <?= (int) $line['quantity'] ?></small></span><strong><?= money((float) $line['unit_price'] * (int) $line['quantity']) ?></strong></p>
                            <?php endforeach; ?>
                        </div>
                        <div class="order-bottom"><span>Demo payment · <?= e($order['payment_reference']) ?> · <?= (float) $order['delivery_fee'] === 0.0 ? 'Delivery on us' : 'Delivery ' . money((float) $order['delivery_fee']) ?></span><strong><?= money((float) $order['total']) ?></strong></div>
                        <p class="order-updated" data-order-updated>Updated <?= e(date('j M Y, H:i', strtotime((string) $order['updated_at']))) ?></p>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <section class="account-section" id="favourites" aria-labelledby="favourites-title">
        <div class="section-heading account-section-heading">
            <div><p class="section-eyebrow">SAVED FOR A LITTLE LATER</p><h2 id="favourites-title">Your favourites</h2></div>
            <span class="result-count"><?= count($favourites) ?> saved</span>
        </div>
        <?php if ($favourites === []): ?>
            <div class="favourites-empty"><p>A favourite takes just one little heart.</p><a class="text-link" href="index.php#shop">Find something to love <span aria-hidden="true">↗</span></a></div>
        <?php else: ?>
            <div class="favourites-grid">
                <?php foreach ($favourites as $favourite): ?>
                    <article class="favourite-card">
                        <?php if ((bool) $favourite['is_active']): ?>
                            <a class="favourite-art" href="product.php?id=<?= (int) $favourite['id'] ?>"><img src="<?= e($favourite['image']) ?>" alt=""></a>
                        <?php else: ?>
                            <div class="favourite-art"><img src="<?= e($favourite['image']) ?>" alt=""></div>
                        <?php endif; ?>
                        <div><p class="product-category"><?= e($favourite['category']) ?></p><h3><?php if ((bool) $favourite['is_active']): ?><a href="product.php?id=<?= (int) $favourite['id'] ?>"><?= e($favourite['name']) ?></a><?php else: ?><?= e($favourite['name']) ?> <small>Taking a rest</small><?php endif; ?></h3><strong><?= money((float) $favourite['price']) ?></strong></div>
                        <form method="post">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="action" value="wishlist_remove">
                            <input type="hidden" name="product_id" value="<?= (int) $favourite['id'] ?>">
                            <button class="wishlist-button" type="submit">Remove <span aria-hidden="true">×</span></button>
                        </form>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <section class="account-section security-section" id="security" aria-labelledby="security-title">
        <div class="section-heading account-section-heading">
            <div><p class="section-eyebrow">A LITTLE PEACE OF MIND</p><h2 id="security-title">Your security</h2></div>
            <span class="security-status <?= $user['mfa_enabled'] ? 'is-on' : '' ?>"><?= $user['mfa_enabled'] ? 'Extra protection on' : 'Password protected' ?></span>
        </div>
        <div class="security-card">
            <div class="security-copy">
                <h3>Multi-factor authentication</h3>
                <p>Add a second check from your authenticator app when you sign in. Your code stays on your device.</p>
            </div>
            <div class="security-actions">
                <?php if (!$user['mfa_enabled'] && !is_string($setupSecret)): ?>
                    <form method="post">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="action" value="mfa_setup">
                        <button class="button button-dark" type="submit">Set up MFA <span aria-hidden="true">↗</span></button>
                    </form>
                <?php elseif (!$user['mfa_enabled'] && is_string($setupSecret)): ?>
                    <?php $otpUri = 'otpauth://totp/' . base32_label('CloudCart:' . (string) $user['email']) . '?secret=' . rawurlencode($setupSecret) . '&issuer=CloudCart&algorithm=SHA1&digits=6&period=30'; ?>
                    <div class="setup-secret">
                        <p>In your authenticator app, add CloudCart using this key:</p>
                        <code><?= e(chunk_split($setupSecret, 4, ' ')) ?></code>
                        <p class="setup-uri">Manual setup URI: <a href="<?= e($otpUri) ?>">open in an authenticator app</a></p>
                        <form method="post" class="mfa-confirm-form">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="action" value="mfa_confirm">
                            <label class="form-field"><span>Enter the 6-digit code to confirm</span><input name="mfa_code" inputmode="numeric" pattern="[0-9]{6}" autocomplete="one-time-code" maxlength="6" required></label>
                            <button class="button button-dark" type="submit">Confirm and turn on MFA</button>
                        </form>
                    </div>
                <?php else: ?>
                    <form method="post" class="mfa-disable-form">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="action" value="mfa_disable">
                        <label class="form-field"><span>Password or authenticator code</span><input name="password" type="password" autocomplete="current-password" placeholder="Enter your password or current code"></label>
                        <label class="form-field"><span>Or 6-digit authenticator code</span><input name="mfa_code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" placeholder="000000"></label>
                        <button class="button button-light" type="submit">Turn off MFA</button>
                    </form>
                <?php endif; ?>
                <?php if (entraid_configured() && $oauthSubject === null): ?>
                    <a class="button button-light" href="oauth.php?action=start&amp;link=1">Connect Microsoft sign-in <span aria-hidden="true">↗</span></a>
                <?php elseif ($oauthSubject !== null): ?>
                    <p class="connected-account">Microsoft sign-in is connected to this account.</p>
                <?php endif; ?>
            </div>
        </div>
        <p class="account-email">Signed in as <strong><?= e($user['email']) ?></strong></p>
    </section>
<?php site_shell_end(); ?>
