<?php
require_once __DIR__ . '/../includes/auth.php';
require_bookings_access();
$activeNav = 'messages';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify()) {
    $id = (int)($_POST['id'] ?? 0);
    if (($_POST['action'] ?? '') === 'mark_status') {
        $status = $_POST['status'] ?? 'read';
        if (in_array($status, ['new', 'read', 'replied', 'archived'], true)) {
            $upd = db()->prepare('UPDATE contact_messages SET status = ? WHERE id = ?');
            $upd->execute([$status, $id]);
            flash_set('success', 'تم تحديث حالة الرسالة.');
        }
    } elseif (($_POST['action'] ?? '') === 'delete') {
        $del = db()->prepare('DELETE FROM contact_messages WHERE id = ?');
        $del->execute([$id]);
        flash_set('success', 'تم حذف الرسالة.');
    }
    redirect('/admin/messages.php');
}

$messages = db()->query('SELECT * FROM contact_messages ORDER BY created_at DESC LIMIT 200')->fetchAll();
$pageTitle = 'رسائل التواصل';
require __DIR__ . '/includes/layout_top.php';
?>

<div class="admin-page-head"><h1>رسائل التواصل</h1></div>

<div class="admin-card">
  <?php if (empty($messages)): ?>
    <p>ما في رسائل لسا.</p>
  <?php else: ?>
    <table class="data-table">
      <thead><tr><th>الاسم</th><th>وسيلة التواصل</th><th>الموضوع</th><th>الرسالة</th><th>الحالة</th><th>التاريخ</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($messages as $m): ?>
          <tr>
            <td><?= e($m['name']) ?></td>
            <td><?= e($m['contact_method']) ?>: <?= e($m['contact_value']) ?></td>
            <td><?= e($m['subject'] ?: '—') ?></td>
            <td style="max-width:280px;"><?= e(mb_substr($m['message'], 0, 140)) ?><?= mb_strlen($m['message']) > 140 ? '…' : '' ?></td>
            <td>
              <form method="post" style="display:inline;">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="mark_status">
                <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
                <select name="status" onchange="this.form.submit()">
                  <?php foreach (['new'=>'جديدة','read'=>'مقروءة','replied'=>'تم الرد','archived'=>'مؤرشفة'] as $val=>$label): ?>
                    <option value="<?= e($val) ?>" <?= $m['status'] === $val ? 'selected' : '' ?>><?= e($label) ?></option>
                  <?php endforeach; ?>
                </select>
              </form>
            </td>
            <td><?= e(date('Y/m/d H:i', strtotime($m['created_at']))) ?></td>
            <td>
              <form method="post" onsubmit="return confirm('حذف هالرسالة نهائيًا؟');">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= (int)$m['id'] ?>">
                <button type="submit" class="action-links"><span class="danger">حذف</span></button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
