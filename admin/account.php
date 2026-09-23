<?php
require_once __DIR__ . '/../includes/auth.php';
$adminUser = require_admin();
$activeNav = 'account';

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) { $errors[] = 'في مشكلة تقنية. جرّب من جديد.'; }
    $name = trim($_POST['name'] ?? '');
    $currentPassword = $_POST['current_password'] ?? '';
    $newPassword = $_POST['new_password'] ?? '';

    if ($name === '') { $errors[] = 'الاسم مطلوب.'; }
    if ($newPassword !== '' && mb_strlen($newPassword) < 10) { $errors[] = 'كلمة المرور الجديدة لازم تكون ١٠ أحرف على الأقل.'; }
    if ($newPassword !== '' && !password_verify($currentPassword, $adminUser['password_hash'])) {
        $errors[] = 'كلمة المرور الحالية غير صحيحة.';
    }

    if (empty($errors)) {
        if ($newPassword !== '') {
            db()->prepare('UPDATE admin_users SET name = ?, password_hash = ? WHERE id = ?')
                ->execute([$name, password_hash($newPassword, PASSWORD_DEFAULT), $adminUser['id']]);
            flash_set('success', 'تم حفظ الاسم وكلمة المرور الجديدة.');
        } else {
            db()->prepare('UPDATE admin_users SET name = ? WHERE id = ?')->execute([$name, $adminUser['id']]);
            flash_set('success', 'تم حفظ الاسم.');
        }
        redirect('/admin/account.php');
    }
}

$pageTitle = 'حسابي';
require __DIR__ . '/includes/layout_top.php';
?>

<?php foreach ($errors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>

<div class="admin-card" style="max-width:520px;">
  <form method="post">
    <?= csrf_field() ?>
    <div class="form-group"><label>الاسم</label><input type="text" name="name" required value="<?= e($adminUser['name']) ?>"></div>
    <div class="form-group"><label>البريد الإلكتروني</label><input type="email" value="<?= e($adminUser['email']) ?>" disabled></div>
    <div class="form-group"><label>الدور</label><input type="text" value="<?= e(role_label($adminUser['role'])) ?>" disabled></div>
    <hr style="border:none; border-top:1px solid var(--color-border); margin:20px 0;">
    <p class="field-hint" style="margin-bottom:12px;">لتغيير كلمة المرور، عبّي الحقلين تحت. اتركهم فاضيين إذا ما بدك تغيّرها.</p>
    <div class="form-group"><label>كلمة المرور الحالية</label><input type="password" name="current_password"></div>
    <div class="form-group"><label>كلمة المرور الجديدة (١٠ أحرف على الأقل)</label><input type="password" name="new_password" minlength="10"></div>
    <button type="submit" class="btn btn-primary">حفظ</button>
  </form>
</div>

<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
