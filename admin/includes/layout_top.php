<?php
/**
 * Shared admin layout shell. Include AFTER calling require_admin()/require_role()
 * so $adminUser is available, and AFTER setting $pageTitle + $activeNav.
 * Optional: set $primaryAction = ['label' => '...', 'href' => '...'] before
 * including this file to show one contextual action button in the topbar.
 *
 * Navigation itself (groups/tabs/roles) lives entirely in nav_config.php —
 * this file only renders what that config describes. See the plan notes
 * there for why the mapping works this way.
 */
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/nav_config.php';

$adminUser = $adminUser ?? current_admin();
$activeNav = $activeNav ?? '';
$flashes = flash_get_all();
$primaryAction = $primaryAction ?? null;

$currentGroupKey = $NAV_PAGE_TO_GROUP[$activeNav]['group'] ?? null;
$currentSection = $NAV_PAGE_TO_GROUP[$activeNav]['section'] ?? 'main';
$currentGroup = $currentGroupKey ? ($currentSection === 'main' ? ($NAV_GROUPS[$currentGroupKey] ?? null) : ($NAV_BOTTOM[$currentGroupKey] ?? null)) : null;

$alertCounts = $adminUser ? dashboard_alert_counts($adminUser) : ['total' => 0];

/** First tab of a group the current user is actually allowed to open. */
function nav_first_visible_tab(array $group, ?array $user): ?array
{
    foreach ($group['tabs'] as $tab) {
        if (nav_user_can($tab['roles'], $user)) {
            return $tab;
        }
    }
    return null;
}

/** Visible tabs of the CURRENT group, used both for the sub-tab strip and the sidebar badge. */
function nav_visible_tabs(array $group, ?array $user): array
{
    return array_filter($group['tabs'], fn($tab) => nav_user_can($tab['roles'], $user));
}

$groupBadge = function (string $key) use ($alertCounts): int {
    return match ($key) {
        'appointments' => ($alertCounts['new_requests'] ?? 0) + ($alertCounts['pending_confirmation'] ?? 0),
        'content' => $alertCounts['content_in_review'] ?? 0,
        'financial' => $alertCounts['invoices_due'] ?? 0,
        default => 0,
    };
};
?><!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<meta name="csrf-token" content="<?= e(csrf_token()) ?>">
<title><?= e($pageTitle ?? 'لوحة الإدارة') ?> — لوحة إدارة تالا</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/style.css">
<link rel="stylesheet" href="/assets/css/admin.css?v=2">
</head>
<body class="admin-body">
<div class="admin-shell <?= ($adminUser && (int)$adminUser['sidebar_collapsed'] === 1) ? 'is-collapsed' : '' ?>" id="adminShell">

  <div class="admin-mobile-bar">
    <button type="button" class="icon-btn" id="mobileMenuBtn" aria-label="فتح القائمة"><?php admin_icon('menu') ?></button>
    <a href="/admin/dashboard.php" class="mobile-logo">تالا <span>| الإدارة</span></a>
  </div>
  <div class="admin-sidebar-backdrop" id="sidebarBackdrop"></div>

  <aside class="admin-sidebar" id="adminSidebar">
    <div class="sidebar-head">
      <a href="/admin/dashboard.php" class="logo">تالا <span>| الإدارة</span></a>
      <button type="button" class="icon-btn sidebar-collapse-btn" id="collapseBtn" aria-label="طي/توسيع القائمة"><?php admin_icon('collapse') ?></button>
    </div>

    <nav class="admin-nav">
      <?php foreach ($NAV_GROUPS as $groupKey => $group): ?>
        <?php
        if (!nav_user_can($group['roles'], $adminUser)) { continue; }
        if (isset($group['require_setting']) && !setting_bool($group['require_setting'])) { continue; }
        $firstTab = nav_first_visible_tab($group, $adminUser);
        if (!$firstTab) { continue; }
        $badge = $groupBadge($groupKey);
        $isActive = $currentSection === 'main' && $currentGroupKey === $groupKey;
        ?>
        <a href="<?= e($firstTab['href']) ?>" class="nav-item <?= $isActive ? 'active' : '' ?>" title="<?= e($group['label']) ?>">
          <?php admin_icon($group['icon']) ?>
          <span class="nav-label"><?= e($group['label']) ?></span>
          <?php if ($badge > 0): ?><span class="nav-badge"><?= $badge > 99 ? '99+' : $badge ?></span><?php endif; ?>
        </a>
      <?php endforeach; ?>
    </nav>

    <nav class="admin-nav admin-nav-bottom">
      <?php foreach ($NAV_BOTTOM as $groupKey => $group): ?>
        <?php
        if (!nav_user_can($group['roles'], $adminUser)) { continue; }
        $firstTab = nav_first_visible_tab($group, $adminUser);
        if (!$firstTab) { continue; }
        $isActive = $currentSection === 'bottom' && $currentGroupKey === $groupKey;
        ?>
        <a href="<?= e($firstTab['href']) ?>" class="nav-item <?= $isActive ? 'active' : '' ?>" title="<?= e($group['label']) ?>">
          <?php admin_icon($group['icon']) ?>
          <span class="nav-label"><?= e($group['label']) ?></span>
        </a>
      <?php endforeach; ?>
      <a href="/index.php" target="_blank" class="nav-item" title="عرض الموقع">
        <?php admin_icon('external') ?>
        <span class="nav-label">عرض الموقع</span>
      </a>
      <a href="/admin/logout.php" class="nav-item" title="تسجيل الخروج">
        <?php admin_icon('logout') ?>
        <span class="nav-label">تسجيل الخروج</span>
      </a>
    </nav>
  </aside>

  <div class="admin-content-col">
    <header class="admin-topbar">
      <div class="topbar-titles">
        <?php if ($currentGroup): ?>
          <div class="topbar-breadcrumb"><?= e($currentGroup['label']) ?><?php if (count(nav_visible_tabs($currentGroup, $adminUser)) > 1): ?><span> › </span><?= e($currentGroup['tabs'][$activeNav]['label'] ?? '') ?><?php endif; ?></div>
        <?php endif; ?>
        <h1 class="topbar-page-title"><?= e($pageTitle ?? '') ?></h1>
      </div>

      <div class="topbar-actions">
        <button type="button" class="topbar-search-btn" id="searchBtn">
          <?php admin_icon('search') ?>
          <span>بحث أو انتقال سريع…</span>
          <kbd>Ctrl K</kbd>
        </button>

        <?php if ($primaryAction): ?>
          <a href="<?= e($primaryAction['href']) ?>" class="btn btn-primary btn-sm topbar-primary-action">
            <?php admin_icon('plus', '15px') ?> <?= e($primaryAction['label']) ?>
          </a>
        <?php endif; ?>

        <div class="topbar-popover-wrap">
          <button type="button" class="icon-btn" id="notifBtn" aria-label="التنبيهات">
            <?php admin_icon('bell') ?>
            <?php if (($alertCounts['total'] ?? 0) > 0): ?><span class="icon-btn-badge"><?= $alertCounts['total'] > 99 ? '99+' : $alertCounts['total'] ?></span><?php endif; ?>
          </button>
          <div class="topbar-popover" id="notifPanel" hidden>
            <div class="topbar-popover-head">التنبيهات</div>
            <?php if (($alertCounts['total'] ?? 0) === 0): ?>
              <p class="topbar-popover-empty">ما في تنبيهات حاليًا.</p>
            <?php else: ?>
              <ul class="topbar-popover-list">
                <?php if (!empty($alertCounts['new_requests'])): ?><li><a href="/admin/appointments.php?status=new"><?= $alertCounts['new_requests'] ?> طلب موعد جديد</a></li><?php endif; ?>
                <?php if (!empty($alertCounts['pending_confirmation'])): ?><li><a href="/admin/calendar.php"><?= $alertCounts['pending_confirmation'] ?> موعد بانتظار التأكيد</a></li><?php endif; ?>
                <?php if (!empty($alertCounts['open_tasks'])): ?><li><a href="/admin/ops-tasks.php"><?= $alertCounts['open_tasks'] ?> مهمة تشغيلية مفتوحة</a></li><?php endif; ?>
                <?php if (!empty($alertCounts['content_in_review'])): ?><li><a href="/admin/review-queue.php"><?= $alertCounts['content_in_review'] ?> عنصر بانتظار الاعتماد</a></li><?php endif; ?>
                <?php if (!empty($alertCounts['invoices_due'])): ?><li><a href="/admin/billing.php?status=due"><?= $alertCounts['invoices_due'] ?> فاتورة مستحقة</a></li><?php endif; ?>
              </ul>
            <?php endif; ?>
          </div>
        </div>

        <div class="topbar-popover-wrap">
          <button type="button" class="user-menu-btn" id="userMenuBtn">
            <span class="user-menu-avatar"><?= e(mb_substr($adminUser['name'] ?? '؟', 0, 1)) ?></span>
            <span class="user-menu-name mobile-hide"><?= e($adminUser['name'] ?? '') ?></span>
            <?php admin_icon('chevron-down', '14px') ?>
          </button>
          <div class="topbar-popover" id="userMenuPanel" hidden>
            <div class="topbar-popover-head"><?= e($adminUser['name'] ?? '') ?><br><span class="field-hint"><?= e(role_label($adminUser['role'] ?? '')) ?></span></div>
            <ul class="topbar-popover-list">
              <li><a href="/admin/account.php"><?php admin_icon('account', '15px') ?> حسابي</a></li>
              <li><a href="/admin/logout.php"><?php admin_icon('logout', '15px') ?> تسجيل الخروج</a></li>
            </ul>
          </div>
        </div>
      </div>
    </header>

    <?php if ($currentGroup && count(nav_visible_tabs($currentGroup, $adminUser)) > 1): ?>
    <nav class="admin-subtabs">
      <?php foreach (nav_visible_tabs($currentGroup, $adminUser) as $tabKey => $tab): ?>
        <a href="<?= e($tab['href']) ?>" class="subtab <?= $activeNav === $tabKey ? 'active' : '' ?>"><?= e($tab['label']) ?></a>
      <?php endforeach; ?>
    </nav>
    <?php endif; ?>

    <main class="admin-main">
      <?php foreach ($flashes as $f): ?>
        <div class="alert alert-<?= e($f['type']) ?>"><?= e($f['message']) ?></div>
      <?php endforeach; ?>
