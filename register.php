<?php
session_start();
$_SESSION['csrf_token'] = bin2hex(random_bytes(32));

$messages = [
    'missing_fields' => 'Please fill in all fields.',
    'invalid_email' => 'Enter a valid email address.',
    'short_password' => 'Your password must be at least 8 characters.',
    'invalid_request' => 'Your form session expired. Please try again.',
    'unavailable' => 'Registration is temporarily unavailable. Please try again later.',
];
$errorKey = $_GET['error'] ?? '';
$error = is_string($errorKey) ? ($messages[$errorKey] ?? '') : '';
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Register - MyShop</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body class="form-page">
    <header class="site-header site-header-fixed">
        <h1>MyShop</h1>
        <p>Create Your Account</p>
    </header>
    <main class="form-panel">
        <h2>Register</h2>
        <?php if ($error !== ''): ?>
            <p class="error" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif; ?>
        <form action="process_register.php" method="post">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token'], ENT_QUOTES, 'UTF-8') ?>">
            <div class="form-group">
                <label for="name">Full Name *</label>
                <input id="name" name="name" type="text" autocomplete="name" required maxlength="255">
            </div>
            <div class="form-group">
                <label for="email">Email *</label>
                <input id="email" name="email" type="email" autocomplete="email" required maxlength="255">
            </div>
            <div class="form-group">
                <label for="password">Password *</label>
                <input id="password" name="password" type="password" autocomplete="new-password" required minlength="8">
            </div>
            <button class="button button-full" type="submit">Create account</button>
        </form>
        <a class="back-link" href="index.php">Back to Home</a>
    </main>
</body>
</html>