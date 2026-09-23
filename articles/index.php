<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/i18n.php';

if (current_locale() === 'en') {
    show_untranslated_notice_page('Articles & Guides', '/articles/index.php');
    exit;
}

$pageTitle = 'المقالات والأدلة';
$pageDescription = 'مكتبة مقالات وأدلة إرشادية بالعربي عن القلق، المشاعر، العلاقات، والصحة النفسية بشكل عام.';
require __DIR__ . '/../includes/header.php';

$q = trim($_GET['q'] ?? '');
$categorySlug = trim($_GET['cat'] ?? '');
$contentType = trim($_GET['type'] ?? '');
$readTime = trim($_GET['read'] ?? ''); // short | medium | long
$categories = published_categories();

$sql = "SELECT " . articles_public_select() . ", c.name AS category_name, c.slug AS category_slug
        FROM " . articles_public_from() . "
        WHERE a.published_content IS NOT NULL";
$params = [];

if ($q !== '') {
    $sql .= ' AND (a.published_title LIKE :q OR a.published_excerpt LIKE :q OR a.published_content LIKE :q)';
    $params[':q'] = '%' . $q . '%';
}
if ($categorySlug !== '') {
    $sql .= ' AND c.slug = :cat';
    $params[':cat'] = $categorySlug;
}
if (in_array($contentType, ['article', 'guide', 'faq_extended'], true)) {
    $sql .= ' AND a.published_content_type = :type';
    $params[':type'] = $contentType;
}
if ($readTime === 'short') { $sql .= ' AND a.published_read_minutes <= 3'; }
elseif ($readTime === 'medium') { $sql .= ' AND a.published_read_minutes BETWEEN 4 AND 6'; }
elseif ($readTime === 'long') { $sql .= ' AND a.published_read_minutes >= 7'; }

$sql .= ' ORDER BY a.published_at DESC';

$stmt = db()->prepare($sql);
$stmt->execute($params);
$articles = $stmt->fetchAll();

$contentTypeLabels = ['article' => 'مقالة', 'guide' => 'دليل خطوة بخطوة', 'faq_extended' => 'أسئلة وأجوبة موسعة'];

function articles_query_keep(array $overrides = []): string
{
    global $q, $categorySlug, $contentType, $readTime;
    $base = ['q' => $q ?: null, 'cat' => $categorySlug ?: null, 'type' => $contentType ?: null, 'read' => $readTime ?: null];
    $merged = array_filter(array_merge($base, $overrides));
    return http_build_query($merged);
}
?>

<section class="page-hero container">
  <div style="display:flex; justify-content:center; margin-bottom:6px;"><?php render_topic_art(4); ?></div>
  <span class="eyebrow">المكتبة الإرشادية</span>
  <h1>مقالات وأدلة تساعدك تفهم أكتر</h1>
  <p>محتوى بالعربي، بلغة قريبة منك، لأي حدا بدو يفهم مشاعره أو يتعامل مع ضغوط الحياة بشكل أفضل.</p>
</section>

<section class="section" style="padding-top:0;">
  <div class="container">
    <form method="get" class="search-box">
      <label for="q" class="visually-hidden">ابحث بالمقالات</label>
      <input type="text" id="q" name="q" value="<?= e($q) ?>" placeholder="دوّر بالعناوين والمحتوى...">
      <?php foreach (['cat' => $categorySlug, 'type' => $contentType, 'read' => $readTime] as $k => $v): ?>
        <?php if ($v !== ''): ?><input type="hidden" name="<?= e($k) ?>" value="<?= e($v) ?>"><?php endif; ?>
      <?php endforeach; ?>
      <button type="submit" class="btn btn-primary">بحث</button>
    </form>

    <div class="filter-chips">
      <a href="/articles/index.php?<?= articles_query_keep(['cat' => null]) ?>" class="filter-chip <?= $categorySlug === '' ? 'active' : '' ?>">كل التصنيفات</a>
      <?php foreach ($categories as $c): ?>
        <a href="/articles/index.php?<?= articles_query_keep(['cat' => $c['slug']]) ?>" class="filter-chip <?= $categorySlug === $c['slug'] ? 'active' : '' ?>"><?= category_icon($c['slug']) ?> <?= e($c['name']) ?></a>
      <?php endforeach; ?>
    </div>

    <div class="filter-chips">
      <a href="/articles/index.php?<?= articles_query_keep(['type' => null]) ?>" class="filter-chip <?= $contentType === '' ? 'active' : '' ?>">كل الأنواع</a>
      <?php foreach ($contentTypeLabels as $val => $label): ?>
        <a href="/articles/index.php?<?= articles_query_keep(['type' => $val]) ?>" class="filter-chip <?= $contentType === $val ? 'active' : '' ?>"><?= e($label) ?></a>
      <?php endforeach; ?>
    </div>

    <div class="filter-chips">
      <a href="/articles/index.php?<?= articles_query_keep(['read' => null]) ?>" class="filter-chip <?= $readTime === '' ? 'active' : '' ?>">أي مدة قراءة</a>
      <a href="/articles/index.php?<?= articles_query_keep(['read' => 'short']) ?>" class="filter-chip <?= $readTime === 'short' ? 'active' : '' ?>">قصيرة (≤٣ د)</a>
      <a href="/articles/index.php?<?= articles_query_keep(['read' => 'medium']) ?>" class="filter-chip <?= $readTime === 'medium' ? 'active' : '' ?>">متوسطة (٤-٦ د)</a>
      <a href="/articles/index.php?<?= articles_query_keep(['read' => 'long']) ?>" class="filter-chip <?= $readTime === 'long' ? 'active' : '' ?>">طويلة (٧+ د)</a>
    </div>

    <?php if (empty($articles)): ?>
      <p class="text-center" style="padding:40px 0; color:var(--color-text-muted);">ما لقينا مقالات مطابقة. جرّب كلمة بحث تانية أو تصفية مختلفة.</p>
    <?php else: ?>
      <p class="field-hint" style="margin-bottom:16px;"><?= count($articles) ?> نتيجة</p>
      <div class="grid grid-3">
        <?php foreach ($articles as $a): ?>
          <article class="card article-card">
            <?php render_card_thumb(category_art_variant($a['category_slug'] ?? null)); ?>
            <div class="tag-row" style="margin-bottom:10px;">
              <?php if ($a['category_name']): ?><span class="cat-tag"><?= category_icon($a['category_slug']) ?> <?= e($a['category_name']) ?></span><?php endif; ?>
              <?php if ($a['content_type'] !== 'article'): ?><span class="cat-tag"><?= e($contentTypeLabels[$a['content_type']]) ?></span><?php endif; ?>
            </div>
            <h3><a href="/articles/article.php?slug=<?= urlencode($a['slug']) ?>"><?= e($a['title']) ?></a></h3>
            <p><?= e($a['excerpt']) ?></p>
            <div class="meta">
              <span>⏱ <?= (int)$a['read_minutes'] ?> دقايق قراءة</span>
              <?php if ($a['published_at']): ?><span>📅 <?= e(date('Y/m/d', strtotime($a['published_at']))) ?></span><?php endif; ?>
            </div>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
