<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/i18n.php';

if (current_locale() === 'en') {
    show_untranslated_notice_page('Service Details', '/services.php');
    exit;
}

$slug = $_GET['slug'] ?? '';
$stmt = db()->prepare("SELECT * FROM services WHERE slug = ? AND workflow_status = 'published' AND show_on_website = 1");
$stmt->execute([$slug]);
$service = $stmt->fetch();

if (!$service) {
    http_response_code(404);
    $pageTitle = 'الخدمة غير موجودة';
    require __DIR__ . '/includes/header.php';
    echo '<section class="section container text-center"><h1>ما لقينا هالخدمة</h1><p>يمكن تم تعديلها أو إزالتها.</p><a href="/services.php" class="btn btn-primary">شوف كل الخدمات</a></section>';
    require __DIR__ . '/includes/footer.php';
    exit;
}

$pageTitle = $service['name'];
$pageDescription = $service['summary'] ?: ('تفاصيل خدمة ' . $service['name'] . ' مع تالا في حمص.');
require __DIR__ . '/includes/header.php';
?>

<div class="article-hero-banner no-print" style="background: linear-gradient(135deg, var(--color-sage-light), var(--color-slate-light));">
  <?php render_topic_art((((int)$service['id']) % 6) + 1, '90px'); ?>
</div>

<section class="section container" style="max-width:800px;">
  <div class="breadcrumbs"><a href="/services.php">الخدمات</a> / <?= e($service['name']) ?></div>
  <h1><?= e($service['name']) ?></h1>
  <?php if ($service['summary']): ?><p style="font-size:1.08rem;"><?= e($service['summary']) ?></p><?php endif; ?>

  <div class="grid grid-2" style="margin:30px 0;">
    <?php if ($service['audience']): ?>
      <div class="card"><h3>لمن بتناسب؟</h3><p><?= e($service['audience']) ?></p></div>
    <?php endif; ?>
    <?php if ($service['format']): ?>
      <div class="card"><h3>شكل الجلسة</h3><p><?= e($service['format']) ?></p></div>
    <?php endif; ?>
    <?php if ($service['duration']): ?>
      <div class="card"><h3>المدة</h3><p><?= e($service['duration']) ?></p></div>
    <?php endif; ?>
    <?php if ($service['price']): ?>
      <div class="card"><h3>السعر</h3><p><?= e($service['price']) ?></p></div>
    <?php endif; ?>
  </div>

  <?php if ($service['description']): ?>
    <div class="article-body"><?= $service['description'] ?></div>
  <?php endif; ?>

  <?php if ($service['cancellation_policy']): ?>
    <div class="card" style="margin-top:20px;"><h3>سياسة الإلغاء وإعادة الجدولة</h3><p><?= nl2br(e($service['cancellation_policy'])) ?></p></div>
  <?php endif; ?>

  <div class="notice-inline">
    <?= $service['bookable_online']
      ? 'فيك تشوف الفترات المتاحة فعليًا وتحجز مباشرة من صفحة طلب الموعد.'
      : 'التفاصيل النهائية (المدة، السعر، والتوفر) قابلة للتأكيد وقت التواصل معنا.' ?>
  </div>

  <div class="text-center" style="margin-top:34px;">
    <a href="/book.php?service=<?= urlencode($service['slug']) ?>" class="btn btn-primary">اطلب هالخدمة</a>
  </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
