<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/mailer.php';

// Standalone handler for the footer newsletter widget — entirely separate
// from the booking/contact forms. Never touches appointment_requests, and a
// visitor requesting an appointment is never added here.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('/index.php');
}

$email = trim($_POST['email'] ?? '');
$consent = !empty($_POST['newsletter_consent']);
$sourcePath = $_POST['redirect_to'] ?? '/index.php';
$redirectTo = str_starts_with($sourcePath, '/') ? $sourcePath : '/index.php';

if (!csrf_verify() || !empty($_POST['website'])) {
    redirect($redirectTo . '?newsletter=error');
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || !$consent) {
    redirect($redirectTo . '?newsletter=error');
}
if (!rate_limit_check('newsletter_subscribe', 5, 3600)) {
    redirect($redirectTo . '?newsletter=error');
}

$doubleOptInAvailable = mailer_is_configured();

$existing = db()->prepare('SELECT id, status FROM newsletter_subscribers WHERE email = ?');
$existing->execute([$email]);
$row = $existing->fetch();

if ($row && $row['status'] === 'subscribed') {
    redirect($redirectTo . '?newsletter=already');
}

if ($row && $row['status'] === 'pending') {
    redirect($redirectTo . '?newsletter=pending');
}

$confirmToken = bin2hex(random_bytes(16));
$unsubToken = bin2hex(random_bytes(16));

if ($row) {
    // Was unsubscribed — resuming requires fresh consent + (if available) reconfirmation.
    $upd = db()->prepare("UPDATE newsletter_subscribers SET
        status = ?, consent_source = ?, confirm_token = ?, unsubscribe_token = ?, confirmed_at = NULL, created_at = datetime('now')
        WHERE id = ?");
    $upd->execute([$doubleOptInAvailable ? 'pending' : 'subscribed', $sourcePath, $confirmToken, $unsubToken, $row['id']]);
} else {
    $ins = db()->prepare('INSERT INTO newsletter_subscribers (email, status, consent_source, confirm_token, unsubscribe_token) VALUES (?,?,?,?,?)');
    $ins->execute([$email, $doubleOptInAvailable ? 'pending' : 'subscribed', $sourcePath, $confirmToken, $unsubToken]);
}

if ($doubleOptInAvailable) {
    $confirmUrl = site_base_url() . '/newsletter-confirm.php?token=' . $confirmToken;
    $body = "أهلًا،\n\nوصلنا طلب اشتراكك بنشرة تالا البريدية. لتأكيد الاشتراك، افتح هالرابط:\n{$confirmUrl}\n\nإذا ما كنت إنت يلي طلب الاشتراك، تجاهل هالرسالة ببساطة.";
    $result = smtp_send_mail($email, 'أكّد اشتراكك بنشرة تالا', $body);
    log_notification('newsletter_confirm', $email, 'أكّد اشتراكك بنشرة تالا', $result['success'], $result['error']);
    redirect($redirectTo . '?newsletter=pending');
}

redirect($redirectTo . '?newsletter=ok');
