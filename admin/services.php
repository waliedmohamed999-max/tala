<?php
require_once __DIR__ . '/../includes/auth.php';
$adminUser = require_content_access();
$activeNav = 'services';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify() && ($_POST['action'] ?? '') === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    $inUse = db()->prepare('SELECT COUNT(*) FROM appointment_requests WHERE service_id = ?');
    $inUse->execute([$id]);
    $inUse2 = db()->prepare('SELECT COUNT(*) FROM appointments WHERE service_id = ?');
    $inUse2->execute([$id]);
    if ((int)$inUse->fetchColumn() > 0 || (int)$inUse2->fetchColumn() > 0) {
        flash_set('error', 'ما فيك تحذف خدمة مرتبطة بطلبات مواعيد أو مواعيد فعلية. فيك توقفها بدل الحذف.');
    } else {
        $del = db()->prepare('DELETE FROM services WHERE id = ?');
        $del->execute([$id]);
        flash_set('success', 'تم حذف الخدمة.');
    }
    redirect('/admin/services.php');
}

// Editors/reviewers/owner can submit a draft (or a paused service) for
// review — this never publishes anything by itself, only reviewer/owner can
// actually publish, from /admin/review-queue.php or the "نشر" action below.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify() && ($_POST['action'] ?? '') === 'submit_review') {
    $id = (int)($_POST['id'] ?? 0);
    set_service_workflow_status($id, 'pending_review');
    flash_set('success', 'تم إرسال الخدمة لقائمة الاعتماد.');
    redirect('/admin/services.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify() && ($_POST['action'] ?? '') === 'publish' && can_review($adminUser)) {
    $id = (int)($_POST['id'] ?? 0);
    set_service_workflow_status($id, 'published', $adminUser['name']);
    flash_set('success', 'تم نشر الخدمة.');
    redirect('/admin/services.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify() && ($_POST['action'] ?? '') === 'pause' && can_review($adminUser)) {
    $id = (int)($_POST['id'] ?? 0);
    set_service_workflow_status($id, 'paused', $adminUser['name']);
    flash_set('success', 'تم إيقاف نشر الخدمة.');
    redirect('/admin/services.php');
}

$services = db()->query('SELECT * FROM services ORDER BY sort_order, name')->fetchAll();
$statusLabels = [
    'draft' => ['مسودة', 'status-new'],
    'pending_review' => ['بانتظار اعتماد تالا', 'status-contacting'],
    'published' => ['منشورة', 'status-confirmed'],
    'paused' => ['موقوفة', 'status-paused'],
];
$pageTitle = 'الخدمات';
require __DIR__ . '/includes/layout_top.php';
?>

<div class="admin-page-head">
  <h1>الخدمات</h1>
  <a href="/admin/service-edit.php" class="btn btn-primary">+ خدمة جديدة</a>
</div>

<div class="notice-banner" style="margin-bottom:20px;">
  <strong>تذكير</strong>
  لا تنشر أي خدمة قبل ما تتأكد التفاصيل (المدة، السعر، وطريقة الحضور) من تالا. الخدمة غير المنشورة ما بتظهر للزوار إطلاقًا، والحجز الذاتي يحتاج نشر + مدة بالدقائق + ساعات عمل مضبوطة.
</div>

<div class="admin-card">
  <?php if (empty($services)): ?>
    <p>ما في خدمات مضافة لسا.</p>
  <?php else: ?>
    <table class="data-table">
      <thead><tr><th>الاسم</th><th>الحالة</th><th>الحجز الذاتي</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($services as $s): ?>
          <?php [$label, $cls] = $statusLabels[$s['workflow_status']] ?? ['—', 'status-new']; ?>
          <tr>
            <td><?= e($s['name']) ?></td>
            <td><span class="status-badge <?= $cls ?>"><?= $label ?></span></td>
            <td><?= ($s['workflow_status'] === 'published' && $s['bookable_online']) ? '✓ فعّال' : '—' ?></td>
            <td class="action-links">
              <a href="/admin/service-edit.php?id=<?= (int)$s['id'] ?>">تعديل</a>
              <?php if (in_array($s['workflow_status'], ['draft', 'paused'], true)): ?>
                <form method="post" style="display:inline;">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="submit_review">
                  <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                  <button type="submit">أرسل للاعتماد</button>
                </form>
              <?php endif; ?>
              <?php if (can_review($adminUser) && in_array($s['workflow_status'], ['pending_review', 'paused'], true)): ?>
                <form method="post" style="display:inline;">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="publish">
                  <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                  <button type="submit">نشر</button>
                </form>
              <?php endif; ?>
              <?php if (can_review($adminUser) && $s['workflow_status'] === 'published'): ?>
                <form method="post" style="display:inline;">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="pause">
                  <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                  <button type="submit">إيقاف</button>
                </form>
              <?php endif; ?>
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
