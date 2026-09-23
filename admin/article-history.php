<?php
require_once __DIR__ . '/../includes/auth.php';
$adminUser = require_content_access();
$activeNav = 'articles';

$articleId = (int)($_GET['id'] ?? 0);
$stmt = db()->prepare('SELECT * FROM articles WHERE id = ?');
$stmt->execute([$articleId]);
$article = $stmt->fetch();
if (!$article) {
    flash_set('error', 'المقالة غير موجودة.');
    redirect('/admin/articles.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify() && ($_POST['action'] ?? '') === 'restore') {
    $revisionId = (int)($_POST['revision_id'] ?? 0);
    $revStmt = db()->prepare('SELECT * FROM article_revisions WHERE id = ? AND article_id = ?');
    $revStmt->execute([$revisionId, $articleId]);
    $revision = $revStmt->fetch();

    if ($revision) {
        // Snapshot what's live right now BEFORE overwriting, so restoring
        // never erases history — it just adds a new entry on top of it.
        create_article_revision($articleId, (int)$adminUser['id'], $adminUser['name']);

        $wasLive = $article['workflow_status'] === 'approved' || $article['published_content'] !== null;
        $newStatus = $wasLive ? 'in_review' : $article['workflow_status'];

        $contentHtml = simple_text_to_html($revision['content_raw']);
        $upd = db()->prepare("UPDATE articles SET
            title=?, excerpt=?, content=?, content_raw=?, category_id=?, content_type=?, sources=?,
            read_minutes=?, workflow_status=?, updated_at=datetime('now'), updated_by=?, version=version+1
            WHERE id=?");
        $upd->execute([
            $revision['title'], $revision['excerpt'], $contentHtml, $revision['content_raw'],
            $revision['category_id'], $revision['content_type'], $revision['sources'],
            estimate_read_minutes($contentHtml), $newStatus, $adminUser['name'], $articleId,
        ]);
        db()->prepare('DELETE FROM article_autosaves WHERE article_id = ?')->execute([$articleId]);

        create_article_revision($articleId, (int)$adminUser['id'], $adminUser['name'], $revisionId);

        flash_set('success', 'تم استعادة النسخة القديمة كمسودة عمل جديدة.' . ($wasLive ? ' بما إنه المقال كان منشور/معتمد، رجع لحالة "بانتظار المراجعة" — النسخة المنشورة الحالية ضلت زي ما هي لحد ما تُعتمد النسخة الجديدة.' : ''));
    }
    redirect('/admin/article-history.php?id=' . $articleId);
}

$stmt = db()->prepare('SELECT * FROM article_revisions WHERE article_id = ? ORDER BY created_at DESC');
$stmt->execute([$articleId]);
$revisions = $stmt->fetchAll();

$compareId = (int)($_GET['compare'] ?? 0);
$diff = null;
$compareRevision = null;
if ($compareId) {
    $c = db()->prepare('SELECT * FROM article_revisions WHERE id = ? AND article_id = ?');
    $c->execute([$compareId, $articleId]);
    $compareRevision = $c->fetch();
    if ($compareRevision) {
        $diff = simple_diff_lines($compareRevision['content_raw'], $article['content_raw']);
    }
}

$pageTitle = 'سجل النسخ: ' . $article['title'];
require __DIR__ . '/includes/layout_top.php';
?>

<div class="admin-page-head">
  <h1>سجل النسخ</h1>
  <a href="/admin/article-edit.php?id=<?= $articleId ?>" class="btn btn-outline">رجوع للتحرير</a>
</div>
<p class="field-hint" style="margin-bottom:20px;"><?= e($article['title']) ?></p>

<?php if ($diff !== null): ?>
  <div class="admin-card" style="margin-bottom:24px;">
    <h3 style="margin-top:0;">الفرق بين النسخة المحفوظة بتاريخ <?= e(date('Y/m/d H:i', strtotime($compareRevision['created_at']))) ?> والنسخة الحالية</h3>
    <div style="font-family:monospace; font-size:.85rem; line-height:1.8; background:var(--color-bg-alt); border-radius:var(--radius-sm); padding:14px; max-height:400px; overflow:auto;">
      <?php foreach ($diff as $line): ?>
        <?php if ($line['type'] === 'same'): ?>
          <div style="color:var(--color-text-muted);"><?= e($line['text']) ?: '&nbsp;' ?></div>
        <?php elseif ($line['type'] === 'removed'): ?>
          <div style="background:#FBEAE8; color:var(--color-danger); text-decoration:line-through;">− <?= e($line['text']) ?></div>
        <?php else: ?>
          <div style="background:#E7F3EC; color:var(--color-success);">+ <?= e($line['text']) ?></div>
        <?php endif; ?>
      <?php endforeach; ?>
    </div>
  </div>
<?php endif; ?>

<div class="admin-card">
  <?php if (empty($revisions)): ?>
    <p>ما في نسخ سابقة محفوظة لهالمقالة لسا — أول حفظ حقيقي رح يسجّل أول نسخة.</p>
  <?php else: ?>
    <table class="data-table">
      <thead><tr><th>التاريخ</th><th>المحرر</th><th>الحالة وقتها</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($revisions as $r): ?>
          <tr>
            <td><?= e(date('Y/m/d H:i', strtotime($r['created_at']))) ?><?= $r['restored_from_revision_id'] ? ' <span class="field-hint">(استعادة)</span>' : '' ?></td>
            <td><?= e($r['editor_name'] ?: '—') ?></td>
            <td><?= e($r['workflow_status']) ?></td>
            <td class="action-links">
              <a href="/admin/article-history.php?id=<?= $articleId ?>&compare=<?= (int)$r['id'] ?>">قارن بالحالي</a>
              <form method="post" style="display:inline;" onsubmit="return confirm('استعادة هالنسخة؟ رح تنحفظ نسخة من الحالة الحالية قبل ما نستبدلها.');">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="restore">
                <input type="hidden" name="revision_id" value="<?= (int)$r['id'] ?>">
                <button type="submit">استعادة</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
