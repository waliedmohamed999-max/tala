<?php
require_once __DIR__ . '/../includes/auth.php';
require_financial_access();

$id = (int)($_GET['id'] ?? 0);
$stmt = db()->prepare('SELECT i.*, p.full_name AS patient_name, s.name AS service_name FROM invoices i
    LEFT JOIN patients p ON p.id = i.patient_id LEFT JOIN services s ON s.id = i.service_id WHERE i.id = ?');
$stmt->execute([$id]);
$invoice = $stmt->fetch();
if (!$invoice) { http_response_code(404); exit('الفاتورة غير موجودة.'); }

$paid = invoice_paid_total($id);
$clinicName = get_setting('display_title', 'تالا | معالجة نفسية');
?><!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<title>إيصال <?= e($invoice['receipt_number'] ?? ('#' . $id)) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">
<style>
  body { font-family: 'Tajawal', sans-serif; max-width: 520px; margin: 40px auto; padding: 0 20px; color: #222; }
  h1 { font-size: 1.3rem; margin-bottom: 4px; }
  table { width: 100%; border-collapse: collapse; margin-top: 20px; }
  td, th { padding: 8px 4px; border-bottom: 1px solid #ddd; text-align: start; }
  .total-row td { font-weight: 800; font-size: 1.1rem; border-top: 2px solid #333; border-bottom: none; }
  .no-print { margin-top: 24px; }
  @media print { .no-print { display: none; } }
</style>
</head>
<body>
  <h1><?= e($clinicName) ?></h1>
  <p>إيصال رقم: <?= e($invoice['receipt_number'] ?? ('#' . $id)) ?> — تاريخ: <?= e(date('Y/m/d', strtotime($invoice['created_at']))) ?></p>
  <table>
    <tr><th>المريض</th><td><?= e($invoice['patient_name'] ?? '—') ?></td></tr>
    <tr><th>الخدمة</th><td><?= e($invoice['service_name'] ?? '—') ?></td></tr>
    <tr><th>الحالة</th><td><?= e(['not_required' => 'غير مطلوبة', 'due' => 'مستحقة', 'partially_paid' => 'مدفوعة جزئيًا', 'paid' => 'مدفوعة', 'refunded' => 'مستردة'][$invoice['status']] ?? $invoice['status']) ?></td></tr>
    <tr class="total-row"><td>المبلغ الإجمالي</td><td><?= e(number_format((float)$invoice['amount'], 2)) ?> <?= e((string)$invoice['currency']) ?></td></tr>
    <tr><td>المدفوع</td><td><?= e(number_format($paid, 2)) ?> <?= e((string)$invoice['currency']) ?></td></tr>
  </table>
  <div class="no-print">
    <button onclick="window.print()">🖨️ طباعة</button>
  </div>
</body>
</html>
