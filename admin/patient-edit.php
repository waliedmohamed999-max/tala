<?php
require_once __DIR__ . '/../includes/auth.php';
$adminUser = require_clinic_ops_access();
$activeNav = 'patients';

$id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$patient = ['id' => null, 'full_name' => '', 'contact_method' => 'phone', 'contact_value' => '', 'contact_preference' => ''];
if ($id) {
    $stmt = db()->prepare('SELECT * FROM patients WHERE id = ?');
    $stmt->execute([$id]);
    $found = $stmt->fetch();
    if (!$found) { flash_set('error', 'ملف المريض غير موجود.'); redirect('/admin/patients.php'); }
    $patient = $found;
    log_access('view', 'patient', $id, $adminUser);
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) { $errors[] = 'في مشكلة تقنية. جرّب من جديد.'; }
    $patient['full_name'] = trim($_POST['full_name'] ?? '');
    $patient['contact_method'] = in_array($_POST['contact_method'] ?? '', ['phone', 'whatsapp', 'email'], true) ? $_POST['contact_method'] : 'phone';
    $patient['contact_value'] = trim($_POST['contact_value'] ?? '');
    $patient['contact_preference'] = trim($_POST['contact_preference'] ?? '') ?: null;

    if ($patient['full_name'] === '') { $errors[] = 'الاسم مطلوب.'; }
    if ($patient['contact_value'] === '') { $errors[] = 'بيانات التواصل مطلوبة.'; }

    if (empty($errors)) {
        if ($id) {
            $upd = db()->prepare('UPDATE patients SET full_name=?, contact_method=?, contact_value=?, contact_preference=? WHERE id=?');
            $upd->execute([$patient['full_name'], $patient['contact_method'], $patient['contact_value'], $patient['contact_preference'], $id]);
            log_access('edit', 'patient', $id, $adminUser);
            flash_set('success', 'تم حفظ ملف المريض.');
            redirect('/admin/patient-view.php?id=' . $id);
        } else {
            $newId = create_patient($patient, $adminUser['name']);
            flash_set('success', 'تم إنشاء ملف المريض.');
            redirect('/admin/patient-view.php?id=' . $newId);
        }
    }
}

$pageTitle = $id ? 'تعديل ملف مريض' : 'ملف مريض جديد';
require __DIR__ . '/includes/layout_top.php';
?>

<div class="admin-page-head"><h1><?= $id ? 'تعديل ملف مريض' : 'ملف مريض جديد' ?></h1></div>

<?php foreach ($errors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>

<div class="admin-card">
  <form method="post" class="form-narrow">
    <?= csrf_field() ?>
    <div class="form-group"><label>الاسم الكامل *</label><input type="text" name="full_name" required value="<?= e($patient['full_name']) ?>"></div>
    <div class="form-group">
      <label>وسيلة التواصل المفضلة *</label>
      <div class="radio-group">
        <label class="radio-pill"><input type="radio" name="contact_method" value="phone" <?= $patient['contact_method'] === 'phone' ? 'checked' : '' ?>> هاتف</label>
        <label class="radio-pill"><input type="radio" name="contact_method" value="whatsapp" <?= $patient['contact_method'] === 'whatsapp' ? 'checked' : '' ?>> واتساب</label>
        <label class="radio-pill"><input type="radio" name="contact_method" value="email" <?= $patient['contact_method'] === 'email' ? 'checked' : '' ?>> بريد إلكتروني</label>
      </div>
    </div>
    <div class="form-group"><label>بيانات التواصل *</label><input type="text" name="contact_value" required value="<?= e($patient['contact_value']) ?>"></div>
    <div class="form-group"><label>أوقات مناسبة للتواصل (اختياري)</label><input type="text" name="contact_preference" value="<?= e((string)$patient['contact_preference']) ?>"></div>
    <button type="submit" class="btn btn-primary">حفظ</button>
  </form>
</div>

<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
