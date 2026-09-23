<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/mailer.php';
$adminUser = require_bookings_access();

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_verify()) {
    flash_set('error', 'في مشكلة تقنية. جرّب من جديد.');
    redirect('/admin/appointments.php');
}

$appointmentId = (int)($_POST['appointment_id'] ?? 0);
$templateKey = $_POST['template_key'] ?? '';

$stmt = db()->prepare('SELECT * FROM appointment_requests WHERE id = ?');
$stmt->execute([$appointmentId]);
$appointment = $stmt->fetch();
if (!$appointment) {
    flash_set('error', 'الطلب غير موجود.');
    redirect('/admin/appointments.php');
}

$tplStmt = db()->prepare('SELECT * FROM reply_templates WHERE `key` = ?');
$tplStmt->execute([$templateKey]);
$template = $tplStmt->fetch();
if (!$template) {
    flash_set('error', 'القالب غير موجود.');
    redirect('/admin/appointments.php?id=' . $appointmentId);
}

// Confirmation messages only make sense once the request is actually
// confirmed — never send "your appointment is confirmed" just because a
// form arrived. Received/reschedule/unavailable have no such restriction.
if ($templateKey === 'confirmed' && $appointment['status'] !== 'confirmed') {
    flash_set('error', 'ما فيك ترسل رسالة "تأكيد الموعد" قبل ما تغيّر حالة الطلب لـ«مؤكد» فعليًا.');
    redirect('/admin/appointments.php?id=' . $appointmentId);
}

if ($appointment['contact_method'] !== 'email') {
    flash_set('error', 'الإرسال الآلي متاح بس لطلبات وسيلتها بريد إلكتروني. لباقي الوسائل، انسخ النص يدويًا وأرسله عبر هاتف/واتساب.');
    redirect('/admin/appointments.php?id=' . $appointmentId);
}

if (!mailer_is_configured()) {
    flash_set('error', 'البريد غير مربوط بعد — اضبطه من إعدادات الإشعارات والبريد أولًا.');
    redirect('/admin/appointments.php?id=' . $appointmentId);
}

$body = str_replace('[الاسم]', $appointment['name'], $template['body']);
$result = smtp_send_mail($appointment['contact_value'], $template['label'] . ' — تالا | معالجة نفسية', $body);
log_notification($templateKey, $appointment['contact_value'], $template['label'], $result['success'], $result['error'], $appointmentId, $adminUser['name']);

if ($result['success']) {
    flash_set('success', 'تم إرسال الرسالة فعليًا للزائر.');
} else {
    flash_set('error', 'فشل الإرسال: ' . $result['error']);
}
redirect('/admin/appointments.php?id=' . $appointmentId);
