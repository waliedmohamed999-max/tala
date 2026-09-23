<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/i18n.php';

$slug = $_GET['slug'] ?? '';
$stmt = db()->prepare("SELECT " . articles_public_select() . ", c.name AS category_name, c.slug AS category_slug
    FROM " . articles_public_from() . "
    WHERE a.slug = ? AND a.published_content IS NOT NULL");
$stmt->execute([$slug]);
$article = $stmt->fetch();

if (!$article) {
    http_response_code(404);
    $pageTitle = 'المقالة غير موجودة';
    require __DIR__ . '/../includes/header.php';
    echo '<section class="section container text-center"><h1>ما لقينا هالمقالة</h1><p>يمكن تم نقلها أو حذفها.</p><a href="/articles/index.php" class="btn btn-primary">شوف كل المقالات</a></section>';
    require __DIR__ . '/../includes/footer.php';
    exit;
}

// Browsing in English: only ever show a real, separately-reviewed English
// translation row (locale='en', linked via translation_of) — never machine
// translate the Arabic working copy on the fly.
if (current_locale() === 'en' && $article['locale'] !== 'en') {
    $tr = db()->prepare("SELECT slug FROM articles WHERE translation_of = ? AND locale = 'en' AND published_content IS NOT NULL");
    $tr->execute([$article['id']]);
    $englishSlug = $tr->fetchColumn();
    if ($englishSlug) {
        redirect('/articles/article.php?slug=' . urlencode($englishSlug) . '&lang=en');
    }
    show_untranslated_notice_page($article['title'] . ' (Arabic only)', '/articles/article.php?slug=' . urlencode($slug));
    exit;
}

$pageTitle = $article['title'];
$pageDescription = $article['excerpt'] ?: mb_substr(strip_tags($article['content']), 0, 155);
require __DIR__ . '/../includes/header.php';

$related = [];
if ($article['category_id']) {
    $r = db()->prepare("SELECT " . articles_public_select() . " FROM articles a WHERE a.published_category_id = ? AND a.id != ? AND a.published_content IS NOT NULL ORDER BY a.published_at DESC LIMIT 3");
    $r->execute([$article['category_id'], $article['id']]);
    $related = $r->fetchAll();
}
$sources = array_filter(array_map('trim', explode("\n", $article['sources'] ?? '')));
[$contentHtml, $toc] = build_table_of_contents($article['content']);
?>

<div class="article-hero-banner no-print" style="background: linear-gradient(135deg, var(--color-sage-light), var(--color-slate-light));">
  <?php render_topic_art(category_art_variant($article['category_slug'] ?? null), '90px'); ?>
</div>

<section class="section">
  <div class="container article-body">
    <div class="breadcrumbs">
      <a href="/articles/index.php">المقالات</a>
      <?php if ($article['category_name']): ?> / <a href="/articles/category.php?slug=<?= urlencode($article['category_slug']) ?>"><?= category_icon($article['category_slug']) ?> <?= e($article['category_name']) ?></a><?php endif; ?>
    </div>

    <?php if ($article['content_type'] !== 'article'): ?><span class="tag" style="margin-bottom:10px; display:inline-block;"><?= e(content_type_label($article['content_type'])) ?></span><?php endif; ?>
    <h1><?= e($article['title']) ?></h1>

    <div class="article-meta">
      <span>✍️ <?= e($article['author_name']) ?></span>
      <?php if ($article['reviewer_name']): ?><span>✅ راجعه: <?= e($article['reviewer_name']) ?></span><?php endif; ?>
      <?php if ($article['published_at']): ?><span>📅 نُشر: <?= e(date('Y/m/d', strtotime($article['published_at']))) ?></span><?php endif; ?>
      <?php if ($article['last_reviewed_at']): ?><span>🔄 آخر مراجعة: <?= e(date('Y/m/d', strtotime($article['last_reviewed_at']))) ?></span><?php endif; ?>
      <span>⏱ <?= (int)$article['read_minutes'] ?> دقايق قراءة</span>
    </div>

    <div class="article-toolbar no-print">
      <button type="button" class="btn btn-outline btn-sm" id="shareBtn">🔗 مشاركة / نسخ الرابط</button>
      <button type="button" class="btn btn-outline btn-sm" onclick="window.print()">🖨️ نسخة للطباعة</button>
      <button type="button" class="btn btn-outline btn-sm" data-save-article="<?= e($article['slug']) ?>" data-title="<?= e($article['title']) ?>" data-url="/articles/article.php?slug=<?= urlencode($article['slug']) ?>"></button>
      <span id="shareConfirm" class="field-hint" role="status" style="display:none;">تم نسخ الرابط ✓</span>
    </div>

    <div class="notice-inline" style="margin-bottom:30px;">
      المحتوى للتوعية العامة وما بيغني عن تقييم شخصي من مختص.
    </div>

    <?php if (count($toc) >= 3): ?>
      <nav class="toc-box no-print" aria-label="جدول محتويات المقالة">
        <strong>بهالمقالة:</strong>
        <ol>
          <?php foreach ($toc as $item): ?>
            <li><a href="#<?= e($item['id']) ?>"><?= e($item['text']) ?></a></li>
          <?php endforeach; ?>
        </ol>
      </nav>
    <?php endif; ?>

    <?= $contentHtml ?>

    <?php if (!empty($sources)): ?>
      <h3 style="margin-top:40px;">المصادر</h3>
      <ul>
        <?php foreach ($sources as $src): ?><li><?= e($src) ?></li><?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </div>
</section>

<?php if (!empty($related)): ?>
<section class="section section--alt no-print">
  <div class="container">
    <h2 class="text-center">مقالات ذات صلة</h2>
    <div class="grid grid-3">
      <?php foreach ($related as $a): ?>
        <article class="card article-card">
          <h3><a href="/articles/article.php?slug=<?= urlencode($a['slug']) ?>"><?= e($a['title']) ?></a></h3>
          <p><?= e($a['excerpt']) ?></p>
          <div class="meta"><span>⏱ <?= (int)$a['read_minutes'] ?> دقايق قراءة</span></div>
        </article>
      <?php endforeach; ?>
    </div>
  </div>
</section>
<?php endif; ?>

<section class="section text-center no-print">
  <div class="container" style="max-width:600px;">
    <h2>بدك تحكي مع حدا؟</h2>
    <p>القراءة بتساعد، بس مو بديل عن مساحة حقيقية للحديث.</p>
    <a href="/book.php" class="btn btn-primary">اطلب موعد</a>
  </div>
</section>

<script src="/assets/js/reading-list.js"></script>
<script>
document.getElementById('shareBtn')?.addEventListener('click', async function () {
  const url = window.location.href;
  const title = document.title;
  if (navigator.share) {
    try { await navigator.share({ title, url }); return; } catch (e) { /* user cancelled, fall through */ }
  }
  try {
    await navigator.clipboard.writeText(url);
    const confirmEl = document.getElementById('shareConfirm');
    confirmEl.style.display = 'inline';
    setTimeout(() => { confirmEl.style.display = 'none'; }, 2500);
  } catch (e) {
    prompt('انسخ الرابط:', url);
  }
});
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
