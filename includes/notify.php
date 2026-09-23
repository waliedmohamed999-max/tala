<?php
require_once __DIR__ . '/mailer.php';

/**
 * Internal "new request arrived" alerts to the practice's own inbox. This is
 * the ONE notification type sent without a staff member clicking send each
 * time — see includes/mailer.php for why that's safe (own inbox only, no
 * visitor-facing content, no personal/health details in the subject, and a
 * true no-op until SMTP is configured and admin_alert_email is set).
 *
 * Visitor-facing messages (received/confirmed/reschedule/unavailable) are
 * NEVER sent from here — those require an explicit click from
 * /admin/appointments.php, see admin/send-reply.php.
 */
function notify_new_booking(int $requestId, string $referenceCode = ''): void
{
    notify_admin_new_booking($requestId, $referenceCode ?: ('#' . $requestId));
}

function notify_new_contact_message(int $messageId): void
{
    $to = get_setting('admin_alert_email');
    if (!mailer_is_configured() || $to === '') {
        return;
    }
    $subject = 'رسالة تواصل جديدة بانتظار المراجعة';
    $body = "وصلت رسالة تواصل جديدة رقم #{$messageId}.\nراجعها من لوحة الإدارة لمعرفة التفاصيل:\n" . site_base_url() . '/admin/messages.php';
    $result = smtp_send_mail($to, $subject, $body);
    log_notification('admin_new_contact', $to, $subject, $result['success'], $result['error'], null, 'system');
}
