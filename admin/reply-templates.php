<?php
require_once __DIR__ . '/../includes/auth.php';
require_bookings_access();
$activeNav = 'templates';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify()) {
    $id = (int)($_POST['id'] ?? 0);
    $body = trim($_POST['body'] ?? '');
    if ($body !== '') {
        $upd = db()->prepare("UPDATE reply_templates SET body = ?, updated_at = datetime('now') WHERE id = ?");
        $upd->execute([$body, $id]);
        flash_set('success', 'تم حفظ القالب.');
    }
    redirect('/admin/reply-templates.php');
}

$templates = db()->query('SELECT * FROM reply_templates ORDER BY id')->fetchAll();
$pageTitle = 'قوالب الردود';
require __DIR__ . '/includes/layout_top.php';
?>

<div class="admin-page-head"><h1>قوالب الردود</h1></div>

<div class="notice-inline" style="margin-bottom:24px;">
  هاي نصوص جاهزة تُنسخ يدويًا من صفحة تفاصيل كل طلب موعد. ما في إرسال آلي لأي رسالة — لازم إعداد بريد SMTP فعلي أولًا، وموافقة تالا على النصوص. استخدم <code>[الاسم]</code>، <code>[اليوم]</code>، و<code>[الوقت]</code> كنقاط تُستبدل يدويًا أو تلقائيًا (الاسم فقط بيتعوض تلقائيًا).
</div>

<?php foreach ($templates as $t): ?>
  <div class="admin-card" style="margin-bottom:16px;">
    <form method="post" class="form-narrow">
      <?= csrf_field() ?>
      <input type="hidden" name="id" value="<?= (int)$t['id'] ?>">
      <div class="form-group">
        <label><?= e($t['label']) ?></label>
        <textarea name="body" rows="6"><?= e($t['body']) ?></textarea>
      </div>
      <button type="submit" class="btn btn-primary">حفظ</button>
    </form>
  </div>
<?php endforeach; ?>

<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
