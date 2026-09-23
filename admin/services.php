<?php
require_once __DIR__ . '/../includes/auth.php';
require_content_access();
$activeNav = 'services';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify() && ($_POST['action'] ?? '') === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    $inUse = db()->prepare('SELECT COUNT(*) FROM appointment_requests WHERE service_id = ?');
    $inUse->execute([$id]);
    if ((int)$inUse->fetchColumn() > 0) {
        flash_set('error', 'ما فيك تحذف خدمة مرتبطة بطلبات مواعيد موجودة. فيك تلغي نشرها بدل الحذف.');
    } else {
        $del = db()->prepare('DELETE FROM services WHERE id = ?');
        $del->execute([$id]);
        flash_set('success', 'تم حذف الخدمة.');
    }
    redirect('/admin/services.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify() && ($_POST['action'] ?? '') === 'toggle_publish') {
    $id = (int)($_POST['id'] ?? 0);
    $upd = db()->prepare('UPDATE services SET is_published = 1 - is_published WHERE id = ?');
    $upd->execute([$id]);
    redirect('/admin/services.php');
}

$services = db()->query('SELECT * FROM services ORDER BY sort_order, name')->fetchAll();
$pageTitle = 'الخدمات';
require __DIR__ . '/includes/layout_top.php';
?>

<div class="admin-page-head">
  <h1>الخدمات</h1>
  <a href="/admin/service-edit.php" class="btn btn-primary">+ خدمة جديدة</a>
</div>

<div class="notice-banner" style="margin-bottom:20px;">
  <strong>تذكير</strong>
  لا تنشر أي خدمة قبل ما تتأكد التفاصيل (المدة، السعر، وطريقة الحضور) من تالا. الخدمة غير المنشورة ما بتظهر للزوار إطلاقًا.
</div>

<div class="admin-card">
  <?php if (empty($services)): ?>
    <p>ما في خدمات مضافة لسا.</p>
  <?php else: ?>
    <table class="data-table">
      <thead><tr><th>الاسم</th><th>الحالة</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($services as $s): ?>
          <tr>
            <td><?= e($s['name']) ?></td>
            <td><span class="status-badge <?= $s['is_published'] ? 'status-confirmed' : 'status-new' ?>"><?= $s['is_published'] ? 'منشورة' : 'مسودة' ?></span></td>
            <td class="action-links">
              <a href="/admin/service-edit.php?id=<?= (int)$s['id'] ?>">تعديل</a>
              <form method="post" style="display:inline;">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="toggle_publish">
                <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                <button type="submit"><?= $s['is_published'] ? 'إلغاء النشر' : 'نشر' ?></button>
              </form>
              <form method="post" style="display:inline;" onsubmit="return confirm('حذف هالخدمة نهائيًا؟');">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
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
