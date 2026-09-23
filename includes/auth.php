<?php
require_once __DIR__ . '/functions.php';

/**
 * Roles: owner | editor | reviewer | bookings_manager | financial
 *   owner            -> everything
 *   editor           -> create/edit content, can only move it to in_review (cannot self-publish)
 *   reviewer         -> approve + publish content (the professional review step); also the
 *                        clinical-access role (Tala herself) — see require_clinical_access()
 *   bookings_manager -> appointments/calendar/patients (administrative fields only) + contact messages
 *   financial        -> billing/invoices only, plus the minimal patient name needed for a receipt
 */

function current_admin(): ?array
{
    if (empty($_SESSION['admin_id'])) {
        return null;
    }
    static $user = null;
    if ($user === null) {
        $stmt = db()->prepare('SELECT * FROM admin_users WHERE id = ? AND is_active = 1');
        $stmt->execute([$_SESSION['admin_id']]);
        $user = $stmt->fetch() ?: false;

        // Session revocation: an owner can force-logout a user (e.g. a lost
        // device) from admin/user-edit.php, which stamps force_logout_at.
        // Any session whose login happened before that stamp is invalidated
        // here rather than trusting the still-valid PHP session cookie.
        if ($user && $user['force_logout_at'] && (!isset($_SESSION['admin_login_at']) || $_SESSION['admin_login_at'] < $user['force_logout_at'])) {
            logout_admin();
            $user = false;
        }
    }
    return $user ?: null;
}

function require_admin(): array
{
    $user = current_admin();
    if (!$user) {
        redirect('/admin/index.php?next=' . urlencode($_SERVER['REQUEST_URI'] ?? ''));
    }
    return $user;
}

function require_role(string ...$roles): array
{
    $user = require_admin();
    if (!in_array($user['role'], $roles, true)) {
        http_response_code(403);
        echo 'ما عندك صلاحية توصل لهالصفحة.';
        exit;
    }
    return $user;
}

/** Content CRUD access: owner, editor, reviewer. */
function require_content_access(): array
{
    return require_role('owner', 'editor', 'reviewer');
}

/** Approve/publish access — the professional-review step. */
function require_review_access(): array
{
    return require_role('owner', 'reviewer');
}

/** Bookings + contact messages access. */
function require_bookings_access(): array
{
    return require_role('owner', 'bookings_manager');
}

/**
 * Calendar/patients administrative access (schedule, patient contact info,
 * archiving) — NOT clinical notes. See require_clinical_access() below.
 */
function require_clinic_ops_access(): array
{
    return require_role('owner', 'bookings_manager');
}

/**
 * Broader READ access to a patient's page than require_clinic_ops_access():
 * a reviewer (the clinical role) needs to open a patient's page to reach
 * their clinical notes, even though they have no administrative rights
 * over the patient record itself. Individual admin actions (edit/archive/
 * delete/create) stay gated behind require_clinic_ops_access() at the
 * point of action, not here.
 */
function require_patient_view_access(): array
{
    return require_role('owner', 'bookings_manager', 'reviewer');
}

/**
 * Clinical file access (notes, attachments) — owner + reviewer only, since
 * "reviewer" is the professional-review role Tala herself holds. A distinct
 * function name (rather than reusing require_review_access() directly) so a
 * future split between "content reviewer" and "clinician" roles doesn't
 * require touching every clinical call site.
 */
function require_clinical_access(): array
{
    return require_role('owner', 'reviewer');
}

/** Billing/invoices access. */
function require_financial_access(): array
{
    return require_role('owner', 'financial');
}

function can_review(array $user): bool
{
    return in_array($user['role'], ['owner', 'reviewer'], true);
}

function role_label(string $role): string
{
    return [
        'owner' => 'مالك الموقع',
        'editor' => 'محرر محتوى',
        'reviewer' => 'مراجع مهني',
        'bookings_manager' => 'مسؤول حجوزات',
        'financial' => 'مسؤول مالي',
    ][$role] ?? $role;
}

function attempt_login(string $email, string $password): ?array
{
    if (!rate_limit_check('admin_login', 8, 900)) {
        return null;
    }
    $stmt = db()->prepare('SELECT * FROM admin_users WHERE email = ? AND is_active = 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    if ($user && password_verify($password, $user['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['admin_id'] = $user['id'];
        $_SESSION['admin_login_at'] = date('Y-m-d H:i:s');
        $upd = db()->prepare("UPDATE admin_users SET last_login_at = datetime('now') WHERE id = ?");
        $upd->execute([$user['id']]);
        return $user;
    }
    return null;
}

function logout_admin(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
    }
    session_destroy();
}

function any_admin_exists(): bool
{
    return (int)db()->query('SELECT COUNT(*) FROM admin_users')->fetchColumn() > 0;
}

/** Invalidates every currently active session for this user (see current_admin()'s force_logout_at check). */
function revoke_user_sessions(int $userId): void
{
    $upd = db()->prepare("UPDATE admin_users SET force_logout_at = datetime('now') WHERE id = ?");
    $upd->execute([$userId]);
}
