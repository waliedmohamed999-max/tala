<?php
require_once __DIR__ . '/../includes/auth.php';
$adminUser = require_admin();
header('Content-Type: application/json; charset=utf-8');

$q = trim($_GET['q'] ?? '');
if (mb_strlen($q) < 2) {
    echo json_encode(['groups' => []]);
    exit;
}
$like = '%' . $q . '%';
$groups = [];

// Every branch below is gated by the SAME role checks used to guard the
// real pages (auth.php) — never by hiding results in the client. A role
// that cannot open patients.php can never see a patient name here either.
if (in_array($adminUser['role'], ['owner', 'bookings_manager', 'reviewer'], true)) {
    $stmt = db()->prepare("SELECT id, full_name, file_number FROM patients WHERE full_name LIKE ? OR file_number LIKE ? LIMIT 6");
    $stmt->execute([$like, $like]);
    $rows = $stmt->fetchAll();
    if ($rows) {
        $groups[] = ['label' => 'المرضى', 'items' => array_map(fn($r) => [
            'title' => $r['full_name'], 'subtitle' => $r['file_number'], 'href' => '/admin/patient-view.php?id=' . $r['id'],
        ], $rows)];
    }
}

if (in_array($adminUser['role'], ['owner', 'bookings_manager'], true)) {
    $stmt = db()->prepare("SELECT id, name, reference_code, status FROM appointment_requests WHERE name LIKE ? OR reference_code LIKE ? ORDER BY created_at DESC LIMIT 6");
    $stmt->execute([$like, $like]);
    $rows = $stmt->fetchAll();
    if ($rows) {
        $groups[] = ['label' => 'طلبات المواعيد', 'items' => array_map(fn($r) => [
            'title' => $r['name'], 'subtitle' => $r['reference_code'] ?: ('#' . $r['id']), 'href' => '/admin/appointments.php?id=' . $r['id'],
        ], $rows)];
    }
}

if (in_array($adminUser['role'], ['owner', 'editor', 'reviewer'], true)) {
    $stmt = db()->prepare("SELECT id, title FROM articles WHERE title LIKE ? LIMIT 6");
    $stmt->execute([$like]);
    $rows = $stmt->fetchAll();
    if ($rows) {
        $groups[] = ['label' => 'المقالات', 'items' => array_map(fn($r) => [
            'title' => $r['title'], 'subtitle' => '', 'href' => '/admin/article-edit.php?id=' . $r['id'],
        ], $rows)];
    }

    $stmt = db()->prepare("SELECT id, name FROM services WHERE name LIKE ? LIMIT 6");
    $stmt->execute([$like]);
    $rows = $stmt->fetchAll();
    if ($rows) {
        $groups[] = ['label' => 'الخدمات', 'items' => array_map(fn($r) => [
            'title' => $r['name'], 'subtitle' => '', 'href' => '/admin/service-edit.php?id=' . $r['id'],
        ], $rows)];
    }
}

if ($adminUser['role'] === 'owner') {
    $stmt = db()->prepare("SELECT id, name, email FROM admin_users WHERE name LIKE ? OR email LIKE ? LIMIT 6");
    $stmt->execute([$like, $like]);
    $rows = $stmt->fetchAll();
    if ($rows) {
        $groups[] = ['label' => 'المستخدمون', 'items' => array_map(fn($r) => [
            'title' => $r['name'], 'subtitle' => $r['email'], 'href' => '/admin/user-edit.php?id=' . $r['id'],
        ], $rows)];
    }
}

echo json_encode(['groups' => $groups], JSON_UNESCAPED_UNICODE);
