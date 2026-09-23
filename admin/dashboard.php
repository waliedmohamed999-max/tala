<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/mailer.php';
$adminUser = require_admin();
$pageTitle = 'نظرة عامة';
$activeNav = 'dashboard';

$canSeeBookings = in_array($adminUser['role'], ['owner', 'bookings_manager'], true);
$canSeeContent = in_array($adminUser['role'], ['owner', 'editor', 'reviewer'], true);
$canSeeClinicOps = in_array($adminUser['role'], ['owner', 'bookings_manager'], true);
$canSeeFinancial = in_array($adminUser['role'], ['owner', 'financial'], true);
$isOwner = $adminUser['role'] === 'owner';

$stats = [];
if ($canSeeContent) {
    $stats['articles_in_review'] = (int)db()->query("SELECT COUNT(*) FROM articles WHERE workflow_status='in_review'")->fetchColumn();
    $stats['articles_changes_requested'] = (int)db()->query("SELECT COUNT(*) FROM articles WHERE workflow_status='changes_requested'")->fetchColumn();
    $stats['articles_needs_update'] = (int)db()->query("SELECT COUNT(*) FROM articles WHERE workflow_status='needs_update'")->fetchColumn();

    $staleDays = (int)get_setting('article_stale_after_days', '180');
    $staleArticles = db()->prepare("SELECT * FROM articles
        WHERE published_content IS NOT NULL AND (published_reviewed_at IS NULL OR published_reviewed_at < datetime('now', ?))
        ORDER BY published_reviewed_at ASC LIMIT 5");
    $staleArticles->execute(['-' . $staleDays . ' days']);
    $staleArticles = $staleArticles->fetchAll();
}
if ($canSeeBookings) {
    $stats['bookings_new'] = (int)db()->query("SELECT COUNT(*) FROM appointment_requests WHERE status='new'")->fetchColumn();
    $recentBookings = db()->query("SELECT * FROM appointment_requests WHERE status IN ('new','contacting','in_review','awaiting_visitor_reply') ORDER BY created_at DESC LIMIT 5")->fetchAll();
}
if ($canSeeClinicOps) {
    $today = date('Y-m-d');
    $todayStmt = db()->prepare("SELECT COUNT(*) FROM appointments WHERE date(starts_at) = ? AND status != 'cancelled'");
    $todayStmt->execute([$today]);
    $stats['appts_today'] = (int)$todayStmt->fetchColumn();
    $stats['appts_pending_confirmation'] = (int)db()->query("SELECT COUNT(*) FROM appointments WHERE status = 'pending_confirmation'")->fetchColumn();
    $stats['ops_tasks_open'] = (int)db()->query("SELECT COUNT(*) FROM ops_tasks WHERE status = 'open'")->fetchColumn();

    $todayAppts = db()->prepare("SELECT a.*, s.name AS service_name, p.full_name AS patient_name FROM appointments a
        LEFT JOIN services s ON s.id = a.service_id LEFT JOIN patients p ON p.id = a.patient_id
        WHERE date(a.starts_at) = ? AND a.status != 'cancelled' ORDER BY a.starts_at");
    $todayAppts->execute([$today]);
    $todayAppts = $todayAppts->fetchAll();

    // "Next up": the nearest not-yet-started appointment today.
    $nextStmt = db()->prepare("SELECT a.*, s.name AS service_name, p.full_name AS patient_name FROM appointments a
        LEFT JOIN services s ON s.id = a.service_id LEFT JOIN patients p ON p.id = a.patient_id
        WHERE a.starts_at >= datetime('now') AND a.status != 'cancelled' ORDER BY a.starts_at LIMIT 1");
    $nextStmt->execute();
    $nextAppt = $nextStmt->fetch();
}
if ($canSeeFinancial && setting_bool('billing_enabled')) {
    $stats['invoices_due'] = (int)db()->query("SELECT COUNT(*) FROM invoices WHERE status IN ('due','partially_paid')")->fetchColumn();
}
$openTasks = [];
if ($canSeeClinicOps) {
    $openTasks = db()->query("SELECT * FROM ops_tasks WHERE status='open' ORDER BY due_at IS NULL, due_at LIMIT 5")->fetchAll();
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

<p class="field-hint" style="margin-bottom:var(--admin-sp-4);">أهلًا <?= e($adminUser['name']) ?> — هيك وضع اليوم.</p>

<?php if (!empty($gaps)): ?>
<div class="notice-banner" style="margin-bottom:var(--admin-sp-4);">
  <strong>إعدادات ناقصة</strong>
  <ul style="margin:8px 0 0; padding-inline-start:18px;">
    <?php foreach ($gaps as [$label, $link]): ?><li><a href="<?= e($link) ?>"><?= e($label) ?></a></li><?php endforeach; ?>
  </ul>
  <p style="margin-top:8px;"><a href="/admin/readiness.php">عرض شاشة جاهزية التشغيل الكاملة →</a></p>
</div>
<?php endif; ?>

<div class="stat-grid">
  <?php if ($canSeeClinicOps): ?>
    <div class="stat-card"><div class="num"><?= $stats['appts_today'] ?></div><div class="label">مواعيد اليوم</div></div>
    <div class="stat-card"><div class="num"><?= $stats['appts_pending_confirmation'] ?></div><div class="label">بانتظار التأكيد</div></div>
  <?php endif; ?>
  <?php if ($canSeeBookings): ?>
    <div class="stat-card"><div class="num"><?= $stats['bookings_new'] ?></div><div class="label">طلبات جديدة</div></div>
  <?php endif; ?>
  <?php if ($canSeeClinicOps): ?>
    <div class="stat-card"><div class="num"><?= $stats['ops_tasks_open'] ?></div><div class="label">مهام تشغيلية مفتوحة</div></div>
  <?php endif; ?>
  <?php if ($canSeeContent): ?>
    <div class="stat-card"><div class="num"><?= $stats['articles_in_review'] ?></div><div class="label">محتوى بانتظار الاعتماد</div></div>
  <?php endif; ?>
  <?php if ($canSeeFinancial && isset($stats['invoices_due'])): ?>
    <div class="stat-card"><div class="num"><?= $stats['invoices_due'] ?></div><div class="label">فواتير مستحقة</div></div>
  <?php endif; ?>
</div>

<?php if ($canSeeClinicOps && $nextAppt): ?>
<div class="admin-card" style="margin-bottom:var(--admin-sp-4); border-inline-start:3px solid var(--color-sage-dark);">
  <div class="field-hint" style="margin-bottom:4px;">التالي الآن</div>
  <div style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:var(--admin-sp-3);">
    <div>
      <strong style="font-size:1.05rem;"><?= e(date('H:i', strtotime($nextAppt['starts_at']))) ?> — <?= e($nextAppt['service_name'] ?? 'حظر فترة') ?></strong>
      <?php if ($nextAppt['patient_name']): ?><span class="field-hint"> · <?= e($nextAppt['patient_name']) ?></span><?php endif; ?>
    </div>
    <a href="/admin/appointment-edit.php?id=<?= (int)$nextAppt['id'] ?>" class="btn btn-outline btn-sm">فتح الموعد</a>
  </div>
</div>
<?php endif; ?>

<?php if ($canSeeClinicOps): ?>
<div class="admin-card" style="margin-bottom:var(--admin-sp-4);">
  <div class="admin-page-head">
    <h2 style="margin:0;">مواعيد اليوم</h2>
    <a href="/admin/calendar.php" class="btn btn-outline btn-sm">فتح التقويم</a>
  </div>
  <?php if (empty($todayAppts)): ?>
    <div class="empty-state"><p>ما في مواعيد اليوم.</p></div>
  <?php else: ?>
    <table class="data-table">
      <thead><tr><th>الوقت</th><th>الخدمة</th><th>المريض</th><th>الحالة</th></tr></thead>
      <tbody>
        <?php foreach ($todayAppts as $a): ?>
          <tr>
            <td><a href="/admin/appointment-edit.php?id=<?= (int)$a['id'] ?>" dir="ltr" style="unicode-bidi:isolate;"><?= e(substr($a['starts_at'], 11, 5)) ?></a></td>
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

<?php if ($canSeeBookings): ?>
<div class="admin-card" style="margin-bottom:var(--admin-sp-4);">
  <div class="admin-page-head">
    <h2 style="margin:0;">طلبات تحتاج متابعة</h2>
    <a href="/admin/appointments.php" class="btn btn-outline btn-sm">كل الطلبات</a>
  </div>
  <?php if (empty($recentBookings)): ?>
    <div class="empty-state"><p>ما في طلبات مفتوحة حاليًا. 🎉</p></div>
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

<?php if ($canSeeClinicOps && !empty($openTasks)): ?>
<div class="admin-card" style="margin-bottom:var(--admin-sp-4);">
  <div class="admin-page-head">
    <h2 style="margin:0;">مهام تشغيلية</h2>
    <a href="/admin/ops-tasks.php" class="btn btn-outline btn-sm">كل المهام</a>
  </div>
  <ul style="margin:0; padding:0; list-style:none;">
    <?php foreach ($openTasks as $t): ?>
      <li style="padding:8px 0; border-bottom:1px solid var(--admin-border); display:flex; justify-content:space-between; gap:10px;">
        <span><?= e($t['title']) ?></span>
        <span class="field-hint"><?= $t['due_at'] ? e($t['due_at']) : '' ?></span>
      </li>
    <?php endforeach; ?>
  </ul>
</div>
<?php endif; ?>

<?php if ($canSeeContent && !empty($staleArticles)): ?>
<div class="admin-card">
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

<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
