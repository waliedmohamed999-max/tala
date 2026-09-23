<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/i18n.php';

if (current_locale() === 'en') {
    show_untranslated_notice_page('FAQ', '/faq.php');
    exit;
}

$pageTitle = 'الأسئلة الشائعة';
$pageDescription = 'أجوبة على أكتر الأسئلة يلي بتوصلنا حول الحجز، الجلسات، والخصوصية.';
require __DIR__ . '/includes/header.php';

$faqs = published_faqs();
?>

<section class="page-hero container">
  <div style="display:flex; justify-content:center; margin-bottom:6px;"><?php render_topic_art(3); ?></div>
  <span class="eyebrow">الأسئلة الشائعة</span>
  <h1>عندك سؤال؟ يمكن جاوبنا عليه هون</h1>
</section>

<section class="section">
  <div class="container" style="max-width:800px;">
    <?php if (empty($faqs)): ?>
      <p class="text-center">ما في أسئلة منشورة حاليًا.</p>
    <?php else: ?>
      <?php foreach ($faqs as $f): ?>
        <details class="faq-item">
          <summary><?= e($f['question']) ?></summary>
          <div class="faq-answer"><?= e($f['answer']) ?></div>
        </details>
      <?php endforeach; ?>
    <?php endif; ?>

    <div class="notice-inline">
      ما لقيت جواب سؤالك؟ <a href="/contact.php">تواصل معنا</a> وبنجاوبك مباشرة.
    </div>
  </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
