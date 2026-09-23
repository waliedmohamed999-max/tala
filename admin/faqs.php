<?php
require_once __DIR__ . '/../includes/auth.php';
require_content_access();
$activeNav = 'faqs';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify()) {
    $action = $_POST['action'] ?? '';
    if ($action === 'create') {
        $q = trim($_POST['question'] ?? '');
        $a = trim($_POST['answer'] ?? '');
        if ($q !== '' && $a !== '') {
            $maxOrder = (int)db()->query('SELECT COALESCE(MAX(sort_order),0) FROM faqs')->fetchColumn();
            $ins = db()->prepare('INSERT INTO faqs (question, answer, sort_order, is_published) VALUES (?, ?, ?, 1)');
            $ins->execute([$q, $a, $maxOrder + 1]);
            flash_set('success', 'تمت إضافة السؤال.');
        }
    } elseif ($action === 'update') {
        $id = (int)($_POST['id'] ?? 0);
        $q = trim($_POST['question'] ?? '');
        $a = trim($_POST['answer'] ?? '');
        $order = (int)($_POST['sort_order'] ?? 0);
        $published = !empty($_POST['is_published']) ? 1 : 0;
        $upd = db()->prepare('UPDATE faqs SET question=?, answer=?, sort_order=?, is_published=? WHERE id=?');
        $upd->execute([$q, $a, $order, $published, $id]);
        flash_set('success', 'تم حفظ التعديلات.');
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $del = db()->prepare('DELETE FROM faqs WHERE id = ?');
        $del->execute([$id]);
        flash_set('success', 'تم حذف السؤال.');
    }
    redirect('/admin/faqs.php');
}

$faqs = db()->query('SELECT * FROM faqs ORDER BY sort_order, id')->fetchAll();
$pageTitle = 'الأسئلة الشائعة';
require __DIR__ . '/includes/layout_top.php';
?>

<div class="admin-page-head"><h1>الأسئلة الشائعة</h1></div>

<div class="admin-card" style="margin-bottom:24px;">
  <h3>إضافة سؤال جديد</h3>
  <form method="post" class="form-narrow">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="create">
    <div class="form-group"><label>السؤال</label><input type="text" name="question" required></div>
    <div class="form-group"><label>الجواب</label><textarea name="answer" required></textarea></div>
    <button type="submit" class="btn btn-primary">إضافة</button>
  </form>
</div>

<?php foreach ($faqs as $f): ?>
  <div class="admin-card" style="margin-bottom:16px;">
    <form method="post" class="form-narrow">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="update">
      <input type="hidden" name="id" value="<?= (int)$f['id'] ?>">
      <div class="form-group"><label>السؤال</label><input type="text" name="question" value="<?= e($f['question']) ?>" required></div>
      <div class="form-group"><label>الجواب</label><textarea name="answer" required><?= e($f['answer']) ?></textarea></div>
      <div class="form-row-2">
        <div class="form-group"><label>الترتيب</label><input type="number" name="sort_order" value="<?= (int)$f['sort_order'] ?>"></div>
        <div class="form-group">
          <label><input type="checkbox" name="is_published" value="1" <?= (int)$f['is_published'] === 1 ? 'checked' : '' ?> style="width:auto;"> منشور</label>
        </div>
      </div>
      <div style="display:flex; gap:10px;">
        <button type="submit" class="btn btn-primary">حفظ</button>
      </div>
    </form>
    <form method="post" onsubmit="return confirm('حذف هالسؤال؟');" style="margin-top:8px;">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="delete">
      <input type="hidden" name="id" value="<?= (int)$f['id'] ?>">
      <button type="submit" class="action-links"><span class="danger">حذف السؤال</span></button>
    </form>
  </div>
<?php endforeach; ?>

<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
