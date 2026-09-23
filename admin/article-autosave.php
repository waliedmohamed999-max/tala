<?php
require_once __DIR__ . '/../includes/auth.php';
$adminUser = require_content_access();

header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !csrf_verify()) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'طلب غير صحيح.']);
    exit;
}

$articleId = (int)($_POST['id'] ?? 0);
if ($articleId <= 0) {
    // New, unsaved article — nothing to autosave against server-side yet.
    // The browser still guards against tab-close via beforeunload.
    echo json_encode(['success' => true, 'skipped' => true]);
    exit;
}

$stmt = db()->prepare('SELECT version FROM articles WHERE id = ?');
$stmt->execute([$articleId]);
$currentVersion = $stmt->fetchColumn();
if ($currentVersion === false) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'المقالة غير موجودة.']);
    exit;
}

// Has someone else saved (real save, not autosave) since this editor loaded the page?
$baseVersion = (int)($_POST['base_version'] ?? 0);
$versionConflict = $baseVersion > 0 && (int)$currentVersion !== $baseVersion;

// Is someone ELSE actively autosaving this same article right now?
$existing = db()->prepare('SELECT saved_by_id, saved_by_name, saved_at FROM article_autosaves WHERE article_id = ?');
$existing->execute([$articleId]);
$existingAutosave = $existing->fetch();
$otherEditorActive = $existingAutosave
    && (int)$existingAutosave['saved_by_id'] !== (int)$adminUser['id']
    && strtotime($existingAutosave['saved_at']) > (time() - 120);

$upsert = db()->prepare('INSERT INTO article_autosaves
    (article_id, title, excerpt, content_raw, category_id, content_type, sources, base_version, saved_by_id, saved_by_name, saved_at)
    VALUES (?,?,?,?,?,?,?,?,?,?, datetime("now"))
    ON CONFLICT(article_id) DO UPDATE SET
        title=excluded.title, excerpt=excluded.excerpt, content_raw=excluded.content_raw,
        category_id=excluded.category_id, content_type=excluded.content_type, sources=excluded.sources,
        base_version=excluded.base_version, saved_by_id=excluded.saved_by_id, saved_by_name=excluded.saved_by_name,
        saved_at=datetime("now")');
$upsert->execute([
    $articleId,
    trim($_POST['title'] ?? ''),
    trim($_POST['excerpt'] ?? ''),
    trim($_POST['content_raw'] ?? ''),
    $_POST['category_id'] ?: null,
    $_POST['content_type'] ?? 'article',
    trim($_POST['sources'] ?? ''),
    $baseVersion,
    $adminUser['id'],
    $adminUser['name'],
]);

echo json_encode([
    'success' => true,
    'saved_at' => date('H:i:s'),
    'version_conflict' => $versionConflict,
    'other_editor_active' => $otherEditorActive,
    'other_editor_name' => $otherEditorActive ? $existingAutosave['saved_by_name'] : null,
]);
