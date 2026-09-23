<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/mailer.php';
require_role('owner');
$activeNav = 'subscribers';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify() && ($_POST['action'] ?? '') === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    db()->prepare('DELETE FROM newsletter_subscribers WHERE id = ?')->execute([$id]);
    flash_set('success', 'تم حذف المشترك.');
    redirect('/admin/subscribers.php');
}

if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    $rows = db()->query("SELECT email, status, created_at FROM newsletter_subscribers WHERE status = 'subscribed' ORDER BY created_at")->fetchAll();
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="newsletter-subscribers.csv"');
    echo "\xEF\xBB\xBF"; // UTF-8 BOM so Excel opens Arabic-adjacent content correctly
    $out = fopen('php://output', 'w');
    fputcsv($out, ['email', 'status', 'subscribed_at']);
    foreach ($rows as $r) { fputcsv($out, [$r['email'], $r['status'], $r['created_at']]); }
    fclose($out);
    exit;
}

$subscribers = db()->query('SELECT * FROM newsletter_subscribers ORDER BY created_at DESC')->fetchAll();
$activeCount = count(array_filter($subscribers, fn($s) => $s['status'] === 'subscribed'));
$pendingCount = count(array_filter($subscribers, fn($s) => $s['status'] === 'pending'));
$statusLabels = ['subscribed' => 'نشط', 'pending' => 'بانتظار التأكيد', 'unsubscribed' => 'ملغى'];
$statusBadges = ['subscribed' => 'status-confirmed', 'pending' => 'status-contacting', 'unsubscribed' => 'status-cancelled'];
$pageTitle = 'النشرة البريدية';
require __DIR__ . '/includes/layout_top.php';
?>

<div class="admin-page-head">
  <h1>النشرة البريدية</h1>
  <a href="/admin/subscribers.php?export=csv" class="btn btn-outline">تصدير CSV (المشتركين النشطين فقط)</a>
</div>

<div class="notice-inline" style="margin-bottom:20px;">
  الاشتراك بيحتاج تأكيد بالبريد (opt-in مزدوج) — إذا ما كان البريد مربوط وقت الاشتراك، بينضاف كـ«نشط» مباشرة كحل احتياطي، وبينعرض هون بوضوح.
  حالة الربط حاليًا: <strong><?= mailer_is_configured() ? '🟢 مربوط' : '🔴 غير مربوط' ?></strong>
</div>

<div class="stat-grid" style="grid-template-columns: repeat(3, 1fr);">
  <div class="stat-card"><div class="num"><?= $activeCount ?></div><div class="label">مشترك نشط ومؤكد</div></div>
  <div class="stat-card"><div class="num"><?= $pendingCount ?></div><div class="label">بانتظار تأكيد البريد</div></div>
  <div class="stat-card"><div class="num"><?= count($subscribers) - $activeCount - $pendingCount ?></div><div class="label">ألغى اشتراكه</div></div>
</div>

<div class="admin-card">
  <table class="data-table">
    <thead><tr><th>البريد</th><th>الحالة</th><th>مصدر الموافقة</th><th>تاريخ الطلب</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($subscribers as $s): ?>
        <tr>
          <td><?= e($s['email']) ?></td>
          <td><span class="status-badge <?= $statusBadges[$s['status']] ?? '' ?>"><?= e($statusLabels[$s['status']] ?? $s['status']) ?></span></td>
          <td class="field-hint"><?= e($s['consent_source'] ?: '—') ?></td>
          <td><?= e(date('Y/m/d', strtotime($s['created_at']))) ?></td>
          <td><form method="post" onsubmit="return confirm('حذف هالمشترك نهائيًا؟');"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>"><button type="submit" class="action-links"><span class="danger">حذف</span></button></form></td>
        </tr>
      <?php endforeach; ?>
      <?php if (empty($subscribers)): ?><tr><td colspan="4">ما في مشتركين لسا.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>

<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
