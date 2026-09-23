<?php
require_once __DIR__ . '/../includes/auth.php';
$adminUser = require_content_access();
$activeNav = 'articles';

$workflowLabels = [
    'draft' => 'مسودة',
    'in_review' => 'بانتظار المراجعة',
    'changes_requested' => 'بانتظار تعديل المحرر',
    'approved' => 'معتمدة (بانتظار النشر)',
    'published' => 'منشورة',
    'needs_update' => 'بحاجة تحديث',
    'archived' => 'مؤرشفة',
];
$workflowBadge = [
    'draft' => 'status-new',
    'in_review' => 'status-contacting',
    'changes_requested' => 'status-cancelled',
    'approved' => 'status-contacting',
    'published' => 'status-confirmed',
    'needs_update' => 'status-cancelled',
    'archived' => 'status-cancelled',
];
$contentTypeLabels = ['article' => 'مقالة', 'guide' => 'دليل خطوة بخطوة', 'faq_extended' => 'أسئلة وأجوبة موسعة'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify() && ($_POST['action'] ?? '') === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    $del = db()->prepare('DELETE FROM articles WHERE id = ?');
    $del->execute([$id]);
    flash_set('success', 'تم حذف المقالة.');
    redirect('/admin/articles.php');
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify() && ($_POST['action'] ?? '') === 'archive') {
    $id = (int)($_POST['id'] ?? 0);
    $upd = db()->prepare("UPDATE articles SET workflow_status='archived', updated_at=datetime('now') WHERE id = ?");
    $upd->execute([$id]);
    flash_set('success', 'تمت أرشفة المقالة.');
    redirect('/admin/articles.php');
}
// Quick "send to review" action available to editors directly from the list.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify() && ($_POST['action'] ?? '') === 'send_review') {
    $id = (int)($_POST['id'] ?? 0);
    $upd = db()->prepare("UPDATE articles SET workflow_status='in_review', updated_at=datetime('now') WHERE id = ? AND workflow_status IN ('draft','needs_update','changes_requested')");
    $upd->execute([$id]);
    flash_set('success', 'تم إرسال المقالة للمراجعة.');
    redirect('/admin/articles.php');
}
// Reviewer/owner quick actions: approve (in_review -> approved), publish (approved -> live).
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify() && in_array($_POST['action'] ?? '', ['approve', 'publish'], true) && can_review($adminUser)) {
    $id = (int)($_POST['id'] ?? 0);
    if ($_POST['action'] === 'approve') {
        $upd = db()->prepare("UPDATE articles SET workflow_status='approved', reviewer_name=?, last_reviewed_at=datetime('now'), updated_at=datetime('now') WHERE id = ? AND workflow_status = 'in_review'");
        $upd->execute([$adminUser['name'], $id]);
        flash_set('success', 'تم اعتماد المقالة. جاهزة للنشر.');
    } else {
        $check = db()->prepare("SELECT workflow_status FROM articles WHERE id = ?");
        $check->execute([$id]);
        if ($check->fetchColumn() === 'approved') {
            publish_article($id, $adminUser['name']);
            flash_set('success', 'تم نشر المقالة — صارت ظاهرة للزوار.');
        } else {
            flash_set('error', 'لازم تُعتمد المقالة أولًا قبل النشر.');
        }
    }
    redirect('/admin/articles.php');
}

$statusFilter = $_GET['status'] ?? '';
$sql = "SELECT a.*, c.name AS category_name FROM articles a LEFT JOIN categories c ON c.id = a.category_id WHERE 1=1";
$params = [];
if (array_key_exists($statusFilter, $workflowLabels)) {
    $sql .= ' AND a.workflow_status = :status';
    $params[':status'] = $statusFilter;
}
$sql .= ' ORDER BY a.updated_at DESC';
$stmt = db()->prepare($sql);
$stmt->execute($params);
$articles = $stmt->fetchAll();

$pageTitle = 'المقالات والأدلة';
require __DIR__ . '/includes/layout_top.php';
?>

<div class="admin-page-head">
  <h1>المقالات والأدلة</h1>
  <a href="/admin/article-edit.php" class="btn btn-primary">+ محتوى جديد</a>
</div>

<div class="notice-inline" style="margin-bottom:20px;">
  سير العمل: <strong>مسودة</strong> ← <strong>بانتظار المراجعة</strong> ← <strong>معتمدة</strong> ← <strong>منشورة</strong>. بس المحتوى المنشور يظهر للزوار، وتعديل مقال منشور بيرجعه للمراجعة بدون ما يغيّر النسخة الظاهرة للزوار.
  <?= can_review($adminUser) ? '' : ' إنت بدور محرر محتوى، فيك ترسل للمراجعة بس، مو تعتمد أو تنشر مباشرة.' ?>
</div>

<div class="filter-chips">
  <a href="/admin/articles.php" class="filter-chip <?= $statusFilter === '' ? 'active' : '' ?>">الكل</a>
  <?php foreach ($workflowLabels as $val => $label): ?>
    <a href="/admin/articles.php?status=<?= e($val) ?>" class="filter-chip <?= $statusFilter === $val ? 'active' : '' ?>"><?= e($label) ?></a>
  <?php endforeach; ?>
</div>

<div class="admin-card">
  <?php if (empty($articles)): ?>
    <p>ما في محتوى مطابق.</p>
  <?php else: ?>
    <table class="data-table">
      <thead><tr><th>العنوان</th><th>النوع</th><th>التصنيف</th><th>الحالة</th><th>آخر تحديث</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($articles as $a): ?>
          <tr>
            <td><?= e($a['title']) ?></td>
            <td><?= e($contentTypeLabels[$a['content_type']] ?? $a['content_type']) ?></td>
            <td><?= e($a['category_name'] ?? '—') ?></td>
            <td>
              <span class="status-badge <?= $workflowBadge[$a['workflow_status']] ?? '' ?>"><?= e($workflowLabels[$a['workflow_status']] ?? $a['workflow_status']) ?></span>
              <?php if ($a['published_content'] !== null && $a['workflow_status'] !== 'published'): ?>
                <div class="field-hint">📢 منشورة حاليًا — نسخة جديدة قيد المراجعة</div>
              <?php endif; ?>
            </td>
            <td><?= e(date('Y/m/d', strtotime($a['updated_at']))) ?></td>
            <td class="action-links">
              <a href="/admin/article-edit.php?id=<?= (int)$a['id'] ?>">تعديل</a>
              <a href="/admin/preview-article.php?id=<?= (int)$a['id'] ?>" target="_blank">معاينة المسودة</a>
              <?php if ($a['published_content'] !== null): ?><a href="/articles/article.php?slug=<?= urlencode($a['slug']) ?>" target="_blank">شوف المنشور</a><?php endif; ?>

              <?php if (in_array($a['workflow_status'], ['draft', 'needs_update', 'changes_requested'], true)): ?>
                <form method="post" style="display:inline;">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="send_review">
                  <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                  <button type="submit">أرسل للمراجعة</button>
                </form>
              <?php endif; ?>

              <?php if (can_review($adminUser) && $a['workflow_status'] === 'in_review'): ?>
                <form method="post" style="display:inline;">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="approve">
                  <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                  <button type="submit">اعتماد</button>
                </form>
              <?php endif; ?>
              <?php if (can_review($adminUser) && $a['workflow_status'] === 'approved'): ?>
                <form method="post" style="display:inline;">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="publish">
                  <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                  <button type="submit">نشر</button>
                </form>
              <?php endif; ?>

              <?php if ($a['workflow_status'] !== 'archived'): ?>
              <form method="post" style="display:inline;">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="archive">
                <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                <button type="submit">أرشفة</button>
              </form>
              <?php endif; ?>
              <form method="post" style="display:inline;" onsubmit="return confirm('حذف هالمقالة نهائيًا؟');">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
                <button type="submit" class="danger">حذف</button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
