<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/i18n.php';

$locale = current_locale();
$pageTitle = $locale === 'en' ? 'Your Space' : 'مساحة إلك';
$pageDescription = $locale === 'en'
    ? 'Simple tools for reflection and self-organization that run only on your device — no account, nothing sent to any server.'
    : 'أدوات بسيطة للتأمل والتنظيم الذاتي، تشتغل على جهازك فقط، بدون حساب وبدون ما نرسل أي شي منها للخادم.';
require __DIR__ . '/../includes/header.php';
?>

<section class="page-hero container">
  <div style="display:flex; justify-content:center; margin-bottom:6px;"><?php render_topic_art(4); ?></div>
  <span class="eyebrow"><?= $locale === 'en' ? 'Your Space' : 'مساحة إلك' ?></span>
  <?php if ($locale === 'en'): ?>
    <h1>Simple tools, at your own pace</h1>
    <p>These tools are for reflection and self-organization only — not an assessment or treatment. They're all optional, and you can use the whole site without them. Anything you write in these tools stays on your device and never reaches us.</p>
  <?php else: ?>
    <h1>أدوات بسيطة، بوقتك الخاص</h1>
    <p>هاي الأدوات للتأمل والتنظيم الذاتي فقط — مو تقييم ولا علاج. كلها اختيارية، وفيك تستخدم الموقع بشكل كامل بدونها. كل شي بتكتبه بهالأدوات بضل على جهازك بس، وما بيوصلنا إطلاقًا.</p>
  <?php endif; ?>
</section>

<section class="section" style="padding-top:0;">
  <div class="container">
    <?php if ($locale === 'en'): ?>
      <div class="grid grid-3">
        <div class="card"><div class="card-icon">🏷️</div><h3>Name Your Feeling</h3><p>A simple word list to help you find the closest word to how you feel right now.</p><a href="/toolkit/name-feeling.php?lang=en" class="btn btn-outline">Try it</a></div>
        <div class="card"><div class="card-icon">🌬️</div><h3>Short Break</h3><p>A simple calming exercise for a minute or two, with a text alternative if the exercise doesn't suit you.</p><a href="/toolkit/breathing.php?lang=en" class="btn btn-outline">Try it</a></div>
        <div class="card"><div class="card-icon">📝</div><h3>Sort Your Thoughts</h3><p>A personal writing space with simple prompts. Stays saved on your device only.</p><a href="/toolkit/journal.php?lang=en" class="btn btn-outline">Try it</a></div>
        <div class="card"><div class="card-icon">✅</div><h3>First Session Prep</h3><p>A list of questions you can pick from for what you want to talk about — printable or downloadable.</p><a href="/toolkit/prep-list.php?lang=en" class="btn btn-outline">Try it</a></div>
        <div class="card"><div class="card-icon">📚</div><h3>My Reading List</h3><p>Save articles you want to come back to, kept on your device only.</p><a href="/toolkit/reading-list.php?lang=en" class="btn btn-outline">View your list</a></div>
      </div>
      <div class="notice-inline" style="margin-top:30px;">
        These tools are for reflection and self-awareness and are not a psychological assessment or treatment. If you feel like you want a real space to talk, <a href="/book.php?lang=en">book an appointment</a>.
      </div>
      <div class="card" style="margin-top:24px; text-align:center;">
        <h3>🔒 Privacy & local data</h3>
        <p>Anything you write in these tools (Sort Your Thoughts, Prep List, Reading List) is saved on this device and browser only — it never reaches us or any third party. If this device is shared with someone else, you can clear everything in one click:</p>
        <button type="button" class="btn btn-outline" id="clearAllToolsBtn" style="color:var(--color-danger); border-color:var(--color-danger);">🗑️ Clear all tool data from this device</button>
        <p id="clearAllStatus" class="field-hint" style="margin-top:10px;"></p>
      </div>
    <?php else: ?>
      <div class="grid grid-3">
        <div class="card"><div class="card-icon">🏷️</div><h3>سمّي شعورك</h3><p>قائمة كلمات بسيطة تساعدك تلاقي الكلمة الأقرب لشعورك هلق.</p><a href="/toolkit/name-feeling.php" class="btn btn-outline">جرّب</a></div>
        <div class="card"><div class="card-icon">🌬️</div><h3>استراحة قصيرة</h3><p>تمرين تهدئة بسيط لدقيقة أو دقيقتين، مع بديل نصي إذا ما ناسبك التمرين.</p><a href="/toolkit/breathing.php" class="btn btn-outline">جرّب</a></div>
        <div class="card"><div class="card-icon">📝</div><h3>رتّب أفكارك</h3><p>مساحة كتابة شخصية بأسئلة بسيطة. بتضل محفوظة على جهازك فقط.</p><a href="/toolkit/journal.php" class="btn btn-outline">جرّب</a></div>
        <div class="card"><div class="card-icon">✅</div><h3>تحضير للجلسة الأولى</h3><p>قائمة أسئلة تختار منها شو بدك تحكي فيه، قابلة للطباعة أو التنزيل.</p><a href="/toolkit/prep-list.php" class="btn btn-outline">جرّب</a></div>
        <div class="card"><div class="card-icon">📚</div><h3>قائمة قراءتي</h3><p>احفظ مقالات بدك ترجعلها لاحقًا، محفوظة على جهازك بس.</p><a href="/toolkit/reading-list.php" class="btn btn-outline">شوف قائمتك</a></div>
      </div>
      <div class="notice-inline" style="margin-top:30px;">
        هاي الأدوات للتأمل والتوعية الذاتية وما بتعتبر تقييمًا أو علاجًا نفسيًا. إذا حسيت إنك بدك مساحة حقيقية للحديث، <a href="/book.php">اطلب موعد</a>.
      </div>
      <div class="card" style="margin-top:24px; text-align:center;">
        <h3>🔒 الخصوصية والبيانات المحلية</h3>
        <p>أي نص بتكتبه بهالأدوات (رتّب أفكارك، قائمة التحضير، قائمة القراءة) بينحفظ على هالجهاز والمتصفح بس، وما بيوصل إلنا أو لأي طرف تالت إطلاقًا. إذا الجهاز مشترك مع حدا تاني، فيك تمسح كل شي بضغطة وحدة:</p>
        <button type="button" class="btn btn-outline" id="clearAllToolsBtn" style="color:var(--color-danger); border-color:var(--color-danger);">🗑️ امسح كل بيانات أدوات الموقع من هالجهاز</button>
        <p id="clearAllStatus" class="field-hint" style="margin-top:10px;"></p>
      </div>
    <?php endif; ?>
  </div>
</section>

<script src="/assets/js/toolkit-privacy.js"></script>
<script>TalaToolkitPrivacy.bindClearAllButton('clearAllToolsBtn', 'clearAllStatus');</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
