<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/mailer.php';
$adminUser = require_admin();
$pageTitle = 'لوحة التحكم';
$activeNav = 'dashboard';

$canSeeBookings = in_array($adminUser['role'], ['owner', 'bookings_manager'], true);
$canSeeContent = in_array($adminUser['role'], ['owner', 'editor', 'reviewer'], true);
$canSeeClinicOps = in_array($adminUser['role'], ['owner', 'bookings_manager'], true);
$canSeeFinancial = in_array($adminUser['role'], ['owner', 'financial'], true);
$isOwner = $adminUser['role'] === 'owner';

$stats = [];
if ($canSeeContent) {
    $stats['articles_published'] = (int)db()->query("SELECT COUNT(*) FROM articles WHERE published_content IS NOT NULL")->fetchColumn();
    $stats['articles_in_review'] = (int)db()->query("SELECT COUNT(*) FROM articles WHERE workflow_status='in_review'")->fetchColumn();
    $stats['articles_changes_requested'] = (int)db()->query("SELECT COUNT(*) FROM articles WHERE workflow_status='changes_requested'")->fetchColumn();
    $stats['articles_needs_update'] = (int)db()->query("SELECT COUNT(*) FROM articles WHERE workflow_status='needs_update'")->fetchColumn();
    $stats['services_published'] = (int)db()->query("SELECT COUNT(*) FROM services WHERE workflow_status='published'")->fetchColumn();

    $staleDays = (int)get_setting('article_stale_after_days', '180');
    $staleArticles = db()->prepare("SELECT * FROM articles
        WHERE published_content IS NOT NULL AND (published_reviewed_at IS NULL OR published_reviewed_at < datetime('now', ?))
        ORDER BY published_reviewed_at ASC LIMIT 10");
    $staleArticles->execute(['-' . $staleDays . ' days']);
    $staleArticles = $staleArticles->fetchAll();
}
if ($canSeeBookings) {
    $stats['bookings_new'] = (int)db()->query("SELECT COUNT(*) FROM appointment_requests WHERE status='new'")->fetchColumn();
    $recentBookings = db()->query("SELECT * FROM appointment_requests ORDER BY created_at DESC LIMIT 5")->fetchAll();

    $thirtyDaysAgo = date('Y-m-d', strtotime('-30 days'));
    $cancelledCount = db()->prepare("SELECT COUNT(*) FROM appointment_requests WHERE status='cancelled' AND date(created_at) >= ?");
    $cancelledCount->execute([$thirtyDaysAgo]);
    $totalCountStmt = db()->prepare("SELECT COUNT(*) FROM appointment_requests WHERE date(created_at) >= ?");
    $totalCountStmt->execute([$thirtyDaysAgo]);
    $totalLast30 = (int)$totalCountStmt->fetchColumn();
    $cancelledLast30 = (int)$cancelledCount->fetchColumn();
    $stats['cancellation_rate'] = $totalLast30 > 0 ? round(($cancelledLast30 / $totalLast30) * 100) : null;
}
if ($canSeeClinicOps) {
    $today = date('Y-m-d');
    $weekEnd = date('Y-m-d', strtotime('+7 days'));
    $todayStmt = db()->prepare("SELECT COUNT(*) FROM appointments WHERE date(starts_at) = ? AND status != 'cancelled'");
    $todayStmt->execute([$today]);
    $stats['appts_today'] = (int)$todayStmt->fetchColumn();
    $weekStmt = db()->prepare("SELECT COUNT(*) FROM appointments WHERE date(starts_at) BETWEEN ? AND ? AND status != 'cancelled'");
    $weekStmt->execute([$today, $weekEnd]);
    $stats['appts_week'] = (int)$weekStmt->fetchColumn();
    $stats['appts_pending_confirmation'] = (int)db()->query("SELECT COUNT(*) FROM appointments WHERE status = 'pending_confirmation'")->fetchColumn();
    $stats['ops_tasks_open'] = (int)db()->query("SELECT COUNT(*) FROM ops_tasks WHERE status = 'open'")->fetchColumn();

    $todayAppts = db()->prepare("SELECT a.*, s.name AS service_name, p.full_name AS patient_name FROM appointments a
        LEFT JOIN services s ON s.id = a.service_id LEFT JOIN patients p ON p.id = a.patient_id
        WHERE date(a.starts_at) = ? AND a.status != 'cancelled' ORDER BY a.starts_at");
    $todayAppts->execute([$today]);
    $todayAppts = $todayAppts->fetchAll();
}
if ($canSeeFinancial && setting_bool('billing_enabled')) {
    $stats['invoices_due'] = (int)db()->query("SELECT COUNT(*) FROM invoices WHERE status IN ('due','partially_paid')")->fetchColumn();
}

// --- Settings-gap alerts (owner only) ---
$gaps = [];
if ($isOwner) {
    if (!mailer_is_configured()) { $gaps[] = ['البريد الإلكتروني غير مربوط', '/admin/notifications-settings.php']; }
    if ((int)db()->query("SELECT COUNT(*) FROM clinic_hours WHERE is_active = 1")->fetchColumn() === 0) { $gaps[] = ['ما في ساعات عمل مضبوطة بعد', '/admin/clinic-hours.php']; }
    if ((int)db()->query("SELECT COUNT(*) FROM services WHERE workflow_status = 'published'")->fetchColumn() === 0) { $gaps[] = ['ما في خدمة منشورة بعد', '/admin/services.php']; }
}

require __DIR__ . '/includes/layout_top.php';
?>

<div class="admin-page-head">
  <h1>أهلًا <?= e($adminUser['name']) ?> 👋</h1>
</div>

<?php if (!empty($gaps)): ?>
<div class="notice-banner" style="margin-bottom:20px;">
  <strong>إعدادات ناقصة</strong>
  <ul style="margin:8px 0 0; padding-inline-start:18px;">
    <?php foreach ($gaps as [$label, $link]): ?><li><a href="<?= e($link) ?>"><?= e($label) ?></a></li><?php endforeach; ?>
  </ul>
  <p style="margin-top:8px;"><a href="/admin/readiness.php">عرض شاشة جاهزية التشغيل الكاملة →</a></p>
</div>
<?php endif; ?>

<div style="display:flex; gap:10px; flex-wrap:wrap; margin-bottom:24px;">
  <?php if ($canSeeClinicOps): ?><a href="/admin/appointment-edit.php" class="btn btn-outline btn-sm">+ إضافة موعد</a><?php endif; ?>
  <?php if ($canSeeBookings): ?><a href="/admin/appointments.php?status=new" class="btn btn-outline btn-sm">مراجعة طلب</a><?php endif; ?>
  <?php if ($canSeeContent): ?><a href="/admin/service-edit.php" class="btn btn-outline btn-sm">+ إضافة خدمة</a><?php endif; ?>
  <?php if ($canSeeClinicOps): ?><a href="/admin/calendar.php" class="btn btn-outline btn-sm">📅 فتح التقويم</a><?php endif; ?>
</div>

<div class="stat-grid">
  <?php if ($canSeeBookings): ?>
  <div class="stat-card"><div class="num"><?= $stats['bookings_new'] ?></div><div class="label">طلبات مواعيد جديدة</div></div>
  <?php if ($stats['cancellation_rate'] !== null): ?>
  <div class="stat-card"><div class="num">٪<?= $stats['cancellation_rate'] ?></div><div class="label">معدّل الإلغاء (٣٠ يوم)</div></div>
  <?php endif; ?>
  <?php endif; ?>
  <?php if ($canSeeClinicOps): ?>
  <div class="stat-card"><div class="num"><?= $stats['appts_today'] ?></div><div class="label">مواعيد اليوم</div></div>
  <div class="stat-card"><div class="num"><?= $stats['appts_week'] ?></div><div class="label">مواعيد ٧ أيام قادمة</div></div>
  <div class="stat-card"><div class="num"><?= $stats['appts_pending_confirmation'] ?></div><div class="label">بانتظار التأكيد</div></div>
  <div class="stat-card"><div class="num"><?= $stats['ops_tasks_open'] ?></div><div class="label">مهام تشغيلية مفتوحة</div></div>
  <?php endif; ?>
  <?php if ($canSeeFinancial && isset($stats['invoices_due'])): ?>
  <div class="stat-card"><div class="num"><?= $stats['invoices_due'] ?></div><div class="label">فواتير مستحقة</div></div>
  <?php endif; ?>
  <?php if ($canSeeContent): ?>
  <div class="stat-card"><div class="num"><?= $stats['articles_published'] ?></div><div class="label">مقالات منشورة</div></div>
  <div class="stat-card"><div class="num"><?= $stats['articles_in_review'] ?></div><div class="label">بانتظار المراجعة المهنية</div></div>
  <div class="stat-card"><div class="num"><?= $stats['articles_changes_requested'] ?></div><div class="label">بانتظار تعديل المحرر</div></div>
  <div class="stat-card"><div class="num"><?= $stats['articles_needs_update'] ?></div><div class="label">مقالات بحاجة تحديث</div></div>
  <?php endif; ?>
</div>

<?php if ($canSeeClinicOps): ?>
<div class="admin-card" style="margin-bottom:24px;">
  <div class="admin-page-head">
    <h2 style="margin:0;">مواعيد اليوم</h2>
    <a href="/admin/calendar.php" class="btn btn-outline">فتح التقويم</a>
  </div>
  <?php if (empty($todayAppts)): ?>
    <p>ما في مواعيد اليوم.</p>
  <?php else: ?>
    <table class="data-table">
      <thead><tr><th>الوقت</th><th>الخدمة</th><th>المريض</th><th>الحالة</th></tr></thead>
      <tbody>
        <?php foreach ($todayAppts as $a): ?>
          <tr>
            <td><a href="/admin/appointment-edit.php?id=<?= (int)$a['id'] ?>"><?= e(substr($a['starts_at'], 11, 5)) ?></a></td>
            <td><?= e($a['service_name'] ?? 'حظر فترة') ?></td>
            <td><?= e($a['patient_name'] ?? '—') ?></td>
            <td><?= e($a['status']) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php if ($canSeeContent && !empty($staleArticles)): ?>
<div class="admin-card" style="margin-bottom:24px;">
  <h2 style="margin-top:0;">مقالات ما اتراجعت منذ فترة</h2>
  <p class="field-hint">حسب الإعداد الحالي (كل <?= (int)get_setting('article_stale_after_days', '180') ?> يوم) — من <a href="/admin/settings.php">الإعدادات العامة</a> فيك تغيّر هالمدة.</p>
  <table class="data-table">
    <thead><tr><th>العنوان</th><th>آخر مراجعة</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($staleArticles as $a): ?>
        <tr>
          <td><?= e($a['title']) ?></td>
          <td><?= $a['published_reviewed_at'] ? e(date('Y/m/d', strtotime($a['published_reviewed_at']))) : 'ما اتراجعت أبدًا' ?></td>
          <td><a href="/admin/article-edit.php?id=<?= (int)$a['id'] ?>">مراجعة</a></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
<?php endif; ?>

<?php if ($canSeeBookings): ?>
<div class="admin-card">
  <div class="admin-page-head">
    <h2 style="margin:0;">آخر طلبات المواعيد</h2>
    <a href="/admin/appointments.php" class="btn btn-outline">كل الطلبات</a>
  </div>
  <?php if (empty($recentBookings)): ?>
    <p>ما في طلبات لسا.</p>
  <?php else: ?>
    <table class="data-table">
      <thead><tr><th>الاسم</th><th>وسيلة التواصل</th><th>الحالة</th><th>التاريخ</th></tr></thead>
      <tbody>
        <?php foreach ($recentBookings as $b): ?>
          <tr>
            <td><a href="/admin/appointments.php?id=<?= (int)$b['id'] ?>"><?= e($b['name']) ?></a></td>
            <td><?= e($b['contact_method']) ?>: <?= e($b['contact_value']) ?></td>
            <td><span class="status-badge status-<?= e($b['status']) ?>"><?= e($b['status']) ?></span></td>
            <td><?= e(date('Y/m/d H:i', strtotime($b['created_at']))) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
