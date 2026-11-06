<?php
declare(strict_types=1);

function env_value(string $name, string $fallback = ''): string
{
    $value = getenv($name);
    return $value === false ? $fallback : trim($value);
}

function notify_order_function(array $payload): ?string
{
    $url = env_value('CLOUDCART_ORDER_FUNCTION_URL');
    if ($url === '') {
        return null;
    }

    $key = env_value('CLOUDCART_ORDER_FUNCTION_KEY');
    $urlParts = parse_url($url);
    $isLocalHttp = is_array($urlParts)
        && ($urlParts['scheme'] ?? '') === 'http'
        && in_array(strtolower((string) ($urlParts['host'] ?? '')), ['localhost', '127.0.0.1'], true);
    if (
        $key === ''
        || !filter_var($url, FILTER_VALIDATE_URL)
        || !is_array($urlParts)
        || !in_array($urlParts['scheme'] ?? '', ['https', 'http'], true)
        || (($urlParts['scheme'] ?? '') !== 'https' && !$isLocalHttp)
        || isset($urlParts['user'])
        || isset($urlParts['pass'])
        || isset($urlParts['query'])
        || isset($urlParts['fragment'])
    ) {
        error_log('CloudCart order Function configuration is invalid or incomplete.');
        return 'Your order is saved, but its order notification could not be sent.';
    }
    if (!function_exists('curl_init')) {
        error_log('CloudCart order Function notification requires the PHP cURL extension.');
        return 'Your order is saved, but its order notification could not be sent.';
    }

    $body = json_encode($payload, JSON_THROW_ON_ERROR);
    $request = curl_init($url);
    if ($request === false) {
        error_log('CloudCart could not initialize the order Function request.');
        return 'Your order is saved, but its order notification could not be sent.';
    }
    $configured = curl_setopt_array($request, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $body,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Accept: application/json',
            'x-functions-key: ' . $key,
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 2,
        CURLOPT_TIMEOUT => 5,
    ]);
    if (!$configured) {
        curl_close($request);
        error_log('CloudCart could not configure the order Function request.');
        return 'Your order is saved, but its order notification could not be sent.';
    }

    $response = curl_exec($request);
    $status = (int) curl_getinfo($request, CURLINFO_RESPONSE_CODE);
    $requestError = $response === false ? curl_error($request) : '';
    curl_close($request);
    if ($response === false || $status < 200 || $status >= 300) {
        error_log('CloudCart order Function notification failed: ' . ($requestError !== '' ? $requestError : 'HTTP ' . $status));
        return 'Your order is saved, but its order notification could not be sent.';
    }

    return null;
}

$isHttps = (
    (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https'
);
$redisHost = env_value('REDIS_HOST');
if ($redisHost !== '') {
    if (!extension_loaded('redis')) {
        throw new RuntimeException('Configure the PHP Redis extension before enabling shared CloudCart sessions.');
    }
    $redisPort = filter_var(env_value('REDIS_PORT', '6380'), FILTER_VALIDATE_INT);
    if ($redisPort === false || $redisPort < 1 || $redisPort > 65535) {
        throw new RuntimeException('REDIS_PORT must be a valid port number.');
    }
    $redisScheme = filter_var(env_value('REDIS_TLS', '1'), FILTER_VALIDATE_BOOLEAN) ? 'tls' : 'tcp';
    $redisOptions = ['database=0', 'prefix=cloudcart:'];
    $redisPassword = env_value('REDIS_PASSWORD');
    if ($redisPassword !== '') {
        $redisOptions[] = 'auth=' . rawurlencode($redisPassword);
    }
    $handlerChanged = ini_set('session.save_handler', 'redis');
    $pathChanged = ini_set('session.save_path', $redisScheme . '://' . $redisHost . ':' . $redisPort . '?' . implode('&', $redisOptions));
    ini_set('session.gc_maxlifetime', '7200');
    if ($handlerChanged === false || $pathChanged === false) {
        throw new RuntimeException('The shared CloudCart session handler could not be configured.');
    }
}
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'secure' => $isHttps,
    'httponly' => true,
    'samesite' => 'Lax',
]);
if (session_status() !== PHP_SESSION_ACTIVE) {
    if (!session_start()) {
        throw new RuntimeException('The CloudCart session store could not be reached.');
    }
}

header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('X-Frame-Options: DENY');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
if ($isHttps) {
    header('Strict-Transport-Security: max-age=31536000');
}
header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self'; script-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'");

final class ShopDatabase
{
    private ?PDO $pdo = null;
    private $sqlsrv = null;
    private string $driver;
    private bool $pdoTransactionActive = false;

    public function __construct()
    {
        $configuredDriver = strtolower(env_value('DB_DRIVER', env_value('MYSHOP_DB_DRIVER')));
        $server = env_value('DB_SERVER', env_value('SQLSRV_SERVER'));
        $password = env_value('DB_PASSWORD', env_value('SQLSRV_PWD'));
        $this->driver = $configuredDriver !== ''
            ? $configuredDriver
            : ($server !== '' && $password !== '' ? 'sqlsrv' : 'sqlite');

        if ($this->driver === 'sqlsrv') {
            if (!function_exists('sqlsrv_connect')) {
                throw new RuntimeException('The PHP SQL Server extension is not available.');
            }
            if ($server === '' || $password === '') {
                throw new RuntimeException('Set DB_SERVER and DB_PASSWORD for the Azure SQL connection.');
            }
            $database = env_value('DB_DATABASE', env_value('SQLSRV_DATABASE'));
            $username = env_value('DB_USERNAME', env_value('SQLSRV_UID'));
            if ($database === '' || $username === '') {
                throw new RuntimeException('Set DB_DATABASE and DB_USERNAME for the Azure SQL connection.');
            }
            $options = [
                'Database' => $database,
                'Uid' => $username,
                'PWD' => $password,
                'Encrypt' => true,
                'TrustServerCertificate' => false,
                'LoginTimeout' => 8,
            ];
            $this->sqlsrv = sqlsrv_connect($server, $options);
            if ($this->sqlsrv === false) {
                $this->reportSqlsrvError('Azure SQL connection failed');
                throw new RuntimeException('The store database is temporarily unavailable.');
            }
        } elseif ($this->driver === 'sqlite') {
            if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
                throw new RuntimeException('Enable PDO SQLite for local development or configure Azure SQL.');
            }
            $defaultDataDirectory = env_value('HOME') !== ''
                ? env_value('HOME') . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'cloudcart'
                : __DIR__ . DIRECTORY_SEPARATOR . 'data';
            $dataDirectory = env_value('DATA_DIRECTORY', $defaultDataDirectory);
            if (!is_dir($dataDirectory) && !mkdir($dataDirectory, 0770, true) && !is_dir($dataDirectory)) {
                throw new RuntimeException('The local data directory could not be created.');
            }
            $this->pdo = new PDO('sqlite:' . $dataDirectory . DIRECTORY_SEPARATOR . 'myshop.sqlite');
            $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
            $this->pdo->exec('PRAGMA foreign_keys = ON');
            $this->pdo->exec('PRAGMA busy_timeout = 5000');
        } else {
            throw new RuntimeException('DB_DRIVER must be either sqlsrv or sqlite.');
        }

        $this->ensureSchema();
        $this->seedProducts();
    }

    public function isSqlServer(): bool
    {
        return $this->driver === 'sqlsrv';
    }

    private function reportSqlsrvError(string $context): void
    {
        $errors = function_exists('sqlsrv_errors') ? sqlsrv_errors(SQLSRV_ERR_ALL) : [];
        error_log($context . ': ' . json_encode($errors, JSON_UNESCAPED_SLASHES));
    }

    private function execute(string $sql, array $parameters = [])
    {
        if ($this->pdo !== null) {
            $statement = $this->pdo->prepare($sql);
            $statement->execute($parameters);
            return $statement;
        }

        $statement = sqlsrv_query($this->sqlsrv, $sql, $parameters);
        if ($statement === false) {
            $this->reportSqlsrvError('Azure SQL query failed');
            throw new RuntimeException('The store database request failed.');
        }
        return $statement;
    }

    public function all(string $sql, array $parameters = []): array
    {
        $statement = $this->execute($sql, $parameters);
        if ($statement instanceof PDOStatement) {
            return $statement->fetchAll();
        }

        $rows = [];
        while (($row = sqlsrv_fetch_array($statement, SQLSRV_FETCH_ASSOC)) !== null) {
            if ($row === false) {
                break;
            }
            foreach ($row as $key => $value) {
                if ($value instanceof DateTimeInterface) {
                    $row[$key] = $value->format('Y-m-d H:i:s');
                }
            }
            $rows[] = $row;
        }
        sqlsrv_free_stmt($statement);
        return $rows;
    }

    public function one(string $sql, array $parameters = []): ?array
    {
        $rows = $this->all($sql, $parameters);
        return $rows[0] ?? null;
    }

    public function run(string $sql, array $parameters = []): int
    {
        $statement = $this->execute($sql, $parameters);
        if ($statement instanceof PDOStatement) {
            return $statement->rowCount();
        }
        $affected = sqlsrv_rows_affected($statement);
        sqlsrv_free_stmt($statement);
        return $affected === false ? 0 : $affected;
    }

    public function insert(string $table, array $values): int
    {
        $columns = array_keys($values);
        $columnList = implode(', ', $columns);
        $placeholders = implode(', ', array_fill(0, count($columns), '?'));
        $sql = 'INSERT INTO ' . $table . ' (' . $columnList . ') VALUES (' . $placeholders . ')';

        if ($this->pdo !== null) {
            $this->execute($sql, array_values($values));
            return (int) $this->pdo->lastInsertId();
        }

        $statement = $this->execute(
            'INSERT INTO ' . $table . ' (' . $columnList . ') OUTPUT INSERTED.id VALUES (' . $placeholders . ')',
            array_values($values)
        );
        $row = sqlsrv_fetch_array($statement, SQLSRV_FETCH_ASSOC);
        sqlsrv_free_stmt($statement);
        return (int) ($row['id'] ?? 0);
    }

    public function begin(): void
    {
        if ($this->pdo !== null) {
            $this->pdo->exec('BEGIN IMMEDIATE');
            $this->pdoTransactionActive = true;
            return;
        }
        if (!sqlsrv_begin_transaction($this->sqlsrv)) {
            $this->reportSqlsrvError('Azure SQL transaction could not start');
            throw new RuntimeException('The checkout could not be started.');
        }
    }

    public function commit(): void
    {
        if ($this->pdo !== null) {
            $this->pdo->exec('COMMIT');
            $this->pdoTransactionActive = false;
            return;
        }
        if (!sqlsrv_commit($this->sqlsrv)) {
            $this->reportSqlsrvError('Azure SQL transaction could not be committed');
            throw new RuntimeException('The checkout could not be completed.');
        }
    }

    public function rollback(): void
    {
        if ($this->pdo !== null) {
            if ($this->pdoTransactionActive) {
                $this->pdo->exec('ROLLBACK');
                $this->pdoTransactionActive = false;
            }
            return;
        }
        sqlsrv_rollback($this->sqlsrv);
    }

    private function ensureSchema(): void
    {
        if ($this->pdo !== null) {
            $statements = [
                'CREATE TABLE IF NOT EXISTS users (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, email TEXT NOT NULL COLLATE NOCASE UNIQUE, password_hash TEXT NOT NULL, mfa_secret TEXT NULL, mfa_enabled INTEGER NOT NULL DEFAULT 0, oauth_subject TEXT NULL, is_admin INTEGER NOT NULL DEFAULT 0, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)',
                'CREATE UNIQUE INDEX IF NOT EXISTS ux_users_oauth_subject ON users(oauth_subject) WHERE oauth_subject IS NOT NULL',
                'CREATE TABLE IF NOT EXISTS products (id INTEGER PRIMARY KEY AUTOINCREMENT, name TEXT NOT NULL, description TEXT NOT NULL, category TEXT NOT NULL, price REAL NOT NULL, stock INTEGER NOT NULL DEFAULT 0, image TEXT NOT NULL, is_featured INTEGER NOT NULL DEFAULT 0, is_active INTEGER NOT NULL DEFAULT 1, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP)',
                'CREATE INDEX IF NOT EXISTS ix_products_catalogue ON products(is_active, category, is_featured, id)',
                'CREATE TABLE IF NOT EXISTS orders (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL, status TEXT NOT NULL, total REAL NOT NULL, delivery_fee REAL NOT NULL DEFAULT 0, payment_reference TEXT NOT NULL UNIQUE, delivery_name TEXT NOT NULL, delivery_address TEXT NOT NULL, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, updated_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, FOREIGN KEY (user_id) REFERENCES users(id))',
                'CREATE INDEX IF NOT EXISTS ix_orders_user_created ON orders(user_id, created_at DESC, id DESC)',
                'CREATE TABLE IF NOT EXISTS order_items (id INTEGER PRIMARY KEY AUTOINCREMENT, order_id INTEGER NOT NULL, product_id INTEGER NOT NULL, product_name TEXT NOT NULL, quantity INTEGER NOT NULL, unit_price REAL NOT NULL, FOREIGN KEY (order_id) REFERENCES orders(id), FOREIGN KEY (product_id) REFERENCES products(id))',
                'CREATE TABLE IF NOT EXISTS wishlist_items (id INTEGER PRIMARY KEY AUTOINCREMENT, user_id INTEGER NOT NULL, product_id INTEGER NOT NULL, created_at TEXT NOT NULL DEFAULT CURRENT_TIMESTAMP, UNIQUE (user_id, product_id), FOREIGN KEY (user_id) REFERENCES users(id), FOREIGN KEY (product_id) REFERENCES products(id))',
            ];
            foreach ($statements as $statement) {
                $this->pdo->exec($statement);
            }
            return;
        }

        $statements = [
            "IF OBJECT_ID(N'dbo.users', N'U') IS NULL CREATE TABLE dbo.users (id INT IDENTITY(1,1) NOT NULL PRIMARY KEY, name NVARCHAR(120) NOT NULL, email NVARCHAR(254) NOT NULL, password_hash NVARCHAR(255) NOT NULL, mfa_secret NVARCHAR(64) NULL, mfa_enabled BIT NOT NULL DEFAULT 0, oauth_subject NVARCHAR(160) NULL, is_admin BIT NOT NULL DEFAULT 0, created_at DATETIME2 NOT NULL DEFAULT SYSUTCDATETIME(), CONSTRAINT uq_users_email UNIQUE (email))",
            "IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'ux_users_oauth_subject' AND object_id = OBJECT_ID(N'dbo.users')) CREATE UNIQUE INDEX ux_users_oauth_subject ON dbo.users(oauth_subject) WHERE oauth_subject IS NOT NULL",
            "IF OBJECT_ID(N'dbo.products', N'U') IS NULL CREATE TABLE dbo.products (id INT IDENTITY(1,1) NOT NULL PRIMARY KEY, name NVARCHAR(120) NOT NULL, description NVARCHAR(1000) NOT NULL, category NVARCHAR(60) NOT NULL, price DECIMAL(10,2) NOT NULL, stock INT NOT NULL DEFAULT 0, image NVARCHAR(255) NOT NULL, is_featured BIT NOT NULL DEFAULT 0, is_active BIT NOT NULL DEFAULT 1, created_at DATETIME2 NOT NULL DEFAULT SYSUTCDATETIME())",
            "IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'ix_products_catalogue' AND object_id = OBJECT_ID(N'dbo.products')) CREATE INDEX ix_products_catalogue ON dbo.products(is_active, category, is_featured, id)",
            "IF OBJECT_ID(N'dbo.orders', N'U') IS NULL CREATE TABLE dbo.orders (id BIGINT IDENTITY(1,1) NOT NULL PRIMARY KEY, user_id INT NOT NULL, status NVARCHAR(30) NOT NULL, total DECIMAL(10,2) NOT NULL, delivery_fee DECIMAL(10,2) NOT NULL DEFAULT 0, payment_reference NVARCHAR(40) NOT NULL UNIQUE, delivery_name NVARCHAR(120) NOT NULL, delivery_address NVARCHAR(300) NOT NULL, created_at DATETIME2 NOT NULL DEFAULT SYSUTCDATETIME(), updated_at DATETIME2 NOT NULL DEFAULT SYSUTCDATETIME(), CONSTRAINT fk_orders_user FOREIGN KEY (user_id) REFERENCES dbo.users(id))",
            "IF NOT EXISTS (SELECT 1 FROM sys.indexes WHERE name = N'ix_orders_user_created' AND object_id = OBJECT_ID(N'dbo.orders')) CREATE INDEX ix_orders_user_created ON dbo.orders(user_id, created_at DESC, id DESC)",
            "IF OBJECT_ID(N'dbo.order_items', N'U') IS NULL CREATE TABLE dbo.order_items (id BIGINT IDENTITY(1,1) NOT NULL PRIMARY KEY, order_id BIGINT NOT NULL, product_id INT NOT NULL, product_name NVARCHAR(120) NOT NULL, quantity INT NOT NULL, unit_price DECIMAL(10,2) NOT NULL, CONSTRAINT fk_items_order FOREIGN KEY (order_id) REFERENCES dbo.orders(id), CONSTRAINT fk_items_product FOREIGN KEY (product_id) REFERENCES dbo.products(id))",
            "IF OBJECT_ID(N'dbo.wishlist_items', N'U') IS NULL CREATE TABLE dbo.wishlist_items (id INT IDENTITY(1,1) NOT NULL PRIMARY KEY, user_id INT NOT NULL, product_id INT NOT NULL, created_at DATETIME2 NOT NULL DEFAULT SYSUTCDATETIME(), CONSTRAINT uq_wishlist_user_product UNIQUE (user_id, product_id), CONSTRAINT fk_wishlist_user FOREIGN KEY (user_id) REFERENCES dbo.users(id), CONSTRAINT fk_wishlist_product FOREIGN KEY (product_id) REFERENCES dbo.products(id))",
        ];
        foreach ($statements as $statement) {
            $this->execute($statement);
        }
    }

    private function seedProducts(): void
    {
        $existingProduct = $this->isSqlServer()
            ? $this->one('SELECT TOP 1 id FROM products')
            : $this->one('SELECT id FROM products LIMIT 1');
        if ($existingProduct !== null) {
            return;
        }

        $this->begin();
        $existingProduct = $this->isSqlServer()
            ? $this->one('SELECT TOP 1 id FROM products WITH (UPDLOCK, HOLDLOCK)')
            : $this->one('SELECT id FROM products LIMIT 1');
        if ($existingProduct !== null) {
            $this->commit();
            return;
        }

        $products = [
            ['Aero headphones', 'Immersive sound, all-day comfort and quiet that lets you focus.', 'Audio', 189.00, 24, 'assets/hero-headphones.svg', 1],
            ['Orbit speaker', 'Room-filling sound with a soft touch and a surprisingly small footprint.', 'Audio', 129.00, 18, 'assets/hero-speaker.svg', 0],
            ['Pulse smart watch', 'A little more insight into your day, wrapped around your wrist.', 'Wearables', 219.00, 15, 'assets/hero-watch.svg', 1],
            ['Halo desk light', 'Warm, adjustable light for late-night ideas and early starts.', 'Home', 89.00, 32, 'assets/hero-lamp.svg', 0],
            ['Echo wireless buds', 'Pocket-sized listening with a secure fit and clear calls.', 'Audio', 99.00, 42, 'assets/hero-earbuds.svg', 0],
            ['Frame instant camera', 'Keep the moments you want to hold onto, beautifully.', 'Photography', 149.00, 9, 'assets/hero-camera.svg', 0],
            ['Cloud charging stand', 'One calm place to power up the devices you use every day.', 'Accessories', 59.00, 50, 'assets/hero-speaker.svg', 0],
            ['Studio keyboard', 'A satisfying, quiet typing experience for your best work.', 'Accessories', 109.00, 21, 'assets/hero-keyboard.svg', 0],
        ];
        foreach ($products as $product) {
            $this->insert('products', [
                'name' => $product[0],
                'description' => $product[1],
                'category' => $product[2],
                'price' => $product[3],
                'stock' => $product[4],
                'image' => $product[5],
                'is_featured' => $product[6],
            ]);
        }
        $this->commit();
    }
}

function db(): ShopDatabase
{
    static $database;
    if (!$database instanceof ShopDatabase) {
        $database = new ShopDatabase();
    }
    return $database;
}

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function post_string(string $key): string
{
    $value = $_POST[$key] ?? '';
    return is_string($value) ? $value : '';
}

function redirect(string $location): never
{
    header('Location: ' . $location, true, 303);
    exit;
}

function csrf_token(): string
{
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function verify_csrf(): void
{
    $submitted = $_POST['csrf_token'] ?? '';
    if (!is_string($submitted) || !hash_equals(csrf_token(), $submitted)) {
        http_response_code(400);
        exit('Your session expired. Refresh the page and try again.');
    }
}

function set_flash(string $type, string $message): void
{
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function take_flash(): ?array
{
    $flash = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return is_array($flash) ? $flash : null;
}

function password_hash_secure(string $password): string
{
    $algorithm = defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT;
    $hash = password_hash($password, $algorithm);
    if ($hash === false) {
        throw new RuntimeException('The account password could not be secured.');
    }
    return $hash;
}

function current_user(): ?array
{
    $userId = $_SESSION['user_id'] ?? null;
    if (!is_int($userId) && !ctype_digit((string) $userId)) {
        return null;
    }
    $user = db()->one('SELECT id, name, email, mfa_enabled, is_admin FROM users WHERE id = ?', [(int) $userId]);
    if ($user === null) {
        unset($_SESSION['user_id'], $_SESSION['mfa_verified']);
        return null;
    }
    $adminEmail = strtolower(env_value('ADMIN_EMAIL'));
    if ($adminEmail !== '' && strtolower((string) $user['email']) === $adminEmail && !(bool) $user['is_admin']) {
        db()->run('UPDATE users SET is_admin = 1 WHERE id = ?', [(int) $user['id']]);
        $user['is_admin'] = 1;
    }
    $user['is_admin'] = (bool) $user['is_admin'];
    $user['mfa_enabled'] = (bool) $user['mfa_enabled'];
    $user['mfa_verified'] = !$user['mfa_enabled'] || ($_SESSION['mfa_verified'] ?? false) === true;
    return $user;
}

function require_user(): array
{
    $user = current_user();
    if ($user === null) {
        set_flash('info', 'Sign in to continue to your account.');
        redirect('login.php');
    }
    if (!$user['mfa_verified']) {
        unset($_SESSION['user_id']);
        set_flash('error', 'Complete multi-factor verification before continuing.');
        redirect('login.php');
    }
    return $user;
}

function require_admin(): array
{
    $user = require_user();
    if (!$user['is_admin']) {
        http_response_code(403);
        exit('This page is available to store administrators only.');
    }
    return $user;
}

function products(array $filters = [], bool $includeHidden = false): array
{
    $where = $includeHidden ? '1 = 1' : 'is_active = 1';
    $parameters = [];
    if (isset($filters['category']) && $filters['category'] !== '') {
        $where .= ' AND category = ?';
        $parameters[] = (string) $filters['category'];
    }
    if (isset($filters['search']) && $filters['search'] !== '') {
        $where .= ' AND (name LIKE ? OR description LIKE ? OR category LIKE ?)';
        $search = '%' . (string) $filters['search'] . '%';
        array_push($parameters, $search, $search, $search);
    }
    $sort = match ($filters['sort'] ?? 'featured') {
        'price-low' => 'price ASC, name ASC',
        'price-high' => 'price DESC, name ASC',
        'name' => 'name ASC',
        default => 'is_featured DESC, id ASC',
    };
    $limit = db()->isSqlServer() && !$includeHidden ? 'SELECT TOP 100' : 'SELECT';
    $suffix = db()->isSqlServer() || $includeHidden ? '' : ' LIMIT 100';
    return db()->all($limit . ' id, name, description, category, price, stock, image, is_featured, is_active FROM products WHERE ' . $where . ' ORDER BY ' . $sort . $suffix, $parameters);
}

function product_by_id(int $id): ?array
{
    return db()->one('SELECT id, name, description, category, price, stock, image, is_featured, is_active FROM products WHERE id = ?', [$id]);
}

function cart_items(): array
{
    $cart = $_SESSION['cart'] ?? [];
    if (!is_array($cart)) {
        return [];
    }
    $quantities = [];
    foreach ($cart as $productId => $quantity) {
        $validatedId = filter_var((string) $productId, FILTER_VALIDATE_INT);
        $validatedQuantity = filter_var($quantity, FILTER_VALIDATE_INT);
        if ($validatedId !== false && $validatedId > 0 && $validatedQuantity !== false && $validatedQuantity > 0) {
            $quantities[(int) $validatedId] = (int) $validatedQuantity;
        }
    }
    if ($quantities === []) {
        return [];
    }
    $placeholders = implode(', ', array_fill(0, count($quantities), '?'));
    $products = db()->all(
        'SELECT id, name, description, category, price, stock, image, is_featured, is_active FROM products WHERE id IN (' . $placeholders . ')',
        array_keys($quantities)
    );
    $productsById = [];
    foreach ($products as $product) {
        $productsById[(int) $product['id']] = $product;
    }
    $items = [];
    foreach ($quantities as $productId => $quantity) {
        $product = $productsById[$productId] ?? null;
        if ($product === null || !(bool) $product['is_active'] || (int) $product['stock'] < 1) {
            continue;
        }
        $quantity = max(1, min((int) $quantity, (int) $product['stock']));
        $items[] = [
            'product' => $product,
            'quantity' => $quantity,
            'subtotal' => $quantity * (float) $product['price'],
        ];
    }
    return $items;
}

function cart_count(): int
{
    return array_sum(array_map(static fn(array $item): int => (int) $item['quantity'], cart_items()));
}

function cart_total(array $items): float
{
    return array_sum(array_map(static fn(array $item): float => (float) $item['subtotal'], $items));
}

function money(float $amount): string
{
    return '£' . number_format($amount, 2);
}

function delivery_fee(float $subtotal): float
{
    return $subtotal >= 100 ? 0.0 : 4.95;
}

function totp_secret(): string
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $binary = random_bytes(20);
    $bits = '';
    foreach (str_split($binary) as $byte) {
        $bits .= str_pad(decbin(ord($byte)), 8, '0', STR_PAD_LEFT);
    }
    $secret = '';
    foreach (str_split($bits, 5) as $chunk) {
        if (strlen($chunk) === 5) {
            $secret .= $alphabet[bindec($chunk)];
        }
    }
    return $secret;
}

function totp_code(string $secret, ?int $timestamp = null): string
{
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $secret = strtoupper(rtrim($secret, '='));
    $bits = '';
    foreach (str_split($secret) as $character) {
        $index = strpos($alphabet, $character);
        if ($index === false) {
            return '';
        }
        $bits .= str_pad(decbin($index), 5, '0', STR_PAD_LEFT);
    }
    $binary = '';
    foreach (str_split($bits, 8) as $byte) {
        if (strlen($byte) === 8) {
            $binary .= chr(bindec($byte));
        }
    }
    $counter = (int) floor(($timestamp ?? time()) / 30);
    $counterBytes = pack('N2', ($counter >> 32) & 0xFFFFFFFF, $counter & 0xFFFFFFFF);
    $hash = hash_hmac('sha1', $counterBytes, $binary, true);
    $offset = ord($hash[19]) & 0x0F;
    $number = unpack('N', substr($hash, $offset, 4))[1] & 0x7FFFFFFF;
    return str_pad((string) ($number % 1000000), 6, '0', STR_PAD_LEFT);
}

function verify_totp(string $secret, string $code): bool
{
    if (!preg_match('/^\d{6}$/', $code)) {
        return false;
    }
    foreach ([-30, 0, 30] as $drift) {
        if (hash_equals(totp_code($secret, time() + $drift), $code)) {
            return true;
        }
    }
    return false;
}

function base32_label(string $value): string
{
    return rawurlencode($value);
}

function entraid_configured(): bool
{
    return env_value('ENTRA_CLIENT_ID') !== ''
        && env_value('ENTRA_TENANT_ID') !== ''
        && env_value('ENTRA_CLIENT_SECRET') !== ''
        && env_value('ENTRA_REDIRECT_URI') !== '';
}

function oauth_redirect_uri(): string
{
    return env_value('ENTRA_REDIRECT_URI');
}

function site_shell_start(string $title, string $pageClass = ''): void
{
    $user = current_user();
    $flash = take_flash();
    $cartCount = cart_count();
    ?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f5f5f7">
    <title><?= e($title) ?> · CloudCart</title>
    <link rel="stylesheet" href="styles.css">
    <script src="store.js" defer></script>
</head>
<body class="store-page <?= e($pageClass) ?>">
    <a class="skip-link" href="#main-content">Skip to content</a>
    <header class="store-header">
        <a class="store-brand" href="index.php" aria-label="CloudCart home">cloud<span>cart</span><i aria-hidden="true">.</i></a>
        <button class="mobile-menu-toggle" type="button" aria-expanded="false" aria-controls="primary-navigation">Menu</button>
        <nav class="store-nav" id="primary-navigation" aria-label="Main navigation">
            <a href="index.php#shop">Shop</a>
            <a href="index.php#story">Our approach</a>
            <?php if ($user !== null): ?>
                <a href="account.php">Your orders</a>
                <?php if ($user['is_admin']): ?><a href="admin.php">Manage store</a><?php endif; ?>
            <?php else: ?>
                <a href="login.php">Sign in</a>
            <?php endif; ?>
        </nav>
        <div class="header-actions">
            <?php if ($user !== null): ?>
                <span class="header-greeting">Hi, <?= e(explode(' ', (string) $user['name'])[0]) ?></span>
                <form action="logout.php" method="post" class="inline-form">
                    <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                    <button class="text-button" type="submit">Sign out</button>
                </form>
            <?php else: ?>
                <a class="header-signin" href="register.php">Join us</a>
            <?php endif; ?>
            <a class="bag-link" href="cart.php" aria-label="Shopping bag, <?= $cartCount ?> items">
                Bag <span class="bag-count"><?= $cartCount ?></span>
            </a>
        </div>
    </header>
    <?php if ($flash !== null): ?>
        <div class="flash flash-<?= e($flash['type'] ?? 'info') ?>" role="<?= ($flash['type'] ?? '') === 'error' ? 'alert' : 'status' ?>">
            <?= e($flash['message'] ?? '') ?>
        </div>
    <?php endif; ?>
    <main id="main-content">
<?php
}

function site_shell_end(): void
{
    ?>
    </main>
    <footer class="store-footer">
        <a class="store-brand footer-logo" href="index.php">cloud<span>cart</span><i aria-hidden="true">.</i></a>
        <p>Thoughtful tech, made for everyday.</p>
        <span>Demo checkout · No real payment is taken</span>
    </footer>
</body>
</html>
<?php
}
