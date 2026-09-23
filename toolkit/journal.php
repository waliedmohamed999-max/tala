<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/i18n.php';

$locale = current_locale();
$pageTitle = $locale === 'en' ? 'Sort Your Thoughts' : 'رتّب أفكارك';
$pageDescription = $locale === 'en'
    ? 'A personal writing space with simple prompts. Everything you write stays on your device only.'
    : 'مساحة كتابة شخصية بأسئلة بسيطة. كل شي بتكتبه بضل على جهازك فقط.';
require __DIR__ . '/../includes/header.php';
?>

<section class="page-hero container">
  <div style="display:flex; justify-content:center; margin-bottom:6px;"><?php render_topic_art(4); ?></div>
  <span class="eyebrow"><?= $locale === 'en' ? 'Your Space' : 'مساحة إلك' ?></span>
  <h1><?= $pageTitle ?></h1>
  <p><?= $locale === 'en'
    ? "A free writing space with simple prompts. There's no submit button — everything you write stays saved on your device only (in your browser), and never reaches us."
    : 'مساحة كتابة حرة بأسئلة بسيطة. ما في زر إرسال — كل شي بتكتبه بضل محفوظ على جهازك بس (بمتصفحك)، وما بيوصلنا إطلاقًا.' ?></p>
</section>

<section class="section" style="padding-top:0;">
  <div class="container" style="max-width:680px;">

    <div class="notice-banner" id="consentGate" style="margin-bottom:24px; display:none;">
      <?php if ($locale === 'en'): ?>
        <strong>🔒 Before you start</strong>
        <p style="margin:8px 0;">What you write here is saved on <strong>this device and browser only</strong> (not with us, not on any server). This means anyone else using the same device or browser — whether or not they're logged in — could see what you wrote. If this device is shared (family, work, an internet cafe), it's best not to write sensitive details, or use the "Delete my writing" button before closing the page.</p>
        <button type="button" class="btn btn-primary" id="consentAcceptBtn">Got it, let's start</button>
      <?php else: ?>
        <strong>🔒 قبل ما تبلّش</strong>
        <p style="margin:8px 0;">اللي بتكتبه هون بينحفظ على <strong>هالجهاز وهالمتصفح بس</strong> (مو عنا، ومو بأي سيرفر). هاد معناه إنه أي حدا تاني بيستخدم نفس الجهاز أو نفس المتصفح — بعدك مسجل دخول عليه أو لأ — فيه يشوف اللي كتبته. إذا الجهاز مشترك (عيلة، شغل، مقهى إنترنت)، يفضّل ما تكتب تفاصيل حساسة، أو تستخدم زر «امسح كتابتي» قبل ما تسكر الصفحة.</p>
        <button type="button" class="btn btn-primary" id="consentAcceptBtn">فهمت، بلّش</button>
      <?php endif; ?>
    </div>

    <div class="notice-inline" style="margin-bottom:24px;" id="privacyNote"><?= $locale === 'en'
      ? "🔒 This text is saved only in your browser (localStorage), never sent to any server, analytics tool, or AI service. If you clear your browser data or use a different device/browser, the text won't be there — you can download it as a text file as a backup."
      : '🔒 هالنصوص بتنحفظ بمتصفحك فقط (localStorage)، ما بترسل لأي سيرفر أو أداة تحليلات أو ذكاء اصطناعي. إذا مسحت بيانات المتصفح أو استخدمت جهاز/متصفح تاني، النص ما رح يضل موجود — فيك تنزّله كملف نصي كنسخة احتياطية.' ?>
    </div>

    <form id="journalForm">
      <?php if ($locale === 'en'): ?>
        <div class="form-group"><label for="q1">What's on your mind right now?</label><textarea id="q1" rows="4" placeholder="Write freely, no need to organize or correct..."></textarea></div>
        <div class="form-group"><label for="q2">How do you feel about it?</label><textarea id="q2" rows="4"></textarea></div>
        <div class="form-group"><label for="q3">What's one small possible step today?</label><textarea id="q3" rows="3"></textarea></div>
        <div class="form-group"><label for="q4">Anything else you want to write (optional)</label><textarea id="q4" rows="3"></textarea></div>
      <?php else: ?>
        <div class="form-group"><label for="q1">شو اللي شاغلك هلق؟</label><textarea id="q1" rows="4" placeholder="اكتب بحرية، بدون ترتيب أو تصحيح..."></textarea></div>
        <div class="form-group"><label for="q2">شو حاسس فيه تجاه هالموضوع؟</label><textarea id="q2" rows="4"></textarea></div>
        <div class="form-group"><label for="q3">شو الخطوة الصغيرة الممكنة اليوم؟</label><textarea id="q3" rows="3"></textarea></div>
        <div class="form-group"><label for="q4">أي شي تاني بدك تكتبه (اختياري)</label><textarea id="q4" rows="3"></textarea></div>
      <?php endif; ?>
    </form>

    <div style="display:flex; gap:12px; flex-wrap:wrap; margin-top:10px;">
      <button type="button" class="btn btn-outline" id="downloadBtn"><?= $locale === 'en' ? '⬇️ Download as text file' : '⬇️ نزّل كملف نصي' ?></button>
      <button type="button" class="btn btn-outline" id="clearBtn" style="color:var(--color-danger); border-color:var(--color-danger);"><?= $locale === 'en' ? '🗑️ Delete my writing from this device' : '🗑️ احذف كتابتي من هالجهاز' ?></button>
      <span id="saveStatus" class="field-hint" style="align-self:center;"></span>
    </div>

    <div class="admin-card" style="margin-top:26px; background:var(--color-bg-alt);">
      <?php if ($locale === 'en'): ?>
        <p class="field-hint" style="margin-bottom:10px;">This only clears this tool's data. To clear data from all "Your Space" tools (Sort Your Thoughts + Prep List + Reading List) at once from this device:</p>
        <button type="button" class="btn btn-outline" id="clearAllToolsBtn" style="color:var(--color-danger); border-color:var(--color-danger);">🗑️ Clear all tool data from this device</button>
      <?php else: ?>
        <p class="field-hint" style="margin-bottom:10px;">هاد بيمسح بيانات هالأداة بس. إذا بدك تمسح بيانات كل أدوات «مساحة إلك» (رتّب أفكارك + قائمة التحضير + قائمة القراءة) دفعة وحدة من هالجهاز:</p>
        <button type="button" class="btn btn-outline" id="clearAllToolsBtn" style="color:var(--color-danger); border-color:var(--color-danger);">🗑️ امسح كل بيانات أدوات الموقع من هالجهاز</button>
      <?php endif; ?>
      <span id="clearAllStatus" class="field-hint" style="margin-inline-start:10px;"></span>
    </div>

    <div class="notice-inline" style="margin-top:30px;"><?= $locale === 'en'
      ? "Writing here is for self-organization and reflection, not a substitute for a real session. If you want to share this with Tala, you can print it or bring a copy to your session."
      : 'الكتابة هون للتنظيم الذاتي والتأمل، مو بديل عن جلسة حقيقية. إذا بدك تشارك هاد الكلام مع تالا، فيك تطبعه أو تاخد نسخة معك للجلسة.' ?>
    </div>
  </div>
</section>

<script src="/assets/js/toolkit-privacy.js"></script>
<script>
(function () {
  var isEn = <?= $locale === 'en' ? 'true' : 'false' ?>;
  var fields = ['q1', 'q2', 'q3', 'q4'];
  var storageKey = 'tala_journal_v1';
  var consentKey = 'tala_journal_consent_seen_v1';
  var saveStatus = document.getElementById('saveStatus');
  var saveTimeout = null;
  var text = isEn
    ? { saved: 'Saved on your device ✓', blocked: "Couldn't save locally (browser privacy setting)", confirmClear: "Are you sure you want to delete all this writing? This can't be undone." }
    : { saved: 'انحفظ على جهازك ✓', blocked: 'ما قدرنا نحفظ محليًا (خصوصية المتصفح مفعّلة)', confirmClear: 'متأكد إنك بدك تمسح كل النصوص؟ ما فيك ترجعها بعدين.' };

  // One-time gate: shown until dismissed once, then remembered locally —
  // the privacy notice below stays visible permanently either way.
  var consentGate = document.getElementById('consentGate');
  try {
    if (!localStorage.getItem(consentKey)) {
      consentGate.style.display = 'block';
    }
  } catch (e) {}
  document.getElementById('consentAcceptBtn').addEventListener('click', function () {
    consentGate.style.display = 'none';
    try { localStorage.setItem(consentKey, '1'); } catch (e) {}
  });

  TalaToolkitPrivacy.bindClearAllButton('clearAllToolsBtn', 'clearAllStatus');

  function load() {
    try {
      var data = JSON.parse(localStorage.getItem(storageKey) || '{}');
      fields.forEach(function (id) {
        var el = document.getElementById(id);
        if (data[id]) el.value = data[id];
      });
    } catch (e) { /* private browsing or storage blocked — page still works, just doesn't persist */ }
  }

  function save() {
    try {
      var data = {};
      fields.forEach(function (id) { data[id] = document.getElementById(id).value; });
      localStorage.setItem(storageKey, JSON.stringify(data));
      saveStatus.textContent = text.saved;
      clearTimeout(saveTimeout);
      saveTimeout = setTimeout(function () { saveStatus.textContent = ''; }, 2000);
    } catch (e) {
      saveStatus.textContent = text.blocked;
    }
  }

  fields.forEach(function (id) {
    document.getElementById(id).addEventListener('input', function () {
      clearTimeout(saveTimeout);
      saveTimeout = setTimeout(save, 500);
    });
  });

  document.getElementById('clearBtn').addEventListener('click', function () {
    if (!confirm(text.confirmClear)) return;
    fields.forEach(function (id) { document.getElementById(id).value = ''; });
    try { localStorage.removeItem(storageKey); } catch (e) {}
  });

  document.getElementById('downloadBtn').addEventListener('click', function () {
    var labels = isEn
      ? { q1: "What's on your mind right now?", q2: 'How do you feel about it?', q3: "What's one small possible step today?", q4: 'Anything else' }
      : { q1: 'شو اللي شاغلك هلق؟', q2: 'شو حاسس فيه تجاه هالموضوع؟', q3: 'شو الخطوة الصغيرة الممكنة اليوم؟', q4: 'أي شي تاني' };
    var out = fields.map(function (id) {
      return labels[id] + '\n' + (document.getElementById(id).value || '—') + '\n';
    }).join('\n');
    var blob = new Blob([out], { type: 'text/plain;charset=utf-8' });
    var a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = (isEn ? 'my-thoughts-' : 'افكاري-') + new Date().toISOString().slice(0, 10) + '.txt';
    a.click();
  });

  load();
})();
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
