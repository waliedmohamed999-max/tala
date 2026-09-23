<?php
require_once __DIR__ . '/includes/functions.php';

$token = $_GET['token'] ?? '';
$done = false;

if ($token !== '') {
    $stmt = db()->prepare("UPDATE newsletter_subscribers SET status = 'unsubscribed' WHERE unsubscribe_token = ?");
    $stmt->execute([$token]);
    $done = $stmt->rowCount() > 0;
}

$pageTitle = 'إلغاء الاشتراك';
$noIndex = true;
require __DIR__ . '/includes/header.php';
?>
<section class="section container text-center" style="max-width:500px;">
  <?php if ($done): ?>
    <h1>تم إلغاء اشتراكك</h1>
    <p>ما رح توصلك رسائل جديدة من النشرة البريدية. فيك ترجع تشترك أي وقت من الموقع.</p>
  <?php else: ?>
    <h1>رابط غير صحيح</h1>
    <p>ما قدرنا نلاقي هالاشتراك، يمكن يكون تم إلغاؤه مسبقًا.</p>
  <?php endif; ?>
  <a href="/index.php" class="btn btn-outline">رجوع للصفحة الرئيسية</a>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
