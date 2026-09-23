<?php
require_once __DIR__ . '/../includes/auth.php';
require_bookings_access();
$activeNav = 'speaking';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify()) {
    $id = (int)($_POST['id'] ?? 0);
    if (($_POST['action'] ?? '') === 'mark_status') {
        $status = $_POST['status'] ?? 'new';
        if (in_array($status, ['new', 'read', 'replied', 'archived'], true)) {
            db()->prepare('UPDATE speaking_inquiries SET status = ? WHERE id = ?')->execute([$status, $id]);
        }
    } elseif (($_POST['action'] ?? '') === 'delete') {
        db()->prepare('DELETE FROM speaking_inquiries WHERE id = ?')->execute([$id]);
        flash_set('success', 'تم الحذف.');
    }
    redirect('/admin/speaking-inquiries.php');
}

$inquiries = db()->query('SELECT * FROM speaking_inquiries ORDER BY created_at DESC')->fetchAll();
$pageTitle = 'طلبات دعوات التحدث';
require __DIR__ . '/includes/layout_top.php';
?>

<div class="admin-page-head"><h1>طلبات دعوات التحدث</h1></div>

<div class="admin-card">
  <?php if (empty($inquiries)): ?>
    <p>ما في طلبات لسا.</p>
  <?php else: ?>
    <table class="data-table">
      <thead><tr><th>الاسم</th><th>الجهة</th><th>التواصل</th><th>التفاصيل</th><th>الحالة</th><th>التاريخ</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($inquiries as $i): ?>
          <tr>
            <td><?= e($i['name']) ?></td>
            <td><?= e($i['organization'] ?: '—') ?></td>
            <td><?= e($i['contact_value']) ?></td>
            <td style="max-width:260px;"><?= e(mb_substr($i['event_details'], 0, 120)) ?><?= mb_strlen($i['event_details']) > 120 ? '…' : '' ?></td>
            <td>
              <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="mark_status"><input type="hidden" name="id" value="<?= (int)$i['id'] ?>">
                <select name="status" onchange="this.form.submit()">
                  <?php foreach (['new'=>'جديد','read'=>'مقروء','replied'=>'تم الرد','archived'=>'مؤرشف'] as $val=>$label): ?>
                    <option value="<?= e($val) ?>" <?= $i['status'] === $val ? 'selected' : '' ?>><?= e($label) ?></option>
                  <?php endforeach; ?>
                </select>
              </form>
            </td>
            <td><?= e(date('Y/m/d', strtotime($i['created_at']))) ?></td>
            <td><form method="post" onsubmit="return confirm('حذف؟');"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$i['id'] ?>"><button type="submit" class="action-links"><span class="danger">حذف</span></button></form></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
