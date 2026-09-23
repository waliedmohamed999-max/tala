<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/mailer.php';
$adminUser = require_role('owner');
$activeNav = 'notifications';

$testResult = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify()) {
    $action = $_POST['action'] ?? '';

    if ($action === 'save') {
        set_setting('smtp_enabled', !empty($_POST['smtp_enabled']) ? '1' : '0');
        set_setting('smtp_host', trim($_POST['smtp_host'] ?? ''));
        set_setting('smtp_port', trim($_POST['smtp_port'] ?? '587'));
        set_setting('smtp_encryption', in_array($_POST['smtp_encryption'] ?? '', ['tls', 'ssl', 'none'], true) ? $_POST['smtp_encryption'] : 'tls');
        set_setting('smtp_username', trim($_POST['smtp_username'] ?? ''));
        set_setting('smtp_from_email', trim($_POST['smtp_from_email'] ?? ''));
        set_setting('smtp_from_name', trim($_POST['smtp_from_name'] ?? ''));
        set_setting('admin_alert_email', trim($_POST['admin_alert_email'] ?? ''));
        // Password: only overwrite if a new value was actually typed — the
        // field is always shown blank, never round-tripped in the HTML.
        $newPassword = $_POST['smtp_password'] ?? '';
        if ($newPassword !== '') {
            set_setting('smtp_password_enc', smtp_encrypt($newPassword));
        }
        flash_set('success', 'تم حفظ إعدادات البريد.');
        redirect('/admin/notifications-settings.php');
    }

    if ($action === 'test_connection') {
        $overrideConfig = smtp_config();
        // Use whatever is in the form right now, not just what's saved, so
        // the owner can test before committing.
        $overrideConfig['host'] = trim($_POST['smtp_host'] ?? '');
        $overrideConfig['port'] = (int)($_POST['smtp_port'] ?? 587);
        $overrideConfig['encryption'] = $_POST['smtp_encryption'] ?? 'tls';
        $overrideConfig['username'] = trim($_POST['smtp_username'] ?? '');
        $typedPassword = $_POST['smtp_password'] ?? '';
        $overrideConfig['password'] = $typedPassword !== '' ? $typedPassword : smtp_decrypt(get_setting('smtp_password_enc'));
        $testResult = smtp_test_connection($overrideConfig);
        $testResult['label'] = 'اختبار الاتصال';
    }

    if ($action === 'test_send') {
        $testTo = trim($_POST['test_to'] ?? '');
        if (!filter_var($testTo, FILTER_VALIDATE_EMAIL)) {
            $testResult = ['success' => false, 'error' => 'اكتب بريد إلكتروني صحيح لإرسال الاختبار إليه.', 'label' => 'إرسال تجريبي'];
        } else {
            $result = smtp_send_mail($testTo, 'رسالة اختبار من موقع تالا', "هاي رسالة اختبار للتأكد إنه إعدادات البريد شغالة صح.\n\nوقت الإرسال: " . date('Y-m-d H:i'));
            log_notification('test', $testTo, 'رسالة اختبار من موقع تالا', $result['success'], $result['error'], null, $adminUser['name']);
            $testResult = $result;
            $testResult['label'] = 'إرسال تجريبي إلى ' . $testTo;
        }
    }
}

$c = smtp_config();
$hasPassword = get_setting('smtp_password_enc') !== '';
$recentLog = db()->query('SELECT * FROM notification_log ORDER BY created_at DESC LIMIT 20')->fetchAll();

$pageTitle = 'إعدادات الإشعارات والبريد';
require __DIR__ . '/includes/layout_top.php';
?>

<div class="notice-banner" style="margin-bottom:24px;">
  <strong>الحالة: <?= mailer_is_configured() ? '🟢 البريد مربوط ومفعّل' : '🔴 البريد غير مربوط' ?></strong>
  <?php if (!mailer_is_configured()): ?>
    لحد ما تعبّي بيانات السيرفر تحت وتختبريها بنجاح، ما رح يُرسل أي بريد فعلي — بس النسخ اليدوي للقوالب رح يضل متاح كالعادة.
  <?php endif; ?>
</div>

<?php if ($testResult): ?>
  <div class="alert <?= $testResult['success'] ? 'alert-success' : 'alert-error' ?>">
    <?= e($testResult['label']) ?>: <?= $testResult['success'] ? 'نجح ✓' : 'فشل — ' . e($testResult['error']) ?>
  </div>
<?php endif; ?>

<form method="post" class="admin-card">
  <?= csrf_field() ?>

  <div class="form-group">
    <label><input type="checkbox" name="smtp_enabled" value="1" <?= $c['enabled'] ? 'checked' : '' ?> style="width:auto;"> فعّل إرسال البريد الفعلي</label>
    <p class="field-hint">حتى لو مفعّل، الرسائل الموجّهة للزائر (استلام/تأكيد/إلخ) ما بترسل إلا بضغطة يدوية من صفحة الطلب.</p>
  </div>

  <div class="form-row-2">
    <div class="form-group"><label>سيرفر SMTP (Host)</label><input type="text" name="smtp_host" value="<?= e($c['host']) ?>" placeholder="smtp.example.com"></div>
    <div class="form-group"><label>المنفذ (Port)</label><input type="text" name="smtp_port" value="<?= e((string)$c['port']) ?>" placeholder="587"></div>
  </div>
  <div class="form-row-2">
    <div class="form-group">
      <label>نوع التشفير</label>
      <select name="smtp_encryption">
        <option value="tls" <?= $c['encryption'] === 'tls' ? 'selected' : '' ?>>STARTTLS (الأكثر شيوعًا، منفذ 587)</option>
        <option value="ssl" <?= $c['encryption'] === 'ssl' ? 'selected' : '' ?>>SSL/TLS مباشر (منفذ 465)</option>
        <option value="none" <?= $c['encryption'] === 'none' ? 'selected' : '' ?>>بدون تشفير (غير مستحسن)</option>
      </select>
    </div>
    <div class="form-group"><label>اسم المستخدم</label><input type="text" name="smtp_username" value="<?= e($c['username']) ?>" autocomplete="off"></div>
  </div>
  <div class="form-group">
    <label>كلمة المرور <?= $hasPassword ? '(محفوظة — اكتب قيمة جديدة بس إذا بدك تغييرها)' : '' ?></label>
    <input type="password" name="smtp_password" autocomplete="new-password" placeholder="<?= $hasPassword ? '••••••••' : '' ?>">
  </div>
  <div class="form-row-2">
    <div class="form-group"><label>البريد المُرسِل (From)</label><input type="email" name="smtp_from_email" value="<?= e($c['from_email']) ?>"></div>
    <div class="form-group"><label>الاسم المعروض للمُرسِل</label><input type="text" name="smtp_from_name" value="<?= e($c['from_name']) ?>"></div>
  </div>
  <div class="form-group">
    <label>بريد التنبيهات الداخلية (لطلبات المواعيد الجديدة)</label>
    <input type="email" name="admin_alert_email" value="<?= e(get_setting('admin_alert_email')) ?>">
    <p class="field-hint">هاد البريد بيوصله تنبيه مختصر بدون تفاصيل شخصية كل ما وصل طلب موعد جديد — التفاصيل الكاملة تُفتح من لوحة الإدارة بعد تسجيل الدخول.</p>
  </div>

  <div style="display:flex; gap:12px; flex-wrap:wrap; margin-top:10px;">
    <button type="submit" name="action" value="save" class="btn btn-primary">حفظ الإعدادات</button>
    <button type="submit" name="action" value="test_connection" class="btn btn-outline">اختبار الاتصال (بدون إرسال)</button>
  </div>
</form>

<div class="admin-card" style="margin-top:20px;">
  <h3>إرسال بريد تجريبي</h3>
  <form method="post" style="display:flex; gap:10px; flex-wrap:wrap; align-items:end;">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="test_send">
    <div class="form-group" style="margin:0; flex:1; min-width:220px;">
      <label>أرسل رسالة اختبار إلى</label>
      <input type="email" name="test_to" placeholder="you@example.com" required>
    </div>
    <button type="submit" class="btn btn-outline">إرسال الآن</button>
  </form>
  <p class="field-hint">هاد بيستخدم الإعدادات المحفوظة حاليًا — احفظ التغييرات فوق أولًا لو عدّلتي شي.</p>
</div>

<div class="admin-card" style="margin-top:20px;">
  <h3>سجل الإرسال (آخر ٢٠)</h3>
  <?php if (empty($recentLog)): ?>
    <p>ما في محاولات إرسال بعد.</p>
  <?php else: ?>
    <table class="data-table">
      <thead><tr><th>النوع</th><th>المستلم</th><th>الموضوع</th><th>الحالة</th><th>التاريخ</th></tr></thead>
      <tbody>
        <?php foreach ($recentLog as $l): ?>
          <tr>
            <td><?= e($l['type']) ?></td>
            <td><?= e($l['recipient']) ?></td>
            <td><?= e($l['subject']) ?></td>
            <td>
              <span class="status-badge <?= $l['status'] === 'sent' ? 'status-confirmed' : 'status-cancelled' ?>"><?= $l['status'] === 'sent' ? 'تم الإرسال' : 'فشل' ?></span>
              <?php if ($l['error_message']): ?><div class="field-hint"><?= e($l['error_message']) ?></div><?php endif; ?>
            </td>
            <td><?= e(date('Y/m/d H:i', strtotime($l['created_at']))) ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
