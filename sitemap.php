<?php
require_once __DIR__ . '/includes/functions.php';
header('Content-Type: application/xml; charset=utf-8');

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$base = $scheme . '://' . $_SERVER['HTTP_HOST'];

$staticPages = [
    '/index.php', '/about.php', '/services.php', '/how-it-works.php', '/start-here.php',
    '/articles/index.php', '/resources/index.php', '/toolkit/index.php',
    '/toolkit/name-feeling.php', '/toolkit/breathing.php', '/toolkit/journal.php', '/toolkit/prep-list.php',
    '/faq.php', '/contact.php', '/book.php',
    '/privacy.php', '/terms.php',
];

$articles = db()->query("SELECT slug, published_at AS updated_at FROM articles WHERE published_content IS NOT NULL AND locale = 'ar'")->fetchAll();
$articlesEn = db()->query("SELECT slug, published_at AS updated_at FROM articles WHERE published_content IS NOT NULL AND locale = 'en'")->fetchAll();
$services = db()->query("SELECT slug, updated_at FROM services WHERE is_published = 1")->fetchAll();
$categories = db()->query("SELECT slug FROM categories")->fetchAll();
$eventsEnabled = setting_bool('events_section_enabled');
$events = $eventsEnabled ? db()->query("SELECT slug, updated_at FROM events WHERE is_published = 1")->fetchAll() : [];

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

foreach ($staticPages as $path) {
    echo '  <url><loc>' . e($base . $path) . '</loc></url>' . "\n";
}
if ($eventsEnabled) {
    echo '  <url><loc>' . e($base . '/events/index.php') . '</loc></url>' . "\n";
}
foreach ($categories as $c) {
    echo '  <url><loc>' . e($base . '/articles/category.php?slug=' . $c['slug']) . '</loc></url>' . "\n";
}
foreach ($services as $s) {
    echo '  <url><loc>' . e($base . '/service.php?slug=' . $s['slug']) . '</loc><lastmod>' . e(date('Y-m-d', strtotime($s['updated_at']))) . '</lastmod></url>' . "\n";
}
foreach ($articles as $a) {
    echo '  <url><loc>' . e($base . '/articles/article.php?slug=' . $a['slug']) . '</loc><lastmod>' . e(date('Y-m-d', strtotime($a['updated_at']))) . '</lastmod></url>' . "\n";
}
// English article translations only ever appear here once a reviewer has
// actually published them (see articles.locale/translation_of) — never
// machine-translated placeholders.
foreach ($articlesEn as $a) {
    echo '  <url><loc>' . e($base . '/articles/article.php?slug=' . $a['slug'] . '&lang=en') . '</loc><lastmod>' . e(date('Y-m-d', strtotime($a['updated_at']))) . '</lastmod></url>' . "\n";
}
foreach ($events as $ev) {
    echo '  <url><loc>' . e($base . '/events/event.php?slug=' . $ev['slug']) . '</loc><lastmod>' . e(date('Y-m-d', strtotime($ev['updated_at']))) . '</lastmod></url>' . "\n";
}

echo '</urlset>';
