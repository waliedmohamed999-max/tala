<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/i18n.php';

$locale = current_locale();
$pageTitle = $locale === 'en' ? 'Short Break' : 'استراحة قصيرة';
$pageDescription = $locale === 'en'
    ? 'A simple calming exercise for a minute or two — stop it any time you like.'
    : 'تمرين تهدئة بسيط لدقيقة أو دقيقتين، تقدر توقفه بأي لحظة.';
require __DIR__ . '/../includes/header.php';
?>

<section class="page-hero container">
  <span class="eyebrow"><?= $locale === 'en' ? 'Your Space' : 'مساحة إلك' ?></span>
  <h1><?= $pageTitle ?></h1>
  <p><?= $locale === 'en'
    ? "A simple, fully optional breathing exercise. You can stop it anytime, and it's fine if it doesn't suit you — there's a simple text alternative below."
    : 'تمرين تنفس بسيط، اختياري بالكامل. فيك توقفه بأي لحظة، وما في مشكلة إذا ما ناسبك — تحت في بديل نصي بسيط.' ?></p>
</section>

<section class="section" style="padding-top:0;">
  <div class="container" style="max-width:560px;">

    <div class="card text-center" id="breathingCard">
      <div id="breathCircleWrap" style="display:flex; justify-content:center; margin:20px 0;">
        <div id="breathCircle" class="breath-circle">
          <span id="breathLabel"><?= $locale === 'en' ? 'Ready?' : 'جاهز؟' ?></span>
        </div>
      </div>
      <div id="breathControls">
        <button type="button" class="btn btn-primary" id="startBreath"><?= $locale === 'en' ? 'Start (one minute)' : 'ابدأ التمرين (دقيقة وحدة)' ?></button>
      </div>
      <div id="breathActive" style="display:none;">
        <button type="button" class="btn btn-outline" id="stopBreath"><?= $locale === 'en' ? 'Stop' : 'إيقاف' ?></button>
      </div>
      <p class="field-hint" style="margin-top:18px;"><?= $locale === 'en'
        ? 'Breathe in as the circle grows, breathe out as it shrinks. No pressure — any pace that feels comfortable is enough.'
        : 'شهيق لما الدائرة تكبر، زفير لما تصغر. بلا ضغط — أي إيقاع مريح إلك كفاية.' ?></p>
    </div>

    <?php if ($locale === 'en'): ?>
    <div class="card" style="margin-top:24px;">
      <h3>Exercise not for you? Try this instead</h3>
      <p>Take a moment and name — out loud or in your head — 5 things you can see around you right now, 4 things you can touch, 3 sounds you can hear, 2 things you can smell, and 1 taste. This simple exercise helps bring you back to the present moment in a different way than breathing.</p>
    </div>
    <div class="notice-inline" style="margin-top:24px;">This exercise is for general relaxation only, not a treatment for anxiety or panic. If you feel your stress is ongoing and affecting your life, you can talk to a professional — <a href="/book.php?lang=en">book an appointment</a>.</div>
    <?php else: ?>
    <div class="card" style="margin-top:24px;">
      <h3>ما بيناسبك التمرين؟ جرّب هاد بدل</h3>
      <p>خذ لحظة وسمّي بصوت عالي أو براسك ٥ أشياء تشوفها حواليك هلق، ٤ أشياء فيك تلمسها، ٣ أصوات بتسمعها، صوتين بتشمهم، وطعمة وحدة. هالتمرين البسيط بيساعد يرجعك للحظة الحالية بطريقة مختلفة عن التنفس.</p>
    </div>
    <div class="notice-inline" style="margin-top:24px;">هالتمرين للاسترخاء العام فقط، مو علاج لحالة قلق أو ذعر. إذا حاسس إنه التوتر عندك مستمر وبيأثر على حياتك، فيك تحكي مع مختص — <a href="/book.php">اطلب موعد</a>.</div>
    <?php endif; ?>
  </div>
</section>

<style>
.breath-circle {
  width: 140px; height: 140px;
  border-radius: 50%;
  background: var(--color-sage-light);
  border: 3px solid var(--color-sage-dark);
  display: flex; align-items: center; justify-content: center;
  font-weight: 700;
  color: var(--color-sage-dark);
  transition: transform 4s ease-in-out;
}
.breath-circle.inhale { transform: scale(1.4); }
.breath-circle.exhale { transform: scale(0.85); }
</style>

<script>
(function () {
  var circle = document.getElementById('breathCircle');
  var label = document.getElementById('breathLabel');
  var startBtn = document.getElementById('startBreath');
  var stopBtn = document.getElementById('stopBreath');
  var controls = document.getElementById('breathControls');
  var active = document.getElementById('breathActive');
  var timer = null;
  var phaseTimer = null;
  var isEn = <?= $locale === 'en' ? 'true' : 'false' ?>;
  var text = isEn
    ? { inhale: 'Breathe in...', exhale: 'Breathe out...', done: 'Done 🌿', restart: 'Start again' }
    : { inhale: 'شهيق...', exhale: 'زفير...', done: 'خلصنا 🌿', restart: 'ابدأ من جديد' };

  function cyclePhase() {
    circle.classList.remove('inhale', 'exhale');
    void circle.offsetWidth; // restart CSS transition
    circle.classList.add('inhale');
    label.textContent = text.inhale;
    phaseTimer = setTimeout(function () {
      circle.classList.remove('inhale');
      circle.classList.add('exhale');
      label.textContent = text.exhale;
    }, 4000);
  }

  function stop() {
    clearInterval(timer);
    clearTimeout(phaseTimer);
    circle.classList.remove('inhale', 'exhale');
    label.textContent = text.done;
    controls.style.display = 'block';
    active.style.display = 'none';
    startBtn.textContent = text.restart;
  }

  startBtn.addEventListener('click', function () {
    controls.style.display = 'none';
    active.style.display = 'block';
    cyclePhase();
    timer = setInterval(cyclePhase, 8000);
    setTimeout(stop, 60000); // one minute session, auto-stops
  });

  stopBtn.addEventListener('click', stop);
})();
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
