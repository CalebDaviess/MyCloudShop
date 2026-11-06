<?php
require_once __DIR__ . '/app.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('register.php');
}
verify_csrf();

$name = $_POST['name'] ?? '';
$email = $_POST['email'] ?? '';
$password = $_POST['password'] ?? '';
if (!is_string($name) || !is_string($email) || !is_string($password)) {
    set_flash('error', 'Please check the details you entered and try again.');
    redirect('register.php');
}

$name = trim($name);
$email = strtolower(trim($email));
if ($name === '' || strlen($name) > 120 || filter_var($email, FILTER_VALIDATE_EMAIL) === false || strlen($email) > 254) {
    set_flash('error', 'Add your name and a valid email address to continue.');
    redirect('register.php');
}
if (strlen($password) < 12 || strlen($password) > 1024) {
    set_flash('error', 'Choose a password with at least 12 characters.');
    redirect('register.php');
}
if (db()->one('SELECT id FROM users WHERE email = ?', [$email]) !== null) {
    set_flash('error', 'There is already an account with that email. Try signing in instead.');
    redirect('login.php');
}

$adminEmail = strtolower(env_value('ADMIN_EMAIL'));
$userId = db()->insert('users', [
    'name' => $name,
    'email' => $email,
    'password_hash' => password_hash_secure($password),
    'is_admin' => $adminEmail !== '' && $email === $adminEmail ? 1 : 0,
]);

session_regenerate_id(true);
$_SESSION['user_id'] = $userId;
$_SESSION['mfa_verified'] = true;
unset($_SESSION['csrf_token']);
set_flash('success', 'Your account is ready. Welcome to CloudCart.');
redirect('account.php');
