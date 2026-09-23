<?php
require_once __DIR__ . '/../includes/auth.php';
$adminUser = require_patient_view_access();
$canManagePatients = in_array($adminUser['role'], ['owner', 'bookings_manager'], true);
$activeNav = 'patients';

$statusFilter = $_GET['status'] ?? 'active';
$q = trim($_GET['q'] ?? '');
$sql = 'SELECT * FROM patients WHERE 1=1';
$params = [];
if (in_array($statusFilter, ['active', 'archived'], true)) {
    $sql .= ' AND status = :status';
    $params[':status'] = $statusFilter;
}
if ($q !== '') {
    $sql .= ' AND (full_name LIKE :q OR file_number LIKE :q OR contact_value LIKE :q)';
    $params[':q'] = '%' . $q . '%';
}
$sql .= ' ORDER BY full_name LIMIT 300';
$stmt = db()->prepare($sql);
$stmt->execute($params);
$patients = $stmt->fetchAll();

if ($canManagePatients) {
    $primaryAction = ['label' => 'ملف مريض جديد', 'href' => '/admin/patient-edit.php'];
}
$pageTitle = 'المرضى';
require __DIR__ . '/includes/layout_top.php';
?>

<div class="notice-inline" style="margin-bottom:var(--admin-sp-4);">
  ملف المريض بيتنشئ فقط بإجراء صريح من هون أو من صفحة طلب موعد ("تحويل لملف مريض") — أبدًا تلقائيًا. هاي بيانات إدارية فقط (اسم، تواصل، مواعيد) — الملاحظات السريرية إلها قسم منفصل ومحمي بصلاحية أعلى.
</div>

<form method="get" style="display:flex; gap:8px; flex-wrap:wrap; margin-bottom:var(--admin-sp-3);">
  <input type="text" name="q" placeholder="بحث بالاسم، رقم الملف، أو وسيلة التواصل" value="<?= e($q) ?>" style="max-width:300px;">
  <button type="submit" class="btn btn-outline btn-sm">بحث</button>
</form>

<div style="display:flex; gap:6px; margin-bottom:var(--admin-sp-4);">
  <a href="/admin/patients.php?status=active<?= $q ? '&q=' . urlencode($q) : '' ?>" class="btn <?= $statusFilter === 'active' ? 'btn-primary' : 'btn-outline' ?> btn-sm">نشط</a>
  <a href="/admin/patients.php?status=archived<?= $q ? '&q=' . urlencode($q) : '' ?>" class="btn <?= $statusFilter === 'archived' ? 'btn-primary' : 'btn-outline' ?> btn-sm">مؤرشف</a>
  <a href="/admin/patients.php?status=<?= $q ? '&q=' . urlencode($q) : '' ?>" class="btn <?= $statusFilter === '' ? 'btn-primary' : 'btn-outline' ?> btn-sm">الكل</a>
</div>

<div class="admin-card" style="padding:0; overflow:hidden;">
  <?php if (empty($patients)): ?>
    <div class="empty-state">
      <p>ما في مرضى مطابقين<?= $q ? ' لبحثك' : '' ?>.</p>
      <?php if ($canManagePatients && $q === ''): ?><a href="/admin/patient-edit.php" class="btn btn-primary btn-sm">+ ملف مريض جديد</a><?php endif; ?>
    </div>
  <?php else: ?>
    <table class="data-table">
      <thead><tr><th>رقم الملف</th><th>الاسم</th><th>التواصل</th><th>الحالة</th></tr></thead>
      <tbody>
        <?php foreach ($patients as $p): ?>
          <tr>
            <td class="field-hint"><a href="/admin/patient-view.php?id=<?= (int)$p['id'] ?>"><?= e($p['file_number']) ?></a></td>
            <td><a href="/admin/patient-view.php?id=<?= (int)$p['id'] ?>" style="font-weight:700;"><?= e($p['full_name']) ?></a></td>
            <td class="field-hint"><?= e($p['contact_method']) ?>: <?= e($p['contact_value']) ?></td>
            <td><span class="status-badge <?= $p['status'] === 'active' ? 'status-confirmed' : 'status-cancelled' ?>"><?= $p['status'] === 'active' ? 'نشط' : 'مؤرشف' ?></span></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
