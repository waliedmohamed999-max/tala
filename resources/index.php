<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/i18n.php';

if (current_locale() === 'en') {
    show_untranslated_notice_page('Useful Resources', '/resources/index.php');
    exit;
}

$pageTitle = 'مصادر مفيدة';
$pageDescription = 'روابط وملفات موثوقة عن الصحة النفسية، مراجَعة دوريًا.';
require __DIR__ . '/../includes/header.php';

$links = db()->query('SELECT * FROM external_resources WHERE is_published = 1 ORDER BY sort_order')->fetchAll();
$media = db()->query('SELECT * FROM media_resources WHERE is_published = 1 ORDER BY sort_order')->fetchAll();
$mediaTypeLabels = ['file' => '📄 ملف', 'video_url' => '🎬 فيديو', 'audio_url' => '🎧 تسجيل صوتي'];
?>

<section class="page-hero container">
  <div style="display:flex; justify-content:center; margin-bottom:6px;"><?php render_topic_art(6); ?></div>
  <span class="eyebrow">مركز الموارد</span>
  <h1>مصادر مفيدة</h1>
  <p>روابط وملفات مختارة بعناية، تُراجع بشكل دوري للتأكد إنها لسا موثوقة وشغالة.</p>
</section>

<section class="section" style="padding-top:0;">
  <div class="container">
    <?php if (empty($links) && empty($media)): ?>
      <p class="text-center" style="padding:40px 0; color:var(--color-text-muted);">ما في مصادر منشورة حاليًا. رجاءً عاود لاحقًا.</p>
    <?php endif; ?>

    <?php if (!empty($media)): ?>
      <h2>ملفات وتسجيلات</h2>
      <div class="grid grid-3" style="margin-bottom:40px;">
        <?php foreach ($media as $m): ?>
          <div class="card">
            <span class="tag"><?= e($mediaTypeLabels[$m['media_type']] ?? '') ?></span>
            <h3 style="margin-top:10px;"><?= e($m['title']) ?></h3>
            <?php if ($m['description']): ?><p><?= e($m['description']) ?></p><?php endif; ?>
            <?php $href = $m['media_type'] === 'file' ? $m['file_path'] : $m['external_url']; ?>
            <a href="<?= e($href) ?>" target="_blank" rel="noopener" class="btn btn-outline"><?= $m['media_type'] === 'file' ? 'تنزيل' : 'مشاهدة/استماع' ?></a>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <?php if (!empty($links)): ?>
      <h2>روابط خارجية موثوقة</h2>
      <div class="stack" style="gap:16px;">
        <?php foreach ($links as $l): ?>
          <div class="card">
            <h3><a href="<?= e($l['url']) ?>" target="_blank" rel="noopener"><?= e($l['title']) ?></a></h3>
            <?php if ($l['description']): ?><p><?= e($l['description']) ?></p><?php endif; ?>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
