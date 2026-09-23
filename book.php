<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/notify.php';
require_once __DIR__ . '/includes/i18n.php';

$locale = current_locale();
$services = published_services();
$offersRemote = setting_bool('offers_remote_sessions');
$sent = isset($_GET['sent']);
$refCode = $sent ? trim($_GET['ref'] ?? '') : '';

$old = [
    'name' => '',
    'contact_method' => 'phone',
    'contact_value' => '',
    'service_id' => $_GET['service'] ?? '',
    'location_pref' => 'homs',
    'time_pref' => '',
    'notes' => '',
];
$errors = [];

$msg = function (string $ar, string $en) use ($locale) { return $locale === 'en' ? $en : $ar; };

// Resolve service slug from query (?service=slug) to an id for the initial selection.
if (!empty($old['service_id']) && !ctype_digit((string)$old['service_id'])) {
    $stmt = db()->prepare('SELECT id FROM services WHERE slug = ? AND is_published = 1');
    $stmt->execute([$old['service_id']]);
    $old['service_id'] = $stmt->fetchColumn() ?: '';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old['name'] = trim($_POST['name'] ?? '');
    $old['contact_method'] = $_POST['contact_method'] ?? 'phone';
    $old['contact_value'] = trim($_POST['contact_value'] ?? '');
    $old['service_id'] = $_POST['service_id'] ?? '';
    $old['location_pref'] = $_POST['location_pref'] ?? 'homs';
    $old['time_pref'] = trim($_POST['time_pref'] ?? '');
    $old['notes'] = trim($_POST['notes'] ?? '');
    $consent = !empty($_POST['privacy_consent']);

    if (!csrf_verify()) {
        $errors[] = $msg('في مشكلة تقنية بالنموذج. جرّب ترسل الصفحة من جديد.', 'There was a technical issue with the form. Please try submitting again.');
    }
    if (!empty($_POST['website'])) {
        $errors[] = $msg('تعذّر إرسال الطلب.', 'The request could not be sent.');
    }
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

    $serviceId = null;
    if (!empty($services)) {
        if ($old['service_id'] === '') {
            $errors[] = $msg('اختر نوع الخدمة، أو «استفسار عام عن الجلسات».', 'Choose a session type, or "General inquiry about sessions".');
        } elseif ($old['service_id'] !== 'inquiry') {
            $valid = array_filter($services, fn($s) => (int)$s['id'] === (int)$old['service_id']);
            if (empty($valid)) {
                $errors[] = $msg('نوع الخدمة المختار مو متاح.', 'The selected session type is not available.');
            } else {
                $serviceId = (int)$old['service_id'];
            }
        }
    }

    if (!in_array($old['location_pref'], ['homs', 'remote', 'either'], true)) {
        $errors[] = $msg('اختر طريقة الحضور.', 'Please choose how you would attend.');
    }
    if (!$offersRemote && $old['location_pref'] !== 'homs') {
        $old['location_pref'] = 'homs';
    }

    if (mb_strlen($old['notes']) > 500) {
        $errors[] = $msg('الملاحظات طويلة كتير. اختصرها شوي لو سمحت.', 'The notes are too long — please shorten them.');
    }

    if (!$consent) {
        $errors[] = $msg('لازم توافق على سياسة الخصوصية قبل إرسال الطلب.', 'You must agree to the privacy policy before sending the request.');
    }

    // Duplicate-prevention: block a second request from the same contact
    // details while an earlier one is still open (new/contacting), so the
    // same person doesn't accidentally flood the queue with repeats.
    if (empty($errors)) {
        $dupCheck = db()->prepare("SELECT COUNT(*) FROM appointment_requests
            WHERE contact_value = ? AND status IN ('new', 'contacting') AND created_at >= datetime('now', '-48 hours')");
        $dupCheck->execute([$old['contact_value']]);
        if ((int)$dupCheck->fetchColumn() > 0) {
            $errors[] = $msg('في طلب سابق منك لسا قيد المتابعة بنفس بيانات التواصل. بنتواصل معك قريبًا — ما في داعي لطلب جديد حاليًا.', "There's already an open request from you with the same contact details. We'll be in touch soon — no need for a new request right now.");
        }
    }

    if (empty($errors) && !rate_limit_check('booking_form', 5, 3600)) {
        $errors[] = $msg('في عدد كبير من الطلبات من نفس المصدر. جرّب بعد شوي.', 'Too many requests from the same source. Please try again shortly.');
    }

    if (empty($errors)) {
        $referenceCode = strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
        $stmt = db()->prepare('INSERT INTO appointment_requests
            (reference_code, name, contact_method, contact_value, service_id, location_pref, time_pref, notes, privacy_consent, ip_hash)
            VALUES (:ref, :name, :method, :value, :service_id, :location, :time_pref, :notes, 1, :ip)');
        $stmt->execute([
            ':ref' => $referenceCode,
            ':name' => $old['name'],
            ':method' => $old['contact_method'],
            ':value' => $old['contact_value'],
            ':service_id' => $serviceId,
            ':location' => $old['location_pref'],
            ':time_pref' => $old['time_pref'],
            ':notes' => $old['notes'],
            ':ip' => client_ip_hash(),
        ]);
        $newId = db()->lastInsertId();
        $log = db()->prepare('INSERT INTO appointment_status_log (request_id, old_status, new_status, changed_by) VALUES (?, NULL, ?, ?)');
        $log->execute([$newId, 'new', 'نموذج الموقع']);

        notify_new_booking((int)$newId, $referenceCode); // see includes/notify.php — no-op until SMTP is configured
        record_analytics_event('booking_form_completed', '/book.php');

        redirect('/book.php?sent=1&ref=' . urlencode($referenceCode) . ($locale === 'en' ? '&lang=en' : ''));
    }
}

$pageTitle = t('page_book_title');
$pageDescription = $msg('اطلب موعدك مع تالا بخصوصية وسهولة.', 'Book your appointment with Tala, privately and easily.');
if ($sent) { $noIndex = true; }
require __DIR__ . '/includes/header.php';
?>

<section class="page-hero container">
  <span class="eyebrow"><?= t('page_book_title') ?></span>
  <h1><?= t('page_book_title') ?></h1>
  <p><?= $msg('عبّي النموذج وراجعه قبل الإرسال، وبنتواصل معك لنأكد التفاصيل. إرسال الطلب لحاله ما بيعني إن الموعد تأكّد.', "Fill the form and review it before sending — we'll be in touch to confirm details. Sending the request alone doesn't mean the appointment is confirmed.") ?></p>
</section>

<section class="section">
  <div class="container" style="max-width:640px;">

    <?php if ($sent): ?>
      <div class="alert alert-success" role="status"><?= $locale === 'en' ? "We've received your request. We'll be in touch to confirm the details. Sending the request alone doesn't mean the appointment is confirmed." : e(get_setting('booking_notice')) ?></div>
      <?php if ($refCode): ?>
        <div class="card text-center" style="margin-top:18px;">
          <p class="field-hint" style="margin-bottom:4px;"><?= $msg('رقم مرجعي لطلبك (احتفظ فيه لو حبيت تراجع طلبك معنا):', 'Your reference number (keep it if you want to follow up on your request):') ?></p>
          <p style="font-size:1.6rem; font-weight:800; letter-spacing:2px; color:var(--color-sage-dark); margin:0;"><?= e($refCode) ?></p>
        </div>
      <?php endif; ?>
      <?php $responseNote = get_setting('response_time_note'); if ($responseNote && $locale === 'ar'): ?>
        <p class="notice-inline" style="margin-top:20px;"><?= e($responseNote) ?></p>
      <?php endif; ?>
      <div class="text-center" style="margin-top:20px;">
        <a href="/index.php" class="btn btn-outline"><?= $msg('رجوع للصفحة الرئيسية', 'Back to homepage') ?></a>
      </div>
    <?php else: ?>

      <?php if (!empty($errors)): ?>
        <div class="alert alert-error" role="alert">
          <ul style="margin:0; padding-inline-start:18px;">
            <?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?>
          </ul>
        </div>
      <?php endif; ?>

      <?php if (empty($services)): ?>
        <div class="notice-banner" style="margin-bottom:24px;">
          <strong><?= $msg('ملاحظة', 'Note') ?></strong>
          <?= $msg('صفحة الخدمات لسا بانتظار تأكيد التفاصيل النهائية. فيك ترسل طلبك العام هون وبنتواصل معك نوضح الإمكانيات المتاحة، أو تواصل معنا مباشرة عبر <a href="/contact.php">صفحة التواصل</a>.', 'The services page is still pending final confirmation. You can send a general request here and we\'ll explain what\'s available, or reach out directly via the <a href="/contact.php?lang=en">Contact page</a>.') ?>
        </div>
      <?php endif; ?>

      <p class="field-hint"><?= $msg('هاد نموذج «طلب موعد» — إذا عندك سؤال عام مو مرتبط بحجز مباشر، فيك تستخدم <a href="/contact.php">نموذج التواصل العام</a> بدل هيك.', 'This is the "Book an Appointment" form — if you have a general question not tied to a direct booking, use the <a href="/contact.php?lang=en">general contact form</a> instead.') ?></p>

      <form method="post" novalidate data-timed id="bookingForm">
        <?= csrf_field() ?>
        <input type="hidden" name="form_started_at" value="">
        <div class="hp-field" aria-hidden="true">
          <label for="website"><?= $msg('اتركه فاضي', 'Leave blank') ?></label>
          <input type="text" id="website" name="website" tabindex="-1" autocomplete="off">
        </div>

        <div id="formStep">
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

          <?php if (!empty($services)): ?>
          <div class="form-group">
            <label for="service_id"><?= t('form_service') ?> <span class="required-mark">*</span></label>
            <select id="service_id" name="service_id" required>
              <option value=""><?= $msg('اختر...', 'Choose...') ?></option>
              <?php foreach ($services as $s): ?>
                <option value="<?= (int)$s['id'] ?>" <?= (string)$old['service_id'] === (string)$s['id'] ? 'selected' : '' ?>><?= e($s['name']) ?></option>
              <?php endforeach; ?>
              <option value="inquiry" <?= $old['service_id'] === 'inquiry' ? 'selected' : '' ?>><?= $msg('مو متأكد/ة — استفسار عام عن الجلسات', "Not sure — general inquiry about sessions") ?></option>
            </select>
            <?php if ($locale === 'en'): ?><p class="field-hint">Session names above are shown as entered in Arabic by Tala.</p><?php endif; ?>
          </div>
          <?php endif; ?>

          <?php if ($offersRemote): ?>
          <div class="form-group">
            <label><?= $msg('طريقة الحضور', 'How you\'ll attend') ?> <span class="required-mark">*</span></label>
            <div class="radio-group">
              <label class="radio-pill"><input type="radio" name="location_pref" value="homs" <?= $old['location_pref'] === 'homs' ? 'checked' : '' ?>> <?= $msg('حضوري بحمص', 'In person, Homs') ?></label>
              <label class="radio-pill"><input type="radio" name="location_pref" value="remote" <?= $old['location_pref'] === 'remote' ? 'checked' : '' ?>> <?= $msg('عن بُعد', 'Remote') ?></label>
              <label class="radio-pill"><input type="radio" name="location_pref" value="either" <?= $old['location_pref'] === 'either' ? 'checked' : '' ?>> <?= $msg('ما بفرق معي', "Either works") ?></label>
            </div>
          </div>
          <?php else: ?>
            <input type="hidden" name="location_pref" value="homs">
          <?php endif; ?>

          <div class="form-group">
            <label for="time_pref"><?= $msg('اليوم أو الفترة المفضلة (اختياري)', 'Preferred day or time (optional)') ?></label>
            <input type="text" id="time_pref" name="time_pref" value="<?= e($old['time_pref']) ?>" placeholder="<?= $msg('مثال: بعد الظهر أيام الثلاثاء', 'e.g. Tuesday afternoons') ?>">
            <p class="field-hint"><?= $msg('هاي تفضيلات أولية وليست موعدًا مؤكدًا — رح نتفق على الوقت الدقيق وقت التواصل.', "This is an initial preference, not a confirmed time — we'll agree on the exact time when we're in touch.") ?></p>
          </div>

          <div class="form-group">
            <label for="notes"><?= t('form_notes') ?></label>
            <textarea id="notes" name="notes" maxlength="500"><?= e($old['notes']) ?></textarea>
            <p class="field-hint"><?= $msg('لو سمحت لا تكتب هون تفاصيل صحية أو نفسية حساسة — يكفي أي ملاحظة عملية بسيطة.', "Please don't write sensitive health/personal details here — a brief practical note is enough.") ?></p>
          </div>

          <div class="consent-box form-group">
            <input type="checkbox" id="privacy_consent" name="privacy_consent" value="1" required <?= !empty($consent) ? 'checked' : '' ?>>
            <label for="privacy_consent"><?= $msg('موافق/ة إنه معلوماتي تُستخدم لترتيب الموعد والتواصل معي، وفق', "I agree that my information will be used to arrange the appointment and contact me, per the") ?> <a href="/privacy.php<?= $locale === 'en' ? '?lang=en' : '' ?>" target="_blank"><?= t('footer_privacy') ?></a>. <span class="required-mark">*</span></label>
          </div>

          <button type="button" id="reviewBtn" class="btn btn-primary btn-block"><?= t('btn_review_before_send') ?></button>
        </div>

        <div id="reviewStep" style="display:none;">
          <div class="card" style="margin-bottom:20px;">
            <h3 style="margin-top:0;"><?= $msg('راجع بيانات طلبك', 'Review your request') ?></h3>
            <table class="data-table" id="reviewTable"></table>
          </div>
          <div style="display:flex; gap:12px;">
            <button type="button" id="editBtn" class="btn btn-outline" style="flex:1;"><?= t('btn_back_to_edit') ?></button>
            <button type="submit" class="btn btn-primary" style="flex:1;"><?= t('btn_confirm_send') ?></button>
          </div>
        </div>
      </form>

      <div class="notice-banner" style="margin-top:26px;">
        <strong><?= t('emergency_notice_label') ?></strong>
        <?= $locale === 'en'
          ? 'This site is not for emergencies or immediate crisis response. If you or someone else is in immediate danger, contact local emergency services right away or go to the nearest emergency room.'
          : 'هالموقع مو مخصص للطوارئ أو الاستجابة الفورية. إذا في خطر مباشر عليك أو على شخص تاني، تواصل فورًا مع خدمات الطوارئ المحلية أو توجّه لأقرب قسم إسعاف.' ?>
      </div>

      <script>
      (function () {
        var form = document.getElementById('bookingForm');
        var formStep = document.getElementById('formStep');
        var reviewStep = document.getElementById('reviewStep');
        var reviewBtn = document.getElementById('reviewBtn');
        var editBtn = document.getElementById('editBtn');
        var table = document.getElementById('reviewTable');
        var rowLabels = <?= $locale === 'en'
          ? "{name:'Name',method:'Contact method',value:'Contact details',service:'Session type',location:'Attendance',time:'Preferred time',notes:'Notes'}"
          : "{name:'الاسم',method:'وسيلة التواصل',value:'بيانات التواصل',service:'نوع الجلسة',location:'طريقة الحضور',time:'الوقت المفضل',notes:'ملاحظات'}" ?>;

        function labelFor(name, value) {
          var el = form.querySelector('[name="' + name + '"]' + (value !== undefined ? '[value="' + value + '"]' : ''));
          if (el && el.closest('.radio-pill')) return el.closest('.radio-pill').textContent.trim();
          if (el && el.tagName === 'SELECT') return el.options[el.selectedIndex] ? el.options[el.selectedIndex].text : value;
          return value;
        }

        reviewBtn.addEventListener('click', function () {
          if (!form.checkValidity()) { form.reportValidity(); return; }

          var rows = [];
          rows.push([rowLabels.name, form.name.value]);
          var method = form.querySelector('[name="contact_method"]:checked');
          rows.push([rowLabels.method, method ? labelFor('contact_method', method.value) : '']);
          rows.push([rowLabels.value, form.contact_value.value]);
          var serviceSel = form.querySelector('[name="service_id"]');
          if (serviceSel) rows.push([rowLabels.service, serviceSel.options[serviceSel.selectedIndex].text]);
          var loc = form.querySelector('[name="location_pref"]:checked');
          if (loc) rows.push([rowLabels.location, labelFor('location_pref', loc.value)]);
          if (form.time_pref.value) rows.push([rowLabels.time, form.time_pref.value]);
          if (form.notes.value) rows.push([rowLabels.notes, form.notes.value]);

          table.innerHTML = rows.map(function (r) {
            return '<tr><th style="width:35%;">' + r[0] + '</th><td>' + r[1].replace(/</g, '&lt;') + '</td></tr>';
          }).join('');

          formStep.style.display = 'none';
          reviewStep.style.display = 'block';
          reviewStep.scrollIntoView({ behavior: 'smooth', block: 'start' });
        });

        editBtn.addEventListener('click', function () {
          reviewStep.style.display = 'none';
          formStep.style.display = 'block';
        });
      })();
      </script>
    <?php endif; ?>
  </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
