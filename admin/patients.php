<?php
require_once __DIR__ . '/../includes/auth.php';
$adminUser = require_clinic_ops_access();
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

$pageTitle = 'المرضى';
require __DIR__ . '/includes/layout_top.php';
?>

<div class="admin-page-head">
  <h1>المرضى</h1>
  <a href="/admin/patient-edit.php" class="btn btn-primary">+ ملف مريض جديد</a>
</div>

<div class="notice-inline" style="margin-bottom:20px;">
  ملف المريض بيتنشئ فقط بإجراء صريح من هون أو من صفحة طلب موعد ("تحويل لملف مريض") — أبدًا تلقائيًا. هاي بيانات إدارية فقط (اسم، تواصل، مواعيد) — الملاحظات السريرية إلها قسم منفصل ومحمي بصلاحية أعلى.
</div>

<div class="admin-card">
  <form method="get" style="display:flex; gap:12px; flex-wrap:wrap; margin-bottom:20px;">
    <input type="text" name="q" placeholder="بحث بالاسم، رقم الملف، أو وسيلة التواصل" value="<?= e($q) ?>" style="max-width:280px;">
    <select name="status">
      <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>نشط</option>
      <option value="archived" <?= $statusFilter === 'archived' ? 'selected' : '' ?>>مؤرشف</option>
      <option value="" <?= $statusFilter === '' ? 'selected' : '' ?>>الكل</option>
    </select>
    <button type="submit" class="btn btn-outline">بحث</button>
  </form>

  <?php if (empty($patients)): ?>
    <p>ما في مرضى مطابقين.</p>
  <?php else: ?>
    <table class="data-table">
      <thead><tr><th>رقم الملف</th><th>الاسم</th><th>التواصل</th><th>الحالة</th></tr></thead>
      <tbody>
        <?php foreach ($patients as $p): ?>
          <tr>
            <td><a href="/admin/patient-view.php?id=<?= (int)$p['id'] ?>"><?= e($p['file_number']) ?></a></td>
            <td><a href="/admin/patient-view.php?id=<?= (int)$p['id'] ?>"><?= e($p['full_name']) ?></a></td>
            <td><?= e($p['contact_method']) ?>: <?= e($p['contact_value']) ?></td>
            <td><span class="status-badge <?= $p['status'] === 'active' ? 'status-confirmed' : 'status-cancelled' ?>"><?= $p['status'] === 'active' ? 'نشط' : 'مؤرشف' ?></span></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
