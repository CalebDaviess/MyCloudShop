<?php
require_once __DIR__ . '/app.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = post_string('action');
    $productId = filter_var($_POST['product_id'] ?? null, FILTER_VALIDATE_INT);

    if ($action === 'add' && $productId !== false && $productId !== null) {
        $product = product_by_id((int) $productId);
        if ($product === null || !(bool) $product['is_active'] || (int) $product['stock'] < 1) {
            set_flash('error', 'That item isn’t available right now.');
            redirect('index.php#shop');
        }
        $cart = $_SESSION['cart'] ?? [];
        $cart[(string) $productId] = min((int) ($cart[(string) $productId] ?? 0) + 1, (int) $product['stock']);
        $_SESSION['cart'] = $cart;
        set_flash('success', $product['name'] . ' is in your bag.');
        redirect('cart.php');
    }

    if (($action === 'update' || $action === 'remove') && $productId !== false && $productId !== null) {
        $cart = $_SESSION['cart'] ?? [];
        if ($action === 'remove') {
            unset($cart[(string) $productId]);
        } else {
            $quantity = filter_var($_POST['quantity'] ?? null, FILTER_VALIDATE_INT);
            $product = product_by_id((int) $productId);
            if ($quantity === false || $quantity < 1 || $product === null) {
                unset($cart[(string) $productId]);
            } else {
                $cart[(string) $productId] = min($quantity, max(1, (int) $product['stock']));
            }
        }
        $_SESSION['cart'] = $cart;
        set_flash('success', 'Your bag has been updated.');
        redirect('cart.php');
    }

    if ($action === 'checkout') {
        $user = require_user();
        $deliveryName = trim(post_string('delivery_name'));
        $deliveryAddress = trim(post_string('delivery_address'));
        if ($deliveryName === '' || strlen($deliveryName) > 120 || $deliveryAddress === '' || strlen($deliveryAddress) > 300) {
            set_flash('error', 'Add a name and delivery address to complete your demo order.');
            redirect('cart.php#checkout');
        }
        $items = cart_items();
        if ($items === []) {
            set_flash('error', 'Your bag is empty. Find something lovely first.');
            redirect('index.php#shop');
        }

        $database = db();
        $database->begin();
        $lockedItems = [];
        foreach ($items as $item) {
            $productId = (int) $item['product']['id'];
            $productQuery = $database->isSqlServer()
                ? 'SELECT id, name, price, stock FROM products WITH (UPDLOCK, ROWLOCK) WHERE id = ? AND is_active = 1'
                : 'SELECT id, name, price, stock FROM products WHERE id = ? AND is_active = 1';
            $product = $database->one($productQuery, [$productId]);
            if ($product === null || (int) $product['stock'] < (int) $item['quantity']) {
                $database->rollback();
                set_flash('error', 'An item in your bag just changed availability. Please review your bag.');
                redirect('cart.php');
            }
            $lockedItems[] = [
                'product' => $product,
                'quantity' => (int) $item['quantity'],
                'subtotal' => (int) $item['quantity'] * (float) $product['price'],
            ];
        }

        usort($lockedItems, static fn(array $left, array $right): int => (int) $left['product']['id'] <=> (int) $right['product']['id']);
        $subtotal = round(cart_total($lockedItems), 2);
        $deliveryFee = delivery_fee($subtotal);
        $total = round($subtotal + $deliveryFee, 2);
        $paymentReference = 'DEMO-' . strtoupper(bin2hex(random_bytes(5)));
        $orderId = $database->insert('orders', [
            'user_id' => (int) $user['id'],
            'status' => 'Order received',
            'total' => $total,
            'delivery_fee' => $deliveryFee,
            'payment_reference' => $paymentReference,
            'delivery_name' => $deliveryName,
            'delivery_address' => $deliveryAddress,
        ]);
        foreach ($lockedItems as $item) {
            $product = $item['product'];
            $updated = $database->run(
                'UPDATE products SET stock = stock - ? WHERE id = ? AND stock >= ?',
                [$item['quantity'], (int) $product['id'], $item['quantity']]
            );
            if ($updated !== 1) {
                $database->rollback();
                set_flash('error', 'An item in your bag just changed availability. Please try again.');
                redirect('cart.php');
            }
            $database->insert('order_items', [
                'order_id' => $orderId,
                'product_id' => (int) $product['id'],
                'product_name' => (string) $product['name'],
                'quantity' => $item['quantity'],
                'unit_price' => (float) $product['price'],
            ]);
        }
        $database->commit();
        unset($_SESSION['cart']);
        redirect('success.php?order=' . $orderId);
    }

    set_flash('error', 'That request couldn’t be completed. Please try again.');
    redirect('cart.php');
}

$items = cart_items();
$subtotal = round(cart_total($items), 2);
$deliveryFee = delivery_fee($subtotal);
$total = round($subtotal + $deliveryFee, 2);
site_shell_start('Your bag', 'cart-page');
?>
    <section class="page-intro">
        <p class="section-eyebrow">YOUR LITTLE COLLECTION</p>
        <h1>Your bag<span class="title-dot">.</span></h1>
        <p>The good things you’ve found so far.</p>
    </section>

    <?php if ($items === []): ?>
        <section class="empty-state cart-empty">
            <span class="empty-symbol" aria-hidden="true">♡</span>
            <h2>Nothing in your bag. Yet.</h2>
            <p>Have a look around. Your next favourite might be closer than you think.</p>
            <a class="button button-dark" href="index.php#shop">Explore the collection <span aria-hidden="true">↗</span></a>
        </section>
    <?php else: ?>
        <div class="checkout-layout">
            <section class="bag-panel" aria-labelledby="bag-title">
                <h2 class="panel-heading" id="bag-title"><?= count($items) ?> <?= count($items) === 1 ? 'good find' : 'good finds' ?></h2>
                <?php foreach ($items as $item): ?>
                    <?php $product = $item['product']; ?>
                    <article class="bag-item">
                        <a class="bag-item-art" href="product.php?id=<?= (int) $product['id'] ?>">
                            <img src="<?= e($product['image']) ?>" alt="">
                        </a>
                        <div class="bag-item-details">
                            <p class="product-category"><?= e($product['category']) ?></p>
                            <h3><a href="product.php?id=<?= (int) $product['id'] ?>"><?= e($product['name']) ?></a></h3>
                            <p class="bag-unit-price"><?= money((float) $product['price']) ?> each</p>
                            <div class="bag-item-controls">
                                <form action="cart.php" method="post" class="quantity-form">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="action" value="update">
                                    <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
                                    <label for="quantity-<?= (int) $product['id'] ?>">Qty</label>
                                    <input id="quantity-<?= (int) $product['id'] ?>" name="quantity" type="number" min="1" max="<?= (int) $product['stock'] ?>" value="<?= (int) $item['quantity'] ?>">
                                    <button class="text-button" type="submit">Update</button>
                                </form>
                                <form action="cart.php" method="post">
                                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                                    <input type="hidden" name="action" value="remove">
                                    <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
                                    <button class="text-button" type="submit">Remove</button>
                                </form>
                            </div>
                        </div>
                        <p class="bag-line-total"><?= money((float) $item['subtotal']) ?></p>
                    </article>
                <?php endforeach; ?>
                <a class="continue-link" href="index.php#shop"><span aria-hidden="true">←</span> Keep looking</a>
            </section>

            <aside class="checkout-panel" id="checkout" aria-labelledby="checkout-title">
                <p class="section-eyebrow">JUST A FEW DETAILS</p>
                <h2 id="checkout-title">Make it yours.</h2>
                <div class="checkout-breakdown">
                    <p><span>Your finds</span><strong><?= money($subtotal) ?></strong></p>
                    <p><span>Delivery</span><strong><?= $deliveryFee === 0.0 ? 'On us' : money($deliveryFee) ?></strong></p>
                </div>
                <div class="checkout-total"><span>Your total</span><strong><?= money($total) ?></strong></div>
                <p class="delivery-note"><?= $deliveryFee === 0.0 ? 'Lovely — delivery is on us.' : 'Add ' . money(100 - $subtotal) . ' more for delivery on us.' ?></p>
                <?php if (current_user() === null): ?>
                    <p class="signin-note">Sign in or create an account to place your order and follow its journey.</p>
                    <a class="button button-dark button-full" href="login.php">Sign in to continue <span aria-hidden="true">↗</span></a>
                    <a class="checkout-register" href="register.php">New here? Create your free account</a>
                <?php else: ?>
                    <form action="cart.php" method="post" class="checkout-form">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="action" value="checkout">
                        <label class="form-field">
                            <span>Name for delivery</span>
                            <input name="delivery_name" type="text" autocomplete="name" maxlength="120" value="<?= e(current_user()['name']) ?>" required>
                        </label>
                        <label class="form-field">
                            <span>Delivery address</span>
                            <textarea name="delivery_address" autocomplete="street-address" rows="3" maxlength="300" required placeholder="House number, street, town and postcode"></textarea>
                        </label>
                        <div class="demo-payment">
                            <span class="demo-payment-icon" aria-hidden="true">✓</span>
                            <span><strong>Demo payment</strong><small>No card details. No money taken.</small></span>
                            <span class="demo-tag">SIMULATED</span>
                        </div>
                        <button class="button button-dark button-full" type="submit">Place demo order <span aria-hidden="true">↗</span></button>
                        <p class="checkout-legal">This demonstration records a simulated order only. It does not charge a payment method.</p>
                    </form>
                <?php endif; ?>
                <p class="checkout-reassurance"><span aria-hidden="true">↗</span> Secure sign-in <span aria-hidden="true">✳</span> Clear order updates</p>
            </aside>
        </div>
    <?php endif; ?>
<?php site_shell_end(); ?>
