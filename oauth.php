<?php
require_once __DIR__ . '/app.php';

function oauth_fail(string $message): never
{
    set_flash('error', $message);
    redirect('login.php');
}

function base64url_decode_value(string $value): string|false
{
    $padding = strlen($value) % 4;
    if ($padding !== 0) {
        $value .= str_repeat('=', 4 - $padding);
    }
    return base64_decode(strtr($value, '-_', '+/'), true);
}

function der_length(int $length): string
{
    if ($length < 128) {
        return chr($length);
    }
    $bytes = '';
    while ($length > 0) {
        $bytes = chr($length & 0xFF) . $bytes;
        $length >>= 8;
    }
    return chr(0x80 | strlen($bytes)) . $bytes;
}

function der_integer(string $value): string
{
    $value = ltrim($value, "\x00");
    if ($value === '') {
        $value = "\x00";
    }
    if ((ord($value[0]) & 0x80) !== 0) {
        $value = "\x00" . $value;
    }
    return "\x02" . der_length(strlen($value)) . $value;
}

function entra_rsa_public_key(string $modulus, string $exponent): string|false
{
    $modulusBytes = base64url_decode_value($modulus);
    $exponentBytes = base64url_decode_value($exponent);
    if ($modulusBytes === false || $exponentBytes === false) {
        return false;
    }
    $rsa = der_integer($modulusBytes) . der_integer($exponentBytes);
    $rsa = "\x30" . der_length(strlen($rsa)) . $rsa;
    $algorithm = hex2bin('300d06092a864886f70d0101010500');
    $bitString = "\x03" . der_length(strlen($rsa) + 1) . "\x00" . $rsa;
    $sequence = $algorithm . $bitString;
    $spki = "\x30" . der_length(strlen($sequence)) . $sequence;
    return "-----BEGIN PUBLIC KEY-----\n"
        . chunk_split(base64_encode($spki), 64, "\n")
        . "-----END PUBLIC KEY-----\n";
}

function https_json(string $url, ?array $form = null): array
{
    if (!function_exists('curl_init')) {
        throw new RuntimeException('The PHP cURL extension is required for Microsoft sign-in.');
    }
    $handle = curl_init($url);
    $options = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 12,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_HTTPHEADER => ['Accept: application/json'],
    ];
    if ($form !== null) {
        $options[CURLOPT_POST] = true;
        $options[CURLOPT_POSTFIELDS] = http_build_query($form);
        $options[CURLOPT_HTTPHEADER][] = 'Content-Type: application/x-www-form-urlencoded';
    }
    curl_setopt_array($handle, $options);
    $response = curl_exec($handle);
    $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
    $error = $response === false ? curl_error($handle) : '';
    curl_close($handle);
    if (!is_string($response) || $status < 200 || $status >= 300) {
        error_log('CloudCart Microsoft sign-in request failed: HTTP ' . $status . ($error !== '' ? ' (' . $error . ')' : ''));
        throw new RuntimeException('Microsoft sign-in is temporarily unavailable.');
    }
    $decoded = json_decode($response, true);
    if (!is_array($decoded)) {
        error_log('CloudCart Microsoft sign-in returned an invalid response.');
        throw new RuntimeException('Microsoft sign-in returned an invalid response.');
    }
    return $decoded;
}

function verify_entra_id_token(string $token, string $expectedNonce): array
{
    if (!function_exists('openssl_verify') || !function_exists('openssl_pkey_get_public')) {
        throw new RuntimeException('The PHP OpenSSL extension is required for Microsoft sign-in.');
    }
    $parts = explode('.', $token);
    if (count($parts) !== 3) {
        throw new RuntimeException('Microsoft returned an invalid identity token.');
    }
    $headerJson = base64url_decode_value($parts[0]);
    $claimsJson = base64url_decode_value($parts[1]);
    $signature = base64url_decode_value($parts[2]);
    if ($headerJson === false || $claimsJson === false || $signature === false) {
        throw new RuntimeException('Microsoft returned an invalid identity token.');
    }
    $header = json_decode($headerJson, true);
    $claims = json_decode($claimsJson, true);
    if (!is_array($header) || !is_array($claims) || ($header['alg'] ?? '') !== 'RS256' || !is_string($header['kid'] ?? null)) {
        throw new RuntimeException('Microsoft returned an unsupported identity token.');
    }

    $tenant = strtolower(env_value('ENTRA_TENANT_ID'));
    if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $tenant)) {
        throw new RuntimeException('ENTRA_TENANT_ID must be the Microsoft Entra directory tenant ID.');
    }
    $issuer = 'https://login.microsoftonline.com/' . $tenant . '/v2.0';
    if (
        strtolower((string) ($claims['iss'] ?? '')) !== $issuer
        || strtolower((string) ($claims['tid'] ?? '')) !== $tenant
        || !hash_equals(env_value('ENTRA_CLIENT_ID'), (string) ($claims['aud'] ?? ''))
        || !is_string($claims['nonce'] ?? null)
        || !hash_equals($expectedNonce, $claims['nonce'])
        || (int) ($claims['exp'] ?? 0) <= time()
        || (int) ($claims['nbf'] ?? 0) > time() + 60
    ) {
        throw new RuntimeException('Microsoft identity verification did not match this sign-in request.');
    }

    $jwks = https_json('https://login.microsoftonline.com/' . $tenant . '/discovery/v2.0/keys');
    $key = null;
    foreach (($jwks['keys'] ?? []) as $candidate) {
        if (is_array($candidate) && ($candidate['kid'] ?? '') === $header['kid'] && ($candidate['kty'] ?? '') === 'RSA') {
            $key = $candidate;
            break;
        }
    }
    if ($key === null || !is_string($key['n'] ?? null) || !is_string($key['e'] ?? null)) {
        throw new RuntimeException('Microsoft identity signing key could not be verified.');
    }
    $publicKey = entra_rsa_public_key($key['n'], $key['e']);
    if ($publicKey === false || openssl_verify($parts[0] . '.' . $parts[1], $signature, $publicKey, OPENSSL_ALGO_SHA256) !== 1) {
        throw new RuntimeException('Microsoft identity signature verification failed.');
    }
    return $claims;
}

if (!entraid_configured()) {
    oauth_fail('Microsoft sign-in isn’t configured yet. You can still sign in with your CloudCart account.');
}

$action = $_GET['action'] ?? '';
$tenant = strtolower(env_value('ENTRA_TENANT_ID'));
if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $tenant)) {
    oauth_fail('Set ENTRA_TENANT_ID to your Microsoft Entra directory tenant ID before enabling sign-in.');
}
$redirectUri = oauth_redirect_uri();
$redirectParts = parse_url($redirectUri);
if (
    !is_array($redirectParts)
    || !isset($redirectParts['scheme'], $redirectParts['host'])
    || ($redirectParts['scheme'] !== 'https' && !in_array($redirectParts['host'], ['localhost', '127.0.0.1'], true))
) {
    oauth_fail('Microsoft sign-in needs an HTTPS callback URL, except for local development.');
}

if ($action === 'start') {
    $linkUser = null;
    if (($_GET['link'] ?? '') === '1') {
        $linkUser = require_user();
        $_SESSION['oauth_link_user_id'] = (int) $linkUser['id'];
    } elseif (current_user() !== null) {
        redirect('account.php');
    }
    $state = bin2hex(random_bytes(32));
    $nonce = bin2hex(random_bytes(32));
    $verifier = rtrim(strtr(base64_encode(random_bytes(48)), '+/', '-_'), '=');
    $_SESSION['entra_oauth'] = [
        'state' => $state,
        'nonce' => $nonce,
        'verifier' => $verifier,
        'created' => time(),
    ];
    unset($_SESSION['csrf_token']);
    $query = http_build_query([
        'client_id' => env_value('ENTRA_CLIENT_ID'),
        'response_type' => 'code',
        'redirect_uri' => $redirectUri,
        'response_mode' => 'query',
        'scope' => 'openid profile email',
        'state' => $state,
        'nonce' => $nonce,
        'code_challenge' => rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '='),
        'code_challenge_method' => 'S256',
        'prompt' => 'select_account',
    ]);
    redirect('https://login.microsoftonline.com/' . $tenant . '/oauth2/v2.0/authorize?' . $query);
}

if ($action !== 'callback' && !isset($_GET['code']) && !isset($_GET['error'])) {
    redirect('login.php');
}

$pending = $_SESSION['entra_oauth'] ?? null;
$returnedState = $_GET['state'] ?? '';
if (
    !is_array($pending)
    || !is_string($returnedState)
    || !is_string($pending['state'] ?? null)
    || !hash_equals($pending['state'], $returnedState)
    || time() - (int) ($pending['created'] ?? 0) > 600
) {
    unset($_SESSION['entra_oauth'], $_SESSION['oauth_link_user_id']);
    oauth_fail('That Microsoft sign-in link has expired. Please start again.');
}
if (isset($_GET['error'])) {
    unset($_SESSION['entra_oauth'], $_SESSION['oauth_link_user_id']);
    oauth_fail('Microsoft sign-in was cancelled or couldn’t be completed.');
}
$code = $_GET['code'] ?? '';
if (!is_string($code) || $code === '' || strlen($code) > 4096) {
    unset($_SESSION['entra_oauth'], $_SESSION['oauth_link_user_id']);
    oauth_fail('Microsoft sign-in was not completed. Please try again.');
}
unset($_SESSION['entra_oauth']);

try {
    $tokens = https_json('https://login.microsoftonline.com/' . $tenant . '/oauth2/v2.0/token', [
        'client_id' => env_value('ENTRA_CLIENT_ID'),
        'client_secret' => env_value('ENTRA_CLIENT_SECRET'),
        'grant_type' => 'authorization_code',
        'code' => $code,
        'redirect_uri' => $redirectUri,
        'code_verifier' => (string) $pending['verifier'],
        'scope' => 'openid profile email',
    ]);
    if (!is_string($tokens['id_token'] ?? null)) {
        throw new RuntimeException('Microsoft did not return an identity token.');
    }
    $claims = verify_entra_id_token($tokens['id_token'], (string) $pending['nonce']);
} catch (RuntimeException $exception) {
    error_log('CloudCart Microsoft sign-in could not be verified: ' . $exception->getMessage());
    unset($_SESSION['oauth_link_user_id']);
    oauth_fail('We couldn’t verify your Microsoft sign-in. Please try again or use your CloudCart account.');
}

$email = strtolower(trim((string) ($claims['preferred_username'] ?? $claims['email'] ?? '')));
$displayName = trim((string) ($claims['name'] ?? 'CloudCart customer'));
$subject = strtolower((string) $claims['tid']) . ':' . (string) ($claims['sub'] ?? '');
if (filter_var($email, FILTER_VALIDATE_EMAIL) === false || $subject === (strtolower((string) $claims['tid']) . ':')) {
    unset($_SESSION['oauth_link_user_id']);
    oauth_fail('Your Microsoft account did not provide the account details CloudCart needs.');
}

$existingSubject = db()->one('SELECT id, name, email, mfa_enabled FROM users WHERE oauth_subject = ?', [$subject]);
$linkUserId = $_SESSION['oauth_link_user_id'] ?? null;
unset($_SESSION['oauth_link_user_id']);

if (is_int($linkUserId) && $linkUserId > 0) {
    $linkUser = db()->one('SELECT id, mfa_enabled FROM users WHERE id = ?', [$linkUserId]);
    if ($linkUser === null || (current_user()['id'] ?? null) !== $linkUserId) {
        oauth_fail('Sign in to your CloudCart account before connecting Microsoft sign-in.');
    }
    if ($existingSubject !== null && (int) $existingSubject['id'] !== $linkUserId) {
        oauth_fail('That Microsoft account is already connected to another CloudCart account.');
    }
    db()->run('UPDATE users SET oauth_subject = ? WHERE id = ?', [$subject, $linkUserId]);
    set_flash('success', 'Microsoft sign-in is connected to your account.');
    redirect('account.php#security');
}

if ($existingSubject === null) {
    $emailAccount = db()->one('SELECT id FROM users WHERE email = ?', [$email]);
    if ($emailAccount !== null) {
        oauth_fail('An account already uses that email. Sign in with it first, then connect Microsoft from your security settings.');
    }
    $name = $displayName === '' ? 'CloudCart customer' : substr($displayName, 0, 120);
    $adminEmail = strtolower(env_value('ADMIN_EMAIL'));
    $userId = db()->insert('users', [
        'name' => $name,
        'email' => $email,
        'password_hash' => password_hash_secure(bin2hex(random_bytes(32))),
        'oauth_subject' => $subject,
        'is_admin' => $adminEmail !== '' && $email === $adminEmail ? 1 : 0,
    ]);
    $existingSubject = ['id' => $userId, 'name' => $name, 'email' => $email, 'mfa_enabled' => 0];
}

if ((bool) $existingSubject['mfa_enabled']) {
    session_regenerate_id(true);
    $_SESSION['pending_mfa_user_id'] = (int) $existingSubject['id'];
    $_SESSION['mfa_attempts'] = 0;
    unset($_SESSION['user_id'], $_SESSION['mfa_verified'], $_SESSION['csrf_token']);
    redirect('login.php');
}

session_regenerate_id(true);
$_SESSION['user_id'] = (int) $existingSubject['id'];
$_SESSION['mfa_verified'] = true;
unset($_SESSION['csrf_token']);
set_flash('success', 'You’re securely signed in with Microsoft.');
redirect('account.php');
