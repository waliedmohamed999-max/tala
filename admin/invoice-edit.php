<?php
require_once __DIR__ . '/../includes/auth.php';
$adminUser = require_financial_access();
$activeNav = 'billing';

$id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$invoice = ['id' => null, 'patient_id' => '', 'appointment_id' => '', 'service_id' => '', 'amount' => '', 'currency' => '', 'status' => 'due', 'notes' => ''];
if ($id) {
    $stmt = db()->prepare('SELECT * FROM invoices WHERE id = ?');
    $stmt->execute([$id]);
    $found = $stmt->fetch();
    if (!$found) { flash_set('error', 'الفاتورة غير موجودة.'); redirect('/admin/billing.php'); }
    $invoice = $found;
}

// Financial role sees only patient names (for the receipt) — no clinical/administrative detail beyond that.
$patients = db()->query("SELECT id, full_name, file_number FROM patients WHERE status = 'active' ORDER BY full_name")->fetchAll();
$services = db()->query("SELECT id, name FROM services ORDER BY sort_order, name")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify() && ($_POST['action'] ?? 'save') === 'save') {
    $invoice['patient_id'] = $_POST['patient_id'] !== '' ? (int)$_POST['patient_id'] : null;
    $invoice['service_id'] = $_POST['service_id'] !== '' ? (int)$_POST['service_id'] : null;
    $invoice['amount'] = (float)($_POST['amount'] ?? 0);
    $invoice['currency'] = trim($_POST['currency'] ?? '') ?: null;
    $invoice['status'] = $_POST['status'] ?? 'due';
    $invoice['notes'] = trim($_POST['notes'] ?? '') ?: null;

    if ($id) {
        db()->prepare("UPDATE invoices SET patient_id=?, service_id=?, amount=?, currency=?, status=?, notes=?, updated_at=datetime('now') WHERE id=?")
            ->execute([$invoice['patient_id'], $invoice['service_id'], $invoice['amount'], $invoice['currency'], $invoice['status'], $invoice['notes'], $id]);
        flash_set('success', 'تم حفظ الفاتورة.');
        redirect('/admin/invoice-edit.php?id=' . $id);
    } else {
        $ins = db()->prepare('INSERT INTO invoices (patient_id, service_id, amount, currency, status, notes, created_by) VALUES (?,?,?,?,?,?,?)');
        $ins->execute([$invoice['patient_id'], $invoice['service_id'], $invoice['amount'], $invoice['currency'], $invoice['status'], $invoice['notes'], $adminUser['name']]);
        $newId = (int)db()->lastInsertId();
        db()->prepare('UPDATE invoices SET receipt_number = ? WHERE id = ?')->execute([generate_receipt_number($newId), $newId]);
        flash_set('success', 'تم إنشاء الفاتورة.');
        redirect('/admin/invoice-edit.php?id=' . $newId);
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify() && ($_POST['action'] ?? '') === 'record_payment' && $id) {
    $amount = (float)($_POST['payment_amount'] ?? 0);
    $method = trim($_POST['payment_method'] ?? '') ?: null;
    $reference = trim($_POST['payment_reference'] ?? '') ?: null;
    if ($amount <= 0) {
        flash_set('error', 'مبلغ الدفعة لازم يكون أكبر من صفر.');
    } else {
        $ins = db()->prepare('INSERT INTO invoice_payments (invoice_id, amount, method, reference, recorded_by) VALUES (?,?,?,?,?)');
        $ins->execute([$id, $amount, $method, $reference, $adminUser['name']]);
        refresh_invoice_status($id);
        flash_set('success', 'تم تسجيل الدفعة.');
    }
    redirect('/admin/invoice-edit.php?id=' . $id);
}

$payments = [];
if ($id) {
    $pStmt = db()->prepare('SELECT * FROM invoice_payments WHERE invoice_id = ? ORDER BY recorded_at DESC');
    $pStmt->execute([$id]);
    $payments = $pStmt->fetchAll();
    // re-fetch invoice in case refresh_invoice_status() changed it
    $stmt = db()->prepare('SELECT * FROM invoices WHERE id = ?');
    $stmt->execute([$id]);
    $invoice = $stmt->fetch();
}
$paidTotal = $id ? invoice_paid_total($id) : 0;

$pageTitle = $id ? ('فاتورة ' . ($invoice['receipt_number'] ?? '')) : 'فاتورة جديدة';
require __DIR__ . '/includes/layout_top.php';
?>

<div class="admin-card" style="margin-bottom:20px;">
  <form method="post" class="form-narrow">
    <?= csrf_field() ?><input type="hidden" name="action" value="save">
    <div class="form-group">
      <label>المريض</label>
      <select name="patient_id">
        <option value="">— بدون —</option>
        <?php foreach ($patients as $p): ?>
          <option value="<?= (int)$p['id'] ?>" <?= (string)$invoice['patient_id'] === (string)$p['id'] ? 'selected' : '' ?>><?= e($p['full_name']) ?> (<?= e($p['file_number']) ?>)</option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group">
      <label>الخدمة</label>
      <select name="service_id">
        <option value="">— بدون —</option>
        <?php foreach ($services as $s): ?>
          <option value="<?= (int)$s['id'] ?>" <?= (string)$invoice['service_id'] === (string)$s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-row-2">
      <div class="form-group"><label>المبلغ</label><input type="number" step="0.01" min="0" name="amount" value="<?= e((string)$invoice['amount']) ?>"></div>
      <div class="form-group"><label>العملة</label><input type="text" name="currency" value="<?= e((string)$invoice['currency']) ?>"></div>
    </div>
    <div class="form-group">
      <label>الحالة</label>
      <select name="status">
        <?php foreach (['not_required' => 'غير مطلوبة', 'due' => 'مستحقة', 'partially_paid' => 'مدفوعة جزئيًا', 'paid' => 'مدفوعة', 'refunded' => 'مستردة'] as $val => $label): ?>
          <option value="<?= $val ?>" <?= $invoice['status'] === $val ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
      <p class="field-hint">بتتحدث تلقائيًا لو سجّلت دفعات تحت (due/partially_paid/paid).</p>
    </div>
    <div class="form-group"><label>ملاحظات</label><textarea name="notes"><?= e((string)$invoice['notes']) ?></textarea></div>
    <button type="submit" class="btn btn-primary">حفظ</button>
  </form>
</div>

<?php if ($id): ?>
<div class="admin-card" style="margin-bottom:20px;">
  <h3 style="margin-top:0;">الدفعات المسجَّلة (إجمالي: <?= e(number_format($paidTotal, 2)) ?> <?= e((string)$invoice['currency']) ?>)</h3>
  <?php if (!empty($payments)): ?>
    <table class="data-table">
      <thead><tr><th>المبلغ</th><th>الطريقة</th><th>المرجع</th><th>التاريخ</th></tr></thead>
      <tbody>
        <?php foreach ($payments as $p): ?>
          <tr><td><?= e(number_format((float)$p['amount'], 2)) ?></td><td><?= e((string)$p['method']) ?></td><td><?= e((string)$p['reference']) ?></td><td><?= e(date('Y/m/d', strtotime($p['recorded_at']))) ?></td></tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php else: ?>
    <p class="field-hint">ما في دفعات مسجّلة بعد.</p>
  <?php endif; ?>
  <form method="post" style="margin-top:14px; display:flex; gap:10px; align-items:end; flex-wrap:wrap;">
    <?= csrf_field() ?><input type="hidden" name="action" value="record_payment">
    <div class="form-group" style="margin:0;"><label>مبلغ الدفعة</label><input type="number" step="0.01" min="0.01" name="payment_amount" required></div>
    <div class="form-group" style="margin:0;"><label>طريقة الدفع</label><input type="text" name="payment_method"></div>
    <div class="form-group" style="margin:0;"><label>مرجع (اختياري)</label><input type="text" name="payment_reference"></div>
    <button type="submit" class="btn btn-outline">تسجيل دفعة</button>
  </form>
</div>
<a href="/admin/receipt.php?id=<?= (int)$id ?>" target="_blank" class="btn btn-outline">🖨️ عرض/طباعة الإيصال</a>
<?php endif; ?>

<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
