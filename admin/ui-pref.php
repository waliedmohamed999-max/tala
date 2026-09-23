<?php
require_once __DIR__ . '/../includes/auth.php';
$adminUser = require_admin();
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_verify()) {
    http_response_code(400);
    echo json_encode(['ok' => false]);
    exit;
}

if (($_POST['pref'] ?? '') === 'sidebar_collapsed') {
    $value = !empty($_POST['value']) ? 1 : 0;
    db()->prepare('UPDATE admin_users SET sidebar_collapsed = ? WHERE id = ?')->execute([$value, $adminUser['id']]);
    echo json_encode(['ok' => true]);
    exit;
}

http_response_code(400);
echo json_encode(['ok' => false]);
