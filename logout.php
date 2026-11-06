<?php
require_once __DIR__ . '/app.php';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('index.php');
}
verify_csrf();
$_SESSION = [];
session_regenerate_id(true);
session_destroy();
redirect('index.php');
