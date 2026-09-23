<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/i18n.php';

$locale = current_locale();
$pageTitle = $locale === 'en' ? 'My Reading List' : 'قائمة قراءتي';
$pageDescription = $locale === 'en'
    ? 'Articles you saved to read later — kept on your device only.'
    : 'المقالات اللي حفظتها لتقراها لاحقًا — محفوظة على جهازك فقط.';
require __DIR__ . '/../includes/header.php';
?>

<section class="page-hero container">
  <div style="display:flex; justify-content:center; margin-bottom:6px;"><?php render_topic_art(3); ?></div>
  <span class="eyebrow"><?= $locale === 'en' ? 'Your Space' : 'مساحة إلك' ?></span>
  <h1><?= $pageTitle ?></h1>
  <p><?= $locale === 'en'
    ? 'Articles you saved with the "Save for later" button — kept on your device only, and will disappear if you clear your browser data.'
    : 'المقالات اللي حفظتها من زر "احفظ للقراءة لاحقًا" — محفوظة على جهازك فقط، وبتختفي إذا مسحت بيانات المتصفح.' ?></p>
  <?php if ($locale === 'en'): ?>
    <p class="field-hint">Note: the article library is currently Arabic-only, so saved titles below will be in Arabic.</p>
  <?php endif; ?>
</section>

<section class="section" style="padding-top:0;">
  <div class="container" style="max-width:700px;">
    <div id="readingListEmpty" style="display:none;" class="text-center">
      <?php if ($locale === 'en'): ?>
        <p style="color:var(--color-text-muted); padding:30px 0;">You haven't saved any articles yet. Add articles using the "📌 Save for later" button on any article page.</p>
        <a href="/articles/index.php" class="btn btn-primary">Browse Articles</a>
      <?php else: ?>
        <p style="color:var(--color-text-muted); padding:30px 0;">ما حفظت أي مقالة لسا. فيك تضيف مقالات من زر "📌 احفظ للقراءة لاحقًا" الموجود بصفحة كل مقالة.</p>
        <a href="/articles/index.php" class="btn btn-primary">تصفح المقالات</a>
      <?php endif; ?>
    </div>
    <ul id="readingListItems" style="list-style:none; padding:0; margin:0; display:flex; flex-direction:column; gap:12px;"></ul>
  </div>
</section>

<script src="/assets/js/reading-list.js"></script>
<script>
(function () {
  var removeLabel = <?= $locale === 'en' ? "'Remove'" : "'إزالة'" ?>;
  var list = TalaReadingList.getAll();
  var container = document.getElementById('readingListItems');
  var empty = document.getElementById('readingListEmpty');
  if (list.length === 0) { empty.style.display = 'block'; return; }

  list.forEach(function (item) {
    var li = document.createElement('li');
    li.className = 'card';
    li.style.display = 'flex';
    li.style.justifyContent = 'space-between';
    li.style.alignItems = 'center';
    li.innerHTML = '<a href="' + item.url + '" style="font-weight:700;">' + item.title.replace(/</g, '&lt;') + '</a>';
    var btn = document.createElement('button');
    btn.className = 'action-links';
    btn.innerHTML = '<span class="danger">' + removeLabel + '</span>';
    btn.addEventListener('click', function () {
      TalaReadingList.remove(item.slug);
      li.remove();
      if (container.children.length === 0) empty.style.display = 'block';
    });
    li.appendChild(btn);
    container.appendChild(li);
  });
})();
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
