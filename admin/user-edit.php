<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('owner');
$activeNav = 'users';

$roles = ['editor', 'reviewer', 'bookings_manager', 'financial', 'owner'];

$id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$user = ['id' => null, 'name' => '', 'email' => '', 'role' => 'editor'];
if ($id) {
    $stmt = db()->prepare('SELECT * FROM admin_users WHERE id = ?');
    $stmt->execute([$id]);
    $found = $stmt->fetch();
    if (!$found) { flash_set('error', 'المستخدم غير موجود.'); redirect('/admin/users.php'); }
    $user = $found;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'revoke_sessions' && csrf_verify()) {
    revoke_user_sessions((int)($_POST['id'] ?? 0));
    flash_set('success', 'تم إبطال جلسات المستخدم. رح يحتاج يسجّل دخول من جديد.');
    redirect('/admin/user-edit.php?id=' . (int)($_POST['id'] ?? 0));
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') !== 'revoke_sessions') {
    if (!csrf_verify()) { $errors[] = 'في مشكلة تقنية. جرّب من جديد.'; }
    $user['name'] = trim($_POST['name'] ?? '');
    $user['email'] = trim($_POST['email'] ?? '');
    $user['role'] = $_POST['role'] ?? 'editor';
    $password = $_POST['password'] ?? '';

    if ($user['name'] === '') { $errors[] = 'الاسم مطلوب.'; }
    if (!filter_var($user['email'], FILTER_VALIDATE_EMAIL)) { $errors[] = 'البريد الإلكتروني مو صحيح.'; }
    if (!in_array($user['role'], $roles, true)) { $errors[] = 'دور غير صحيح.'; }
    if (!$id && mb_strlen($password) < 10) { $errors[] = 'كلمة المرور لازم تكون ١٠ أحرف على الأقل.'; }
    if ($id && $password !== '' && mb_strlen($password) < 10) { $errors[] = 'كلمة المرور الجديدة لازم تكون ١٠ أحرف على الأقل.'; }

    if (empty($errors)) {
        $emailCheck = db()->prepare('SELECT id FROM admin_users WHERE email = ? AND id != ?');
        $emailCheck->execute([$user['email'], $id ?: 0]);
        if ($emailCheck->fetch()) {
            $errors[] = 'هالبريد الإلكتروني مستخدم من حساب تاني.';
        }
    }

    if (empty($errors)) {
        if ($id) {
            if ($password !== '') {
                $upd = db()->prepare('UPDATE admin_users SET name=?, email=?, role=?, password_hash=? WHERE id=?');
                $upd->execute([$user['name'], $user['email'], $user['role'], password_hash($password, PASSWORD_DEFAULT), $id]);
            } else {
                $upd = db()->prepare('UPDATE admin_users SET name=?, email=?, role=? WHERE id=?');
                $upd->execute([$user['name'], $user['email'], $user['role'], $id]);
            }
            flash_set('success', 'تم حفظ بيانات المستخدم.');
        } else {
            $ins = db()->prepare('INSERT INTO admin_users (name, email, password_hash, role) VALUES (?,?,?,?)');
            $ins->execute([$user['name'], $user['email'], password_hash($password, PASSWORD_DEFAULT), $user['role']]);
            flash_set('success', 'تمت إضافة المستخدم.');
        }
        redirect('/admin/users.php');
    }
}

$pageTitle = $id ? 'تعديل مستخدم' : 'مستخدم جديد';
require __DIR__ . '/includes/layout_top.php';
?>

<div class="admin-page-head"><h1><?= $id ? 'تعديل مستخدم' : 'مستخدم جديد' ?></h1></div>

<?php foreach ($errors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>

<div class="admin-card">
  <form method="post" class="form-narrow">
    <?= csrf_field() ?>
    <div class="form-group"><label>الاسم *</label><input type="text" name="name" required value="<?= e($user['name']) ?>"></div>
    <div class="form-group"><label>البريد الإلكتروني *</label><input type="email" name="email" required value="<?= e($user['email']) ?>"></div>
    <div class="form-group">
      <label><?= $id ? 'كلمة مرور جديدة (اتركها فاضية للإبقاء على القديمة)' : 'كلمة المرور *' ?></label>
      <input type="password" name="password" minlength="10" <?= $id ? '' : 'required' ?>>
    </div>
    <div class="form-group">
      <label>الدور</label>
      <select name="role">
        <option value="editor" <?= $user['role'] === 'editor' ? 'selected' : '' ?>>محرر محتوى — ينشئ ويعدّل، يرسل للمراجعة فقط</option>
        <option value="reviewer" <?= $user['role'] === 'reviewer' ? 'selected' : '' ?>>مراجع مهني — يعتمد وينشر المحتوى</option>
        <option value="bookings_manager" <?= $user['role'] === 'bookings_manager' ? 'selected' : '' ?>>مسؤول حجوزات — طلبات المواعيد، التقويم، والمرضى (بيانات إدارية فقط)</option>
        <option value="financial" <?= $user['role'] === 'financial' ? 'selected' : '' ?>>مسؤول مالي — الفواتير والمدفوعات فقط</option>
        <option value="owner" <?= $user['role'] === 'owner' ? 'selected' : '' ?>>مالك الموقع — صلاحيات كاملة</option>
      </select>
    </div>
    <button type="submit" class="btn btn-primary">حفظ</button>
  </form>
</div>

<?php if ($id): ?>
<div class="admin-card" style="margin-top:20px;">
  <h3 style="margin-top:0;">إبطال الجلسات</h3>
  <p class="field-hint">بيسجّل خروج المستخدم فورًا من كل الأجهزة يلي مسجل دخول فيها حاليًا (مفيد لو جهازه ضاع أو انسرقت كلمة مروره). بيحتاج يسجّل دخول من جديد.</p>
  <form method="post" onsubmit="return confirm('إبطال كل جلسات هالمستخدم الحالية؟');">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="revoke_sessions">
    <input type="hidden" name="id" value="<?= (int)$id ?>">
    <button type="submit" class="btn btn-outline">إبطال كل الجلسات</button>
  </form>
</div>
<?php endif; ?>

<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
