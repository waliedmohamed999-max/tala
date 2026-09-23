<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/mailer.php';
$adminUser = require_bookings_access();
$activeNav = 'appointments';

$statusLabels = [
    'new' => 'جديد',
    'contacting' => 'جارٍ التواصل',
    'confirmed' => 'مؤكد',
    'cancelled' => 'أُلغي',
    'done' => 'مكتمل',
];

// --- handle status update ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    if (!csrf_verify()) {
        flash_set('error', 'في مشكلة تقنية. جرّب من جديد.');
        redirect('/admin/appointments.php');
    }
    $id = (int)($_POST['id'] ?? 0);
    $newStatus = $_POST['status'] ?? '';
    if (!array_key_exists($newStatus, $statusLabels)) {
        flash_set('error', 'حالة غير صحيحة.');
        redirect('/admin/appointments.php?id=' . $id);
    }
    $cur = db()->prepare('SELECT status FROM appointment_requests WHERE id = ?');
    $cur->execute([$id]);
    $oldStatus = $cur->fetchColumn();
    if ($oldStatus !== false) {
        $upd = db()->prepare("UPDATE appointment_requests SET status = ?, updated_at = datetime('now') WHERE id = ?");
        $upd->execute([$newStatus, $id]);
        $log = db()->prepare('INSERT INTO appointment_status_log (request_id, old_status, new_status, changed_by) VALUES (?, ?, ?, ?)');
        $log->execute([$id, $oldStatus, $newStatus, $adminUser['name']]);
        flash_set('success', 'تم تحديث حالة الطلب.');
    }
    redirect('/admin/appointments.php?id=' . $id);
}

// --- handle delete (retention policy cleanup) ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'delete') {
    if (csrf_verify()) {
        $id = (int)($_POST['id'] ?? 0);
        $del = db()->prepare('DELETE FROM appointment_requests WHERE id = ?');
        $del->execute([$id]);
        flash_set('success', 'تم حذف الطلب.');
    }
    redirect('/admin/appointments.php');
}

$viewId = isset($_GET['id']) ? (int)$_GET['id'] : null;
$detail = null;
$history = [];
if ($viewId) {
    $stmt = db()->prepare('SELECT r.*, s.name AS service_name FROM appointment_requests r LEFT JOIN services s ON s.id = r.service_id WHERE r.id = ?');
    $stmt->execute([$viewId]);
    $detail = $stmt->fetch();
    if ($detail) {
        $h = db()->prepare('SELECT * FROM appointment_status_log WHERE request_id = ? ORDER BY changed_at DESC');
        $h->execute([$viewId]);
        $history = $h->fetchAll();
    }
}

$statusFilter = $_GET['status'] ?? '';
$q = trim($_GET['q'] ?? '');
$sql = "SELECT r.*, s.name AS service_name FROM appointment_requests r LEFT JOIN services s ON s.id = r.service_id WHERE 1=1";
$params = [];
if ($statusFilter !== '' && array_key_exists($statusFilter, $statusLabels)) {
    $sql .= ' AND r.status = :status';
    $params[':status'] = $statusFilter;
}
if ($q !== '') {
    $sql .= ' AND (r.name LIKE :q OR r.contact_value LIKE :q OR r.reference_code LIKE :q)';
    $params[':q'] = '%' . $q . '%';
}
$sql .= ' ORDER BY r.created_at DESC LIMIT 200';
$stmt = db()->prepare($sql);
$stmt->execute($params);
$list = $stmt->fetchAll();

$pageTitle = 'طلبات المواعيد';
require __DIR__ . '/includes/layout_top.php';
?>

<div class="admin-page-head">
  <h1>طلبات المواعيد</h1>
</div>

<?php if ($detail): ?>
  <div class="admin-card" style="margin-bottom:26px;">
    <div class="admin-page-head">
      <h2 style="margin:0;">طلب <?= $detail['reference_code'] ? e($detail['reference_code']) : '#' . (int)$detail['id'] ?> — <?= e($detail['name']) ?></h2>
      <a href="/admin/appointments.php" class="btn btn-outline">رجوع للقائمة</a>
    </div>

    <div class="grid grid-2" style="margin-bottom:20px;">
      <p><strong>وسيلة التواصل:</strong> <?= e($detail['contact_method']) ?> — <?= e($detail['contact_value']) ?></p>
      <p><strong>الخدمة:</strong> <?= e($detail['service_name'] ?? 'غير محدد') ?></p>
      <p><strong>طريقة الحضور:</strong> <?= e($detail['location_pref']) ?></p>
      <p><strong>الوقت المفضل:</strong> <?= e($detail['time_pref'] ?: '—') ?></p>
      <p><strong>تاريخ الطلب:</strong> <?= e(date('Y/m/d H:i', strtotime($detail['created_at']))) ?></p>
      <p><strong>الحالة الحالية:</strong> <span class="status-badge status-<?= e($detail['status']) ?>"><?= e($statusLabels[$detail['status']] ?? $detail['status']) ?></span></p>
    </div>
    <?php if ($detail['notes']): ?><p><strong>ملاحظات:</strong><br><?= nl2br(e($detail['notes'])) ?></p><?php endif; ?>

    <?php
    $templates = db()->query('SELECT * FROM reply_templates ORDER BY id')->fetchAll();
    $sentLog = db()->prepare("SELECT * FROM notification_log WHERE appointment_request_id = ? AND type IN ('received','confirmed','reschedule','unavailable') ORDER BY created_at DESC");
    $sentLog->execute([$detail['id']]);
    $sentByType = [];
    foreach ($sentLog->fetchAll() as $log) {
        if (!isset($sentByType[$log['type']])) { $sentByType[$log['type']] = $log; } // most recent per type
    }
    $canEmailSend = $detail['contact_method'] === 'email';
    $mailerReady = mailer_is_configured();
    ?>
    <?php if (!empty($templates)): ?>
    <div class="admin-card" style="background:var(--color-bg-alt); margin:20px 0;">
      <h3 style="margin-top:0;">قوالب ردود جاهزة</h3>
      <?php if (!$mailerReady): ?>
        <div class="alert alert-info">🔴 البريد غير مربوط — فيك تنسخ النصوص يدويًا بس. اضبط <a href="/admin/notifications-settings.php">إعدادات الإشعارات والبريد</a> لتفعيل الإرسال المباشر.</div>
      <?php elseif (!$canEmailSend): ?>
        <div class="alert alert-info">وسيلة تواصل هالطلب مو بريد إلكتروني — الإرسال المباشر متاح بس للبريد. انسخ النص وأرسله عبر <?= e($detail['contact_method']) ?> يدويًا.</div>
      <?php endif; ?>
      <div class="grid grid-2">
        <?php foreach ($templates as $t): ?>
          <?php $filled = str_replace('[الاسم]', $detail['name'], $t['body']); ?>
          <?php $sent = $sentByType[$t['key']] ?? null; ?>
          <?php $canSendThis = $mailerReady && $canEmailSend && ($t['key'] !== 'confirmed' || $detail['status'] === 'confirmed'); ?>
          <div>
            <strong style="font-size:.88rem;"><?= e($t['label']) ?></strong>
            <textarea readonly rows="4" style="font-size:.85rem; margin-top:6px;" id="tpl-<?= (int)$t['id'] ?>"><?= e($filled) ?></textarea>
            <div style="display:flex; gap:8px; align-items:center; margin-top:6px; flex-wrap:wrap;">
              <button type="button" class="btn btn-outline btn-sm" onclick="navigator.clipboard.writeText(document.getElementById('tpl-<?= (int)$t['id'] ?>').value)">نسخ النص</button>
              <?php if ($t['key'] === 'confirmed' && $detail['status'] !== 'confirmed'): ?>
                <span class="field-hint">غيّر الحالة لـ«مؤكد» أولًا لتقدر ترسل هالرسالة</span>
              <?php elseif ($canSendThis): ?>
                <form method="post" action="/admin/send-reply.php" onsubmit="return confirm('إرسال هالرسالة فعليًا لبريد الزائر؟');">
                  <?= csrf_field() ?>
                  <input type="hidden" name="appointment_id" value="<?= (int)$detail['id'] ?>">
                  <input type="hidden" name="template_key" value="<?= e($t['key']) ?>">
                  <button type="submit" class="btn btn-primary btn-sm">📧 أرسل فعليًا</button>
                </form>
              <?php endif; ?>
              <?php if ($sent): ?>
                <span class="status-badge <?= $sent['status'] === 'sent' ? 'status-confirmed' : 'status-cancelled' ?>">
                  <?= $sent['status'] === 'sent' ? '✓ أُرسلت' : '✗ فشلت' ?> بتاريخ <?= e(date('Y/m/d H:i', strtotime($sent['created_at']))) ?>
                </span>
              <?php endif; ?>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
      <p class="field-hint" style="margin-top:12px;">فيك تعدّل نصوص القوالب من <a href="/admin/reply-templates.php">قوالب الردود</a>.</p>
    </div>
    <?php endif; ?>

    <form method="post" style="display:flex; gap:12px; align-items:end; flex-wrap:wrap; margin-top:20px;">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="update_status">
      <input type="hidden" name="id" value="<?= (int)$detail['id'] ?>">
      <div class="form-group" style="margin:0;">
        <label for="status">تغيير الحالة</label>
        <select id="status" name="status">
          <?php foreach ($statusLabels as $val => $label): ?>
            <option value="<?= e($val) ?>" <?= $detail['status'] === $val ? 'selected' : '' ?>><?= e($label) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <button type="submit" class="btn btn-primary">تحديث الحالة</button>
    </form>

    <form method="post" onsubmit="return confirm('متأكد إنك بدك تحذف هالطلب نهائيًا؟');" style="margin-top:10px;">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="delete">
      <input type="hidden" name="id" value="<?= (int)$detail['id'] ?>">
      <button type="submit" class="btn" style="color:var(--color-danger); background:none; border:1px solid var(--color-danger); padding:8px 16px; border-radius:999px; font-size:.85rem;">حذف الطلب نهائيًا</button>
    </form>

    <?php if (!empty($history)): ?>
      <h3 style="margin-top:26px;">سجل الحالة</h3>
      <table class="data-table">
        <thead><tr><th>من</th><th>إلى</th><th>بواسطة</th><th>التاريخ</th></tr></thead>
        <tbody>
          <?php foreach ($history as $h): ?>
            <tr>
              <td><?= e($h['old_status'] ?? '—') ?></td>
              <td><?= e($h['new_status']) ?></td>
              <td><?= e($h['changed_by'] ?? '—') ?></td>
              <td><?= e(date('Y/m/d H:i', strtotime($h['changed_at']))) ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </div>
<?php endif; ?>

<div class="admin-card">
  <form method="get" style="display:flex; gap:12px; flex-wrap:wrap; margin-bottom:20px;">
    <input type="text" name="q" placeholder="بحث بالاسم أو وسيلة التواصل" value="<?= e($q) ?>" style="max-width:260px;">
    <select name="status">
      <option value="">كل الحالات</option>
      <?php foreach ($statusLabels as $val => $label): ?>
        <option value="<?= e($val) ?>" <?= $statusFilter === $val ? 'selected' : '' ?>><?= e($label) ?></option>
      <?php endforeach; ?>
    </select>
    <button type="submit" class="btn btn-outline">تصفية</button>
  </form>

  <?php if (empty($list)): ?>
    <p>ما في طلبات مطابقة.</p>
  <?php else: ?>
    <table class="data-table">
      <thead><tr><th>الرقم المرجعي</th><th>الاسم</th><th>وسيلة التواصل</th><th>الخدمة</th><th>الحالة</th><th>التاريخ</th></tr></thead>
      <tbody>
        <?php foreach ($list as $b): ?>
          <tr>
            <td><a href="/admin/appointments.php?id=<?= (int)$b['id'] ?>"><?= e($b['reference_code'] ?: '#' . $b['id']) ?></a></td>
            <td><a href="/admin/appointments.php?id=<?= (int)$b['id'] ?>"><?= e($b['name']) ?></a></td>
            <td><?= e($b['contact_method']) ?>: <?= e($b['contact_value']) ?></td>
            <td><?= e($b['service_name'] ?? '—') ?></td>
            <td><span class="status-badge status-<?= e($b['status']) ?>"><?= e($statusLabels[$b['status']] ?? $b['status']) ?></span></td>
            <td><?= e(date('Y/m/d H:i', strtotime($b['created_at']))) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
