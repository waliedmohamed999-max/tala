<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/i18n.php';

if (!setting_bool('events_section_enabled')) {
    http_response_code(404);
    $pageTitle = 'الصفحة غير موجودة';
    require __DIR__ . '/../includes/header.php';
    echo '<section class="section container text-center"><h1>الصفحة غير متاحة</h1></section>';
    require __DIR__ . '/../includes/footer.php';
    exit;
}

$slug = $_GET['slug'] ?? '';
$stmt = db()->prepare("SELECT * FROM events WHERE slug = ? AND is_published = 1");
$stmt->execute([$slug]);
$event = $stmt->fetch();

if (!$event) {
    http_response_code(404);
    $pageTitle = 'الفعالية غير موجودة';
    require __DIR__ . '/../includes/header.php';
    echo '<section class="section container text-center"><h1>ما لقينا هالفعالية</h1><a href="/events/index.php" class="btn btn-primary">كل الفعاليات</a></section>';
    require __DIR__ . '/../includes/footer.php';
    exit;
}

if (current_locale() === 'en') {
    show_untranslated_notice_page($event['title'] . ' (Arabic only)', '/events/event.php?slug=' . urlencode($slug));
    exit;
}

$pageTitle = $event['title'];
$pageDescription = $event['topic_summary'] ?: $event['title'];
require __DIR__ . '/../includes/header.php';

$isUpcoming = $event['event_date'] && $event['event_date'] >= date('Y-m-d');
$media = db()->prepare('SELECT * FROM event_media WHERE event_id = ? ORDER BY sort_order');
$media->execute([$event['id']]);
$media = $media->fetchAll();
?>

<section class="section container article-body">
  <div class="breadcrumbs"><a href="/events/index.php">محاضرات وفعاليات</a></div>
  <?php if ($event['event_date']): ?><span class="tag"><?= $isUpcoming ? 'فعالية قادمة' : 'فعالية سابقة' ?></span><?php endif; ?>
  <h1><?= e($event['title']) ?></h1>
  <div class="article-meta">
    <?php if ($event['event_date']): ?><span>📅 <?= e($event['event_date']) ?></span><?php endif; ?>
    <?php if ($event['location_or_stream']): ?><span>📍 <?= e($event['location_or_stream']) ?></span><?php endif; ?>
    <?php if ($event['audience']): ?><span>👥 <?= e($event['audience']) ?></span><?php endif; ?>
  </div>
  <?= $event['description'] ?>

  <?php if (!empty($media)): ?>
    <h2 style="margin-top:36px;">صور وفيديو</h2>
    <div class="grid grid-3">
      <?php foreach ($media as $m): ?>
        <div class="card" style="padding:10px;">
          <?php if ($m['media_type'] === 'image'): ?>
            <img src="<?= e($m['file_path']) ?>" alt="<?= e($m['alt_text']) ?>" style="border-radius:8px; width:100%;" loading="lazy">
          <?php elseif ($m['media_type'] === 'video_file'): ?>
            <video src="<?= e($m['file_path']) ?>" controls style="width:100%; border-radius:8px;"></video>
          <?php else: ?>
            <a href="<?= e($m['external_url']) ?>" target="_blank" rel="noopener">🎬 <?= e($m['caption'] ?: 'شاهد الفيديو') ?></a>
          <?php endif; ?>
          <?php if ($m['caption']): ?><p class="field-hint" style="margin-top:6px;"><?= e($m['caption']) ?></p><?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
