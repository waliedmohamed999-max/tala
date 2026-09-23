<?php
require_once __DIR__ . '/../includes/auth.php';
$adminUser = require_patient_view_access();
$canManagePatients = in_array($adminUser['role'], ['owner', 'bookings_manager'], true);
$activeNav = 'patients';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$stmt = db()->prepare('SELECT * FROM patients WHERE id = ?');
$stmt->execute([$id]);
$patient = $stmt->fetch();
if (!$patient) { flash_set('error', 'ملف المريض غير موجود.'); redirect('/admin/patients.php'); }
log_access('view', 'patient', $id, $adminUser);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify() && !$canManagePatients) {
    // Administrative actions (archive/unarchive/delete) require bookings_manager or owner —
    // a reviewer only has read access to reach clinical notes, never these controls.
    flash_set('error', 'ما عندك صلاحية تنفّذ هالإجراء.');
    redirect('/admin/patient-view.php?id=' . $id);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify()) {
    $action = $_POST['action'] ?? '';

    if ($action === 'archive') {
        $reason = trim($_POST['archived_reason'] ?? '');
        if ($reason === '') {
            flash_set('error', 'اكتب سبب الأرشفة.');
        } else {
            db()->prepare("UPDATE patients SET status='archived', archived_at=datetime('now'), archived_reason=? WHERE id=?")->execute([$reason, $id]);
            log_access('archive', 'patient', $id, $adminUser);
            flash_set('success', 'تم أرشفة ملف المريض.');
        }
        redirect('/admin/patient-view.php?id=' . $id);
    }

    if ($action === 'unarchive') {
        db()->prepare("UPDATE patients SET status='active', archived_at=NULL, archived_reason=NULL WHERE id=?")->execute([$id]);
        log_access('edit', 'patient', $id, $adminUser);
        flash_set('success', 'تم إلغاء أرشفة ملف المريض.');
        redirect('/admin/patient-view.php?id=' . $id);
    }

    if ($action === 'delete' && $adminUser['role'] === 'owner') {
        $confirmText = trim($_POST['confirm_text'] ?? '');
        $reason = trim($_POST['delete_reason'] ?? '');
        if ($confirmText !== 'حذف' || $reason === '') {
            flash_set('error', 'لازم تكتب كلمة "حذف" بالضبط وتذكر السبب لتأكيد الحذف النهائي.');
            redirect('/admin/patient-view.php?id=' . $id);
        }
        try {
            log_access('delete', 'patient', $id, $adminUser);
            db()->prepare('DELETE FROM patients WHERE id = ?')->execute([$id]);
            flash_set('success', 'تم حذف ملف المريض نهائيًا.');
            redirect('/admin/patients.php');
        } catch (Throwable $e) {
            flash_set('error', 'ما فيك تحذف مريض إله مواعيد أو سجلات مرتبطة — أرشفته بدل الحذف، أو عالج السجلات المرتبطة فيه أولًا.');
            redirect('/admin/patient-view.php?id=' . $id);
        }
    }
}

$appts = db()->prepare("SELECT a.*, s.name AS service_name FROM appointments a LEFT JOIN services s ON s.id = a.service_id WHERE a.patient_id = ? ORDER BY a.starts_at DESC");
$appts->execute([$id]);
$appointments = $appts->fetchAll();

$canClinical = in_array($adminUser['role'], ['owner', 'reviewer'], true);
$clinicalNotes = [];
if ($canClinical && clinical_module_enabled()) {
    $notesStmt = db()->prepare('SELECT * FROM clinical_notes WHERE patient_id = ? ORDER BY created_at DESC');
    $notesStmt->execute([$id]);
    $clinicalNotes = $notesStmt->fetchAll();
    log_access('view', 'clinical_note', $id, $adminUser);
}

$statusLabels = [
    'pending_confirmation' => ['بانتظار التأكيد', 'status-new'],
    'confirmed' => ['مؤكد', 'status-confirmed'],
    'rescheduled' => ['أُعيدت جدولته', 'status-contacting'],
    'cancelled' => ['أُلغي', 'status-cancelled'],
    'no_show' => ['لم يحضر', 'status-noshow'],
    'completed' => ['انعقد', 'status-done'],
];

$pageTitle = 'ملف المريض — ' . $patient['full_name'];
require __DIR__ . '/includes/layout_top.php';
?>

<div class="admin-page-head">
  <h1><?= e($patient['full_name']) ?> <span class="field-hint">(<?= e($patient['file_number']) ?>)</span></h1>
  <?php if ($canManagePatients): ?><a href="/admin/patient-edit.php?id=<?= (int)$patient['id'] ?>" class="btn btn-outline">تعديل البيانات الإدارية</a><?php endif; ?>
</div>

<div class="admin-card" style="margin-bottom:20px;">
  <div class="grid grid-2">
    <p><strong>الحالة:</strong> <span class="status-badge <?= $patient['status'] === 'active' ? 'status-confirmed' : 'status-cancelled' ?>"><?= $patient['status'] === 'active' ? 'نشط' : 'مؤرشف' ?></span></p>
    <p><strong>وسيلة التواصل:</strong> <?= e($patient['contact_method']) ?> — <?= e($patient['contact_value']) ?></p>
    <p><strong>أوقات مناسبة:</strong> <?= e($patient['contact_preference'] ?: '—') ?></p>
    <p><strong>تاريخ الإنشاء:</strong> <?= e(date('Y/m/d', strtotime($patient['created_at']))) ?></p>
  </div>
  <?php if ($patient['status'] === 'archived'): ?>
    <div class="notice-inline" style="margin-top:14px;">مؤرشف بتاريخ <?= e(date('Y/m/d', strtotime($patient['archived_at']))) ?> — السبب: <?= e($patient['archived_reason']) ?></div>
  <?php endif; ?>

  <?php if ($canManagePatients): ?>
  <div style="display:flex; gap:10px; flex-wrap:wrap; margin-top:16px;">
    <a href="/admin/appointment-edit.php?date=<?= date('Y-m-d') ?>" class="btn btn-outline btn-sm">+ موعد جديد لهالمريض</a>
    <?php if ($patient['status'] === 'active'): ?>
      <details><summary style="cursor:pointer; display:inline-block;" class="btn btn-outline btn-sm">أرشفة الملف</summary>
        <form method="post" style="margin-top:10px; display:flex; gap:8px; align-items:end; flex-wrap:wrap;">
          <?= csrf_field() ?><input type="hidden" name="action" value="archive">
          <div class="form-group" style="margin:0;"><label>سبب الأرشفة</label><input type="text" name="archived_reason" required></div>
          <button type="submit" class="btn btn-outline btn-sm">تأكيد الأرشفة</button>
        </form>
      </details>
    <?php else: ?>
      <form method="post" style="display:inline;"><?= csrf_field() ?><input type="hidden" name="action" value="unarchive"><button type="submit" class="btn btn-outline btn-sm">إلغاء الأرشفة</button></form>
    <?php endif; ?>
    <?php if ($adminUser['role'] === 'owner'): ?>
      <details><summary style="cursor:pointer; display:inline-block; color:var(--color-danger);" class="btn btn-outline btn-sm">حذف نهائي</summary>
        <form method="post" style="margin-top:10px; display:flex; gap:8px; align-items:end; flex-wrap:wrap;">
          <?= csrf_field() ?><input type="hidden" name="action" value="delete">
          <div class="form-group" style="margin:0;"><label>سبب الحذف</label><input type="text" name="delete_reason" required></div>
          <div class="form-group" style="margin:0;"><label>اكتب "حذف" للتأكيد</label><input type="text" name="confirm_text" required></div>
          <button type="submit" class="btn" style="color:var(--color-danger); background:none; border:1px solid var(--color-danger); padding:8px 16px; border-radius:999px; font-size:.85rem;">حذف نهائي</button>
        </form>
        <p class="field-hint">الحذف رح يفشل لو المريض إله مواعيد أو سجلات مرتبطة — استخدم الأرشفة بدل هيك بمعظم الحالات.</p>
      </details>
    <?php endif; ?>
  </div>
  <?php endif; ?>
</div>

<div class="admin-card" style="margin-bottom:20px;">
  <h2 style="margin-top:0;">المواعيد</h2>
  <?php if (empty($appointments)): ?>
    <p class="field-hint">ما في مواعيد مسجّلة لهالمريض بعد.</p>
  <?php else: ?>
    <table class="data-table">
      <thead><tr><th>التاريخ</th><th>الخدمة</th><th>الحالة</th></tr></thead>
      <tbody>
        <?php foreach ($appointments as $a): [$label, $cls] = $statusLabels[$a['status']] ?? ['—', 'status-new']; ?>
          <tr>
            <td><a href="/admin/appointment-edit.php?id=<?= (int)$a['id'] ?>"><?= e(date('Y/m/d H:i', strtotime($a['starts_at']))) ?></a></td>
            <td><?= e($a['service_name'] ?? 'حظر فترة') ?></td>
            <td><span class="status-badge <?= $cls ?>"><?= $label ?></span></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<?php if ($canClinical): ?>
<div class="admin-card">
  <h2 style="margin-top:0;">الملاحظات السريرية</h2>
  <?php if (!clinical_module_enabled()): ?>
    <div class="notice-banner">
      <strong>الوحدة غير مفعّلة</strong>
      وحدة الملاحظات السريرية مبنية بالنظام لكنها معطّلة حاليًا حتى تُحدَّد متطلبات الاستضافة والاحتفاظ والموافقة. فعّلها من <a href="/admin/clinical-settings.php">إعدادات الملاحظات السريرية</a> (لمالك الموقع فقط) بعد تعبئة المتطلبات.
    </div>
  <?php else: ?>
    <a href="/admin/clinical-note-edit.php?patient_id=<?= (int)$patient['id'] ?>" class="btn btn-primary btn-sm" style="margin-bottom:14px;">+ ملاحظة جديدة</a>
    <?php if (empty($clinicalNotes)): ?>
      <p class="field-hint">ما في ملاحظات مسجّلة بعد.</p>
    <?php else: ?>
      <table class="data-table">
        <thead><tr><th>التاريخ</th><th>الكاتب</th><th>مقفلة</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($clinicalNotes as $n): ?>
            <tr>
              <td><?= e(date('Y/m/d H:i', strtotime($n['created_at']))) ?></td>
              <td><?= e($n['author_name']) ?></td>
              <td><?= $n['locked_at'] ? '🔒' : '—' ?></td>
              <td><a href="/admin/clinical-note-edit.php?id=<?= (int)$n['id'] ?>">فتح</a></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
