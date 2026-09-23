<?php
require_once __DIR__ . '/../includes/auth.php';
require_clinic_ops_access();
$activeNav = 'clinic-hours';

$weekdays = ['الأحد', 'الاثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت'];
$services = db()->query("SELECT id, name FROM services WHERE workflow_status = 'published' ORDER BY sort_order, name")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify()) {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_hours') {
        $weekday = (int)($_POST['weekday'] ?? -1);
        $start = trim($_POST['start_time'] ?? '');
        $end = trim($_POST['end_time'] ?? '');
        $serviceId = $_POST['service_id'] !== '' ? (int)$_POST['service_id'] : null;
        $locationMode = in_array($_POST['location_mode'] ?? '', ['in_person', 'remote'], true) ? $_POST['location_mode'] : null;

        if ($weekday < 0 || $weekday > 6 || !preg_match('/^\d{2}:\d{2}$/', $start) || !preg_match('/^\d{2}:\d{2}$/', $end) || $start >= $end) {
            flash_set('error', 'تحقق من اليوم ووقتي البداية والنهاية (النهاية لازم تكون بعد البداية).');
        } else {
            $ins = db()->prepare('INSERT INTO clinic_hours (provider_id, weekday, start_time, end_time, service_id, location_mode) VALUES (1, ?, ?, ?, ?, ?)');
            $ins->execute([$weekday, $start, $end, $serviceId, $locationMode]);
            flash_set('success', 'تمت إضافة فترة العمل.');
        }
        redirect('/admin/clinic-hours.php');
    }

    if ($action === 'delete_hours') {
        db()->prepare('DELETE FROM clinic_hours WHERE id = ?')->execute([(int)($_POST['id'] ?? 0)]);
        flash_set('success', 'تم حذف الفترة.');
        redirect('/admin/clinic-hours.php');
    }

    if ($action === 'toggle_hours') {
        db()->prepare('UPDATE clinic_hours SET is_active = 1 - is_active WHERE id = ?')->execute([(int)($_POST['id'] ?? 0)]);
        redirect('/admin/clinic-hours.php');
    }

    if ($action === 'add_closure') {
        $from = trim($_POST['date_from'] ?? '');
        $to = trim($_POST['date_to'] ?? '');
        $reason = trim($_POST['reason'] ?? '');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $to) || $from > $to) {
            flash_set('error', 'تحقق من التواريخ.');
        } else {
            $adminUser = current_admin();
            $ins = db()->prepare('INSERT INTO clinic_closures (provider_id, date_from, date_to, reason, created_by) VALUES (1, ?, ?, ?, ?)');
            $ins->execute([$from, $to, $reason ?: null, $adminUser['name']]);
            flash_set('success', 'تمت إضافة فترة الإغلاق.');
        }
        redirect('/admin/clinic-hours.php');
    }

    if ($action === 'delete_closure') {
        db()->prepare('DELETE FROM clinic_closures WHERE id = ?')->execute([(int)($_POST['id'] ?? 0)]);
        flash_set('success', 'تم حذف فترة الإغلاق.');
        redirect('/admin/clinic-hours.php');
    }
}

$hours = db()->query('SELECT h.*, s.name AS service_name FROM clinic_hours h LEFT JOIN services s ON s.id = h.service_id ORDER BY h.weekday, h.start_time')->fetchAll();
$closures = db()->query("SELECT * FROM clinic_closures WHERE date_to >= date('now') ORDER BY date_from")->fetchAll();

$pageTitle = 'ساعات العمل';
require __DIR__ . '/includes/layout_top.php';
?>

<div class="admin-page-head"><h1>ساعات العمل والإغلاقات</h1></div>

<div class="notice-inline" style="margin-bottom:24px;">
  الفترات "العامة" (بدون خدمة محددة) تطبّق على كل خدمة ما إلها فترات خاصة فيها. لو ضفت فترة لخدمة معينة، رح تحل محل الفترات العامة لتلك الخدمة بالكامل بذلك اليوم. المنطقة الزمنية: <?= e(get_setting('clinic_timezone_display', 'Asia/Damascus')) ?>.
</div>

<div class="admin-card" style="margin-bottom:24px;">
  <h2 style="margin-top:0;">إضافة فترة عمل أسبوعية</h2>
  <form method="post" style="display:flex; gap:12px; flex-wrap:wrap; align-items:end;">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="add_hours">
    <div class="form-group" style="margin:0;">
      <label>اليوم</label>
      <select name="weekday">
        <?php foreach ($weekdays as $i => $w): ?><option value="<?= $i ?>"><?= e($w) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="form-group" style="margin:0;"><label>من الساعة</label><input type="time" name="start_time" required></div>
    <div class="form-group" style="margin:0;"><label>لحد الساعة</label><input type="time" name="end_time" required></div>
    <div class="form-group" style="margin:0;">
      <label>خاصة بخدمة (اختياري)</label>
      <select name="service_id">
        <option value="">عامة (كل الخدمات)</option>
        <?php foreach ($services as $s): ?><option value="<?= (int)$s['id'] ?>"><?= e($s['name']) ?></option><?php endforeach; ?>
      </select>
    </div>
    <div class="form-group" style="margin:0;">
      <label>طريقة الحضور</label>
      <select name="location_mode">
        <option value="">كله</option>
        <option value="in_person">حضوري فقط</option>
        <option value="remote">عن بُعد فقط</option>
      </select>
    </div>
    <button type="submit" class="btn btn-primary">إضافة</button>
  </form>
</div>

<div class="admin-card" style="margin-bottom:24px;">
  <h2 style="margin-top:0;">الفترات الحالية</h2>
  <?php if (empty($hours)): ?>
    <p class="field-hint">ما في ساعات عمل مضبوطة بعد — الموقع رح يستقبل طلبات مواعيد فقط (بدون حجز ذاتي مباشر) لحد ما تضيف فترات هون.</p>
  <?php else: ?>
    <table class="data-table">
      <thead><tr><th>اليوم</th><th>من</th><th>لحد</th><th>الخدمة</th><th>طريقة الحضور</th><th>مفعّلة</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($hours as $h): ?>
          <tr>
            <td><?= e($weekdays[(int)$h['weekday']]) ?></td>
            <td><?= e($h['start_time']) ?></td>
            <td><?= e($h['end_time']) ?></td>
            <td><?= e($h['service_name'] ?? 'عامة') ?></td>
            <td><?= ['in_person' => 'حضوري فقط', 'remote' => 'عن بُعد فقط'][$h['location_mode']] ?? 'كله' ?></td>
            <td><span class="status-badge <?= $h['is_active'] ? 'status-confirmed' : 'status-cancelled' ?>"><?= $h['is_active'] ? 'نعم' : 'لا' ?></span></td>
            <td class="action-links">
              <form method="post" style="display:inline;"><?= csrf_field() ?><input type="hidden" name="action" value="toggle_hours"><input type="hidden" name="id" value="<?= (int)$h['id'] ?>"><button type="submit"><?= $h['is_active'] ? 'إيقاف' : 'تفعيل' ?></button></form>
              <form method="post" style="display:inline;" onsubmit="return confirm('حذف هالفترة؟');"><?= csrf_field() ?><input type="hidden" name="action" value="delete_hours"><input type="hidden" name="id" value="<?= (int)$h['id'] ?>"><button type="submit" class="danger">حذف</button></form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<div class="admin-card" style="margin-bottom:24px;">
  <h2 style="margin-top:0;">إضافة إغلاق/إجازة</h2>
  <form method="post" style="display:flex; gap:12px; flex-wrap:wrap; align-items:end;">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="add_closure">
    <div class="form-group" style="margin:0;"><label>من تاريخ</label><input type="date" name="date_from" required></div>
    <div class="form-group" style="margin:0;"><label>لحد تاريخ</label><input type="date" name="date_to" required></div>
    <div class="form-group" style="margin:0;"><label>السبب (اختياري، داخلي)</label><input type="text" name="reason"></div>
    <button type="submit" class="btn btn-primary">إضافة إغلاق</button>
  </form>
</div>

<div class="admin-card">
  <h2 style="margin-top:0;">الإغلاقات القادمة</h2>
  <?php if (empty($closures)): ?>
    <p class="field-hint">ما في إغلاقات مسجّلة قادمة.</p>
  <?php else: ?>
    <table class="data-table">
      <thead><tr><th>من</th><th>لحد</th><th>السبب</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($closures as $c): ?>
          <tr>
            <td><?= e($c['date_from']) ?></td>
            <td><?= e($c['date_to']) ?></td>
            <td><?= e($c['reason'] ?: '—') ?></td>
            <td class="action-links">
              <form method="post" onsubmit="return confirm('حذف هالإغلاق؟');"><?= csrf_field() ?><input type="hidden" name="action" value="delete_closure"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><button type="submit" class="danger">حذف</button></form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
