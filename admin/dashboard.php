<?php
require_once __DIR__ . '/../includes/auth.php';
$adminUser = require_admin();
$pageTitle = 'لوحة التحكم';
$activeNav = 'dashboard';

$canSeeBookings = in_array($adminUser['role'], ['owner', 'bookings_manager'], true);
$canSeeContent = in_array($adminUser['role'], ['owner', 'editor', 'reviewer'], true);

$stats = [];
if ($canSeeContent) {
    $stats['articles_published'] = (int)db()->query("SELECT COUNT(*) FROM articles WHERE published_content IS NOT NULL")->fetchColumn();
    $stats['articles_in_review'] = (int)db()->query("SELECT COUNT(*) FROM articles WHERE workflow_status='in_review'")->fetchColumn();
    $stats['articles_changes_requested'] = (int)db()->query("SELECT COUNT(*) FROM articles WHERE workflow_status='changes_requested'")->fetchColumn();
    $stats['articles_needs_update'] = (int)db()->query("SELECT COUNT(*) FROM articles WHERE workflow_status='needs_update'")->fetchColumn();
    $stats['services_published'] = (int)db()->query("SELECT COUNT(*) FROM services WHERE is_published=1")->fetchColumn();

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
}

require __DIR__ . '/includes/layout_top.php';
?>

<div class="admin-page-head">
  <h1>أهلًا <?= e($adminUser['name']) ?> 👋</h1>
</div>

<div class="stat-grid">
  <?php if ($canSeeBookings): ?>
  <div class="stat-card">
    <div class="num"><?= $stats['bookings_new'] ?></div>
    <div class="label">طلبات مواعيد جديدة</div>
  </div>
  <?php endif; ?>
  <?php if ($canSeeContent): ?>
  <div class="stat-card">
    <div class="num"><?= $stats['articles_published'] ?></div>
    <div class="label">مقالات منشورة</div>
  </div>
  <div class="stat-card">
    <div class="num"><?= $stats['articles_in_review'] ?></div>
    <div class="label">بانتظار المراجعة المهنية</div>
  </div>
  <div class="stat-card">
    <div class="num"><?= $stats['articles_changes_requested'] ?></div>
    <div class="label">بانتظار تعديل المحرر</div>
  </div>
  <div class="stat-card">
    <div class="num"><?= $stats['articles_needs_update'] ?></div>
    <div class="label">مقالات بحاجة تحديث</div>
  </div>
  <?php endif; ?>
</div>

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
