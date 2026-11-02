<?php
$serverName = getenv('DB_SERVER') ?: (getenv('SQLSRV_SERVER') ?: 'tcp:mycardiffmet-caleb.database.windows.net,1433');
$connectionOptions = [
    'Database' => getenv('DB_DATABASE') ?: (getenv('SQLSRV_DATABASE') ?: 'myDatabase'),
    'Uid' => getenv('DB_USERNAME') ?: (getenv('SQLSRV_UID') ?: 'myadmin'),
    'PWD' => getenv('DB_PASSWORD') ?: (getenv('SQLSRV_PWD') ?: ''),
    'Encrypt' => true,
    'TrustServerCertificate' => false,
];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: register.php');
    exit();
}

$name = $_POST['name'] ?? '';
$email = $_POST['email'] ?? '';
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

if (!function_exists('sqlsrv_connect')) {
    error_log('MyShop registration unavailable: the PHP sqlsrv extension is not loaded.');
    header('Location: register.php?error=unavailable');
    exit();
}
if ($connectionOptions['PWD'] === '') {
    error_log('MyShop registration unavailable: neither DB_PASSWORD nor SQLSRV_PWD is set.');
    header('Location: register.php?error=unavailable');
    exit();
}

$hashedPassword = password_hash($password, PASSWORD_DEFAULT);
$connection = sqlsrv_connect($serverName, $connectionOptions);
if ($connection === false) {
    error_log('MyShop registration could not connect to SQL Server: ' . json_encode(sqlsrv_errors(SQLSRV_ERR_ALL)));
    header('Location: register.php?error=unavailable');
    exit();
}

$statement = sqlsrv_query(
    $connection,
    'INSERT INTO shopusers (name, email, password) VALUES (?, ?, ?)',
    [$name, $email, $hashedPassword]
);
if ($statement === false) {
    error_log('MyShop registration could not insert a user into SQL Server: ' . json_encode(sqlsrv_errors(SQLSRV_ERR_ALL)));
    sqlsrv_close($connection);
    header('Location: register.php?error=unavailable');
    exit();
}

sqlsrv_free_stmt($statement);
sqlsrv_close($connection);
header('Location: success.php');
exit();
