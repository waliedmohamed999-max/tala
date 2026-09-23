<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/i18n.php';

$locale = current_locale();
$pageTitle = $locale === 'en' ? 'Name Your Feeling' : 'سمّي شعورك';
$pageDescription = $locale === 'en'
    ? 'A word list to help you find the closest word to how you feel right now — no automatic analysis or diagnosis.'
    : 'قائمة كلمات تساعدك تلاقي الكلمة الأقرب لشعورك هلق — بدون تحليل أو تشخيص آلي.';
require __DIR__ . '/../includes/header.php';

$feelingGroupsAr = [
    'مرتاح' => ['هادئ', 'مطمئن', 'ممتن', 'راضي', 'آمن'],
    'مبسوط' => ['فرحان', 'متحمس', 'متفائل', 'فخور', 'خفيف'],
    'متعب' => ['منهك', 'مرهق', 'بلا طاقة', 'محتاج راحة', 'مثقّل'],
    'قلقان' => ['متوتر', 'خايف', 'مرتبك', 'مشتت', 'عصبي'],
    'حزين' => ['مكسور', 'وحيد', 'خايب أمل', 'مفتقد', 'حاسس بفراغ'],
    'زعلان' => ['محبط', 'منزعج', 'غضبان', 'مستاء', 'متضايق'],
];
$feelingGroupsEn = [
    'Calm' => ['Peaceful', 'Reassured', 'Grateful', 'Content', 'Safe'],
    'Happy' => ['Joyful', 'Excited', 'Optimistic', 'Proud', 'Light'],
    'Tired' => ['Exhausted', 'Drained', 'Out of energy', 'Need rest', 'Weighed down'],
    'Anxious' => ['Tense', 'Afraid', 'Confused', 'Distracted', 'On edge'],
    'Sad' => ['Broken', 'Lonely', 'Disappointed', 'Missing something', 'Empty'],
    'Upset' => ['Frustrated', 'Annoyed', 'Angry', 'Bothered', 'Irritated'],
];
$feelingGroups = $locale === 'en' ? $feelingGroupsEn : $feelingGroupsAr;
?>

<section class="page-hero container">
  <div style="display:flex; justify-content:center; margin-bottom:6px;"><?php render_topic_art(1); ?></div>
  <span class="eyebrow"><?= $locale === 'en' ? 'Your Space' : 'مساحة إلك' ?></span>
  <h1><?= $pageTitle ?></h1>
  <p><?= $locale === 'en'
    ? "Tap the word closest to how you feel right now. It doesn't need to be 100% precise — it's just a simple exercise to help you clarify what's going on for you."
    : 'دوّس على أقرب كلمة لشعورك هلق. مو لازم تكون دقيقة ١٠٠٪ — بس تجربة بسيطة تساعدك توضح لنفسك شو عم يصير معك.' ?></p>
</section>

<section class="section" style="padding-top:0;">
  <div class="container" style="max-width:760px;">
    <div id="feelingGroups">
      <?php foreach ($feelingGroups as $group => $words): ?>
        <div class="feeling-group">
          <h3><?= e($group) ?></h3>
          <div class="feeling-chips">
            <?php foreach ($words as $w): ?>
              <button type="button" class="feeling-chip" data-word="<?= e($w) ?>"><?= e($w) ?></button>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </div>

    <div id="feelingResult" class="card" style="display:none; margin-top:26px; text-align:center;">
      <p class="field-hint"><?= $locale === 'en' ? 'You chose:' : 'اخترت:' ?></p>
      <p style="font-size:1.6rem; font-weight:800; color:var(--color-sage-dark);" id="chosenWord"></p>
      <?php if ($locale === 'en'): ?>
        <p>There's no "right" or "wrong" here. Simply naming your feeling is a step on its own. If you'd like to understand more about why you feel this way, or talk about it with someone, you can always <a href="/book.php?lang=en">book an appointment</a>.</p>
        <button type="button" class="btn btn-outline" id="resetFeeling">Choose another word</button>
      <?php else: ?>
        <p>ما في إشي "صح" أو "غلط" هون. مجرد إنك سمّيت شعورك خطوة بحد ذاتها. إذا حاب تفهم أكتر ليش حاسس هيك، أو بدك تحكي عنه مع حدا، فيك دايمًا <a href="/book.php">تطلب موعد</a>.</p>
        <button type="button" class="btn btn-outline" id="resetFeeling">اختر كلمة تانية</button>
      <?php endif; ?>
    </div>

    <div class="notice-inline" style="margin-top:30px;"><?= $locale === 'en'
      ? "This tool is for self-reflection only — it doesn't analyze or diagnose anything. No data from this page is ever sent anywhere."
      : 'هاي الأداة للتأمل الذاتي فقط، ما بتحلل أو تشخّص أي شي. ما في أي بيانات بترسل من هالصفحة لأي مكان.' ?></div>
  </div>
</section>

<style>
.feeling-group { margin-bottom: 22px; }
.feeling-group h3 { font-size: 1rem; margin-bottom: 10px; }
.feeling-chips { display: flex; flex-wrap: wrap; gap: 10px; }
.feeling-chip {
  padding: 10px 18px;
  border-radius: 999px;
  border: 1.5px solid var(--color-border);
  background: #fff;
  font-family: inherit;
  font-size: .92rem;
  cursor: pointer;
  transition: all .15s ease;
}
.feeling-chip:hover, .feeling-chip:focus-visible { border-color: var(--color-sage-dark); background: var(--color-sage-light); }
</style>

<script>
document.querySelectorAll('.feeling-chip').forEach(function (btn) {
  btn.addEventListener('click', function () {
    document.getElementById('chosenWord').textContent = btn.dataset.word;
    document.getElementById('feelingResult').style.display = 'block';
    document.getElementById('feelingGroups').style.display = 'none';
    document.getElementById('feelingResult').scrollIntoView({ behavior: 'smooth', block: 'start' });
  });
});
document.getElementById('resetFeeling').addEventListener('click', function () {
  document.getElementById('feelingResult').style.display = 'none';
  document.getElementById('feelingGroups').style.display = 'block';
});
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
