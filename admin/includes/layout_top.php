<?php
/**
 * Shared admin layout header. Include AFTER calling require_admin()/require_role()
 * so $adminUser is available, and AFTER setting $pageTitle + $activeNav.
 */
require_once __DIR__ . '/../../includes/auth.php';

$adminUser = $adminUser ?? current_admin();
$activeNav = $activeNav ?? '';
$flashes = flash_get_all();
$canSeeContent = $adminUser && in_array($adminUser['role'], ['owner', 'editor', 'reviewer'], true);
$canSeeBookings = $adminUser && in_array($adminUser['role'], ['owner', 'bookings_manager'], true);
$canReview = $adminUser && in_array($adminUser['role'], ['owner', 'reviewer'], true);
$isOwner = $adminUser && $adminUser['role'] === 'owner';
?><!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e($pageTitle ?? 'لوحة الإدارة') ?> — لوحة إدارة تالا</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/style.css">
<link rel="stylesheet" href="/assets/css/admin.css">
</head>
<body class="admin-body">
<div class="admin-shell">
  <aside class="admin-sidebar">
    <a href="/admin/dashboard.php" class="logo">تالا <span>| الإدارة</span></a>
    <ul class="admin-nav">
      <li><a href="/admin/dashboard.php" class="<?= $activeNav === 'dashboard' ? 'active' : '' ?>">لوحة التحكم</a></li>

      <?php if ($canSeeContent): ?>
      <li class="nav-section">المحتوى</li>
      <?php if ($canReview): ?><li><a href="/admin/review-queue.php" class="<?= $activeNav === 'review-queue' ? 'active' : '' ?>">⭐ قائمة الاعتماد</a></li><?php endif; ?>
      <li><a href="/admin/articles.php" class="<?= $activeNav === 'articles' ? 'active' : '' ?>">المقالات والأدلة</a></li>
      <li><a href="/admin/categories.php" class="<?= $activeNav === 'categories' ? 'active' : '' ?>">التصنيفات</a></li>
      <li><a href="/admin/services.php" class="<?= $activeNav === 'services' ? 'active' : '' ?>">الخدمات</a></li>
      <li><a href="/admin/faqs.php" class="<?= $activeNav === 'faqs' ? 'active' : '' ?>">الأسئلة الشائعة</a></li>
      <li><a href="/admin/resources.php" class="<?= $activeNav === 'resources' ? 'active' : '' ?>">مصادر وملفات</a></li>
      <li><a href="/admin/events.php" class="<?= $activeNav === 'events' ? 'active' : '' ?>">محاضرات وفعاليات</a></li>
      <?php endif; ?>

      <?php if ($canSeeBookings): ?>
      <li class="nav-section">الطلبات</li>
      <li><a href="/admin/appointments.php" class="<?= $activeNav === 'appointments' ? 'active' : '' ?>">طلبات المواعيد</a></li>
      <li><a href="/admin/messages.php" class="<?= $activeNav === 'messages' ? 'active' : '' ?>">رسائل التواصل</a></li>
      <li><a href="/admin/speaking-inquiries.php" class="<?= $activeNav === 'speaking' ? 'active' : '' ?>">طلبات دعوات التحدث</a></li>
      <li><a href="/admin/reply-templates.php" class="<?= $activeNav === 'templates' ? 'active' : '' ?>">قوالب الردود</a></li>
      <?php endif; ?>

      <?php if ($isOwner): ?>
      <li class="nav-section">الإعدادات</li>
      <li><a href="/admin/settings.php" class="<?= $activeNav === 'settings' ? 'active' : '' ?>">الملف والإعدادات العامة</a></li>
      <li><a href="/admin/notifications-settings.php" class="<?= $activeNav === 'notifications' ? 'active' : '' ?>">إعدادات الإشعارات والبريد</a></li>
      <li><a href="/admin/subscribers.php" class="<?= $activeNav === 'subscribers' ? 'active' : '' ?>">النشرة البريدية</a></li>
      <li><a href="/admin/analytics.php" class="<?= $activeNav === 'analytics' ? 'active' : '' ?>">إحصاءات الزيارات</a></li>
      <li><a href="/admin/users.php" class="<?= $activeNav === 'users' ? 'active' : '' ?>">المستخدمون والصلاحيات</a></li>
      <?php endif; ?>

      <li style="margin-top:14px;"><a href="/index.php" target="_blank">🔗 عرض الموقع</a></li>
      <li><a href="/admin/logout.php">🚪 تسجيل الخروج</a></li>
    </ul>
  </aside>

  <div>
    <div class="admin-topbar">
      <div></div>
      <div class="admin-user-chip">
        <?php if ($adminUser): ?>
          <span><?= e($adminUser['name']) ?> — <?= e(role_label($adminUser['role'])) ?></span>
        <?php endif; ?>
      </div>
    </div>
    <main class="admin-main">
      <?php foreach ($flashes as $f): ?>
        <div class="alert alert-<?= e($f['type']) ?>"><?= e($f['message']) ?></div>
      <?php endforeach; ?>
