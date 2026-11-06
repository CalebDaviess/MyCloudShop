<?php
require_once __DIR__ . '/app.php';
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    header('Allow: GET');
    http_response_code(405);
    exit;
}
$user = current_user();
if ($user === null || !$user['mfa_verified']) {
    http_response_code(401);
    header('Cache-Control: no-store');
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'Sign in to view your orders.']);
    exit;
}

$orderLimit = db()->isSqlServer() ? 'SELECT TOP 30' : 'SELECT';
$orderSuffix = db()->isSqlServer() ? '' : ' LIMIT 30';
$orders = db()->all(
    $orderLimit . ' id, status, updated_at FROM orders WHERE user_id = ? ORDER BY created_at DESC, id DESC' . $orderSuffix,
    [(int) $user['id']]
);
$result = array_map(static function (array $order): array {
    $timestamp = strtotime((string) $order['updated_at']);
    return [
        'id' => (int) $order['id'],
        'status' => (string) $order['status'],
        'updated_at' => $timestamp === false ? null : gmdate('c', $timestamp),
    ];
}, $orders);

header('Cache-Control: private, no-store, max-age=0');
header('Content-Type: application/json; charset=utf-8');
echo json_encode(['orders' => $result], JSON_THROW_ON_ERROR);
