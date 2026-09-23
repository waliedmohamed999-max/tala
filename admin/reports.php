<?php
require_once __DIR__ . '/../includes/auth.php';
$adminUser = require_role('owner', 'bookings_manager', 'financial');
$activeNav = 'reports';

$canOps = in_array($adminUser['role'], ['owner', 'bookings_manager'], true);
$canFinancial = in_array($adminUser['role'], ['owner', 'financial'], true);

$from = $_GET['from'] ?? date('Y-m-d', strtotime('-30 days'));
$to = $_GET['to'] ?? date('Y-m-d');
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from)) { $from = date('Y-m-d', strtotime('-30 days')); }
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to)) { $to = date('Y-m-d'); }

$pageTitle = 'التقارير';
require __DIR__ . '/includes/layout_top.php';
?>

<div class="admin-card" style="margin-bottom:20px;">
  <form method="get" style="display:flex; gap:12px; align-items:end; flex-wrap:wrap;">
    <div class="form-group" style="margin:0;"><label>من تاريخ</label><input type="date" name="from" value="<?= e($from) ?>"></div>
    <div class="form-group" style="margin:0;"><label>لحد تاريخ</label><input type="date" name="to" value="<?= e($to) ?>"></div>
    <button type="submit" class="btn btn-outline">تحديث</button>
  </form>
</div>

<?php if ($canOps):
    $reqStmt = db()->prepare("SELECT status, COUNT(*) AS c FROM appointment_requests WHERE date(created_at) BETWEEN ? AND ? GROUP BY status");
    $reqStmt->execute([$from, $to]);
    $requestsByStatus = $reqStmt->fetchAll();
    $totalRequests = array_sum(array_column($requestsByStatus, 'c'));

    $apptStmt = db()->prepare("SELECT status, COUNT(*) AS c FROM appointments WHERE date(starts_at) BETWEEN ? AND ? GROUP BY status");
    $apptStmt->execute([$from, $to]);
    $apptByStatus = $apptStmt->fetchAll();

    $topServices = db()->prepare("SELECT s.name, COUNT(*) AS c FROM appointments a JOIN services s ON s.id = a.service_id
        WHERE date(a.starts_at) BETWEEN ? AND ? GROUP BY s.id ORDER BY c DESC LIMIT 5");
    $topServices->execute([$from, $to]);
    $topServices = $topServices->fetchAll();
?>
<div class="admin-card" style="margin-bottom:20px;">
  <h2 style="margin-top:0;">الحجوزات (<?= e($from) ?> — <?= e($to) ?>)</h2>
  <div class="stat-grid" style="margin-bottom:10px;">
    <div class="stat-card"><div class="num"><?= (int)$totalRequests ?></div><div class="label">إجمالي طلبات الحجز</div></div>
    <?php foreach ($apptByStatus as $row): ?>
      <div class="stat-card"><div class="num"><?= (int)$row['c'] ?></div><div class="label"><?= e($row['status']) ?></div></div>
    <?php endforeach; ?>
  </div>
  <?php if (!empty($topServices)): ?>
    <h3>الخدمات الأكثر طلبًا</h3>
    <table class="data-table">
      <thead><tr><th>الخدمة</th><th>عدد المواعيد</th></tr></thead>
      <tbody><?php foreach ($topServices as $s): ?><tr><td><?= e($s['name']) ?></td><td><?= (int)$s['c'] ?></td></tr><?php endforeach; ?></tbody>
    </table>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php if ($canFinancial && setting_bool('billing_enabled')):
    $revStmt = db()->prepare("SELECT COALESCE(SUM(ip.amount), 0) AS total FROM invoice_payments ip WHERE date(ip.recorded_at) BETWEEN ? AND ?");
    $revStmt->execute([$from, $to]);
    $revenue = (float)$revStmt->fetchColumn();

    $dueStmt = db()->prepare("SELECT COALESCE(SUM(amount), 0) FROM invoices WHERE status IN ('due','partially_paid') AND date(created_at) BETWEEN ? AND ?");
    $dueStmt->execute([$from, $to]);
    $due = (float)$dueStmt->fetchColumn();
?>
<div class="admin-card">
  <h2 style="margin-top:0;">المالية (<?= e($from) ?> — <?= e($to) ?>)</h2>
  <div class="stat-grid">
    <div class="stat-card"><div class="num"><?= e(number_format($revenue, 2)) ?></div><div class="label">إجمالي المحصّل</div></div>
    <div class="stat-card"><div class="num"><?= e(number_format($due, 2)) ?></div><div class="label">مستحقات غير محصّلة</div></div>
  </div>
  <p class="field-hint">المبالغ مجموعة بدون تفكيك حسب العملة إن استُخدمت عملات متعددة — تحقّقي من ذلك يدويًا إذا احتاج الأمر.</p>
</div>
<?php elseif ($canFinancial): ?>
<div class="notice-inline">قسم الفوترة معطّل حاليًا — لا تقرير مالي لعرضه.</div>
<?php endif; ?>

<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
