<?php
require_once __DIR__ . '/../includes/auth.php';

// This page only works before any admin account exists — it is the one-time
// bootstrap step, so no password is ever hardcoded anywhere in the codebase.
if (any_admin_exists()) {
    redirect('/admin/index.php');
}

$errors = [];
$old = ['name' => '', 'email' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $errors[] = 'في مشكلة تقنية بالنموذج. جرّب من جديد.';
    }
    $old['name'] = trim($_POST['name'] ?? '');
    $old['email'] = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['password_confirm'] ?? '';

    if ($old['name'] === '') { $errors[] = 'اكتب الاسم.'; }
    if (!filter_var($old['email'], FILTER_VALIDATE_EMAIL)) { $errors[] = 'البريد الإلكتروني مو صحيح.'; }
    if (mb_strlen($password) < 10) { $errors[] = 'كلمة المرور لازم تكون ١٠ أحرف على الأقل.'; }
    if ($password !== $confirm) { $errors[] = 'كلمتا المرور مو متطابقتين.'; }

    if (empty($errors)) {
        $stmt = db()->prepare('INSERT INTO admin_users (name, email, password_hash, role) VALUES (?, ?, ?, "owner")');
        $stmt->execute([$old['name'], $old['email'], password_hash($password, PASSWORD_DEFAULT)]);
        flash_set('success', 'تم إنشاء حساب المالك. سجّل الدخول للمتابعة.');
        redirect('/admin/index.php');
    }
}
?><!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>إعداد حساب المالك الأول</title>
<link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/style.css">
<link rel="stylesheet" href="/assets/css/admin.css">
</head>
<body class="admin-body">
<div class="login-shell">
  <div class="login-card">
    <h1 style="font-size:1.4rem;">إعداد حساب المالك الأول</h1>
    <p class="field-hint">هاي الخطوة بتظهر مرة وحدة بس، قبل ما ينعمل أي حساب إدارة.</p>

    <?php foreach ($errors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>

    <form method="post">
      <?= csrf_field() ?>
      <div class="form-group">
        <label for="name">الاسم</label>
        <input type="text" id="name" name="name" required value="<?= e($old['name']) ?>">
      </div>
      <div class="form-group">
        <label for="email">البريد الإلكتروني</label>
        <input type="email" id="email" name="email" required value="<?= e($old['email']) ?>">
      </div>
      <div class="form-group">
        <label for="password">كلمة المرور (١٠ أحرف على الأقل)</label>
        <input type="password" id="password" name="password" required minlength="10">
      </div>
      <div class="form-group">
        <label for="password_confirm">تأكيد كلمة المرور</label>
        <input type="password" id="password_confirm" name="password_confirm" required minlength="10">
      </div>
      <button type="submit" class="btn btn-primary btn-block">إنشاء الحساب</button>
    </form>
  </div>
</div>
</body>
</html>
