<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/i18n.php';

$locale = current_locale();
$pageTitle = $locale === 'en' ? 'First Session Prep' : 'تحضير للجلسة الأولى';
$pageDescription = $locale === 'en'
    ? "A list of questions you can pick from for your first session, printable or downloadable."
    : 'قائمة أسئلة تختار منها شو بدك تحكي فيه بأول جلسة، قابلة للطباعة أو التنزيل.';
require __DIR__ . '/../includes/header.php';

$questionsAr = [
    'شو السبب الرئيسي اللي خلاني أطلب موعد هلق؟',
    'من قديش وأنا حاسس بهيك؟',
    'شو التغيير اللي بدي أشوفه بحياتي؟',
    'في أشخاص أو مواقف معينة بتأثر على شعوري؟',
    'جربت طرق للتعامل مع الموضوع قبل؟ شو صار معها؟',
    'في أسئلة عندي عن طريقة عمل الجلسات؟',
    'في أسئلة عندي عن السرية والخصوصية؟',
    'شو بدي أفهمه أكتر عن حالي؟',
    'في شي بحس فيه بالجسم (توتر، تعب) بدي أذكره؟',
    'شو بيريحني أكتر أثناء الحكي — أحكي بحرية ولا حدا يوجهني بأسئلة؟',
];
$questionsEn = [
    "What's the main reason I'm booking an appointment right now?",
    "How long have I been feeling this way?",
    "What change do I want to see in my life?",
    "Are there specific people or situations affecting how I feel?",
    "Have I tried ways to deal with this before? How did that go?",
    "Do I have questions about how sessions work?",
    "Do I have questions about confidentiality and privacy?",
    "What do I want to understand more about myself?",
    "Is there something I feel in my body (tension, fatigue) I want to mention?",
    "What's more comfortable for me while talking — speaking freely, or being guided with questions?",
];
$questions = $locale === 'en' ? $questionsEn : $questionsAr;
?>

<section class="page-hero container">
  <span class="eyebrow"><?= $locale === 'en' ? 'Your Space' : 'مساحة إلك' ?></span>
  <h1><?= $pageTitle ?></h1>
  <p><?= $locale === 'en'
    ? "Pick the questions that matter to you below — not all of them are required. The list helps you organize your thoughts; you don't have to answer everything, and nothing is sent to us."
    : 'اختر الأسئلة يلي بتهمك من تحت — مو لازم كلها. القائمة بتساعدك تحضّر أفكارك، ومو مطلوب تجاوب على كلشي أو تصلنا نسخة منها.' ?></p>
</section>

<section class="section" style="padding-top:0;">
  <div class="container" style="max-width:680px;">
    <div class="notice-inline no-print" style="margin-bottom:20px;">
      <?= $locale === 'en'
        ? '🔒 Your selections are saved on your device only. This list is never automatically sent to Tala — you can print it or bring it to your session if you like.'
        : '🔒 اختياراتك بتنحفظ على جهازك بس. هاي القائمة ما بترسل لتالا تلقائيًا — فيك تطبعها أو تاخدها معك للجلسة إذا حبيت.' ?>
    </div>

    <div class="card">
      <ul style="list-style:none; padding:0; margin:0;" id="prepList">
        <?php foreach ($questions as $i => $q): ?>
          <li style="padding:10px 0; border-bottom:1px solid var(--color-border);">
            <label style="display:flex; gap:10px; align-items:flex-start; cursor:pointer;">
              <input type="checkbox" class="prep-check" data-i="<?= $i ?>" style="margin-top:4px;">
              <span><?= e($q) ?></span>
            </label>
          </li>
        <?php endforeach; ?>
        <li style="padding:10px 0;">
          <label style="display:block; margin-bottom:6px;"><?= $locale === 'en' ? 'Any other question you have (optional):' : 'أي سؤال إضافي عندك (اختياري):' ?></label>
          <textarea id="prepCustom" rows="3" class="no-print"></textarea>
        </li>
      </ul>
    </div>

    <div style="display:flex; gap:12px; flex-wrap:wrap; margin-top:20px;" class="no-print">
      <button type="button" class="btn btn-outline" onclick="window.print()"><?= $locale === 'en' ? '🖨️ Print' : '🖨️ طباعة' ?></button>
      <button type="button" class="btn btn-outline" id="downloadPrep"><?= $locale === 'en' ? '⬇️ Download as text file' : '⬇️ تنزيل كملف نصي' ?></button>
    </div>
  </div>
</section>

<script>
(function () {
  var isEn = <?= $locale === 'en' ? 'true' : 'false' ?>;
  var storageKey = 'tala_prep_v1';
  var checks = document.querySelectorAll('.prep-check');
  var customField = document.getElementById('prepCustom');

  function load() {
    try {
      var data = JSON.parse(localStorage.getItem(storageKey) || '{}');
      checks.forEach(function (c) { c.checked = !!data['q' + c.dataset.i]; });
      if (data.custom) customField.value = data.custom;
    } catch (e) {}
  }
  function save() {
    try {
      var data = {};
      checks.forEach(function (c) { data['q' + c.dataset.i] = c.checked; });
      data.custom = customField.value;
      localStorage.setItem(storageKey, JSON.stringify(data));
    } catch (e) {}
  }
  checks.forEach(function (c) { c.addEventListener('change', save); });
  customField.addEventListener('input', save);

  document.getElementById('downloadPrep').addEventListener('click', function () {
    var lines = [isEn ? 'First Session Prep List' : 'قائمة تحضير للجلسة الأولى', ''];
    checks.forEach(function (c) {
      var text = c.closest('label').querySelector('span').textContent;
      lines.push((c.checked ? '[x] ' : '[ ] ') + text);
    });
    if (customField.value) lines.push('', (isEn ? 'Additional question: ' : 'سؤال إضافي: ') + customField.value);
    var blob = new Blob([lines.join('\n')], { type: 'text/plain;charset=utf-8' });
    var a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = isEn ? 'first-session-prep.txt' : 'تحضير-الجلسة-الاولى.txt';
    a.click();
  });

  load();
})();
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
