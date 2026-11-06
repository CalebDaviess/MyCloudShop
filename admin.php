<?php
require_once __DIR__ . '/app.php';
$user = require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = post_string('action');
    $productId = filter_var($_POST['product_id'] ?? null, FILTER_VALIDATE_INT);

    if (($action === 'archive' || $action === 'restore') && $productId !== false && $productId !== null) {
        db()->run('UPDATE products SET is_active = ? WHERE id = ?', [$action === 'restore' ? 1 : 0, (int) $productId]);
        set_flash('success', $action === 'archive' ? 'The item has been tucked away.' : 'The item is back in the collection.');
        redirect('admin.php');
    }

    if ($action === 'order_status') {
        $orderId = filter_var($_POST['order_id'] ?? null, FILTER_VALIDATE_INT);
        $status = post_string('status');
        $allowedStatuses = ['Order received', 'Being prepared', 'On its way', 'Delivered'];
        if ($orderId !== false && $orderId !== null && is_string($status) && in_array($status, $allowedStatuses, true)) {
            $updatedAt = db()->isSqlServer() ? 'SYSUTCDATETIME()' : 'CURRENT_TIMESTAMP';
            db()->run('UPDATE orders SET status = ?, updated_at = ' . $updatedAt . ' WHERE id = ?', [$status, (int) $orderId]);
            set_flash('success', 'The order tracking status has been updated.');
            redirect('admin.php#orders');
        }
        set_flash('error', 'Choose a valid order and tracking status.');
        redirect('admin.php#orders');
    }

    if ($action === 'save') {
        $name = trim(post_string('name'));
        $description = trim(post_string('description'));
        $category = trim(post_string('category'));
        $price = filter_var($_POST['price'] ?? null, FILTER_VALIDATE_FLOAT);
        $stock = filter_var($_POST['stock'] ?? null, FILTER_VALIDATE_INT);
        $image = post_string('image');
        $productId = $productId === false ? null : $productId;

        if (
            $name === '' || strlen($name) > 120
            || $description === '' || strlen($description) > 1000
            || $category === '' || strlen($category) > 60
            || $price === false || $price <= 0 || $price > 99999
            || $stock === false || $stock < 0 || $stock > 1000000
            || !preg_match('#^assets/[a-zA-Z0-9._-]+\.svg$#', $image)
            || !is_file(__DIR__ . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $image))
        ) {
            set_flash('error', 'Check the product details and choose an image from the available collection.');
            redirect('admin.php');
        }

        $values = [
            'name' => $name,
            'description' => $description,
            'category' => $category,
            'price' => round((float) $price, 2),
            'stock' => (int) $stock,
            'image' => $image,
            'is_featured' => isset($_POST['is_featured']) ? 1 : 0,
        ];
        if ($productId !== null && $productId > 0) {
            db()->run(
                'UPDATE products SET name = ?, description = ?, category = ?, price = ?, stock = ?, image = ?, is_featured = ? WHERE id = ?',
                [...array_values($values), (int) $productId]
            );
            set_flash('success', 'Your product details have been saved.');
        } else {
            db()->insert('products', $values);
            set_flash('success', 'Your new find is in the collection.');
        }
        redirect('admin.php');
    }

    set_flash('error', 'That request couldn’t be completed. Please try again.');
    redirect('admin.php');
}

$catalogue = products([], true);
$orderLimit = db()->isSqlServer() ? 'SELECT TOP 30' : 'SELECT';
$orderSuffix = db()->isSqlServer() ? '' : ' LIMIT 30';
$orders = db()->all(
    $orderLimit . ' o.id, o.status, o.total, o.payment_reference, o.created_at, o.delivery_name, o.delivery_address, u.email FROM orders o JOIN users u ON u.id = o.user_id ORDER BY o.created_at DESC, o.id DESC' . $orderSuffix
);
$images = [
    'assets/hero-headphones.svg',
    'assets/hero-speaker.svg',
    'assets/hero-watch.svg',
    'assets/hero-lamp.svg',
    'assets/hero-camera.svg',
    'assets/hero-earbuds.svg',
    'assets/hero-keyboard.svg',
];
site_shell_start('Store studio', 'admin-page');
?>
    <section class="page-intro admin-intro">
        <p class="section-eyebrow">THE CLOUDCART STUDIO</p>
        <h1>A good thing<br>starts here<span class="title-dot">.</span></h1>
        <p>Look after the collection, keep stock up to date, and make room for something new.</p>
        <span class="admin-badge">Store administrator</span>
    </section>

    <section class="admin-section" aria-labelledby="create-product-title">
        <div class="section-heading account-section-heading">
            <div><p class="section-eyebrow">ADD SOMETHING LOVELY</p><h2 id="create-product-title">New to the collection</h2></div>
        </div>
        <form method="post" class="admin-product-form">
            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="save">
            <label class="form-field"><span>Name</span><input name="name" maxlength="120" required></label>
            <label class="form-field"><span>Category</span><input name="category" maxlength="60" placeholder="Audio, Home…" required></label>
            <label class="form-field"><span>Price (GBP)</span><input name="price" type="number" min="0.01" max="99999" step="0.01" required></label>
            <label class="form-field"><span>Available stock</span><input name="stock" type="number" min="0" max="1000000" step="1" required></label>
            <label class="form-field admin-wide"><span>A few words about it</span><textarea name="description" rows="3" maxlength="1000" required></textarea></label>
            <label class="form-field"><span>Product artwork</span><select name="image" required><?php foreach ($images as $image): ?><option value="<?= e($image) ?>"><?= e(str_replace(['assets/hero-', '.svg'], '', $image)) ?></option><?php endforeach; ?></select></label>
            <label class="check-field"><input type="checkbox" name="is_featured" value="1"> <span>Make this one of our favourites</span></label>
            <button class="button button-dark" type="submit">Add to the collection <span aria-hidden="true">↗</span></button>
        </form>
    </section>

    <section class="admin-section" id="orders" aria-labelledby="admin-orders-title">
        <div class="section-heading account-section-heading">
            <div><p class="section-eyebrow">KEEP EVERYONE IN THE LOOP</p><h2 id="admin-orders-title">Recent orders</h2></div>
            <span class="result-count"><?= count($orders) ?> latest</span>
        </div>
        <?php if ($orders === []): ?>
            <div class="favourites-empty"><p>Your first demo order will show up here.</p></div>
        <?php else: ?>
            <div class="admin-order-list">
                <?php foreach ($orders as $order): ?>
                    <article class="admin-order-card">
                        <div>
                            <p class="product-category">ORDER CC-<?= e(str_pad((string) $order['id'], 6, '0', STR_PAD_LEFT)) ?> · <?= e(date('j M Y', strtotime((string) $order['created_at']))) ?></p>
                            <h3><?= e($order['delivery_name']) ?> <span>· <?= e($order['email']) ?></span></h3>
                            <p class="admin-delivery"><?= e($order['delivery_address']) ?></p>
                            <p class="admin-delivery">Demo payment <?= e($order['payment_reference']) ?> · <?= money((float) $order['total']) ?></p>
                        </div>
                        <form method="post" class="admin-status-form">
                            <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="action" value="order_status">
                            <input type="hidden" name="order_id" value="<?= (int) $order['id'] ?>">
                            <label class="form-field"><span>Tracking status</span><select name="status"><?php foreach (['Order received', 'Being prepared', 'On its way', 'Delivered'] as $status): ?><option value="<?= e($status) ?>" <?= $order['status'] === $status ? 'selected' : '' ?>><?= e($status) ?></option><?php endforeach; ?></select></label>
                            <button class="button button-dark" type="submit">Update status</button>
                        </form>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <section class="admin-section" aria-labelledby="catalogue-title">
        <div class="section-heading account-section-heading">
            <div><p class="section-eyebrow">THE GOOD STUFF, ALL IN ONE PLACE</p><h2 id="catalogue-title">Your collection</h2></div>
            <span class="result-count"><?= count($catalogue) ?> items</span>
        </div>
        <div class="admin-product-list">
            <?php foreach ($catalogue as $product): ?>
                <article class="admin-product-card <?= !(bool) $product['is_active'] ? 'is-archived' : '' ?>">
                    <img src="<?= e($product['image']) ?>" alt="" loading="lazy">
                    <form method="post" class="admin-edit-form">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="action" value="save">
                        <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
                        <label class="form-field"><span>Name</span><input name="name" value="<?= e($product['name']) ?>" maxlength="120" required></label>
                        <label class="form-field"><span>Category</span><input name="category" value="<?= e($product['category']) ?>" maxlength="60" required></label>
                        <label class="form-field"><span>Price (GBP)</span><input name="price" type="number" min="0.01" step="0.01" value="<?= e($product['price']) ?>" required></label>
                        <label class="form-field"><span>Stock</span><input name="stock" type="number" min="0" max="1000000" step="1" value="<?= (int) $product['stock'] ?>" required></label>
                        <label class="form-field admin-wide"><span>Description</span><textarea name="description" rows="2" maxlength="1000" required><?= e($product['description']) ?></textarea></label>
                        <label class="form-field"><span>Artwork</span><select name="image"><?php foreach ($images as $image): ?><option value="<?= e($image) ?>" <?= $image === $product['image'] ? 'selected' : '' ?>><?= e(str_replace(['assets/hero-', '.svg'], '', $image)) ?></option><?php endforeach; ?></select></label>
                        <label class="check-field"><input type="checkbox" name="is_featured" value="1" <?= (bool) $product['is_featured'] ? 'checked' : '' ?>> <span>Featured favourite</span></label>
                        <div class="admin-product-actions">
                            <button class="button button-dark" type="submit">Save changes</button>
                            <span class="admin-availability"><?= (bool) $product['is_active'] ? 'In the collection' : 'Tucked away' ?></span>
                        </div>
                    </form>
                    <form method="post" class="archive-form">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="product_id" value="<?= (int) $product['id'] ?>">
                        <input type="hidden" name="action" value="<?= (bool) $product['is_active'] ? 'archive' : 'restore' ?>">
                        <button class="text-button" type="submit"><?= (bool) $product['is_active'] ? 'Tuck away' : 'Bring back' ?></button>
                    </form>
                </article>
            <?php endforeach; ?>
        </div>
    </section>
<?php site_shell_end(); ?>
