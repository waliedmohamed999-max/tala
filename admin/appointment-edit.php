<?php
require_once __DIR__ . '/../includes/auth.php';
$adminUser = require_clinic_ops_access();
$activeNav = 'calendar';

$statusLabels = [
    'pending_confirmation' => 'بانتظار التأكيد',
    'confirmed' => 'مؤكد',
    'rescheduled' => 'أُعيدت جدولته',
    'cancelled' => 'أُلغي',
    'no_show' => 'لم يحضر',
    'completed' => 'انعقد',
];

$id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$appt = null;
if ($id) {
    $stmt = db()->prepare('SELECT * FROM appointments WHERE id = ?');
    $stmt->execute([$id]);
    $appt = $stmt->fetch();
    if (!$appt) { flash_set('error', 'الموعد غير موجود.'); redirect('/admin/calendar.php'); }
}

// Prefill from a booking request (?request_id=) or a fixed date (?date=)
$requestId = isset($_GET['request_id']) ? (int)$_GET['request_id'] : null;
$request = null;
if ($requestId) {
    $rs = db()->prepare('SELECT * FROM appointment_requests WHERE id = ?');
    $rs->execute([$requestId]);
    $request = $rs->fetch();
}

$services = db()->query("SELECT * FROM services WHERE workflow_status = 'published' ORDER BY sort_order, name")->fetchAll();
$patients = db()->query("SELECT id, file_number, full_name FROM patients WHERE status = 'active' ORDER BY full_name")->fetchAll();

$defaultDate = $_GET['date'] ?? date('Y-m-d');
$form = [
    'service_id' => $appt['service_id'] ?? ($request['service_id'] ?? ''),
    'patient_id' => $appt['patient_id'] ?? '',
    'date' => $appt ? substr($appt['starts_at'], 0, 10) : $defaultDate,
    'time' => $appt ? substr($appt['starts_at'], 11, 5) : '',
    'duration_minutes' => 50,
    'location_mode' => $appt['location_mode'] ?? '',
    'status' => $appt['status'] ?? 'pending_confirmation',
    'meeting_link' => $appt['meeting_link'] ?? '',
    'operational_notes' => $appt['operational_notes'] ?? '',
    'cancel_reason' => $appt['cancel_reason'] ?? '',
];

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $errors[] = 'في مشكلة تقنية. جرّب من جديد.';
    }
    $form['service_id'] = $_POST['service_id'] !== '' ? (int)$_POST['service_id'] : null;
    $form['patient_id'] = $_POST['patient_id'] !== '' ? (int)$_POST['patient_id'] : null;
    $form['date'] = trim($_POST['date'] ?? '');
    $form['time'] = trim($_POST['time'] ?? '');
    $form['duration_minutes'] = max(5, (int)($_POST['duration_minutes'] ?? 50));
    $form['location_mode'] = in_array($_POST['location_mode'] ?? '', ['in_person', 'remote'], true) ? $_POST['location_mode'] : null;
    $form['status'] = array_key_exists($_POST['status'] ?? '', $statusLabels) ? $_POST['status'] : 'pending_confirmation';
    $form['meeting_link'] = trim($_POST['meeting_link'] ?? '') ?: null;
    $form['operational_notes'] = trim($_POST['operational_notes'] ?? '') ?: null;
    $form['cancel_reason'] = trim($_POST['cancel_reason'] ?? '') ?: null;

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $form['date']) || !preg_match('/^\d{2}:\d{2}$/', $form['time'])) {
        $errors[] = 'تحقق من التاريخ والوقت.';
    }

    if (empty($errors)) {
        $startsAt = $form['date'] . ' ' . $form['time'] . ':00';
        $endsAt = date('Y-m-d H:i:s', strtotime($startsAt) + ($form['duration_minutes'] * 60));

        if ($id) {
            if ($startsAt !== $appt['starts_at'] || $endsAt !== $appt['ends_at']) {
                $result = reschedule_appointment_atomically($id, $startsAt, $endsAt, $adminUser['name']);
                if (!$result['success']) {
                    $errors[] = $result['error'];
                }
            }
            if (empty($errors)) {
                if ($form['status'] === 'cancelled' && $appt['status'] !== 'cancelled') {
                    cancel_appointment($id, $form['cancel_reason'] ?? 'بدون سبب مُدخل', $adminUser['name']);
                } else {
                    $upd = db()->prepare("UPDATE appointments SET service_id=?, patient_id=?, location_mode=?, status=?, meeting_link=?, operational_notes=?, updated_by=?, updated_at=datetime('now') WHERE id=?");
                    $upd->execute([$form['service_id'], $form['patient_id'], $form['location_mode'], $form['status'], $form['meeting_link'], $form['operational_notes'], $adminUser['name'], $id]);
                }
                flash_set('success', 'تم حفظ الموعد.');
                redirect('/admin/calendar.php?date=' . $form['date']);
            }
        } else {
            $result = create_appointment_atomically([
                'service_id' => $form['service_id'],
                'patient_id' => $form['patient_id'],
                'request_id' => $requestId,
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'location_mode' => $form['location_mode'],
                'status' => $form['status'],
                'meeting_link' => $form['meeting_link'],
                'operational_notes' => $form['operational_notes'],
                'created_by' => $adminUser['name'],
            ]);
            if (!$result['success']) {
                $errors[] = $result['error'];
            } else {
                if ($requestId) {
                    db()->prepare('UPDATE appointment_requests SET converted_appointment_id = ? WHERE id = ?')->execute([$result['appointment_id'], $requestId]);
                }
                flash_set('success', 'تم إنشاء الموعد.');
                redirect('/admin/calendar.php?date=' . $form['date']);
            }
        }
    }
}

$pageTitle = $id ? 'تعديل موعد' : 'موعد جديد / حظر فترة';
require __DIR__ . '/includes/layout_top.php';
?>

<?php foreach ($errors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>

<?php if ($request): ?>
  <div class="notice-inline" style="margin-bottom:20px;">
    مربوط بطلب: <strong><?= e($request['name']) ?></strong> (<?= e($request['reference_code'] ?: '#' . $request['id']) ?>) — <?= e($request['contact_method']) ?>: <?= e($request['contact_value']) ?>
    <?php if ($request['time_pref']): ?><br>الوقت المفضل المذكور بالطلب: <?= e($request['time_pref']) ?><?php endif; ?>
  </div>
<?php endif; ?>

<div class="admin-card">
  <form method="post" class="form-narrow">
    <?= csrf_field() ?>
    <div class="form-group">
      <label>الخدمة (اختياري — اتركه فاضي لحظر فترة بدون خدمة)</label>
      <select name="service_id" id="serviceSelect">
        <option value="">— بدون خدمة (حظر فترة) —</option>
        <?php foreach ($services as $s): ?>
          <option value="<?= (int)$s['id'] ?>" data-duration="<?= (int)($s['duration_minutes'] ?: 50) ?>" <?= (string)$form['service_id'] === (string)$s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group">
      <label>المريض (اختياري)</label>
      <select name="patient_id">
        <option value="">— بدون مريض مربوط —</option>
        <?php foreach ($patients as $p): ?>
          <option value="<?= (int)$p['id'] ?>" <?= (string)$form['patient_id'] === (string)$p['id'] ? 'selected' : '' ?>><?= e($p['full_name']) ?> (<?= e($p['file_number']) ?>)</option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-row-2">
      <div class="form-group"><label>التاريخ</label><input type="date" name="date" required value="<?= e($form['date']) ?>"></div>
      <div class="form-group"><label>الوقت</label><input type="time" name="time" required value="<?= e($form['time']) ?>"></div>
    </div>
    <div class="form-row-2">
      <div class="form-group"><label>المدة (دقائق)</label><input type="number" name="duration_minutes" id="durationField" min="5" step="5" value="<?= (int)$form['duration_minutes'] ?>"></div>
      <div class="form-group">
        <label>طريقة الحضور</label>
        <select name="location_mode">
          <option value="">غير محدد</option>
          <option value="in_person" <?= $form['location_mode'] === 'in_person' ? 'selected' : '' ?>>حضوري</option>
          <option value="remote" <?= $form['location_mode'] === 'remote' ? 'selected' : '' ?>>عن بُعد</option>
        </select>
      </div>
    </div>
    <?php if ($id): ?>
    <div class="form-group">
      <label>الحالة</label>
      <select name="status">
        <?php foreach ($statusLabels as $val => $label): ?>
          <option value="<?= e($val) ?>" <?= $form['status'] === $val ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group"><label>سبب الإلغاء (إذا الحالة "أُلغي")</label><input type="text" name="cancel_reason" value="<?= e((string)$form['cancel_reason']) ?>"></div>
    <?php endif; ?>
    <div class="form-group"><label>رابط الاجتماع (يدوي، لو جلسة عن بُعد)</label><input type="text" name="meeting_link" value="<?= e((string)$form['meeting_link']) ?>"></div>
    <div class="form-group">
      <label>ملاحظات تشغيلية (إدارية فقط)</label>
      <textarea name="operational_notes" rows="3"><?= e((string)$form['operational_notes']) ?></textarea>
      <p class="field-hint">⚠️ ما تكتب هون أي محتوى علاجي أو سريري — هاي الملاحظات إدارية بحتة (مثال: "بدها تأكيد بالواتساب"). الملاحظات السريرية إلها قسم منفصل ومحمي.</p>
    </div>
    <button type="submit" class="btn btn-primary"><?= $id ? 'حفظ التعديلات' : 'إنشاء الموعد' ?></button>
  </form>
</div>

<script>
document.getElementById('serviceSelect').addEventListener('change', function () {
  var opt = this.options[this.selectedIndex];
  var dur = opt.getAttribute('data-duration');
  if (dur) { document.getElementById('durationField').value = dur; }
});
</script>

<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
