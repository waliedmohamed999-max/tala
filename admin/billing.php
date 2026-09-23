<?php
require_once __DIR__ . '/../includes/auth.php';
require_financial_access();
$activeNav = 'billing';

if (!setting_bool('billing_enabled')) {
    $pageTitle = 'الفواتير والمدفوعات';
    require __DIR__ . '/includes/layout_top.php';
    ?>
    <div class="admin-page-head"><h1>الفواتير والمدفوعات</h1></div>
    <div class="notice-banner">قسم الفوترة معطّل حاليًا. فعّله من <a href="/admin/settings.php">الإعدادات العامة</a> إذا كنت بحاجة له.</div>
    <?php require __DIR__ . '/includes/layout_bottom.php';
    exit;
}

$statusLabels = [
    'not_required' => ['غير مطلوبة', 'status-done'],
    'due' => ['مستحقة', 'status-contacting'],
    'partially_paid' => ['مدفوعة جزئيًا', 'status-contacting'],
    'paid' => ['مدفوعة', 'status-confirmed'],
    'refunded' => ['مستردة', 'status-cancelled'],
];

$statusFilter = $_GET['status'] ?? '';
$sql = 'SELECT i.*, p.full_name AS patient_name FROM invoices i LEFT JOIN patients p ON p.id = i.patient_id WHERE 1=1';
$params = [];
if (array_key_exists($statusFilter, $statusLabels)) {
    $sql .= ' AND i.status = :status';
    $params[':status'] = $statusFilter;
}
$sql .= ' ORDER BY i.created_at DESC LIMIT 300';
$stmt = db()->prepare($sql);
$stmt->execute($params);
$invoices = $stmt->fetchAll();

$pageTitle = 'الفواتير والمدفوعات';
require __DIR__ . '/includes/layout_top.php';
?>

<div class="admin-page-head">
  <h1>الفواتير والمدفوعات</h1>
  <a href="/admin/invoice-edit.php" class="btn btn-primary">+ فاتورة جديدة</a>
</div>

<div class="admin-card">
  <form method="get" style="margin-bottom:20px;">
    <select name="status" onchange="this.form.submit()">
      <option value="">كل الحالات</option>
      <?php foreach ($statusLabels as $val => [$label, $cls]): ?>
        <option value="<?= e($val) ?>" <?= $statusFilter === $val ? 'selected' : '' ?>><?= e($label) ?></option>
      <?php endforeach; ?>
    </select>
  </form>

  <?php if (empty($invoices)): ?>
    <p>ما في فواتير مطابقة.</p>
  <?php else: ?>
    <table class="data-table">
      <thead><tr><th>المريض</th><th>المبلغ</th><th>الحالة</th><th>التاريخ</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($invoices as $inv): [$label, $cls] = $statusLabels[$inv['status']] ?? ['—', 'status-new']; ?>
          <tr>
            <td><?= e($inv['patient_name'] ?? '—') ?></td>
            <td><?= e(number_format((float)$inv['amount'], 2)) ?> <?= e((string)$inv['currency']) ?></td>
            <td><span class="status-badge <?= $cls ?>"><?= $label ?></span></td>
            <td><?= e(date('Y/m/d', strtotime($inv['created_at']))) ?></td>
            <td class="action-links">
              <a href="/admin/invoice-edit.php?id=<?= (int)$inv['id'] ?>">فتح</a>
              <a href="/admin/receipt.php?id=<?= (int)$inv['id'] ?>" target="_blank">إيصال</a>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
