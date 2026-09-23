<?php
require_once __DIR__ . '/includes/functions.php';
header('Content-Type: application/json; charset=utf-8');

if (!rate_limit_check('book_availability', 60, 60)) {
    http_response_code(429);
    echo json_encode(['error' => 'too_many_requests']);
    exit;
}

$serviceId = isset($_GET['service_id']) ? (int)$_GET['service_id'] : 0;
if ($serviceId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'missing_service_id']);
    exit;
}

$horizonDays = min(60, max(1, (int)get_setting('booking_horizon_days', '30')));
$from = date('Y-m-d');
$to = date('Y-m-d', strtotime($from . " +{$horizonDays} days"));

$slots = compute_available_slots($serviceId, $from, $to);
echo json_encode(['slots' => $slots], JSON_UNESCAPED_UNICODE);
