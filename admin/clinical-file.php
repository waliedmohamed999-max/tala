<?php
require_once __DIR__ . '/../includes/auth.php';
$adminUser = require_clinical_access();

if (!clinical_module_enabled()) {
    http_response_code(404);
    exit('الوحدة غير مفعّلة.');
}

$id = (int)($_GET['id'] ?? 0);
$stmt = db()->prepare('SELECT * FROM clinical_note_attachments WHERE id = ?');
$stmt->execute([$id]);
$att = $stmt->fetch();
if (!$att) {
    http_response_code(404);
    exit('الملف غير موجود.');
}

$path = DATA_DIR . '/clinical_uploads/' . basename($att['file_path']);
if (!is_file($path)) {
    http_response_code(404);
    exit('الملف غير موجود على الخادم.');
}

log_access('view', 'clinical_note', (int)$att['note_id'], $adminUser);

header('Content-Type: ' . $att['mime_type']);
header('Content-Disposition: inline; filename="' . rawurlencode($att['original_name'] ?: basename($path)) . '"');
header('Content-Length: ' . filesize($path));
header('X-Content-Type-Options: nosniff');
readfile($path);
