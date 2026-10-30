<?php
$serverName = 'tcp:mycardiffmet-caleb.database.windows.net,1433';
$connectionOptions = [
    'Database' => 'myDatabase',
    'Uid' => 'myadmin',
    'PWD' => getenv('DB_PASSWORD') ?: '',
    'Encrypt' => true,
    'TrustServerCertificate' => false,
];
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: register.php');
    exit();
}

$submittedToken = $_POST['csrf_token'] ?? '';
$sessionToken = $_SESSION['csrf_token'] ?? '';
unset($_SESSION['csrf_token']);
if (!is_string($submittedToken) || !is_string($sessionToken) || $sessionToken === '' || !hash_equals($sessionToken, $submittedToken)) {
    header('Location: register.php?error=invalid_request');
    exit();
}

$name = $_POST['name'] ?? null;
$email = $_POST['email'] ?? null;
$password = $_POST['password'] ?? '';
if (!is_string($name) || !is_string($email) || !is_string($password)) {
    header('Location: register.php?error=missing_fields');
    exit();
}

$name = trim($name);
$email = trim($email);
if ($name === '' || $email === '' || $password === '') {
    header('Location: register.php?error=missing_fields');
    exit();
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    header('Location: register.php?error=invalid_email');
    exit();
}
if (strlen($password) < 8) {
    header('Location: register.php?error=short_password');
    exit();
}

$hashedPassword = password_hash($password, PASSWORD_DEFAULT);
if (!function_exists('sqlsrv_connect') || $connectionOptions['PWD'] === '') {
    error_log('MyShop registration requires the SQL Server driver and DB_PASSWORD setting.');
    header('Location: register.php?error=unavailable');
    exit();
}

$connection = sqlsrv_connect($serverName, $connectionOptions);
if ($connection === false) {
    error_log('MyShop registration could not connect to SQL Server.');
    header('Location: register.php?error=unavailable');
    exit();
}

$statement = sqlsrv_query($connection, 'INSERT INTO shopusers (name, email, password) VALUES (?, ?, ?)', [$name, $email, $hashedPassword]);
if ($statement === false) {
    error_log('MyShop registration could not insert a user into SQL Server.');
    sqlsrv_close($connection);
    header('Location: register.php?error=unavailable');
    exit();
}

sqlsrv_free_stmt($statement);
sqlsrv_close($connection);
header('Location: success.php');
exit();
