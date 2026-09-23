<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/i18n.php';

// This whole section stays invisible (404) unless Tala explicitly enabled it
// AND at least one real event exists — see admin/settings.php + admin/events.php.
if (!setting_bool('events_section_enabled')) {
    http_response_code(404);
    $pageTitle = 'الصفحة غير موجودة';
    require __DIR__ . '/../includes/header.php';
    echo '<section class="section container text-center"><h1>الصفحة غير متاحة</h1></section>';
    require __DIR__ . '/../includes/footer.php';
    exit;
}

if (current_locale() === 'en') {
    show_untranslated_notice_page('Talks & Events', '/events/index.php');
    exit;
}

$pageTitle = 'محاضرات وفعاليات';
$pageDescription = 'محاضرات وورش عمل مع تالا.';
require __DIR__ . '/../includes/header.php';

$events = db()->query("SELECT * FROM events WHERE is_published = 1 ORDER BY event_date DESC")->fetchAll();
$today = date('Y-m-d');
$upcoming = array_filter($events, fn($e) => $e['event_date'] && $e['event_date'] >= $today);
$past = array_filter($events, fn($e) => !$e['event_date'] || $e['event_date'] < $today);

function render_event_card(array $ev): void {
    ?>
    <article class="card">
      <h3><a href="/events/event.php?slug=<?= urlencode($ev['slug']) ?>"><?= e($ev['title']) ?></a></h3>
      <?php if ($ev['topic_summary']): ?><p><?= e($ev['topic_summary']) ?></p><?php endif; ?>
      <?php if ($ev['event_date']): ?><p class="field-hint">📅 <?= e($ev['event_date']) ?></p><?php endif; ?>
    </article>
    <?php
}
?>

<section class="page-hero container">
  <span class="eyebrow">محاضرات وفعاليات</span>
  <h1>محاضرات وفعاليات</h1>
</section>

<section class="section" style="padding-top:0;">
  <div class="container">
    <?php if (empty($events)): ?>
      <p class="text-center" style="padding:40px 0; color:var(--color-text-muted);">ما في فعاليات معلنة حاليًا.</p>
    <?php else: ?>
      <?php if (!empty($upcoming)): ?>
        <h2>فعاليات قادمة</h2>
        <div class="grid grid-3" style="margin-bottom:36px;">
          <?php foreach ($upcoming as $ev): render_event_card($ev); endforeach; ?>
        </div>
      <?php endif; ?>
      <?php if (!empty($past)): ?>
        <h2>فعاليات سابقة</h2>
        <div class="grid grid-3">
          <?php foreach ($past as $ev): render_event_card($ev); endforeach; ?>
        </div>
      <?php endif; ?>
    <?php endif; ?>

    <div class="card text-center" style="max-width:600px; margin:40px auto 0;">
      <h3>دعوة للتحدث أو تقديم ورشة؟</h3>
      <p>فيك ترسل تفاصيل الفعالية وبنراجعها.</p>
      <a href="/events/speaking-inquiry.php" class="btn btn-primary">أرسل دعوة</a>
    </div>
  </div>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
