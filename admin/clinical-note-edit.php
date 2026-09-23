<?php
require_once __DIR__ . '/../includes/auth.php';
$adminUser = require_clinical_access();
$activeNav = 'patients';

if (!clinical_module_enabled()) {
    $pageTitle = 'الوحدة غير مفعّلة';
    require __DIR__ . '/includes/layout_top.php';
    ?>
    <div class="admin-page-head"><h1>الملاحظات السريرية غير مفعّلة</h1></div>
    <div class="notice-banner">
      وحدة الملاحظات السريرية معطّلة حاليًا. <?= $adminUser['role'] === 'owner' ? 'فعّلها من <a href="/admin/clinical-settings.php">إعدادات الملاحظات السريرية</a> بعد تعبئة المتطلبات.' : 'تواصل مع مالك الموقع لتفعيلها.' ?>
    </div>
    <?php require __DIR__ . '/includes/layout_bottom.php';
    exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$note = null;
if ($id) {
    $stmt = db()->prepare('SELECT * FROM clinical_notes WHERE id = ?');
    $stmt->execute([$id]);
    $note = $stmt->fetch();
    if (!$note) { flash_set('error', 'الملاحظة غير موجودة.'); redirect('/admin/patients.php'); }
    $patientId = (int)$note['patient_id'];
} else {
    $patientId = (int)($_GET['patient_id'] ?? 0);
}
$pStmt = db()->prepare('SELECT * FROM patients WHERE id = ?');
$pStmt->execute([$patientId]);
$patient = $pStmt->fetch();
if (!$patient) { flash_set('error', 'ملف المريض غير موجود.'); redirect('/admin/patients.php'); }

log_access('view', 'clinical_note', $id ?: $patientId, $adminUser);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify() && ($_POST['action'] ?? '') === 'upload_attachment' && $id) {
    if (!empty($_FILES['attachment']['name']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['application/pdf' => 'pdf', 'image/jpeg' => 'jpg', 'image/png' => 'png'];
        $mime = mime_content_type($_FILES['attachment']['tmp_name']);
        if (isset($allowed[$mime]) && $_FILES['attachment']['size'] <= 10 * 1024 * 1024) {
            $dir = DATA_DIR . '/clinical_uploads';
            if (!is_dir($dir)) { mkdir($dir, 0775, true); }
            $filename = 'note' . $id . '-' . bin2hex(random_bytes(6)) . '.' . $allowed[$mime];
            move_uploaded_file($_FILES['attachment']['tmp_name'], $dir . '/' . $filename);
            $ins = db()->prepare('INSERT INTO clinical_note_attachments (note_id, file_path, original_name, mime_type, size_bytes, uploaded_by) VALUES (?,?,?,?,?,?)');
            $ins->execute([$id, $filename, $_FILES['attachment']['name'], $mime, $_FILES['attachment']['size'], $adminUser['name']]);
            log_access('edit', 'clinical_note', $id, $adminUser);
            flash_set('success', 'تم رفع المرفق.');
        } else {
            flash_set('error', 'صيغة الملف أو حجمه غير مدعوم (PDF/JPG/PNG، أقل من 10MB).');
        }
    }
    redirect('/admin/clinical-note-edit.php?id=' . $id);
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? 'save') === 'save') {
    if (!csrf_verify()) { $errors[] = 'في مشكلة تقنية. جرّب من جديد.'; }
    $content = trim($_POST['content'] ?? '');
    if ($content === '') { $errors[] = 'ما فيك تحفظ ملاحظة فاضية.'; }
    if ($note && $note['locked_at'] && empty($_POST['confirm_correction'])) {
        $errors[] = 'هاي الملاحظة مقفلة — أكّد إنك عارف/ة إنه هالتعديل رح يُسجّل كتصحيح موثّق.';
    }

    if (empty($errors)) {
        if ($id) {
            create_clinical_note_revision($id, $note['content'], (int)$adminUser['id'], $adminUser['name']);
            db()->prepare("UPDATE clinical_notes SET content=?, updated_at=datetime('now') WHERE id=?")->execute([$content, $id]);
            log_access('edit', 'clinical_note', $id, $adminUser);
            flash_set('success', 'تم حفظ التعديل كتصحيح موثّق (النسخة السابقة محفوظة بالسجل).');
            redirect('/admin/clinical-note-edit.php?id=' . $id);
        } else {
            $ins = db()->prepare('INSERT INTO clinical_notes (patient_id, appointment_id, author_id, author_name, content) VALUES (?,?,?,?,?)');
            $ins->execute([$patientId, $_POST['appointment_id'] ?: null, $adminUser['id'], $adminUser['name'], $content]);
            $newId = (int)db()->lastInsertId();
            create_clinical_note_revision($newId, $content, (int)$adminUser['id'], $adminUser['name']);
            log_access('edit', 'clinical_note', $newId, $adminUser);
            flash_set('success', 'تم حفظ الملاحظة.');
            redirect('/admin/clinical-note-edit.php?id=' . $newId);
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify() && ($_POST['action'] ?? '') === 'lock' && $id) {
    db()->prepare("UPDATE clinical_notes SET locked_at=datetime('now'), locked_by=? WHERE id=?")->execute([$adminUser['name'], $id]);
    log_access('edit', 'clinical_note', $id, $adminUser);
    flash_set('success', 'تم قفل الملاحظة. أي تعديل لاحق رح يُسجّل كتصحيح موثّق.');
    redirect('/admin/clinical-note-edit.php?id=' . $id);
}

$appointments = db()->prepare('SELECT id, starts_at FROM appointments WHERE patient_id = ? ORDER BY starts_at DESC');
$appointments->execute([$patientId]);
$appointments = $appointments->fetchAll();

$revisions = [];
$attachments = [];
if ($id) {
    $rev = db()->prepare('SELECT * FROM clinical_note_revisions WHERE note_id = ? ORDER BY created_at DESC');
    $rev->execute([$id]);
    $revisions = $rev->fetchAll();
    $att = db()->prepare('SELECT * FROM clinical_note_attachments WHERE note_id = ? ORDER BY created_at DESC');
    $att->execute([$id]);
    $attachments = $att->fetchAll();
}

$pageTitle = $id ? 'ملاحظة سريرية' : 'ملاحظة جديدة';
require __DIR__ . '/includes/layout_top.php';
?>

<div class="admin-page-head">
  <h1><?= $id ? 'ملاحظة سريرية' : 'ملاحظة جديدة' ?> — <?= e($patient['full_name']) ?></h1>
  <a href="/admin/patient-view.php?id=<?= (int)$patientId ?>" class="btn btn-outline">رجوع لملف المريض</a>
</div>

<div class="notice-inline" style="margin-bottom:20px;">🔒 هالصفحة مقتصرة على تالا/المراجع المهني ومالك الموقع فقط. كل اطلاع أو تعديل مُسجَّل بسجل التدقيق.</div>

<?php foreach ($errors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>

<?php if ($note && $note['locked_at']): ?>
  <div class="notice-banner" style="margin-bottom:20px;">🔒 هاي الملاحظة مقفلة (بتاريخ <?= e(date('Y/m/d', strtotime($note['locked_at']))) ?> بواسطة <?= e($note['locked_by']) ?>). أي حفظ إضافي رح يُسجّل كتصحيح موثّق مع الوقت والكاتب، مش تعديل صامت.</div>
<?php endif; ?>

<div class="admin-card" style="margin-bottom:20px;">
  <form method="post" class="form-narrow">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="save">
    <?php if (!$id): ?>
    <div class="form-group">
      <label>مرتبطة بموعد (اختياري)</label>
      <select name="appointment_id">
        <option value="">بدون ربط بموعد محدد</option>
        <?php foreach ($appointments as $a): ?>
          <option value="<?= (int)$a['id'] ?>"><?= e(date('Y/m/d H:i', strtotime($a['starts_at']))) ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <?php endif; ?>
    <div class="form-group">
      <label>نص الملاحظة</label>
      <textarea name="content" rows="12"><?= e($note['content'] ?? '') ?></textarea>
    </div>
    <?php if ($note && $note['locked_at']): ?>
      <div class="form-group"><label><input type="checkbox" name="confirm_correction" value="1" style="width:auto;"> فهمت إنه هالحفظ رح يُسجّل كتصحيح موثّق على ملاحظة مقفلة</label></div>
    <?php endif; ?>
    <button type="submit" class="btn btn-primary">حفظ</button>
  </form>

  <?php if ($id && !$note['locked_at']): ?>
    <form method="post" style="margin-top:14px;" onsubmit="return confirm('قفل هالملاحظة؟ أي تعديل بعدين رح يُسجّل كتصحيح موثّق.');">
      <?= csrf_field() ?><input type="hidden" name="action" value="lock">
      <button type="submit" class="btn btn-outline btn-sm">🔒 قفل الملاحظة</button>
    </form>
  <?php endif; ?>
</div>

<?php if ($id): ?>
<div class="admin-card" style="margin-bottom:20px;">
  <h3 style="margin-top:0;">المرفقات</h3>
  <?php if (!empty($attachments)): ?>
    <ul>
      <?php foreach ($attachments as $a): ?>
        <li><a href="/admin/clinical-file.php?id=<?= (int)$a['id'] ?>"><?= e($a['original_name'] ?: $a['file_path']) ?></a> — <?= e(date('Y/m/d', strtotime($a['created_at']))) ?></li>
      <?php endforeach; ?>
    </ul>
  <?php else: ?>
    <p class="field-hint">ما في مرفقات بعد.</p>
  <?php endif; ?>
  <form method="post" enctype="multipart/form-data" style="margin-top:10px; display:flex; gap:10px; align-items:end;">
    <?= csrf_field() ?><input type="hidden" name="action" value="upload_attachment">
    <div class="form-group" style="margin:0;"><label>ملف جديد (PDF/JPG/PNG، أقل من 10MB)</label><input type="file" name="attachment" required></div>
    <button type="submit" class="btn btn-outline btn-sm">رفع</button>
  </form>
</div>

<?php if (!empty($revisions)): ?>
<div class="admin-card">
  <h3 style="margin-top:0;">النسخ السابقة (سجل دائم، غير قابل للتعديل)</h3>
  <?php foreach ($revisions as $r): ?>
    <details style="margin-bottom:10px;">
      <summary style="cursor:pointer;"><?= e(date('Y/m/d H:i', strtotime($r['created_at']))) ?> — <?= e($r['editor_name']) ?></summary>
      <p style="white-space:pre-wrap; background:var(--color-bg-alt); padding:10px; border-radius:6px; margin-top:8px;"><?= e($r['content']) ?></p>
    </details>
  <?php endforeach; ?>
</div>
<?php endif; ?>
<?php endif; ?>

<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
