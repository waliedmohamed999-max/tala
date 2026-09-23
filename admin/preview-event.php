<?php
require_once __DIR__ . '/../includes/auth.php';
require_content_access();

$id = (int)($_GET['id'] ?? 0);
$stmt = db()->prepare('SELECT * FROM events WHERE id = ?');
$stmt->execute([$id]);
$event = $stmt->fetch();
if (!$event) { http_response_code(404); exit('الفعالية غير موجودة.'); }

$media = db()->prepare('SELECT * FROM event_media WHERE event_id = ? ORDER BY sort_order');
$media->execute([$id]);
$media = $media->fetchAll();

$pageTitle = 'معاينة: ' . $event['title'];
$noIndex = true;
require __DIR__ . '/../includes/header.php';
?>
<div class="notice-banner no-print" style="border-radius:0; text-align:center; margin:0;">
  <strong>هاي معاينة إدارية فقط</strong> — الفعالية <?= $event['is_published'] ? 'منشورة' : 'غير منشورة بعد' ?>، وقسم الفعاليات العام <?= setting_bool('events_section_enabled') ? 'مفعّل' : 'غير مفعّل حاليًا' ?>.
  <a href="javascript:history.back()">رجوع</a>
</div>

<section class="section container article-body">
  <h1><?= e($event['title']) ?></h1>
  <div class="article-meta">
    <?php if ($event['event_date']): ?><span>📅 <?= e($event['event_date']) ?></span><?php endif; ?>
    <?php if ($event['location_or_stream']): ?><span>📍 <?= e($event['location_or_stream']) ?></span><?php endif; ?>
    <?php if ($event['audience']): ?><span>👥 <?= e($event['audience']) ?></span><?php endif; ?>
  </div>
  <?= $event['description'] ?>

  <?php if (!empty($media)): ?>
    <h3 style="margin-top:30px;">معرض الصور والفيديو</h3>
    <div class="grid grid-3">
      <?php foreach ($media as $m): ?>
        <div class="card" style="padding:10px;">
          <?php if ($m['media_type'] === 'image'): ?>
            <img src="<?= e($m['file_path']) ?>" alt="<?= e($m['alt_text']) ?>" style="border-radius:8px; width:100%;">
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
