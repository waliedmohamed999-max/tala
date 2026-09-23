<?php
require_once __DIR__ . '/functions.php';

/**
 * Roles: owner | editor | reviewer | bookings_manager
 *   owner            -> everything
 *   editor           -> create/edit content, can only move it to in_review (cannot self-publish)
 *   reviewer         -> approve + publish content (the professional review step), can also edit
 *   bookings_manager -> appointments + contact messages only, no content access
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
