<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/notify.php';
require_once __DIR__ . '/includes/i18n.php';

$locale = current_locale();
$msg = function (string $ar, string $en) use ($locale) { return $locale === 'en' ? $en : $ar; };

$errors = [];
$old = ['name' => '', 'contact_method' => 'phone', 'contact_value' => '', 'subject' => '', 'message' => ''];
$sent = isset($_GET['sent']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old['name'] = trim($_POST['name'] ?? '');
    $old['contact_method'] = $_POST['contact_method'] ?? 'phone';
    $old['contact_value'] = trim($_POST['contact_value'] ?? '');
    $old['subject'] = trim($_POST['subject'] ?? '');
    $old['message'] = trim($_POST['message'] ?? '');

    if (!csrf_verify()) {
        $errors[] = $msg('في مشكلة تقنية بالنموذج. جرّب ترسل الصفحة من جديد.', 'There was a technical issue with the form. Please try submitting again.');
    }

    // Honeypot: real users never fill this hidden field.
    if (!empty($_POST['website'])) {
        $errors[] = $msg('تعذّر إرسال الطلب.', 'The request could not be sent.');
    }

    // Basic bot-timing check: form should stay open a couple of seconds at least.
    $startedAt = (int)($_POST['form_started_at'] ?? 0);
    if ($startedAt > 0 && (time() * 1000 - $startedAt) < 1500) {
        $errors[] = $msg('تعذّر إرسال الطلب. جرّب مرة تانية.', 'The request could not be sent. Please try again.');
    }

    if ($old['name'] === '' || mb_strlen($old['name']) < 2) {
        $errors[] = $msg('فيك تكتب اسمك (حرفين على الأقل).', 'Please enter your name (at least 2 characters).');
    }
    if (!in_array($old['contact_method'], ['phone', 'whatsapp', 'email'], true)) {
        $errors[] = $msg('اختر وسيلة تواصل صحيحة.', 'Please choose a valid contact method.');
    }
    if ($old['contact_value'] === '') {
        $errors[] = $msg('لازم تكتب بيانات وسيلة التواصل يلي اخترتها.', 'Please fill in the contact details for the method you chose.');
    } elseif ($old['contact_method'] === 'email' && !filter_var($old['contact_value'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = $msg('صيغة البريد الإلكتروني مو صحيحة.', "That email address doesn't look valid.");
    } elseif (in_array($old['contact_method'], ['phone', 'whatsapp'], true) && !preg_match('/^[0-9+\-\s]{7,20}$/', $old['contact_value'])) {
        $errors[] = $msg('صيغة رقم الهاتف مو صحيحة.', "That phone number doesn't look valid.");
    }
    if ($old['message'] === '' || mb_strlen($old['message']) < 5) {
        $errors[] = $msg('اكتب رسالتك (٥ أحرف على الأقل).', 'Please write your message (at least 5 characters).');
    } elseif (mb_strlen($old['message']) > 2000) {
        $errors[] = $msg('الرسالة طويلة كتير. اختصرها شوي لو سمحت.', 'The message is too long — please shorten it.');
    }

    if (empty($errors) && !rate_limit_check('contact_form', 5, 3600)) {
        $errors[] = $msg('في عدد كبير من الطلبات من نفس المصدر. جرّب بعد شوي.', 'Too many requests from the same source. Please try again shortly.');
    }

    if (empty($errors)) {
        $stmt = db()->prepare('INSERT INTO contact_messages (name, contact_method, contact_value, subject, message, ip_hash)
            VALUES (:name, :method, :value, :subject, :message, :ip)');
        $stmt->execute([
            ':name' => $old['name'],
            ':method' => $old['contact_method'],
            ':value' => $old['contact_value'],
            ':subject' => $old['subject'],
            ':message' => $old['message'],
            ':ip' => client_ip_hash(),
        ]);
        notify_new_contact_message((int)db()->lastInsertId());
        record_analytics_event('contact_form_completed', '/contact.php');
        redirect('/contact.php?sent=1' . ($locale === 'en' ? '&lang=en' : ''));
    }
}

$pageTitle = t('page_contact_title');
$pageDescription = $msg('تواصل مع تالا لأي استفسار عام.', 'Get in touch with Tala for any general question.');
if ($sent) { $noIndex = true; }
require __DIR__ . '/includes/header.php';

$phone = get_setting('phone');
$whatsapp = get_setting('whatsapp');
$whatsappEnabled = setting_bool('whatsapp_enabled');
$email = get_setting('email');
$hours = get_setting('working_hours');
$address = get_setting('address');
$showAddress = setting_bool('show_address');
$mapUrl = get_setting('map_embed_url');
$city = get_setting('city');
$cityEn = get_setting('city_en');
$offersRemote = setting_bool('offers_remote_sessions');
?>

<section class="page-hero container">
  <div style="display:flex; justify-content:center; margin-bottom:6px;"><?php render_topic_art(5); ?></div>
  <span class="eyebrow"><?= t('nav_contact') ?></span>
  <h1><?= t('page_contact_title') ?></h1>
  <p><?= $msg('لأي استفسار عام مو مرتبط بطلب موعد مباشر. لطلب موعد، فيك تستخدم <a href="/book.php">نموذج الحجز</a>.', 'For any general question not tied to booking directly. To book an appointment, use the <a href="/book.php?lang=en">booking form</a>.') ?></p>
</section>

<section class="section">
  <div class="container split-layout">
    <div>
      <?php if ($sent): ?>
        <div class="alert alert-success" role="status"><?= $msg('وصلتنا رسالتك، رح نرد عليك قريبًا. شكرًا إلك.', "We've received your message and will reply soon. Thank you.") ?></div>
      <?php endif; ?>

      <?php if (!empty($errors)): ?>
        <div class="alert alert-error" role="alert">
          <ul style="margin:0; padding-inline-start:18px;">
            <?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <form method="post" novalidate data-timed>
        <?= csrf_field() ?>
        <input type="hidden" name="form_started_at" value="">
        <div class="hp-field" aria-hidden="true">
          <label for="website"><?= $msg('اتركه فاضي', 'Leave blank') ?></label>
          <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
        </div>

        <div class="form-group">
          <label for="name"><?= t('form_name') ?> <span class="required-mark">*</span></label>
          <input type="text" id="name" name="name" required value="<?= e($old['name']) ?>">
        </div>

        <div class="form-group">
          <label><?= t('form_contact_method') ?> <span class="required-mark">*</span></label>
          <div class="radio-group">
            <label class="radio-pill"><input type="radio" name="contact_method" value="phone" <?= $old['contact_method'] === 'phone' ? 'checked' : '' ?>> <?= t('form_phone') ?></label>
            <label class="radio-pill"><input type="radio" name="contact_method" value="whatsapp" <?= $old['contact_method'] === 'whatsapp' ? 'checked' : '' ?>> <?= t('form_whatsapp') ?></label>
            <label class="radio-pill"><input type="radio" name="contact_method" value="email" <?= $old['contact_method'] === 'email' ? 'checked' : '' ?>> <?= t('form_email') ?></label>
          </div>
        </div>

        <div class="form-group">
          <label for="contact_value"><?= t('form_contact_value') ?> <span class="required-mark">*</span></label>
          <input type="text" id="contact_value" name="contact_value" required value="<?= e($old['contact_value']) ?>" placeholder="<?= $msg('رقمك أو بريدك الإلكتروني', 'Your number or email') ?>">
        </div>

        <div class="form-group">
          <label for="subject"><?= $msg('موضوع الرسالة (اختياري)', 'Subject (optional)') ?></label>
          <input type="text" id="subject" name="subject" value="<?= e($old['subject']) ?>">
        </div>

        <div class="form-group">
          <label for="message"><?= $msg('رسالتك', 'Your message') ?> <span class="required-mark">*</span></label>
          <textarea id="message" name="message" required maxlength="2000"><?= e($old['message']) ?></textarea>
          <p class="field-hint"><?= $msg('لو سمحت لا تكتب تفاصيل صحية أو نفسية حساسة هون.', "Please don't write sensitive health/personal details here.") ?></p>
        </div>

        <button type="submit" class="btn btn-primary btn-block"><?= $msg('إرسال الرسالة', 'Send Message') ?></button>
      </form>
    </div>

    <div>
      <div class="card" style="margin-bottom:20px;">
        <h3><?= $msg('بيانات التواصل', 'Contact details') ?></h3>
        <ul style="list-style:none; padding:0; margin:0; display:flex; flex-direction:column; gap:10px;">
          <?php if ($phone): ?>
            <li>
              📞 <a href="tel:<?= e(phone_intl_href($phone)) ?>"><?= e($phone) ?></a>
              <button type="button" class="action-links" style="background:none; border:none; cursor:pointer; color:var(--color-sage-dark); font-size:.82rem;" onclick="navigator.clipboard.writeText('<?= e(phone_intl_href($phone)) ?>')"><?= $msg('نسخ الرقم', 'Copy number') ?></button>
            </li>
          <?php endif; ?>
          <?php if ($whatsapp && $whatsappEnabled): ?><li>💬 <a href="https://wa.me/<?= e(whatsapp_digits($whatsapp)) ?>" target="_blank" rel="noopener">WhatsApp: <?= e($whatsapp) ?></a></li><?php endif; ?>
          <?php if ($email): ?><li>✉️ <a href="mailto:<?= e($email) ?>"><?= e($email) ?></a></li><?php endif; ?>
          <?php if ($hours && $locale === 'ar'): ?><li>🕒 <?= e($hours) ?></li><?php endif; ?>
          <?php if ($city): ?><li>📍 <?= $msg('جلسات حضورية في', 'In-person sessions in') ?> <?= e($locale === 'en' ? ($cityEn ?: $city) : $city) ?><?= $offersRemote ? $msg('، وكمان عن بُعد', ', and also remote') : '' ?></li><?php endif; ?>
          <?php if ($showAddress && $address && $locale === 'ar'): ?><li>📍 <?= e($address) ?></li><?php endif; ?>
          <?php if (!$phone && !($whatsapp && $whatsappEnabled) && !$email): ?>
            <li class="field-hint"><?= $msg('بيانات التواصل المباشرة بانتظار اعتمادها من تالا. استخدم النموذج جنب هون بهالأثناء.', 'Direct contact details are pending confirmation. Please use the form for now.') ?></li>
          <?php endif; ?>
        </ul>
      </div>

      <?php if ($showAddress && $mapUrl): ?>
        <div class="card" style="padding:0; overflow:hidden;">
          <iframe src="<?= e($mapUrl) ?>" width="100%" height="260" style="border:0; display:block;" loading="lazy" title="<?= $msg('موقع العيادة على الخريطة', 'Clinic location on the map') ?>"></iframe>
        </div>
      <?php endif; ?>

      <div class="notice-banner" style="margin-top:20px;">
        <strong><?= t('emergency_notice_label') ?></strong>
        <?= $locale === 'en'
          ? 'This site is not for emergencies or immediate crisis response. If you or someone else is in immediate danger, contact local emergency services right away or go to the nearest emergency room.'
          : 'هالموقع مو مخصص للطوارئ أو الاستجابة الفورية. إذا في خطر مباشر عليك أو على شخص تاني، تواصل فورًا مع خدمات الطوارئ المحلية أو توجّه لأقرب قسم إسعاف.' ?>
      </div>
    </div>
  </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
