<?php
require_once __DIR__ . '/../includes/auth.php';
require_content_access();
$activeNav = 'categories';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify()) {
    $action = $_POST['action'] ?? '';
    if ($action === 'create') {
        $name = trim($_POST['name'] ?? '');
        if ($name !== '') {
            $slug = unique_slug('categories', slugify($name));
            $maxOrder = (int)db()->query('SELECT COALESCE(MAX(sort_order),0) FROM categories')->fetchColumn();
            $ins = db()->prepare('INSERT INTO categories (slug, name, sort_order) VALUES (?, ?, ?)');
            $ins->execute([$slug, $name, $maxOrder + 1]);
            flash_set('success', 'تمت إضافة التصنيف.');
        }
    } elseif ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        $inUse = db()->prepare('SELECT COUNT(*) FROM articles WHERE category_id = ?');
        $inUse->execute([$id]);
        if ((int)$inUse->fetchColumn() > 0) {
            flash_set('error', 'ما فيك تحذف تصنيف مستخدم بمقالات. غيّر تصنيف المقالات أولًا.');
        } else {
            $del = db()->prepare('DELETE FROM categories WHERE id = ?');
            $del->execute([$id]);
            flash_set('success', 'تم حذف التصنيف.');
        }
    } elseif ($action === 'rename') {
        $id = (int)($_POST['id'] ?? 0);
        $name = trim($_POST['name'] ?? '');
        if ($name !== '') {
            $upd = db()->prepare('UPDATE categories SET name = ? WHERE id = ?');
            $upd->execute([$name, $id]);
            flash_set('success', 'تم تعديل اسم التصنيف.');
        }
    }
    redirect('/admin/categories.php');
}

$categories = db()->query('SELECT c.*, (SELECT COUNT(*) FROM articles a WHERE a.category_id = c.id) AS article_count FROM categories c ORDER BY sort_order, name')->fetchAll();
$pageTitle = 'التصنيفات';
require __DIR__ . '/includes/layout_top.php';
?>

<div class="admin-page-head"><h1>تصنيفات المقالات</h1></div>

<div class="admin-card" style="margin-bottom:24px;">
  <h3>إضافة تصنيف جديد</h3>
  <form method="post" style="display:flex; gap:10px;">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="create">
    <input type="text" name="name" placeholder="اسم التصنيف" required>
    <button type="submit" class="btn btn-primary">إضافة</button>
  </form>
</div>

<div class="admin-card">
  <table class="data-table">
    <thead><tr><th>الاسم</th><th>الرابط</th><th>عدد المقالات</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($categories as $c): ?>
        <tr>
          <td>
            <form method="post" style="display:flex; gap:8px;">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="rename">
              <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
              <input type="text" name="name" value="<?= e($c['name']) ?>" style="max-width:200px;">
              <button type="submit" class="btn btn-outline" style="padding:6px 14px; font-size:.8rem;">حفظ</button>
            </form>
          </td>
          <td><?= e($c['slug']) ?></td>
          <td><?= (int)$c['article_count'] ?></td>
          <td>
            <form method="post" onsubmit="return confirm('حذف هالتصنيف؟');">
              <?= csrf_field() ?>
              <input type="hidden" name="action" value="delete">
              <input type="hidden" name="id" value="<?= (int)$c['id'] ?>">
              <button type="submit" class="action-links"><span class="danger">حذف</span></button>
            </form>
          </td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
