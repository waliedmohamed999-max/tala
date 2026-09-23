<?php
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/i18n.php';

if (!setting_bool('events_section_enabled')) {
    http_response_code(404);
    $pageTitle = 'الصفحة غير موجودة';
    require __DIR__ . '/../includes/header.php';
    echo '<section class="section container text-center"><h1>الصفحة غير متاحة</h1></section>';
    require __DIR__ . '/../includes/footer.php';
    exit;
}

if (current_locale() === 'en') {
    show_untranslated_notice_page('Speaking Inquiry', '/events/speaking-inquiry.php');
    exit;
}

$errors = [];
$old = ['name' => '', 'organization' => '', 'contact_value' => '', 'event_details' => ''];
$sent = isset($_GET['sent']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old['name'] = trim($_POST['name'] ?? '');
    $old['organization'] = trim($_POST['organization'] ?? '');
    $old['contact_value'] = trim($_POST['contact_value'] ?? '');
    $old['event_details'] = trim($_POST['event_details'] ?? '');

    if (!csrf_verify()) { $errors[] = 'في مشكلة تقنية بالنموذج. جرّب من جديد.'; }
    if (!empty($_POST['website'])) { $errors[] = 'تعذّر إرسال الطلب.'; }

    if ($old['name'] === '') { $errors[] = 'اكتب اسمك.'; }
    if ($old['contact_value'] === '') { $errors[] = 'اكتب وسيلة تواصل (بريد أو هاتف).'; }
    if ($old['event_details'] === '' || mb_strlen($old['event_details']) < 10) { $errors[] = 'اكتب تفاصيل الفعالية (الموضوع، الجمهور، التاريخ التقريبي).'; }

    if (empty($errors) && !rate_limit_check('speaking_inquiry', 5, 3600)) {
        $errors[] = 'في عدد كبير من الطلبات من نفس المصدر. جرّب بعد شوي.';
    }

    if (empty($errors)) {
        $stmt = db()->prepare('INSERT INTO speaking_inquiries (name, organization, contact_value, event_details) VALUES (?,?,?,?)');
        $stmt->execute([$old['name'], $old['organization'], $old['contact_value'], $old['event_details']]);
        redirect('/events/speaking-inquiry.php?sent=1');
    }
}

$pageTitle = 'دعوة للتحدث';
if ($sent) { $noIndex = true; }
require __DIR__ . '/../includes/header.php';
?>

<section class="page-hero container">
  <span class="eyebrow">دعوة للتحدث</span>
  <h1>أرسل دعوة لمحاضرة أو ورشة عمل</h1>
</section>

<section class="section">
  <div class="container" style="max-width:600px;">
    <?php if ($sent): ?>
      <div class="alert alert-success" role="status">وصلتنا الدعوة، شكرًا إلك. رح نراجعها ونرجعلك.</div>
    <?php else: ?>
      <?php foreach ($errors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>
      <form method="post" novalidate>
        <?= csrf_field() ?>
        <div class="hp-field" aria-hidden="true"><label for="website">اتركه فاضي</label><input type="text" id="website" name="website" tabindex="-1" autocomplete="off"></div>
        <div class="form-group"><label>الاسم *</label><input type="text" name="name" required value="<?= e($old['name']) ?>"></div>
        <div class="form-group"><label>الجهة/المنظمة (اختياري)</label><input type="text" name="organization" value="<?= e($old['organization']) ?>"></div>
        <div class="form-group"><label>وسيلة التواصل *</label><input type="text" name="contact_value" required value="<?= e($old['contact_value']) ?>"></div>
        <div class="form-group"><label>تفاصيل الفعالية *</label><textarea name="event_details" required rows="5"><?= e($old['event_details']) ?></textarea></div>
        <button type="submit" class="btn btn-primary btn-block">إرسال الدعوة</button>
      </form>
    <?php endif; ?>
  </div>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
