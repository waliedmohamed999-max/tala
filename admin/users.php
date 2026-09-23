<?php
require_once __DIR__ . '/../includes/auth.php';
$currentUser = require_role('owner');
$activeNav = 'users';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify()) {
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    if ($action === 'toggle_active') {
        if ($id === (int)$currentUser['id']) {
            flash_set('error', 'ما فيك توقف حسابك الخاص.');
        } else {
            $upd = db()->prepare('UPDATE admin_users SET is_active = 1 - is_active WHERE id = ?');
            $upd->execute([$id]);
            flash_set('success', 'تم تحديث حالة الحساب.');
        }
    } elseif ($action === 'delete') {
        if ($id === (int)$currentUser['id']) {
            flash_set('error', 'ما فيك تحذف حسابك الخاص.');
        } else {
            $del = db()->prepare('DELETE FROM admin_users WHERE id = ?');
            $del->execute([$id]);
            flash_set('success', 'تم حذف المستخدم.');
        }
    }
    redirect('/admin/users.php');
}

$users = db()->query('SELECT * FROM admin_users ORDER BY created_at')->fetchAll();
$pageTitle = 'المستخدمون والصلاحيات';
require __DIR__ . '/includes/layout_top.php';
?>

<div class="admin-page-head">
  <h1>المستخدمون والصلاحيات</h1>
  <a href="/admin/user-edit.php" class="btn btn-primary">+ مستخدم جديد</a>
</div>

<div class="admin-card" style="margin-bottom:20px;">
  <h3>الأدوار المتاحة</h3>
  <ul style="color:var(--color-text-muted); font-size:.92rem; line-height:1.9;">
    <li><strong>مالك الموقع</strong> — صلاحية كاملة: المحتوى، الحجوزات، الإعدادات، والمستخدمين.</li>
    <li><strong>محرر محتوى</strong> — ينشئ ويعدّل المقالات/الخدمات/الأسئلة الشائعة، ويرسلها للمراجعة فقط (ما بينشر مباشرة).</li>
    <li><strong>مراجع مهني</strong> — يعتمد وينشر المحتوى بعد المراجعة، وفيه يعدّل كمان.</li>
    <li><strong>مسؤول حجوزات</strong> — طلبات المواعيد، التقويم، وبيانات المرضى الإدارية فقط (بدون الملاحظات السريرية)، بدون وصول للمحتوى.</li>
    <li><strong>مسؤول مالي</strong> — الفواتير والمدفوعات فقط، ما بيشوف الملاحظات السريرية ولا تفاصيل المرضى الكاملة.</li>
  </ul>
</div>

<div class="admin-card">
  <table class="data-table">
    <thead><tr><th>الاسم</th><th>البريد</th><th>الدور</th><th>نشط</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($users as $u): ?>
        <tr>
          <td><?= e($u['name']) ?></td>
          <td><?= e($u['email']) ?></td>
          <td><?= e(role_label($u['role'])) ?></td>
          <td><span class="status-badge <?= $u['is_active'] ? 'status-confirmed' : 'status-cancelled' ?>"><?= $u['is_active'] ? 'نشط' : 'موقوف' ?></span></td>
          <td class="action-links">
            <a href="/admin/user-edit.php?id=<?= (int)$u['id'] ?>">تعديل</a>
            <form method="post" style="display:inline;">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="toggle_active">
              <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
              <button type="submit"><?= $u['is_active'] ? 'إيقاف' : 'تفعيل' ?></button>
            </form>
            <form method="post" style="display:inline;" onsubmit="return confirm('حذف هالمستخدم نهائيًا؟');">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
              <button type="submit" class="danger">حذف</button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
