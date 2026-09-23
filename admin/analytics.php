<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('owner');
$activeNav = 'analytics';

$totalViews = (int)db()->query("SELECT COUNT(*) FROM analytics_events WHERE event_type='page_view' AND day >= date('now','-30 days')")->fetchColumn();
$bookingStarts = (int)db()->query("SELECT COUNT(*) FROM analytics_events WHERE event_type='page_view' AND path='/book.php' AND day >= date('now','-30 days')")->fetchColumn();
$bookingCompletes = (int)db()->query("SELECT COUNT(*) FROM analytics_events WHERE event_type='booking_form_completed' AND day >= date('now','-30 days')")->fetchColumn();
$contactCompletes = (int)db()->query("SELECT COUNT(*) FROM analytics_events WHERE event_type='contact_form_completed' AND day >= date('now','-30 days')")->fetchColumn();
$bookingRate = $bookingStarts > 0 ? round(($bookingCompletes / $bookingStarts) * 100) : 0;

$topPages = db()->query("SELECT path, COUNT(*) AS views FROM analytics_events
    WHERE event_type='page_view' AND day >= date('now','-30 days') AND path IS NOT NULL
    GROUP BY path ORDER BY views DESC LIMIT 15")->fetchAll();

$pageTitle = 'إحصاءات الزيارات (آخر ٣٠ يوم)';
require __DIR__ . '/includes/layout_top.php';
?>

<div class="notice-inline" style="margin-bottom:24px;">
  عدادات مجهولة تمامًا — بدون أي معرّف زائر أو IP أو نص كتبه أي حدا بالنماذج. فيك توقف التسجيل بالكامل من <a href="/admin/settings.php">الإعدادات العامة</a>.
</div>

<div class="stat-grid">
  <div class="stat-card"><div class="num"><?= $totalViews ?></div><div class="label">مشاهدات صفحات</div></div>
  <div class="stat-card"><div class="num"><?= $bookingRate ?>٪</div><div class="label">معدّل إكمال نموذج الحجز</div></div>
  <div class="stat-card"><div class="num"><?= $bookingCompletes ?></div><div class="label">طلبات مواعيد مكتملة</div></div>
  <div class="stat-card"><div class="num"><?= $contactCompletes ?></div><div class="label">رسائل تواصل مكتملة</div></div>
</div>

<div class="admin-card">
  <h2 style="margin-top:0;">أكتر الصفحات زيارة</h2>
  <?php if (empty($topPages)): ?>
    <p>ما في بيانات كافية لسا.</p>
  <?php else: ?>
    <table class="data-table">
      <thead><tr><th>الصفحة</th><th>عدد الزيارات</th></tr></thead>
      <tbody>
        <?php foreach ($topPages as $p): ?>
          <tr><td><?= e($p['path']) ?></td><td><?= (int)$p['views'] ?></td></tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
