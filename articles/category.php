<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/i18n.php';

if (current_locale() === 'en') {
    show_untranslated_notice_page('Article Category', '/articles/index.php');
    exit;
}

$slug = trim($_GET['slug'] ?? '');
$stmt = db()->prepare('SELECT * FROM categories WHERE slug = ?');
$stmt->execute([$slug]);
$category = $stmt->fetch();

if (!$category) {
    http_response_code(404);
    $pageTitle = 'التصنيف غير موجود';
    require __DIR__ . '/../includes/header.php';
    echo '<section class="section container text-center"><h1>ما لقينا هالتصنيف</h1><a href="/articles/index.php" class="btn btn-primary">شوف كل المقالات</a></section>';
    require __DIR__ . '/../includes/footer.php';
    exit;
}

$pageTitle = $category['name'];
$pageDescription = $category['description'] ?: ('مقالات ضمن تصنيف ' . $category['name']);
require __DIR__ . '/../includes/header.php';

$stmt = db()->prepare("SELECT " . articles_public_select() . " FROM articles a WHERE a.published_category_id = ? AND a.published_content IS NOT NULL ORDER BY a.published_at DESC");
$stmt->execute([$category['id']]);
$articles = $stmt->fetchAll();
$contentTypeLabels = ['article' => 'مقالة', 'guide' => 'دليل خطوة بخطوة', 'faq_extended' => 'أسئلة وأجوبة موسعة'];
?>

<section class="page-hero container">
  <div style="display:flex; justify-content:center; margin-bottom:6px; font-size:2.4rem;"><?= category_icon($category['slug']) ?></div>
  <span class="eyebrow">تصنيف</span>
  <h1><?= e($category['name']) ?></h1>
  <?php if ($category['description']): ?><p><?= e($category['description']) ?></p><?php endif; ?>
</section>

<section class="section" style="padding-top:0;">
  <div class="container">
    <div class="breadcrumbs"><a href="/articles/index.php">كل المقالات</a> / <?= e($category['name']) ?></div>

    <?php if (empty($articles)): ?>
      <p class="text-center" style="padding:40px 0; color:var(--color-text-muted);">ما في مقالات منشورة بهالتصنيف حاليًا.</p>
    <?php else: ?>
      <div class="grid grid-3">
        <?php foreach ($articles as $a): ?>
          <article class="card article-card">
            <?php render_card_thumb(category_art_variant($category['slug'])); ?>
            <?php if ($a['content_type'] !== 'article'): ?><span class="cat-tag"><?= e($contentTypeLabels[$a['content_type']]) ?></span><?php endif; ?>
            <h3><a href="/articles/article.php?slug=<?= urlencode($a['slug']) ?>"><?= e($a['title']) ?></a></h3>
            <p><?= e($a['excerpt']) ?></p>
            <div class="meta"><span>⏱ <?= (int)$a['read_minutes'] ?> دقايق قراءة</span></div>
          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
