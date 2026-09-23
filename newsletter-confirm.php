<?php
require_once __DIR__ . '/includes/functions.php';

$token = $_GET['token'] ?? '';
$done = false;

if ($token !== '') {
    $stmt = db()->prepare("UPDATE newsletter_subscribers SET status = 'subscribed', confirmed_at = datetime('now'), confirm_token = NULL WHERE confirm_token = ? AND status = 'pending'");
    $stmt->execute([$token]);
    $done = $stmt->rowCount() > 0;
}

$pageTitle = 'تأكيد الاشتراك';
$noIndex = true;
require __DIR__ . '/includes/header.php';
?>
<section class="section container text-center" style="max-width:500px;">
  <?php if ($done): ?>
    <h1>تم تأكيد اشتراكك ✓</h1>
    <p>شكرًا إلك! رح توصلك رسالة كل ما ينشر محتوى جديد على الموقع.</p>
  <?php else: ?>
    <h1>رابط غير صحيح أو منتهي</h1>
    <p>يمكن يكون الرابط استُخدم من قبل، أو انتهت صلاحيته. جرّب تشترك من جديد من الصفحة الرئيسية.</p>
  <?php endif; ?>
  <a href="/index.php" class="btn btn-outline">رجوع للصفحة الرئيسية</a>
</section>
<?php require __DIR__ . '/includes/footer.php'; ?>
