<?php
require_once __DIR__ . '/functions.php';

/**
 * Minimal, dependency-free SMTP client (no PHPMailer/Composer available in
 * this environment). Supports STARTTLS (587) and implicit TLS/SMTPS (465),
 * AUTH LOGIN, and plain-text UTF-8 messages — everything this site needs to
 * send a short transactional email. Credentials never touch the codebase or
 * version control: they live only in the SQLite settings table, with the
 * password encrypted at rest (see smtp_encrypt/smtp_decrypt below).
 */

// --- credential encryption -------------------------------------------------

function smtp_encryption_key(): string
{
    $keyFile = DATA_DIR . '/.smtp_key';
    if (!file_exists($keyFile)) {
        if (!is_dir(DATA_DIR)) { mkdir(DATA_DIR, 0775, true); }
        file_put_contents($keyFile, random_bytes(32));
        chmod($keyFile, 0600);
    }
    return file_get_contents($keyFile);
}

function smtp_encrypt(string $plain): string
{
    if ($plain === '') { return ''; }
    $key = smtp_encryption_key();
    $iv = random_bytes(16);
    $cipher = openssl_encrypt($plain, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
    return base64_encode($iv . $cipher);
}

function smtp_decrypt(string $encoded): string
{
    if ($encoded === '') { return ''; }
    $key = smtp_encryption_key();
    $raw = base64_decode($encoded);
    if ($raw === false || strlen($raw) < 17) { return ''; }
    $iv = substr($raw, 0, 16);
    $cipher = substr($raw, 16);
    $plain = openssl_decrypt($cipher, 'aes-256-cbc', $key, OPENSSL_RAW_DATA, $iv);
    return $plain === false ? '' : $plain;
}

// --- configuration -----------------------------------------------------

function smtp_config(): array
{
    return [
        'enabled' => setting_bool('smtp_enabled'),
        'host' => get_setting('smtp_host'),
        'port' => (int)get_setting('smtp_port', '587'),
        'encryption' => get_setting('smtp_encryption', 'tls'),
        'username' => get_setting('smtp_username'),
        'password' => smtp_decrypt(get_setting('smtp_password_enc')),
        'from_email' => get_setting('smtp_from_email'),
        'from_name' => get_setting('smtp_from_name', 'تالا | معالجة نفسية'),
    ];
}

function mailer_is_configured(): bool
{
    $c = smtp_config();
    return $c['enabled'] && $c['host'] !== '' && $c['from_email'] !== '';
}

// --- the SMTP client itself ---------------------------------------------

/**
 * Sends one plain-text UTF-8 email. Returns ['success' => bool, 'error' => ?string].
 * Every call — success or failure — should be recorded by the caller via
 * log_notification() so the admin UI reflects real delivery state.
 */
function smtp_send_mail(string $to, string $subject, string $body): array
{
    $c = smtp_config();
    if (!$c['enabled']) {
        return ['success' => false, 'error' => 'البريد غير مفعّل من الإعدادات.'];
    }
    if ($c['host'] === '' || $c['from_email'] === '') {
        return ['success' => false, 'error' => 'إعدادات SMTP غير مكتملة.'];
    }
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
        return ['success' => false, 'error' => 'عنوان المستلم غير صحيح.'];
    }

    $host = $c['host'];
    $port = $c['port'] ?: 587;
    $transport = $c['encryption'] === 'ssl' ? 'ssl://' . $host : $host;

    $errno = 0; $errstr = '';
    $stream = @stream_socket_client($transport . ':' . $port, $errno, $errstr, 15);
    if (!$stream) {
        return ['success' => false, 'error' => "تعذّر الاتصال بسيرفر البريد ({$errstr})"];
    }
    stream_set_timeout($stream, 15);

    try {
        $read = function () use ($stream) {
            $data = '';
            while (($line = fgets($stream, 515)) !== false) {
                $data .= $line;
                if (isset($line[3]) && $line[3] === ' ') { break; }
            }
            return $data;
        };
        $write = function (string $cmd) use ($stream) { fwrite($stream, $cmd . "\r\n"); };
        $expect = function (string $data, string $code) {
            if (strpos($data, $code) !== 0 && strpos($data, "\n{$code}") === false) {
                throw new RuntimeException('رد غير متوقع من سيرفر البريد: ' . trim($data));
            }
        };

        $banner = $read();
        $expect($banner, '220');

        $ehloName = 'localhost';
        $write('EHLO ' . $ehloName);
        $ehloResp = $read();
        $expect($ehloResp, '250');

        if ($c['encryption'] === 'tls') {
            $write('STARTTLS');
            $expect($read(), '220');
            if (!stream_socket_enable_crypto($stream, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('فشل تفعيل التشفير (STARTTLS).');
            }
            $write('EHLO ' . $ehloName);
            $expect($read(), '250');
        }

        if ($c['username'] !== '') {
            $write('AUTH LOGIN');
            $expect($read(), '334');
            $write(base64_encode($c['username']));
            $expect($read(), '334');
            $write(base64_encode($c['password']));
            $authResp = $read();
            $expect($authResp, '235');
        }

        $write('MAIL FROM:<' . $c['from_email'] . '>');
        $expect($read(), '250');
        $write('RCPT TO:<' . $to . '>');
        $expect($read(), '250');
        $write('DATA');
        $expect($read(), '354');

        $encodedSubject = '=?UTF-8?B?' . base64_encode($subject) . '?=';
        $fromHeader = $c['from_name'] !== ''
            ? '=?UTF-8?B?' . base64_encode($c['from_name']) . "?= <{$c['from_email']}>"
            : $c['from_email'];

        $headers = [
            'From: ' . $fromHeader,
            'To: <' . $to . '>',
            'Subject: ' . $encodedSubject,
            'MIME-Version: 1.0',
            'Content-Type: text/plain; charset=UTF-8',
            'Content-Transfer-Encoding: base64',
            'Date: ' . date('r'),
        ];
        // Body is base64-encoded so Arabic text survives any transport quirks
        // and so a lone "." at line-start in the message can't be
        // misinterpreted as the SMTP end-of-data marker.
        $bodyChunks = chunk_split(base64_encode($body));
        $message = implode("\r\n", $headers) . "\r\n\r\n" . $bodyChunks . "\r\n.";
        $write($message);
        $dataResp = $read();
        $expect($dataResp, '250');

        $write('QUIT');
        fclose($stream);
        return ['success' => true, 'error' => null];
    } catch (Throwable $e) {
        if (is_resource($stream)) { fclose($stream); }
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

/**
 * Connects, EHLOs, STARTTLSes and authenticates — but never sends a
 * message. Used by the "اختبار الاتصال" button so the owner can verify
 * host/port/credentials without generating a real email each time.
 */
function smtp_test_connection(?array $overrideConfig = null): array
{
    $c = $overrideConfig ?? smtp_config();
    if ($c['host'] === '') {
        return ['success' => false, 'error' => 'اكتب عنوان سيرفر SMTP أولًا.'];
    }

    $host = $c['host'];
    $port = $c['port'] ?: 587;
    $transport = $c['encryption'] === 'ssl' ? 'ssl://' . $host : $host;

    $errno = 0; $errstr = '';
    $stream = @stream_socket_client($transport . ':' . $port, $errno, $errstr, 12);
    if (!$stream) {
        return ['success' => false, 'error' => "تعذّر الاتصال ({$errstr})"];
    }
    stream_set_timeout($stream, 12);

    try {
        $read = function () use ($stream) {
            $data = '';
            while (($line = fgets($stream, 515)) !== false) {
                $data .= $line;
                if (isset($line[3]) && $line[3] === ' ') { break; }
            }
            return $data;
        };
        $write = function (string $cmd) use ($stream) { fwrite($stream, $cmd . "\r\n"); };
        $expect = function (string $data, string $code) {
            if (strpos($data, $code) !== 0 && strpos($data, "\n{$code}") === false) {
                throw new RuntimeException('رد غير متوقع: ' . trim($data));
            }
        };

        $expect($read(), '220');
        $write('EHLO localhost');
        $expect($read(), '250');

        if ($c['encryption'] === 'tls') {
            $write('STARTTLS');
            $expect($read(), '220');
            if (!stream_socket_enable_crypto($stream, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('فشل تفعيل التشفير (STARTTLS).');
            }
            $write('EHLO localhost');
            $expect($read(), '250');
        }

        if ($c['username'] !== '') {
            $write('AUTH LOGIN');
            $expect($read(), '334');
            $write(base64_encode($c['username']));
            $expect($read(), '334');
            $write(base64_encode($c['password']));
            $expect($read(), '235');
        }

        $write('QUIT');
        fclose($stream);
        return ['success' => true, 'error' => null];
    } catch (Throwable $e) {
        if (is_resource($stream)) { fclose($stream); }
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function log_notification(string $type, string $recipient, string $subject, bool $success, ?string $error = null, ?int $appointmentId = null, ?string $sentBy = null): void
{
    $stmt = db()->prepare('INSERT INTO notification_log (type, appointment_request_id, recipient, subject, status, error_message, sent_by) VALUES (?,?,?,?,?,?,?)');
    $stmt->execute([$type, $appointmentId, $recipient, $subject, $success ? 'sent' : 'failed', $error, $sentBy]);
}

/**
 * The only message type sent WITHOUT explicit staff action per-send: an
 * internal alert to the practice's own inbox that a new request arrived.
 * Never sent to the visitor, never contains health/personal details in the
 * subject line, and is a no-op until SMTP is actually configured.
 */
function notify_admin_new_booking(int $appointmentId, string $referenceCode): void
{
    $to = get_setting('admin_alert_email');
    if (!mailer_is_configured() || $to === '') {
        return;
    }
    // Guard against double-sending the same alert if this function is ever
    // called twice for the same request (e.g. a retry).
    $already = db()->prepare("SELECT COUNT(*) FROM notification_log WHERE type='admin_new_booking' AND appointment_request_id = ? AND status = 'sent'");
    $already->execute([$appointmentId]);
    if ((int)$already->fetchColumn() > 0) {
        return;
    }
    $subject = 'طلب موعد جديد — ' . $referenceCode;
    $body = "وصل طلب موعد جديد رقم {$referenceCode}.\nراجعه من لوحة الإدارة لمعرفة التفاصيل:\n" . site_base_url() . '/admin/appointments.php';
    $result = smtp_send_mail($to, $subject, $body);
    log_notification('admin_new_booking', $to, $subject, $result['success'], $result['error'], $appointmentId, 'system');
}

function site_base_url(): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    return $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost');
}
