<?php
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

$serverName = getenv('SQLSRV_SERVER');
$database = getenv('SQLSRV_DATABASE');
$user = getenv('SQLSRV_UID');
$databasePassword = getenv('SQLSRV_PWD');
if (!function_exists('sqlsrv_connect') || !$serverName || !$database || !$user || !$databasePassword) {
    header('Location: register.php?error=unavailable');
    exit();
}

$connectionOptions = [
    'Database' => $database,
    'Uid' => $user,
    'PWD' => $databasePassword,
    'Encrypt' => true,
    'TrustServerCertificate' => false,
];
$connection = sqlsrv_connect($serverName, $connectionOptions);
if ($connection === false) {
    error_log('MyShop registration could not connect to SQL Server.');
    header('Location: register.php?error=unavailable');
    exit();
}

$hashedPassword = password_hash($password, PASSWORD_DEFAULT);
$sql = 'INSERT INTO shopusers (name, email, password) VALUES (?, ?, ?)';
$statement = sqlsrv_query($connection, $sql, [$name, $email, $hashedPassword]);
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