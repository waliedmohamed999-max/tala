<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/i18n.php';

if (current_locale() === 'en') {
    show_untranslated_notice_page('Services', '/services.php');
    exit;
}

$pageTitle = 'الخدمات';
$pageDescription = 'الخدمات المتاحة مع تالا، معالجة نفسية في حمص، سوريا.';
require __DIR__ . '/includes/header.php';

$services = published_services();
?>

<section class="page-hero container">
  <div style="display:flex; justify-content:center; margin-bottom:6px;"><?php render_topic_art(1); ?></div>
  <span class="eyebrow">الخدمات</span>
  <h1>شو بتقدر تحجز مع تالا؟</h1>
  <p>هاي الخدمات المؤكدة والمتاحة حاليًا فقط. أي خدمة لسا بانتظار التأكيد ما بتظهر هون لتجنّب أي التباس.</p>
</section>

<section class="section">
  <div class="container">
    <?php if (empty($services)): ?>
      <div class="card text-center" style="max-width:640px; margin:0 auto;">
        <div class="card-icon" style="margin-inline:auto;">🌱</div>
        <h3>لسا عم نجهّز صفحة الخدمات</h3>
        <p>بانتظار تأكيد تالا لتفاصيل الخدمات (نوع الجلسات، المدة، السعر، وطريقة الحضور)، ما منعرض أي خدمة غير مؤكدة. إذا بدك تعرف شو متوفر حاليًا، تواصل معنا مباشرة وبنجاوبك بكل وضوح.</p>
        <a href="/contact.php" class="btn btn-primary">تواصل للاستفسار</a>
      </div>
    <?php else: ?>
      <div class="grid grid-3">
        <?php foreach ($services as $i => $s): ?>
          <div class="card">
            <?php render_card_thumb(($i % 6) + 1); ?>
            <h3><?= e($s['name']) ?></h3>
            <?php if ($s['summary']): ?><p><?= e($s['summary']) ?></p><?php endif; ?>
            <?php if ($s['duration']): ?><p><strong>المدة:</strong> <?= e($s['duration']) ?></p><?php endif; ?>
            <?php if ($s['price']): ?><p><strong>السعر:</strong> <?= e($s['price']) ?></p><?php endif; ?>
            <a href="/service.php?slug=<?= urlencode($s['slug']) ?>" class="btn btn-outline">التفاصيل</a>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>

<section class="section section--alt">
  <div class="container text-center" style="max-width:640px;">
    <h2>مو متأكد شو يناسبك؟</h2>
    <p>مو لازم تعرف بالضبط شو محتاج من البداية. احكي معنا وبنساعدك تحدد الخطوة المناسبة.</p>
    <a href="/contact.php" class="btn btn-primary">تواصل معنا</a>
  </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
