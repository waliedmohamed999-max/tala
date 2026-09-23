<?php
require_once __DIR__ . '/../includes/auth.php';
require_content_access();

$id = (int)($_GET['id'] ?? 0);
$stmt = db()->prepare("SELECT a.*, c.name AS category_name, c.slug AS category_slug FROM articles a LEFT JOIN categories c ON c.id = a.category_id WHERE a.id = ?");
$stmt->execute([$id]);
$article = $stmt->fetch();

if (!$article) {
    http_response_code(404);
    exit('المقالة غير موجودة.');
}

[$contentHtml, $toc] = build_table_of_contents($article['content']);
$pageTitle = 'معاينة: ' . $article['title'];
$noIndex = true;
require __DIR__ . '/../includes/header.php';
?>
<div class="notice-banner no-print" style="border-radius:0; text-align:center; margin:0;">
  <strong>هاي معاينة إدارية فقط</strong> — هيك رح تبين المقالة للزوار لو نُشرت. الحالة الحالية: <?= e($article['workflow_status']) ?>.
  <a href="javascript:history.back()">رجوع</a>
</div>

<section class="section">
  <div class="container article-body">
    <div class="breadcrumbs">
      <a href="/articles/index.php">المقالات</a>
      <?php if ($article['category_name']): ?> / <?= e($article['category_name']) ?><?php endif; ?>
    </div>
    <h1><?= e($article['title']) ?></h1>
    <div class="article-meta">
      <span>✍️ <?= e($article['author_name']) ?></span>
      <?php if ($article['reviewer_name']): ?><span>✅ راجعه: <?= e($article['reviewer_name']) ?></span><?php endif; ?>
      <span>⏱ <?= (int)$article['read_minutes'] ?> دقايق قراءة</span>
    </div>
    <?php if (count($toc) >= 3): ?>
      <nav class="toc-box"><strong>بهالمقالة:</strong><ol><?php foreach ($toc as $item): ?><li><a href="#<?= e($item['id']) ?>"><?= e($item['text']) ?></a></li><?php endforeach; ?></ol></nav>
    <?php endif; ?>
    <?= $contentHtml ?>
  </div>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
