<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('owner');
$activeNav = 'clinical-settings';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify()) {
    set_setting('clinical_notes_hosting_note', trim($_POST['clinical_notes_hosting_note'] ?? ''));
    set_setting('clinical_notes_retention_days', trim($_POST['clinical_notes_retention_days'] ?? ''));
    set_setting('clinical_notes_backup_plan', trim($_POST['clinical_notes_backup_plan'] ?? ''));
    set_setting('clinical_notes_consent_note', trim($_POST['clinical_notes_consent_note'] ?? ''));

    $wantsEnabled = !empty($_POST['clinical_notes_enabled']);
    if ($wantsEnabled && !clinical_module_ready()) {
        flash_set('error', 'ما فيك تفعّل الوحدة قبل ما تعبّي المتطلبات الأربعة تحت (الاستضافة، الاحتفاظ، النسخ الاحتياطي، الموافقة).');
        set_setting('clinical_notes_enabled', '0');
    } else {
        set_setting('clinical_notes_enabled', $wantsEnabled ? '1' : '0');
        flash_set('success', 'تم حفظ إعدادات الملاحظات السريرية.');
    }
    redirect('/admin/clinical-settings.php');
}

$pageTitle = 'إعدادات الملاحظات السريرية';
require __DIR__ . '/includes/layout_top.php';
?>

<div class="admin-page-head"><h1>الملاحظات السريرية — الإعداد والتفعيل</h1></div>

<div class="notice-banner" style="margin-bottom:24px;">
  <strong>⚠️ قرار مهم قبل التفعيل</strong>
  وحدة الملاحظات السريرية جاهزة تقنيًا (ملاحظات، نسخ سابقة، مرفقات، قفل، تدقيق وصول) لكنها تتعامل مع معلومات نفسية شديدة الحساسية. لا تُفعّلها بالإنتاج قبل ما تحسمي، مع مرجع قانوني/مهني إذا أمكن: مكان استضافة هالبيانات فعليًا، مين بالضبط بيوصلها، مدة الاحتفاظ فيها، وآلية موافقة واضحة من المريض. لحد ما تتعبّى الحقول الأربعة تحت، الزر بيضل موقوف.
</div>

<div class="admin-card">
  <form method="post" class="form-narrow">
    <?= csrf_field() ?>
    <div class="form-group">
      <label>مكان الاستضافة والوصول</label>
      <textarea name="clinical_notes_hosting_note" rows="3" placeholder="مثال: قاعدة البيانات نفسها على استضافة X، يوصلها فقط تالا وأنا كمالك النظام."><?= e(get_setting('clinical_notes_hosting_note')) ?></textarea>
    </div>
    <div class="form-group">
      <label>مدة الاحتفاظ بالبيانات (بالأيام)</label>
      <input type="number" name="clinical_notes_retention_days" min="1" value="<?= e(get_setting('clinical_notes_retention_days')) ?>">
    </div>
    <div class="form-group">
      <label>خطة النسخ الاحتياطي والاستعادة</label>
      <textarea name="clinical_notes_backup_plan" rows="3" placeholder="مثال: نسخة احتياطية يدوية أسبوعية لملف قاعدة البيانات، محفوظة بمكان منفصل."><?= e(get_setting('clinical_notes_backup_plan')) ?></textarea>
    </div>
    <div class="form-group">
      <label>آلية موافقة المريض</label>
      <textarea name="clinical_notes_consent_note" rows="3" placeholder="مثال: موافقة شفهية موثقة بأول جلسة + نموذج ورقي موقّع، صورته تُرفع كمستند بملف المريض."><?= e(get_setting('clinical_notes_consent_note')) ?></textarea>
    </div>

    <div class="form-group" style="margin-top:20px; padding-top:16px; border-top:1px solid var(--color-border);">
      <label>
        <input type="checkbox" name="clinical_notes_enabled" value="1" style="width:auto;" <?= setting_bool('clinical_notes_enabled') ? 'checked' : '' ?> <?= clinical_module_ready() ? '' : 'disabled' ?>>
        تفعيل وحدة الملاحظات السريرية بالإنتاج
      </label>
      <?php if (!clinical_module_ready()): ?>
        <p class="field-hint" style="color:var(--color-danger);">عبّي الحقول الأربعة فوق واحفظ أولًا حتى يفعّل هالخيار.</p>
      <?php endif; ?>
    </div>
    <button type="submit" class="btn btn-primary">حفظ</button>
  </form>
</div>

<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
