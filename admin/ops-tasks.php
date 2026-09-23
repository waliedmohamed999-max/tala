<?php
require_once __DIR__ . '/../includes/auth.php';
$adminUser = require_clinic_ops_access();
$activeNav = 'ops-tasks';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify()) {
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        $title = trim($_POST['title'] ?? '');
        if ($title !== '') {
            $ins = db()->prepare('INSERT INTO ops_tasks (title, description, due_at, created_by) VALUES (?,?,?,?)');
            $ins->execute([$title, trim($_POST['description'] ?? '') ?: null, trim($_POST['due_at'] ?? '') ?: null, $adminUser['name']]);
            flash_set('success', 'تمت إضافة المهمة.');
        }
    } elseif ($action === 'done') {
        db()->prepare("UPDATE ops_tasks SET status='done', done_at=datetime('now') WHERE id=?")->execute([(int)($_POST['id'] ?? 0)]);
    } elseif ($action === 'reopen') {
        db()->prepare("UPDATE ops_tasks SET status='open', done_at=NULL WHERE id=?")->execute([(int)($_POST['id'] ?? 0)]);
    } elseif ($action === 'delete') {
        db()->prepare('DELETE FROM ops_tasks WHERE id=?')->execute([(int)($_POST['id'] ?? 0)]);
    }
    redirect('/admin/ops-tasks.php');
}

$open = db()->query("SELECT * FROM ops_tasks WHERE status='open' ORDER BY due_at IS NULL, due_at, created_at")->fetchAll();
$done = db()->query("SELECT * FROM ops_tasks WHERE status='done' ORDER BY done_at DESC LIMIT 30")->fetchAll();

$pageTitle = 'مهام تشغيلية';
require __DIR__ . '/includes/layout_top.php';
?>

<div class="admin-card" style="margin-bottom:20px;">
  <form method="post" style="display:flex; gap:10px; flex-wrap:wrap; align-items:end;">
    <?= csrf_field() ?><input type="hidden" name="action" value="add">
    <div class="form-group" style="margin:0; flex:1; min-width:180px;"><label>عنوان المهمة</label><input type="text" name="title" required></div>
    <div class="form-group" style="margin:0; flex:1; min-width:180px;"><label>تفاصيل (اختياري)</label><input type="text" name="description"></div>
    <div class="form-group" style="margin:0;"><label>موعد الاستحقاق (اختياري)</label><input type="date" name="due_at"></div>
    <button type="submit" class="btn btn-primary">إضافة</button>
  </form>
</div>

<div class="admin-card" style="margin-bottom:20px;">
  <h2 style="margin-top:0;">مفتوحة (<?= count($open) ?>)</h2>
  <?php if (empty($open)): ?>
    <p class="field-hint">ما في مهام مفتوحة. 🎉</p>
  <?php else: ?>
    <table class="data-table">
      <thead><tr><th>العنوان</th><th>الاستحقاق</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($open as $t): ?>
          <tr>
            <td><?= e($t['title']) ?><?php if ($t['description']): ?><br><span class="field-hint"><?= e($t['description']) ?></span><?php endif; ?></td>
            <td><?= $t['due_at'] ? e($t['due_at']) : '—' ?></td>
            <td class="action-links">
              <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="done"><input type="hidden" name="id" value="<?= (int)$t['id'] ?>"><button type="submit">✓ إنجاز</button></form>
              <form method="post" onsubmit="return confirm('حذف هالمهمة؟');"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$t['id'] ?>"><button type="submit" class="danger">حذف</button></form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<div class="admin-card">
  <h2 style="margin-top:0;">منجزة مؤخرًا</h2>
  <?php if (empty($done)): ?>
    <p class="field-hint">ما في مهام منجزة بعد.</p>
  <?php else: ?>
    <table class="data-table">
      <thead><tr><th>العنوان</th><th>تاريخ الإنجاز</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($done as $t): ?>
          <tr>
            <td><?= e($t['title']) ?></td>
            <td><?= e(date('Y/m/d', strtotime($t['done_at']))) ?></td>
            <td class="action-links"><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="reopen"><input type="hidden" name="id" value="<?= (int)$t['id'] ?>"><button type="submit">إعادة فتح</button></form></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
