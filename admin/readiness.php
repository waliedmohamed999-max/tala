<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('owner');
$activeNav = 'readiness';

$hasPublishedService = (int)db()->query("SELECT COUNT(*) FROM services WHERE workflow_status = 'published'")->fetchColumn() > 0;
$hasHours = (int)db()->query("SELECT COUNT(*) FROM clinic_hours WHERE is_active = 1")->fetchColumn() > 0;
$mailerTested = (int)db()->query("SELECT COUNT(*) FROM notification_log WHERE type = 'test' AND status = 'sent'")->fetchColumn() > 0;
$privacyReviewed = setting_bool('privacy_policy_reviewed');
$hasActiveOwner = (int)db()->query("SELECT COUNT(*) FROM admin_users WHERE role = 'owner' AND is_active = 1")->fetchColumn() > 0;
$hasBookableOnline = (int)db()->query("SELECT COUNT(*) FROM services WHERE workflow_status = 'published' AND bookable_online = 1")->fetchColumn() > 0;
$clinicalReady = clinical_module_ready();
$clinicalEnabled = setting_bool('clinical_notes_enabled');

$items = [
    ['label' => 'في خدمة واحدة على الأقل منشورة (معتمدة من تالا)', 'ok' => $hasPublishedService, 'link' => '/admin/services.php'],
    ['label' => 'في ساعات عمل مضبوطة بالتقويم', 'ok' => $hasHours, 'link' => '/admin/clinic-hours.php'],
    ['label' => 'قناة إرسال (بريد) مربوطة ومختبرة فعليًا (إرسال تجريبي ناجح)', 'ok' => $mailerTested, 'link' => '/admin/notifications-settings.php'],
    ['label' => 'سياسة الخصوصية مراجعة ومعتمدة', 'ok' => $privacyReviewed, 'link' => '/admin/settings.php'],
    ['label' => 'في مستخدم "مالك" نشط واحد على الأقل', 'ok' => $hasActiveOwner, 'link' => '/admin/users.php'],
];

$pageTitle = 'جاهزية التشغيل';
require __DIR__ . '/includes/layout_top.php';
?>

<div class="notice-inline" style="margin-bottom:24px;">
  هاي الشاشة بتوضح شو ناقص قبل تفعيل أي ميزة عامة على الموقع. اكتمال الواجهات لحاله مو معناه جاهزية — الجاهزية الحقيقية تعني نجاح كل هالبنود + مراجعة تالا للإجراءات المهنية والبيانات المنشورة.
</div>

<div class="admin-card" style="margin-bottom:20px;">
  <h2 style="margin-top:0;">الأساسيات</h2>
  <table class="data-table">
    <tbody>
      <?php foreach ($items as $it): ?>
        <tr>
          <td style="width:36px;"><?= $it['ok'] ? '✅' : '⭕' ?></td>
          <td><?= e($it['label']) ?></td>
          <td><a href="<?= e($it['link']) ?>">فتح</a></td>
        </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>

<div class="admin-card" style="margin-bottom:20px;">
  <h2 style="margin-top:0;">الحجز الذاتي المباشر</h2>
  <p><?= $hasBookableOnline ? '✅ في خدمة واحدة على الأقل متاحة للحجز الذاتي المباشر.' : '⭕ ما في أي خدمة مفعّلة للحجز الذاتي بعد — الموقع رح يستقبل طلبات مواعيد فقط.' ?></p>
  <a href="/admin/services.php">إدارة الخدمات</a>
</div>

<div class="admin-card">
  <h2 style="margin-top:0;">الملاحظات السريرية</h2>
  <p><?= $clinicalEnabled ? '✅ الوحدة مفعّلة بالإنتاج.' : ($clinicalReady ? '⭕ المتطلبات معبّاة لكن الوحدة لسا معطّلة — قرار التفعيل النهائي إلك.' : '⭕ الوحدة معطّلة — المتطلبات (الاستضافة، الاحتفاظ، النسخ الاحتياطي، الموافقة) لسا ناقصة.') ?></p>
  <a href="/admin/clinical-settings.php">إعدادات الملاحظات السريرية</a>
</div>

<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
