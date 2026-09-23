<?php
require_once __DIR__ . '/../includes/auth.php';

if (!any_admin_exists()) {
    redirect('/admin/setup.php');
}

if (current_admin()) {
    redirect('/admin/dashboard.php');
}

$errors = [];
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $errors[] = 'في مشكلة تقنية بالنموذج. جرّب من جديد.';
    }
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($email === '' || $password === '') {
        $errors[] = 'عبّي البريد الإلكتروني وكلمة المرور.';
    } elseif (empty($errors)) {
        $user = attempt_login($email, $password);
        if ($user) {
            $next = $_GET['next'] ?? '/admin/dashboard.php';
            redirect(str_starts_with($next, '/admin') ? $next : '/admin/dashboard.php');
        }
        $errors[] = 'البريد الإلكتروني أو كلمة المرور غير صحيحة، أو تجاوزت عدد المحاولات المسموح. جرّب بعد شوي.';
    }
}

$flashes = flash_get_all();
?><!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>تسجيل الدخول — لوحة إدارة تالا</title>
<link href="https://fonts.googleapis.com/css2?family=Tajawal:wght@400;500;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="/assets/css/style.css">
<link rel="stylesheet" href="/assets/css/admin.css">
</head>
<body class="admin-body">
<div class="login-shell">
  <div class="login-card">
    <h1 style="font-size:1.4rem;">دخول لوحة الإدارة</h1>

    <?php foreach ($flashes as $f): ?><div class="alert alert-<?= e($f['type']) ?>"><?= e($f['message']) ?></div><?php endforeach; ?>
    <?php foreach ($errors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>

    <form method="post">
      <?= csrf_field() ?>
      <div class="form-group">
        <label for="email">البريد الإلكتروني</label>
        <input type="email" id="email" name="email" required value="<?= e($email) ?>" autofocus>
      </div>
      <div class="form-group">
        <label for="password">كلمة المرور</label>
        <input type="password" id="password" name="password" required>
      </div>
      <button type="submit" class="btn btn-primary btn-block">دخول</button>
    </form>
  </div>
</div>
</body>
</html>
