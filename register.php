<?php
$messages = [
    'missing_fields' => 'Please fill in all fields.',
    'invalid_email' => 'Enter a valid email address.',
    'short_password' => 'Your password must be at least 8 characters.',
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
<body class="register-page">
    <header class="header">
        <h1>MyShop</h1>
        <p>Create Your Account</p>
    </header>
    <main class="register-form">
        <h2>Register</h2>
        <?php if ($error !== ''): ?>
            <p class="error" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
        <?php endif; ?>
        <form action="process_register.php" method="post">
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
            <button class="btn" type="submit">Create account</button>
        </form>
        <p class="back-link"><a href="index.php">Back to Home</a></p>
    </main>
</body>
</html>