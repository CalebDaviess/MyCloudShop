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
$redisConfigured = false;
$redisHost = env_value('REDIS_HOST');
if ($redisHost !== '') {
    $redisPort = filter_var(env_value('REDIS_PORT', '6380'), FILTER_VALIDATE_INT);
    if (!extension_loaded('redis')) {
        error_log('CloudCart Redis session skipped: the PHP Redis extension is not loaded.');
    } elseif ($redisPort === false || $redisPort < 1 || $redisPort > 65535) {
        error_log('CloudCart Redis session skipped: REDIS_PORT must be a valid port number.');
    } else {
        $redisScheme = filter_var(env_value('REDIS_TLS', '1'), FILTER_VALIDATE_BOOLEAN) ? 'tls' : 'tcp';
        $redisOptions = ['database=0', 'prefix=cloudcart:'];
        $redisPassword = env_value('REDIS_PASSWORD');
        if ($redisPassword !== '') {
            $redisOptions[] = 'auth=' . rawurlencode($redisPassword);
        }
        $handlerChanged = @ini_set('session.save_handler', 'redis');
        $pathChanged = @ini_set('session.save_path', $redisScheme . '://' . $redisHost . ':' . $redisPort . '?' . implode('&', $redisOptions));
        @ini_set('session.gc_maxlifetime', '7200');
        $redisConfigured = $handlerChanged !== false && $pathChanged !== false;
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
    if (!$redisConfigured) {
        $currentSavePath = session_save_path();
        if ($currentSavePath === '' || !is_dir($currentSavePath) || !is_writable($currentSavePath)) {
            $fallbackSessionDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'cloudcart_sessions';
            if (!is_dir($fallbackSessionDir)) {
                @mkdir($fallbackSessionDir, 0770, true);
            }
            if (is_dir($fallbackSessionDir) && is_writable($fallbackSessionDir)) {
                @session_save_path($fallbackSessionDir);
            } else {
                @session_save_path(sys_get_temp_dir());
            }
        }
    }
    if (!@session_start()) {
        @ini_set('session.save_handler', 'files');
        @session_save_path(sys_get_temp_dir());
        @session_start();
    }
}

set_exception_handler(static function (Throwable $exception): void {
    error_log('CloudCart unhandled exception: ' . $exception->getMessage() . ' in ' . $exception->getFile() . ':' . $exception->getLine());
    if (!headers_sent()) {
        http_response_code(200);
        header('Content-Type: text/html; charset=utf-8');
        header('Cache-Control: no-store');
    }
    echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>CloudCart</title><link rel="stylesheet" href="styles.css"></head><body class="store-page"><main id="main-content" style="max-width:42rem;margin:6rem auto;padding:2rem;text-align:center;"><h1>We’ll be right back.</h1><p>CloudCart is refreshing its store connection. Please try again in a moment.</p><p><a class="button button-dark" href="home.php">Reload store</a></p></main></body></html>';
});

header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('X-Frame-Options: DENY');
header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
if ($isHttps) {
    header('Strict-Transport-Security: max-age=31536000');
}
header("Content-Security-Policy: default-src 'self'; img-src 'self' data: https://images.unsplash.com; style-src 'self' 'unsafe-inline'; script-src 'self'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'");

final class ShopDatabase
{
    private ?PDO $pdo = null;
    private $sqlsrv = null;
    private string $driver;
    private bool $pdoTransactionActive = false;

    public function __construct()
    {
        $configuredDriver = strtolower(env_value('DB_DRIVER', env_value('MYSHOP_DB_DRIVER')));
        $server = env_value('DB_SERVER', env_value('SQLSRV_SERVER', 'tcp:mycardiffmet-caleb.database.windows.net,1433'));
        $password = env_value('DB_PASSWORD', env_value('SQLSRV_PWD'));
        $database = env_value('DB_DATABASE', env_value('SQLSRV_DATABASE', 'myDatabase'));
        $username = env_value('DB_USERNAME', env_value('SQLSRV_UID', 'myadmin'));

        $wantsSqlSrv = $configuredDriver === 'sqlsrv' || ($configuredDriver === '' && $server !== '' && $password !== '');
        $sqlsrvBackedOff = (int) ($_SESSION['sqlsrv_unavailable_until'] ?? 0) > time();

        if ($wantsSqlSrv && !$sqlsrvBackedOff) {
            try {
                if (!function_exists('sqlsrv_connect')) {
                    throw new RuntimeException('The PHP SQL Server extension is not available.');
                }
                if ($server === '' || $password === '' || $database === '' || $username === '') {
                    throw new RuntimeException('Set DB_SERVER, DB_DATABASE, DB_USERNAME, and DB_PASSWORD for the Azure SQL connection.');
                }
                $options = [
                    'Database' => $database,
                    'Uid' => $username,
                    'PWD' => $password,
                    'Encrypt' => true,
                    'TrustServerCertificate' => false,
                    'LoginTimeout' => 8,
                ];
                $connection = @sqlsrv_connect($server, $options);
                if ($connection === false) {
                    $this->reportSqlsrvError('Azure SQL connection failed');
                    throw new RuntimeException('The store database is temporarily unavailable.');
                }
                $this->driver = 'sqlsrv';
                $this->sqlsrv = $connection;
                $this->ensureSchema();
                $this->seedProducts();
                $this->one('SELECT TOP 1 id, name, description, category, price, stock, image, is_featured, is_active FROM products');
                $this->one('SELECT TOP 1 id, name, email, password_hash, mfa_secret, mfa_enabled, oauth_subject, is_admin, created_at FROM users');
                unset($_SESSION['sqlsrv_unavailable_until']);
                return;
            } catch (Throwable $exception) {
                error_log('CloudCart SQL Server unavailable, falling back to SQLite: ' . $exception->getMessage());
                if ($this->sqlsrv !== null && $this->sqlsrv !== false && function_exists('sqlsrv_close')) {
                    @sqlsrv_close($this->sqlsrv);
                }
                $this->sqlsrv = null;
                $_SESSION['sqlsrv_unavailable_until'] = time() + 15;
            }
        }

        $this->initSqlite();
    }

    private function initSqlite(): void
    {
        $this->driver = 'sqlite';
        if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
            throw new RuntimeException('Enable PDO SQLite for local development or configure Azure SQL.');
        }

        $candidates = array_values(array_filter([
            env_value('DATA_DIRECTORY'),
            is_dir('/home/site') ? '/home/site/data/cloudcart' : '',
            is_dir('/home/LogFiles') ? '/home/LogFiles/cloudcart' : '',
            __DIR__ . DIRECTORY_SEPARATOR . 'data',
            sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'cloudcart',
            env_value('HOME') !== '' ? env_value('HOME') . DIRECTORY_SEPARATOR . 'data' . DIRECTORY_SEPARATOR . 'cloudcart' : '',
        ], static fn(string $path): bool => $path !== ''));

        foreach ($candidates as $dataDirectory) {
            try {
                if (!is_dir($dataDirectory) && !@mkdir($dataDirectory, 0770, true) && !is_dir($dataDirectory)) {
                    continue;
                }
                if (!is_writable($dataDirectory)) {
                    continue;
                }
                $this->pdo = new PDO('sqlite:' . $dataDirectory . DIRECTORY_SEPARATOR . 'myshop.sqlite');
                $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
                $this->pdo->exec('PRAGMA foreign_keys = ON');
                $this->pdo->exec('PRAGMA busy_timeout = 5000');
                $this->ensureSchema();
                $this->seedProducts();
                return;
            } catch (Throwable $exception) {
                error_log('CloudCart SQLite initialization failed in ' . $dataDirectory . ': ' . $exception->getMessage());
                $this->pdo = null;
            }
        }

        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->pdo->exec('PRAGMA foreign_keys = ON');
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
        $catalogue = [
            ['Aero headphones', 'Studio-grade wireless ANC over-ear headphones with 40mm beryllium drivers, spatial audio tracking, and 45-hour battery life.', 'Audio', 189.00, 24, 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?auto=format&fit=crop&w=900&q=85', 1],
            ['Orbit speaker', '360-degree Hi-Res smart wireless speaker with dual passive radiators, room-filling acoustic calibration, and Wi-Fi/Bluetooth 5.3.', 'Audio', 129.00, 18, 'https://images.unsplash.com/photo-1608043152269-423dbba4e7e1?auto=format&fit=crop&w=900&q=85', 0],
            ['Pulse smart watch', 'Aerospace-grade titanium smartwatch with sapphire crystal AMOLED display, dual-frequency GPS, ECG, and 7-day battery life.', 'Wearables', 219.00, 15, 'https://images.unsplash.com/photo-1523275335684-37898b6baf30?auto=format&fit=crop&w=900&q=85', 1],
            ['Halo desk light', 'Precision CNC-machined aluminium architect desk light with CRI 98+ daylight spectrum, wireless rotary dimmer, and ambient sensor.', 'Home', 89.00, 32, 'https://images.unsplash.com/photo-1507473885765-e6ed057f782c?auto=format&fit=crop&w=900&q=85', 0],
            ['Echo wireless buds', 'Adaptive hybrid ANC true wireless earbuds with 6-mic AI call clarity, lossless spatial audio, and MagSafe wireless charging case.', 'Audio', 99.00, 42, 'https://images.unsplash.com/photo-1590658268037-6bf12165a8df?auto=format&fit=crop&w=900&q=85', 0],
            ['Frame instant camera', 'Retro-modern hybrid mirrorless instant camera with f/1.8 glass lens, OLED viewfinder, raw SD capture, and instant film print.', 'Photography', 149.00, 9, 'https://images.unsplash.com/photo-1526170375885-4d8ecf77b99f?auto=format&fit=crop&w=900&q=85', 0],
            ['Cloud charging stand', '3-in-1 Qi2 15W magnetic fast wireless charging dock in weighted aluminium for phone, watch, and wireless earbuds.', 'Accessories', 59.00, 50, 'https://images.unsplash.com/photo-1586816879360-004f5b0c51e3?auto=format&fit=crop&w=900&q=85', 0],
            ['Studio keyboard', 'Gasket-mounted 75% wireless mechanical keyboard with hot-swappable tactile switches, PBT keycaps, and multi-host Bluetooth/2.4GHz.', 'Computing', 109.00, 21, 'https://images.unsplash.com/photo-1587829741301-dc798b83add3?auto=format&fit=crop&w=900&q=85', 1],
            ['NovaBook Pro 14" Laptop', 'Ultra-thin creator laptop with 14.2" 120Hz Mini-LED display, 12-core processor, 16GB unified memory, 1TB NVMe SSD, and 18-hour battery.', 'Computing', 1149.00, 11, 'https://images.unsplash.com/photo-1517336714731-489689fd1ca8?auto=format&fit=crop&w=900&q=85', 1],
            ['Apex 34" Curved OLED Monitor', '34-inch WQHD (3440x1440) 240Hz curved QD-OLED gaming and creator monitor with 0.03ms response time and 90W USB-C PD.', 'Computing', 649.00, 8, 'https://images.unsplash.com/photo-1527443224154-c4a3942d3acf?auto=format&fit=crop&w=900&q=85', 1],
            ['Vortex Pro Superlight Mouse', '54g ultra-lightweight wireless esports gaming mouse with 32,000 DPI optical sensor, optical switches, and 90-hour battery life.', 'Gaming', 84.00, 36, 'https://images.unsplash.com/photo-1615663245857-ac93bb7c39e7?auto=format&fit=crop&w=900&q=85', 0],
            ['DualSense Edge Pro Controller', 'Pro wireless gaming controller with hall-effect thumbsticks, remappable alloy back paddles, adjustable trigger stops, and carrying case.', 'Gaming', 169.00, 19, 'https://images.unsplash.com/photo-1600080972464-8e5f35f63d08?auto=format&fit=crop&w=900&q=85', 1],
            ['SkyVision Mini 4K Pro Drone', 'Sub-249g foldable camera drone with 1/1.3" CMOS 4K/60fps HDR sensor, 3-axis mechanical gimbal, omnidirectional sensing, and 38-min flight.', 'Photography', 429.00, 7, 'https://images.unsplash.com/photo-1507582020474-9a35b7d455d9?auto=format&fit=crop&w=900&q=85', 1],
            ['HyperDrive 2TB NVMe SSD', 'Pocket-sized IP65 water- and drop-resistant USB 3.2 Gen 2x2 portable NVMe SSD delivering up to 2,000 MB/s read and write speeds.', 'Accessories', 139.00, 29, 'https://images.unsplash.com/photo-1597872200969-2b65d56bd16b?auto=format&fit=crop&w=900&q=85', 0],
            ['StreamCast XLR Studio Mic', 'Broadcast-grade dynamic USB-C and XLR studio microphone with onboard DSP noise suppression, tap-to-mute halo, and metal boom mount.', 'Audio', 159.00, 14, 'https://images.unsplash.com/photo-1590602847861-f357a9332bbc?auto=format&fit=crop&w=900&q=85', 0],
            ['VisionVR Mixed Reality Headset', 'Standalone 4K+ pancake-optics wireless mixed reality headset with full-colour passthrough, spatial audio, and precision hand tracking.', 'Gaming', 479.00, 12, 'https://images.unsplash.com/photo-1622979135225-d2ba269cf1ac?auto=format&fit=crop&w=900&q=85', 1],
        ];

        $existingProduct = $this->isSqlServer()
            ? $this->one('SELECT TOP 1 id FROM products')
            : $this->one('SELECT id FROM products LIMIT 1');

        if ($existingProduct === null) {
            $this->begin();
            $checkAgain = $this->isSqlServer()
                ? $this->one('SELECT TOP 1 id FROM products WITH (UPDLOCK, HOLDLOCK)')
                : $this->one('SELECT id FROM products LIMIT 1');
            if ($checkAgain === null) {
                foreach ($catalogue as $product) {
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
            }
            $this->commit();
            $_SESSION['catalogue_upgraded_v2'] = true;
            return;
        }

        if (($_SESSION['catalogue_upgraded_v2'] ?? false) === true) {
            return;
        }

        try {
            foreach ($catalogue as $product) {
                $existing = $this->one('SELECT id, image FROM products WHERE name = ?', [$product[0]]);
                if ($existing === null) {
                    $this->insert('products', [
                        'name' => $product[0],
                        'description' => $product[1],
                        'category' => $product[2],
                        'price' => $product[3],
                        'stock' => $product[4],
                        'image' => $product[5],
                        'is_featured' => $product[6],
                    ]);
                } elseif (str_starts_with((string) $existing['image'], 'assets/hero-')) {
                    $this->run(
                        'UPDATE products SET image = ?, description = ?, category = ? WHERE id = ?',
                        [$product[5], $product[1], $product[2], (int) $existing['id']]
                    );
                }
            }
            $_SESSION['catalogue_upgraded_v2'] = true;
        } catch (Throwable $exception) {
            error_log('CloudCart non-destructive catalogue upgrade skipped: ' . $exception->getMessage());
        }
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

function catalogue_image_options(): array
{
    return [
        'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?auto=format&fit=crop&w=900&q=85' => 'Studio Wireless Headphones (Photo)',
        'https://images.unsplash.com/photo-1608043152269-423dbba4e7e1?auto=format&fit=crop&w=900&q=85' => 'Smart Spatial Speaker (Photo)',
        'https://images.unsplash.com/photo-1523275335684-37898b6baf30?auto=format&fit=crop&w=900&q=85' => 'Titanium Smart Watch (Photo)',
        'https://images.unsplash.com/photo-1507473885765-e6ed057f782c?auto=format&fit=crop&w=900&q=85' => 'Architect LED Desk Light (Photo)',
        'https://images.unsplash.com/photo-1590658268037-6bf12165a8df?auto=format&fit=crop&w=900&q=85' => 'True Wireless Earbuds (Photo)',
        'https://images.unsplash.com/photo-1526170375885-4d8ecf77b99f?auto=format&fit=crop&w=900&q=85' => 'Mirrorless Instant Camera (Photo)',
        'https://images.unsplash.com/photo-1586816879360-004f5b0c51e3?auto=format&fit=crop&w=900&q=85' => '3-in-1 Magnetic Charging Dock (Photo)',
        'https://images.unsplash.com/photo-1587829741301-dc798b83add3?auto=format&fit=crop&w=900&q=85' => 'Mechanical Wireless Keyboard (Photo)',
        'https://images.unsplash.com/photo-1517336714731-489689fd1ca8?auto=format&fit=crop&w=900&q=85' => '14" Creator Pro Laptop (Photo)',
        'https://images.unsplash.com/photo-1527443224154-c4a3942d3acf?auto=format&fit=crop&w=900&q=85' => '34" UltraWide OLED Monitor (Photo)',
        'https://images.unsplash.com/photo-1615663245857-ac93bb7c39e7?auto=format&fit=crop&w=900&q=85' => 'Pro Superlight Gaming Mouse (Photo)',
        'https://images.unsplash.com/photo-1600080972464-8e5f35f63d08?auto=format&fit=crop&w=900&q=85' => 'Wireless Esports Controller (Photo)',
        'https://images.unsplash.com/photo-1507582020474-9a35b7d455d9?auto=format&fit=crop&w=900&q=85' => '4K Foldable Camera Drone (Photo)',
        'https://images.unsplash.com/photo-1597872200969-2b65d56bd16b?auto=format&fit=crop&w=900&q=85' => '2TB NVMe Portable SSD (Photo)',
        'https://images.unsplash.com/photo-1590602847861-f357a9332bbc?auto=format&fit=crop&w=900&q=85' => 'XLR Broadcast Studio Mic (Photo)',
        'https://images.unsplash.com/photo-1622979135225-d2ba269cf1ac?auto=format&fit=crop&w=900&q=85' => '4K Mixed Reality VR Headset (Photo)',
    ];
}

function is_valid_product_image(string $image): bool
{
    if (array_key_exists($image, catalogue_image_options())) {
        return true;
    }
    if (preg_match('#^https://images\.unsplash\.com/photo-[a-zA-Z0-9-]+(\?[a-zA-Z0-9=&_%-]+)?$#', $image) === 1) {
        return true;
    }
    if (preg_match('#^assets/[a-zA-Z0-9._-]+\.(svg|png|jpg|jpeg|webp)$#', $image) === 1) {
        return is_file(__DIR__ . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $image));
    }
    return false;
}

function product_image_url(string $image, string $name = ''): string
{
    $legacyMap = [
        'assets/hero-headphones.svg' => 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?auto=format&fit=crop&w=900&q=85',
        'assets/hero-speaker.svg' => 'https://images.unsplash.com/photo-1608043152269-423dbba4e7e1?auto=format&fit=crop&w=900&q=85',
        'assets/hero-watch.svg' => 'https://images.unsplash.com/photo-1523275335684-37898b6baf30?auto=format&fit=crop&w=900&q=85',
        'assets/hero-lamp.svg' => 'https://images.unsplash.com/photo-1507473885765-e6ed057f782c?auto=format&fit=crop&w=900&q=85',
        'assets/hero-earbuds.svg' => 'https://images.unsplash.com/photo-1590658268037-6bf12165a8df?auto=format&fit=crop&w=900&q=85',
        'assets/hero-camera.svg' => 'https://images.unsplash.com/photo-1526170375885-4d8ecf77b99f?auto=format&fit=crop&w=900&q=85',
        'assets/hero-keyboard.svg' => 'https://images.unsplash.com/photo-1587829741301-dc798b83add3?auto=format&fit=crop&w=900&q=85',
    ];
    if (stripos($name, 'charging') !== false && isset($legacyMap[$image])) {
        return 'https://images.unsplash.com/photo-1586816879360-004f5b0c51e3?auto=format&fit=crop&w=900&q=85';
    }
    if (isset($legacyMap[$image])) {
        return $legacyMap[$image];
    }
    if (is_valid_product_image($image)) {
        return $image;
    }
    return 'https://images.unsplash.com/photo-1505740420928-5e560c06d30e?auto=format&fit=crop&w=900&q=85';
}

function product_meta(array $product): array
{
    $id = max(1, (int) ($product['id'] ?? 1));
    $price = (float) ($product['price'] ?? 99.0);
    $isFeatured = (bool) ($product['is_featured'] ?? false);
    $discounts = [18, 22, 15, 25, 20, 17, 24, 19];
    $discountPct = $discounts[$id % count($discounts)];
    $rrp = ceil(($price / (1 - ($discountPct / 100))) / 5) * 5 - 0.01;
    if ($rrp <= $price) {
        $rrp = $price + 25.00;
    }
    $ratings = [4.8, 4.7, 4.9, 4.6, 4.8, 4.7, 4.9, 4.8];
    $rating = $ratings[$id % count($ratings)];
    $reviews = 180 + (($id * 317) % 2400);
    $claimedPct = 58 + (($id * 13) % 34);

    if ($isFeatured && $id % 2 === 1) {
        $badge = 'Best Seller';
        $badgeClass = 'badge-bestseller';
    } elseif ($isFeatured) {
        $badge = "CloudCart's Choice";
        $badgeClass = 'badge-choice';
    } elseif ($discountPct >= 20) {
        $badge = 'Limited Time Deal';
        $badgeClass = 'badge-deal';
    } else {
        $badge = 'Top Rated Tech';
        $badgeClass = 'badge-top';
    }

    $category = strtolower((string) ($product['category'] ?? 'tech'));
    $specs = match ($category) {
        'audio' => [
            'Hi-Res 24-bit/96kHz acoustic architecture with adaptive room & ear calibration',
            'Ultra-low latency Bluetooth 5.3 + multipoint dual-device pairing',
            'Fast USB-C charging: 10 minutes charge provides up to 6 hours playback',
            'Backed by 2-Year CloudCart UK Protection & 30-Day Free Returns',
        ],
        'computing' => [
            'Engineered for high-throughput creative workflows, coding, and multitasking',
            'Thunderbolt 4 / USB-C connectivity with fast power delivery support',
            'Precision anodised aluminium chassis with silent thermal dissipation',
            'Backed by 2-Year CloudCart UK Protection & Next-Day UK Dispatch',
        ],
        'gaming' => [
            'Tournament-grade 1000Hz+ polling rate for sub-millisecond input response',
            'Ergonomic lightweight construction built for marathon gaming sessions',
            'Customisable profiles, onboard memory, and cross-platform compatibility',
            'Backed by 2-Year CloudCart UK Protection & 30-Day Free Returns',
        ],
        'wearables' => [
            'Sapphire crystal display with 2,000-nit peak outdoor readability',
            '24/7 heart-rate, SpO2, sleep stage, and dual-frequency GPS workout tracking',
            '5ATM water resistance with magnetic fast-charge dock included',
            'Backed by 2-Year CloudCart UK Protection & 30-Day Free Returns',
        ],
        'photography' => [
            ' Multi-element glass optics with hardware image stabilisation',
            'High-dynamic-range sensor for crisp low-light photos and 4K video',
            'Instant wireless transfer + USB-C high-speed RAW/JPEG offload',
            'Backed by 2-Year CloudCart UK Protection & 30-Day Free Returns',
        ],
        default => [
            'Premium aluminium and matte soft-touch finish built for daily reliability',
            'Universal compatibility across Windows, macOS, iOS, and Android devices',
            'Integrated over-voltage, thermal, and short-circuit safety protection',
            'Backed by 2-Year CloudCart UK Protection & 30-Day Free Returns',
        ],
    };

    return [
        'rating' => number_format($rating, 1),
        'reviews' => number_format($reviews),
        'rrp' => $rrp,
        'savings' => max(0.0, $rrp - $price),
        'discount_pct' => $discountPct,
        'badge' => $badge,
        'badge_class' => $badgeClass,
        'claimed_pct' => $claimedPct,
        'specs' => $specs,
    ];
}

function site_shell_start(string $title, string $pageClass = ''): void
{
    $user = current_user();
    $flash = take_flash();
    $cartItems = cart_items();
    $cartCount = array_sum(array_map(static fn(array $item): int => (int) $item['quantity'], $cartItems));
    $cartSubtotal = cart_total($cartItems);
    $currentSearch = isset($_GET['search']) && is_string($_GET['search']) ? trim(substr($_GET['search'], 0, 80)) : '';
    $currentCategory = isset($_GET['category']) && is_string($_GET['category']) ? trim(substr($_GET['category'], 0, 60)) : '';
    $navCategories = ['Computing', 'Gaming', 'Audio', 'Wearables', 'Photography', 'Accessories', 'Home'];
    ?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0f172a">
    <title><?= e($title) ?> · CloudCart.co.uk</title>
    <link rel="stylesheet" href="styles.css">
    <script src="store.js" defer></script>
</head>
<body class="store-page <?= e($pageClass) ?>">
    <a class="skip-link" href="#main-content">Skip to content</a>

    <header class="store-header">
        <div class="header-main-bar">
            <div class="header-brand-group">
                <a class="store-brand" href="home.php" aria-label="CloudCart UK home">
                    <span class="brand-main">cloud<span>cart</span><small>.co.uk</small></span>
                    <span class="brand-prime-tag"><i aria-hidden="true">⚡</i> prime tech</span>
                </a>
                <a class="header-location" href="home.php#shop" aria-label="UK Next-Day Delivery">
                    <span class="loc-pin" aria-hidden="true">📍</span>
                    <span>
                        <small>Deliver to Cardiff &amp; UK</small>
                        <strong>FREE Next-Day over £100</strong>
                    </span>
                </a>
            </div>

            <form class="header-search-bar" method="get" action="home.php" role="search">
                <label class="header-search-category">
                    <span class="visually-hidden">Department</span>
                    <select name="category" aria-label="Search department">
                        <option value="">All Tech</option>
                        <?php foreach ($navCategories as $navCat): ?>
                            <option value="<?= e($navCat) ?>" <?= $currentCategory === $navCat ? 'selected' : '' ?>><?= e($navCat) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="header-search-input">
                    <span class="visually-hidden">Search CloudCart.co.uk</span>
                    <input type="search" name="search" value="<?= e($currentSearch) ?>" placeholder="Search laptops, 240Hz OLED monitors, ANC headphones, gaming gear…">
                </label>
                <button class="header-search-submit" type="submit" aria-label="Search">
                    <span aria-hidden="true">⌕</span>
                </button>
            </form>

            <button class="mobile-menu-toggle" type="button" aria-expanded="false" aria-controls="primary-navigation">Menu ☰</button>

            <div class="header-actions" id="primary-navigation">
                <?php if ($user !== null): ?>
                    <a class="header-action-item" href="account.php">
                        <small>Hello, <?= e(explode(' ', (string) $user['name'])[0]) ?></small>
                        <strong>Account &amp; Security</strong>
                    </a>
                    <a class="header-action-item" href="account.php#orders-title">
                        <small>Track &amp; Manage</small>
                        <strong>Returns &amp; Orders</strong>
                    </a>
                    <?php if ($user['is_admin']): ?>
                        <a class="header-action-item header-admin-pill" href="admin.php">
                            <small>Admin Studio</small>
                            <strong>Manage Store</strong>
                        </a>
                    <?php endif; ?>
                    <form action="logout.php" method="post" class="inline-form">
                        <input type="hidden" name="csrf_token" value="<?= e(csrf_token()) ?>">
                        <button class="header-signout-btn" type="submit">Sign out</button>
                    </form>
                <?php else: ?>
                    <a class="header-action-item" href="login.php">
                        <small>Hello, sign in</small>
                        <strong>Account &amp; Lists</strong>
                    </a>
                    <a class="header-action-item" href="register.php">
                        <small>New customer?</small>
                        <strong>Create Account</strong>
                    </a>
                <?php endif; ?>

                <a class="bag-link" href="cart.php" aria-label="Shopping basket, <?= $cartCount ?> items">
                    <span class="bag-icon-wrap">
                        <span class="bag-count"><?= $cartCount ?></span>
                        <span class="bag-svg" aria-hidden="true">🛒</span>
                    </span>
                    <span class="bag-label">
                        <small>Basket</small>
                        <strong><?= money($cartSubtotal) ?></strong>
                    </span>
                </a>
            </div>
        </div>

        <nav class="store-subnav" aria-label="Department navigation">
            <div class="subnav-inner">
                <a class="subnav-link <?= $currentCategory === '' && $currentSearch === '' ? 'is-active' : '' ?>" href="home.php">☰ All Departments</a>
                <a class="subnav-link subnav-deal" href="home.php#deals">🔥 Today’s Lightning Deals</a>
                <?php foreach ($navCategories as $navCat): ?>
                    <a class="subnav-link <?= $currentCategory === $navCat ? 'is-active' : '' ?>" href="home.php?category=<?= rawurlencode($navCat) ?>#shop"><?= e($navCat) ?></a>
                <?php endforeach; ?>
                <span class="subnav-promise">✓ <strong>Prime Tech:</strong> Free Next-Day UK Delivery over £100 &amp; 30-Day Returns</span>
            </div>
        </nav>
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
        <a class="footer-back-top" href="#main-content">Back to top ↑</a>
        <div class="footer-grid">
            <div class="footer-col">
                <a class="store-brand footer-logo" href="home.php">
                    <span class="brand-main">cloud<span>cart</span><small>.co.uk</small></span>
                </a>
                <p>The UK’s flagship destination for high-performance computing, spatial audio, esports hardware, and creator gear.</p>
                <div class="footer-badges">
                    <span>⚡ Next-Day UK Dispatch</span>
                    <span>🔒 256-Bit TLS &amp; TOTP MFA</span>
                    <span>☁️ Powered by Microsoft Azure</span>
                </div>
            </div>
            <div class="footer-col">
                <h3>Shop Departments</h3>
                <ul>
                    <li><a href="home.php?category=Computing#shop">Laptops &amp; OLED Monitors</a></li>
                    <li><a href="home.php?category=Gaming#shop">PC &amp; Console Gaming</a></li>
                    <li><a href="home.php?category=Audio#shop">ANC Headphones &amp; Hi-Fi</a></li>
                    <li><a href="home.php?category=Wearables#shop">Smartwatches &amp; Fitness</a></li>
                    <li><a href="home.php?category=Photography#shop">Cameras &amp; 4K Drones</a></li>
                </ul>
            </div>
            <div class="footer-col">
                <h3>Your Account &amp; Orders</h3>
                <ul>
                    <li><a href="account.php">Track Your Orders</a></li>
                    <li><a href="account.php#favourites">Saved Wish List</a></li>
                    <li><a href="account.php#security">Authenticator MFA &amp; Security</a></li>
                    <li><a href="cart.php">View Shopping Basket</a></li>
                    <li><a href="login.php">Sign In / Register</a></li>
                </ul>
            </div>
            <div class="footer-col">
                <h3>CloudCart Guarantee</h3>
                <ul>
                    <li><a href="home.php#deals">Today’s Lightning Deals</a></li>
                    <li><a href="home.php#shop">Free UK Delivery Over £100</a></li>
                    <li><a href="home.php#shop">30-Day Money-Back Guarantee</a></li>
                    <li><a href="home.php#shop">2-Year UK Hardware Warranty</a></li>
                </ul>
            </div>
        </div>
        <div class="footer-bottom">
            <p>© <?= date('Y') ?> CloudCart.co.uk · Multi-Tier Cloud E-Commerce Demonstrator (Azure App Service + Azure SQL + Azure Functions)</p>
            <span>Simulated Demo Checkout · No Real Payment Is Taken</span>
        </div>
    </footer>
</body>
</html>
<?php
}
