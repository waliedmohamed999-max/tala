<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/i18n.php';

if (current_locale() === 'en') {
    show_untranslated_notice_page('About Tala', '/about.php');
    exit;
}

$pageTitle = 'عن تالا';
$pageDescription = 'تعرّف على تالا، معالجة نفسية في حمص، سوريا: مؤهلاتها، طريقة عملها، ومين ممكن تستقبل.';
require __DIR__ . '/includes/header.php';

$photo = get_setting('photo_path');
$bioLong = get_setting('bio_long');
$bioShort = get_setting('bio_short');
$credentials = get_setting('credentials');
$license = get_setting('license_info');
$yearsExp = get_setting('years_experience');
$specialties = get_setting('specialties');
$city = get_setting('city', 'حمص');
$offersRemote = setting_bool('offers_remote_sessions');
$languages = get_setting('languages');
$introVideo = get_setting('intro_video_url');
$mediaMentions = array_filter(array_map('trim', explode("\n", get_setting('media_mentions'))));
?>

<section class="page-hero container">
  <span class="eyebrow">عن تالا</span>
  <h1>معالجة نفسية بترافقك بهدوء واحترام</h1>
  <p>بلا وعود كبيرة، بس بمساحة حقيقية للحديث والفهم.</p>
</section>

<section class="section">
  <div class="container" style="display:grid; grid-template-columns:.75fr 1.25fr; gap:44px; align-items:start;">
    <div>
      <?php if ($photo): ?>
        <img src="<?= e($photo) ?>" alt="صورة تالا" style="border-radius:var(--radius-lg); box-shadow:var(--shadow-card);">
      <?php else: ?>
        <div style="background:var(--color-sage-light); border-radius:var(--radius-lg); aspect-ratio:1/1; display:flex; align-items:center; justify-content:center; color:var(--color-sage-dark); font-size:3.5rem; font-weight:800;">ت</div>
        <p class="field-hint" style="margin-top:10px;">الصورة الشخصية بانتظار توفيرها من تالا.</p>
      <?php endif; ?>
    </div>

    <div>
      <h2>مين هي تالا؟</h2>
      <?php if ($bioLong): ?>
        <div class="stack"><?= nl2br(e($bioLong)) ?></div>
      <?php elseif ($bioShort): ?>
        <p><?= e($bioShort) ?></p>
      <?php else: ?>
        <p>لسا عم ننتظر النص التعريفي الكامل من تالا. بمجرد ما توفّره، رح ينعرض هون بدل هالفقرة.</p>
      <?php endif; ?>

      <h3 style="margin-top:34px;">المؤهلات المعتمدة</h3>
      <?php if ($credentials): ?>
        <p><?= e($credentials) ?></p>
      <?php else: ?>
        <p class="field-hint">بانتظار تأكيد تالا للمؤهلات والشهادات قبل عرضها هون.</p>
      <?php endif; ?>
      <?php if ($license): ?><p><strong>الترخيص:</strong> <?= e($license) ?></p><?php endif; ?>
      <?php if ($yearsExp): ?><p><strong>سنوات الخبرة:</strong> <?= e($yearsExp) ?></p><?php endif; ?>
      <?php if ($specialties): ?><p><strong>الاختصاصات:</strong> <?= e($specialties) ?></p><?php endif; ?>
      <?php if ($languages): ?><p><strong>اللغات:</strong> <?= e($languages) ?></p><?php endif; ?>

      <?php if ($introVideo): ?>
        <h3 style="margin-top:34px;">فيديو تعريفي</h3>
        <div style="aspect-ratio:16/9; border-radius:var(--radius-md); overflow:hidden; box-shadow:var(--shadow-card);">
          <iframe src="<?= e($introVideo) ?>" width="100%" height="100%" style="border:0;" allowfullscreen loading="lazy" title="فيديو تعريفي عن تالا"></iframe>
        </div>
      <?php endif; ?>

      <?php if (!empty($mediaMentions)): ?>
        <h3 style="margin-top:34px;">ظهور إعلامي ومشاركات مهنية</h3>
        <ul>
          <?php foreach ($mediaMentions as $m): ?><li><?= e($m) ?></li><?php endforeach; ?>
        </ul>
      <?php endif; ?>
    </div>
  </div>
</section>

<section class="section section--alt">
  <div class="container">
    <div class="grid grid-3">
      <div class="card">
        <div class="card-icon">🧭</div>
        <h3>طريقة العمل</h3>
        <p>لسا هالجزء بانتظار وصف تالا لطريقتها بالعمل مع الأشخاص، ليكون دقيقًا وواضحًا قبل النشر.</p>
      </div>
      <div class="card">
        <div class="card-icon">👥</div>
        <h3>الفئات المستقبَلة</h3>
        <p>الفئات العمرية أو الحالات يلي بتستقبلها تالا رح تُذكر هون بعد التأكيد — لتجنّب أي التباس.</p>
      </div>
      <div class="card">
        <div class="card-icon">📍</div>
        <h3>مكان الجلسات</h3>
        <p>
          <?php if ($offersRemote): ?>
            الجلسات متاحة حضوريًا في <?= e($city) ?><?= $offersRemote ? '، وكمان عن بُعد' : '' ?>.
          <?php else: ?>
            تفاصيل مكان الجلسات (حضوري في <?= e($city) ?> و/أو عن بُعد) بانتظار التأكيد النهائي.
          <?php endif; ?>
        </p>
      </div>
    </div>
  </div>
</section>

<section class="section">
  <div class="container text-center" style="max-width:680px;">
    <h2>شو ممكن تتوقع منها؟</h2>
    <p>استماع بلا حكم، احترام لوقتك ومساحتك، وخطوات تناسب وضعك الشخصي — بلا أي ادعاء بنتائج مضمونة. كل شخص وتجربته.</p>
    <a href="/book.php" class="btn btn-primary">اطلب موعد</a>
  </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
