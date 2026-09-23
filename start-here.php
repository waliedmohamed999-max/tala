<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/i18n.php';

if (current_locale() === 'en') {
    show_untranslated_notice_page('Start Here', '/start-here.php');
    exit;
}

$pageTitle = 'ابدأ من هون';
$pageDescription = 'مو عارف من وين تبلّش؟ هاي صفحة بسيطة بتوضحلك كيف تستخدم الموقع وتاخد خطوتك الأولى.';
require __DIR__ . '/includes/header.php';

$offersRemote = setting_bool('offers_remote_sessions');
$responseNote = get_setting('response_time_note');
?>

<section class="page-hero container">
  <div style="display:flex; justify-content:center; margin-bottom:6px;"><?php render_topic_art(3); ?></div>
  <span class="eyebrow">ابدأ من هون</span>
  <h1>مو عارف من وين تبلّش؟</h1>
  <p>هاي الصفحة بتوضحلك بأسئلة بسيطة كيف تستخدم الموقع، بدون أي تقييم أو تشخيص — بس معلومات عملية تساعدك تاخد خطوتك الأولى بارتياح.</p>
</section>

<section class="section" style="padding-top:0;">
  <div class="container" style="max-width:800px;">

    <div class="card" style="margin-bottom:20px;">
      <h3>كيف أعرف إذا بدي أحجز جلسة أو أبدأ بقراءة محتوى؟</h3>
      <p>ما في إجابة "صح" واحدة. إذا حاسس إنك بدك تحكي مع حدا الآن، فيك تروح مباشرة لـ<a href="/book.php">طلب موعد</a>. إذا بدك تفهم موضوع معين أكتر قبل ما تقرر، <a href="/articles/index.php">المكتبة الإرشادية</a> نقطة بداية كويسة. فيك كمان تستخدم <a href="/toolkit/index.php">أدوات «مساحة إلك»</a> لو بدك تنظم أفكارك أول.</p>
    </div>

    <div class="card" style="margin-bottom:20px;">
      <h3>كيف بطلب موعد؟</h3>
      <p>تعبّي <a href="/book.php">نموذج طلب الموعد</a> بمعلومات بسيطة: اسمك، وسيلة تواصل، ونوع الجلسة إذا كان معروف (أو "استفسار عام" إذا مو متأكد). بتراجع طلبك قبل الإرسال، وبعد الإرسال بنتواصل معك لنأكد التفاصيل.</p>
    </div>

    <div class="card" style="margin-bottom:20px;">
      <h3>شو ممكن أتوقع من أول جلسة؟</h3>
      <p>مساحة تتعرف فيها على تالا وتحكي عن اللي جابك، بدون ضغط تحكي كل شي دفعة وحدة. لمزيد من التفاصيل، صفحة <a href="/how-it-works.php">كيف بتصير الجلسة؟</a> بتشرح الخطوات كاملة.</p>
    </div>

    <div class="card" style="margin-bottom:20px;">
      <h3>شو المعلومات اللي رح أحتاج أعطيها؟</h3>
      <p>بس معلومات بسيطة: اسمك ووسيلة تواصل. ما في داعي تكتب أي تفاصيل صحية أو نفسية حساسة بالنموذج — هاد الكلام مكانه الطبيعي هو الجلسة نفسها، مو النموذج.</p>
    </div>

    <div class="card" style="margin-bottom:20px;">
      <h3>متى أتوقع ردًا؟</h3>
      <?php if ($responseNote): ?>
        <p><?= e($responseNote) ?></p>
      <?php else: ?>
        <p>مدة الرد المتوقعة لسا بانتظار تحديدها من تالا. بشكل عام، بنحاول نرجع لأي طلب أو رسالة بأقرب وقت ممكن.</p>
      <?php endif; ?>
    </div>

    <div class="card" style="border-color: #F0DDB2; background:#FFFBF3;">
      <h3>ماذا أفعل إذا كنت أحتاج مساعدة عاجلة؟</h3>
      <p><?= e(get_setting('emergency_notice')) ?></p>
    </div>

    <div class="text-center" style="margin-top:36px;">
      <a href="/book.php" class="btn btn-primary">اطلب موعد</a>
      <a href="/articles/index.php" class="btn btn-outline" style="margin-inline-start:12px;">تصفح المقالات</a>
    </div>
  </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
