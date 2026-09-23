<?php
require_once __DIR__ . '/../includes/auth.php';
require_content_access();
$activeNav = 'events';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify()) {
    $action = $_POST['action'] ?? '';
    if ($action === 'delete') {
        $id = (int)($_POST['id'] ?? 0);
        db()->prepare('DELETE FROM events WHERE id = ?')->execute([$id]);
        flash_set('success', 'تم حذف الفعالية.');
    } elseif ($action === 'toggle_publish') {
        $id = (int)($_POST['id'] ?? 0);
        db()->prepare('UPDATE events SET is_published = 1 - is_published WHERE id = ?')->execute([$id]);
    }
    redirect('/admin/events.php');
}

$events = db()->query('SELECT * FROM events ORDER BY event_date DESC')->fetchAll();
$eventsSectionEnabled = setting_bool('events_section_enabled');
$primaryAction = ['label' => 'فعالية جديدة', 'href' => '/admin/event-edit.php'];
$pageTitle = 'محاضرات وفعاليات';
require __DIR__ . '/includes/layout_top.php';
?>

<div class="notice-banner" style="margin-bottom:24px;">
  <strong>القسم <?= $eventsSectionEnabled ? 'مفعّل حاليًا بالموقع العام' : 'مخفي حاليًا عن الموقع العام' ?></strong>
  رابط "محاضرات وفعاليات" ما رح يظهر بقائمة التنقل للزوار إلا بعد ما تفعّلي القسم من <a href="/admin/settings.php">الإعدادات العامة</a>، ولازم يكون في فعالية واحدة منشورة على الأقل. لا تذكري مؤتمرات أو مشاركات ما صارت فعليًا.
</div>

<div class="admin-card">
  <?php if (empty($events)): ?>
    <p>ما في فعاليات مضافة لسا.</p>
  <?php else: ?>
    <table class="data-table">
      <thead><tr><th>العنوان</th><th>التاريخ</th><th>الحالة</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($events as $e): ?>
          <tr>
            <td><?= e($e['title']) ?></td>
            <td><?= e($e['event_date'] ?: '—') ?></td>
            <td><span class="status-badge <?= $e['is_published'] ? 'status-confirmed' : 'status-new' ?>"><?= $e['is_published'] ? 'منشورة' : 'مسودة' ?></span></td>
            <td class="action-links">
              <a href="/admin/event-edit.php?id=<?= (int)$e['id'] ?>">تعديل</a>
              <form method="post" style="display:inline;"><?= csrf_field() ?><input type="hidden" name="action" value="toggle_publish"><input type="hidden" name="id" value="<?= (int)$e['id'] ?>"><button type="submit"><?= $e['is_published'] ? 'إلغاء النشر' : 'نشر' ?></button></form>
              <form method="post" style="display:inline;" onsubmit="return confirm('حذف هالفعالية؟');"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$e['id'] ?>"><button type="submit" class="danger">حذف</button></form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
