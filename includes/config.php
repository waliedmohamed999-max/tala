<?php
/**
 * Central configuration. Copy this file to config.local.php and adjust
 * values there for a real deployment — config.local.php is gitignored and
 * always wins over the defaults below, so real secrets never live in the
 * tracked file.
 */

if (session_status() === PHP_SESSION_NONE) {
    // Session cookie hardening. Set 'secure' to true once the site is served over HTTPS.
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure' => false,
    ]);
    session_name('tala_admin_sess');
    session_start();
}

define('APP_ROOT', dirname(__DIR__));
define('DATA_DIR', APP_ROOT . '/data');
define('DB_PATH', DATA_DIR . '/tala.sqlite');
define('UPLOADS_DIR', APP_ROOT . '/public_uploads');
define('UPLOADS_URL', '/public_uploads');

// Base site info that is NOT editable content (paths, not facts about Tala).
define('SITE_TIMEZONE', 'Asia/Damascus');
date_default_timezone_set(SITE_TIMEZONE);

// Allow an untracked local override file for real deployments (DB creds if
// ever moved to MySQL, SMTP creds for notifications, etc).
$localConfig = __DIR__ . '/config.local.php';
if (file_exists($localConfig)) {
    require $localConfig;
}

// Email/SMTP settings now live in the settings table, managed from
// /admin/notifications-settings.php (owner only) — see includes/mailer.php.
// This keeps the credential outside the codebase (in the gitignored SQLite
// file) without needing a code deploy to change it.

