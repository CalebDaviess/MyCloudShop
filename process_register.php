<?php
// Simple database connection 
$serverName = "tcp:mycardiffmet-caleb.database.windows.net,1433"; $connectionOptions = array( 
"Database" => "myDatabase (mycardiffmet-caleb/myDatabase)", 
"Uid" => "myadmin", 
"PWD" => "Flapjack200311!", 
"Encrypt" => 1, 
"TrustServerCertificate" => 0 
);
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
$isAzureAppService = getenv('WEBSITE_SITE_NAME') !== false;
$driver = getenv('MYSHOP_DB_DRIVER') ?: ($isAzureAppService ? 'sqlsrv' : 'sqlite');

try {
    if ($driver === 'sqlite') {
        $databasePath = getenv('MYSHOP_DB_PATH') ?: __DIR__ . '/data/myshop.sqlite';
        $databaseDirectory = dirname($databasePath);
        if (!is_dir($databaseDirectory) && !mkdir($databaseDirectory, 0770, true) && !is_dir($databaseDirectory)) {
            throw new RuntimeException('Could not create the SQLite database directory.');
        }

        $connection = new PDO('sqlite:' . $databasePath);
        $connection->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $connection->exec('CREATE TABLE IF NOT EXISTS shopusers (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            email TEXT NOT NULL UNIQUE,
            password TEXT NOT NULL,
            created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP
        )');
        $statement = $connection->prepare('INSERT INTO shopusers (name, email, password) VALUES (?, ?, ?)');
        $statement->execute([$name, $email, $hashedPassword]);
    } elseif ($driver === 'sqlsrv') {
        $serverName = getenv('SQLSRV_SERVER');
        $database = getenv('SQLSRV_DATABASE');
        $user = getenv('SQLSRV_UID');
        $databasePassword = getenv('SQLSRV_PWD');
        if (!function_exists('sqlsrv_connect') || !$serverName || !$database || !$user || !$databasePassword) {
            throw new RuntimeException('SQL Server driver or configuration is missing.');
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
            throw new RuntimeException('Could not connect to SQL Server.');
        }

        $statement = sqlsrv_query($connection, 'INSERT INTO shopusers (name, email, password) VALUES (?, ?, ?)', [$name, $email, $hashedPassword]);
        if ($statement === false) {
            sqlsrv_close($connection);
            throw new RuntimeException('Could not insert a user into SQL Server.');
        }
        sqlsrv_free_stmt($statement);
        sqlsrv_close($connection);
    } else {
        throw new RuntimeException('Unsupported database driver.');
    }
} catch (Throwable $exception) {
    error_log('MyShop registration database error: ' . $exception->getMessage());
    header('Location: register.php?error=unavailable');
    exit();
}

header('Location: success.php');
exit();
