<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/i18n.php';

if (current_locale() === 'en') {
    show_untranslated_notice_page('How Sessions Work', '/how-it-works.php');
    exit;
}

$pageTitle = 'كيف بتصير الجلسة؟';
$pageDescription = 'شو بيصير لما تطلب موعد مع تالا، وكيف تستعد لأول جلسة.';
require __DIR__ . '/includes/header.php';
?>

<section class="page-hero container">
  <div style="display:flex; justify-content:center; margin-bottom:6px;"><?php render_topic_art(2); ?></div>
  <span class="eyebrow">كيف بتبلّش؟</span>
  <h1>كيف بتصير الجلسة؟</h1>
  <p>خطوات بسيطة وواضحة، من لحظة ما تطلب الموعد لحتى أول جلسة.</p>
</section>

<section class="section">
  <div class="container">
    <div class="steps">
      <div class="step">
        <div class="step-number">1</div>
        <h3>بتعبّي نموذج الطلب</h3>
        <p>بتحط اسمك ووسيلة التواصل المفضلة عندك، ونوع الخدمة يلي بتهمك، وأي وقت بيناسبك تقريبًا. النموذج بياخد أقل من دقيقتين.</p>
      </div>
      <div class="step">
        <div class="step-number">2</div>
        <h3>منتواصل معك</h3>
        <p>بنرجع لك عبر الوسيلة يلي اخترتها لنتأكد من التفاصيل ونجاوب على أي سؤال عندك قبل ما نحدد الموعد نهائيًا.</p>
      </div>
      <div class="step">
        <div class="step-number">3</div>
        <h3>بنأكد الموعد سوا</h3>
        <p>بعد ما نتفق على الوقت المناسب، بيتأكد الموعد. طلب الموعد لحاله ما بيعني إنه تأكد قبل هالخطوة.</p>
      </div>
      <div class="step">
        <div class="step-number">4</div>
        <h3>أول جلسة</h3>
        <p>ما إلك داعي تحضّر حالك أو تصيغ كلامك بشكل مثالي. الجلسة الأولى بتكون مساحة تتعرف فيها على بعض وتحكي براحتك.</p>
      </div>
    </div>
  </div>
</section>

<section class="section section--alt">
  <div class="container" style="max-width:760px;">
    <h2>أسئلة بتخطر ببالك قبل أول جلسة</h2>
    <div class="stack" style="gap:20px; margin-top:20px;">
      <div class="card">
        <h3>شو لازم أحكي؟</h3>
        <p>أي شي حاسس إنك بدك تحكي فيه. مو لازم يكون الكلام مرتب أو "صحيح" — الجلسة مساحة لك تحكي متل ما هي الأمور فعلًا.</p>
      </div>
      <div class="card">
        <h3>قديش مدة الجلسة وسعرها؟</h3>
        <p>هالتفاصيل بتختلف حسب نوع الخدمة، وبتوضح لك بشكل دقيق وقت التواصل أو بصفحة الخدمة نفسها إذا كانت منشورة.</p>
      </div>
      <div class="card">
        <h3>وين بتصير الجلسة؟</h3>
        <p>المكان (حضوري بحمص أو عن بُعد) بيتحدد حسب الخدمة المتاحة، وبنوضحه لك بوضوح قبل ما تحجز.</p>
      </div>
      <div class="card">
        <h3>شو إذا بدي ألغي أو أغيّر الموعد؟</h3>
        <p>تواصل معنا بأقرب وقت ممكن وبنرتب التغيير حسب الإمكانية. سياسة الإلغاء التفصيلية رح تُنشر هون بعد اعتمادها من تالا.</p>
      </div>
      <div class="card">
        <h3>هل الكلام يضل سري؟</h3>
        <p>بنتعامل مع خصوصيتك بجدية عالية. التفاصيل الكاملة والحدود المرتبطة بالسرية موجودة بصفحة <a href="/privacy.php">سياسة الخصوصية</a>.</p>
      </div>
    </div>
  </div>
</section>

<section class="section text-center">
  <div class="container" style="max-width:600px;">
    <h2>جاهز تبلّش؟</h2>
    <p>ما في خطوة كبيرة لازم ناخدها اليوم — بس خطوة بسيطة.</p>
    <a href="/book.php" class="btn btn-primary">اطلب موعد</a>
  </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
