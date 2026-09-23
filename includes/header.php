<?php
/**
 * Shared public-site header. Expects (all optional) before include:
 *   $pageTitle        string  — browser tab title (without site suffix)
 *   $pageDescription  string  — meta description, ~150-160 chars
 *   $canonicalPath    string  — e.g. '/articles/xyz.php'
 *   $noIndex          bool    — true to add <meta name="robots" content="noindex">
 */
require_once __DIR__ . '/i18n.php';

$siteName = get_setting('display_title', 'تالا | معالجة نفسية');
$pageTitle = $pageTitle ?? $siteName;
$fullTitle = $pageTitle === $siteName ? $siteName : $pageTitle . ' — ' . $siteName;
$pageDescription = $pageDescription ?? 'مساحة آمنة للحديث والدعم النفسي مع تالا في حمص، سوريا. تعرّف على الخدمات، اقرأ محتوى إرشاديًا، واطلب موعدك بخصوصية.';
$canonicalPath = $canonicalPath ?? $_SERVER['REQUEST_URI'];
$currentFile = basename($_SERVER['SCRIPT_NAME']);
$locale = current_locale();

if (empty($noIndex)) {
    record_analytics_event('page_view', parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
}
?><!DOCTYPE html>
<html lang="<?= $locale === 'en' ? 'en' : 'ar' ?>" dir="<?= is_rtl() ? 'rtl' : 'ltr' ?>">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($fullTitle) ?></title>
<meta name="description" content="<?= e($pageDescription) ?>">
<link rel="canonical" href="<?= e($canonicalPath) ?>">
<?php if (!empty($noIndex)): ?>
<meta name="robots" content="noindex, nofollow">
<?php else: ?>
<link rel="alternate" hreflang="ar" href="<?= e(locale_url('ar')) ?>">
<link rel="alternate" hreflang="en" href="<?= e(locale_url('en')) ?>">
<link rel="alternate" hreflang="x-default" href="<?= e(locale_url('ar')) ?>">
<?php endif; ?>
<meta property="og:type" content="website">
<meta property="og:title" content="<?= e($fullTitle) ?>">
<meta property="og:description" content="<?= e($pageDescription) ?>">
<meta property="og:locale" content="<?= $locale === 'en' ? 'en_US' : 'ar_SY' ?>">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/style.css">
<?php if ($locale === 'en'): ?><link rel="stylesheet" href="/assets/css/ltr.css"><?php endif; ?>
<link rel="icon" href="data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><rect width=%22100%22 height=%22100%22 rx=%2222%22 fill=%22%237C9885%22/><text x=%2250%22 y=%2268%22 font-size=%2258%22 fill=%22white%22 text-anchor=%22middle%22 font-family=%22sans-serif%22>ت</text></svg>">
</head>
<body>
<a href="#main-content" class="skip-link"><?= $locale === 'en' ? 'Skip to main content' : 'تخطَّ إلى المحتوى الرئيسي' ?></a>

<header class="site-header">
  <div class="container nav">
    <a href="/index.php" class="logo">تالا <span>| <?= $locale === 'en' ? 'Psychotherapy' : 'معالجة نفسية' ?></span></a>

    <button class="nav-toggle" aria-expanded="false" aria-controls="primary-nav" aria-label="<?= $locale === 'en' ? 'Open navigation menu' : 'فتح قائمة التنقل' ?>">☰</button>

    <nav aria-label="<?= $locale === 'en' ? 'Main navigation' : 'التنقل الرئيسي' ?>">
      <ul class="nav-links" id="primary-nav">
        <li><a href="/index.php" <?= $currentFile === 'index.php' ? 'aria-current="page"' : '' ?>><?= t('nav_home') ?></a></li>
        <li><a href="/start-here.php" <?= $currentFile === 'start-here.php' ? 'aria-current="page"' : '' ?>><?= t('nav_start_here') ?></a></li>
        <li><a href="/about.php" <?= $currentFile === 'about.php' ? 'aria-current="page"' : '' ?>><?= t('nav_about') ?></a></li>
        <li><a href="/services.php" <?= in_array($currentFile, ['services.php','service.php']) ? 'aria-current="page"' : '' ?>><?= t('nav_services') ?></a></li>
        <li><a href="/articles/index.php" <?= str_contains($_SERVER['REQUEST_URI'], '/articles/') ? 'aria-current="page"' : '' ?>><?= t('nav_articles') ?></a></li>
        <li><a href="/toolkit/index.php" <?= str_contains($_SERVER['REQUEST_URI'], '/toolkit/') ? 'aria-current="page"' : '' ?>><?= t('nav_toolkit') ?></a></li>
        <li><a href="/faq.php" <?= $currentFile === 'faq.php' ? 'aria-current="page"' : '' ?>><?= t('nav_faq') ?></a></li>
        <li><a href="/contact.php" <?= $currentFile === 'contact.php' ? 'aria-current="page"' : '' ?>><?= t('nav_contact') ?></a></li>
        <li class="lang-switch-mobile">
          <?php if ($locale === 'ar'): ?>
            <a href="<?= e(locale_url('en')) ?>">🌐 English</a>
          <?php else: ?>
            <a href="<?= e(locale_url('ar')) ?>">🌐 العربية</a>
          <?php endif; ?>
        </li>
      </ul>
    </nav>

    <div class="nav-cta">
      <a href="<?= e(locale_url($locale === 'ar' ? 'en' : 'ar')) ?>" class="lang-switch-desktop" title="<?= $locale === 'ar' ? 'Switch to English' : 'التبديل للعربية' ?>"><?= $locale === 'ar' ? 'EN' : 'AR' ?></a>
      <a href="/book.php" class="btn btn-primary"><span class="hide-mobile">📅</span> <?= t('nav_book') ?></a>
    </div>
  </div>
</header>

<main id="main-content">
