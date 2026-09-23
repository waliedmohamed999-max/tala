<?php
require_once __DIR__ . '/../includes/auth.php';
require_role('owner');
$activeNav = 'settings';

$fields = [
    'display_title', 'full_professional_name', 'professional_title', 'credentials', 'license_info',
    'years_experience', 'specialties', 'languages', 'bio_short', 'bio_long', 'photo_path',
    'intro_video_url', 'media_mentions',
    'city', 'address', 'show_address', 'map_embed_url',
    'phone', 'whatsapp', 'email', 'working_hours', 'offers_remote_sessions', 'response_time_note',
    'cancellation_policy', 'privacy_last_updated',
    'booking_notice', 'emergency_notice', 'facebook_url', 'instagram_url',
    'events_section_enabled', 'article_stale_after_days', 'analytics_enabled',
    'billing_enabled', 'min_notice_hours', 'booking_horizon_days', 'privacy_policy_reviewed',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        flash_set('error', 'في مشكلة تقنية. جرّب من جديد.');
        redirect('/admin/settings.php');
    }

    // Optional photo upload
    if (!empty($_FILES['photo_file']['name']) && $_FILES['photo_file']['error'] === UPLOAD_ERR_OK) {
        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
        $mime = mime_content_type($_FILES['photo_file']['tmp_name']);
        if (isset($allowed[$mime]) && $_FILES['photo_file']['size'] <= 5 * 1024 * 1024) {
            if (!is_dir(UPLOADS_DIR)) { mkdir(UPLOADS_DIR, 0775, true); }
            $filename = 'photo-' . bin2hex(random_bytes(6)) . '.' . $allowed[$mime];
            move_uploaded_file($_FILES['photo_file']['tmp_name'], UPLOADS_DIR . '/' . $filename);
            set_setting('photo_path', UPLOADS_URL . '/' . $filename);
        } else {
            flash_set('error', 'صيغة الصورة أو حجمها غير مدعوم (JPG/PNG/WEBP، أقل من 5MB).');
        }
    }

    $checkboxFields = ['show_address', 'offers_remote_sessions', 'events_section_enabled', 'analytics_enabled', 'billing_enabled', 'privacy_policy_reviewed'];
    foreach ($fields as $field) {
        if (in_array($field, $checkboxFields, true)) {
            set_setting($field, !empty($_POST[$field]) ? '1' : '0');
        } else {
            set_setting($field, trim($_POST[$field] ?? ''));
        }
    }
    flash_set('success', 'تم حفظ الإعدادات.');
    redirect('/admin/settings.php');
}

$values = [];
foreach ($fields as $f) { $values[$f] = get_setting($f); }

$pageTitle = 'الإعدادات العامة';
require __DIR__ . '/includes/layout_top.php';
?>

<div class="admin-page-head"><h1>الملف الشخصي والإعدادات العامة</h1></div>

<div class="notice-banner" style="margin-bottom:24px;">
  <strong>تذكير</strong>
  أي حقل تسيبه فاضي هون رح يبقى غير معروض بالموقع (بدل ما نخترع معلومة). عبّي بس الحقول المؤكدة.
</div>

<form method="post" enctype="multipart/form-data" class="admin-card">
  <?= csrf_field() ?>

  <h3>الهوية والملف المهني</h3>
  <div class="form-row-2">
    <div class="form-group"><label>عنوان الموقع (يظهر بتبويب المتصفح)</label><input type="text" name="display_title" value="<?= e($values['display_title']) ?>"></div>
    <div class="form-group"><label>المسمى المهني</label><input type="text" name="professional_title" value="<?= e($values['professional_title']) ?>" placeholder="مثال: معالجة نفسية"></div>
  </div>
  <div class="form-group"><label>الاسم المهني الكامل (لا يُعرض لقب "دكتورة" إلا بعد التأكيد)</label><input type="text" name="full_professional_name" value="<?= e($values['full_professional_name']) ?>"></div>
  <div class="form-group"><label>المؤهلات المعتمدة</label><textarea name="credentials" rows="2"><?= e($values['credentials']) ?></textarea></div>
  <div class="form-group"><label>معلومات الترخيص (إن وجد)</label><input type="text" name="license_info" value="<?= e($values['license_info']) ?>"></div>
  <div class="form-row-2">
    <div class="form-group"><label>سنوات الخبرة</label><input type="text" name="years_experience" value="<?= e($values['years_experience']) ?>"></div>
    <div class="form-group"><label>الاختصاصات (مفصولة بفواصل)</label><input type="text" name="specialties" value="<?= e($values['specialties']) ?>"></div>
  </div>
  <div class="form-group"><label>اللغات اللي تُقدَّم فيها الجلسات (مفصولة بفواصل)</label><input type="text" name="languages" value="<?= e($values['languages']) ?>" placeholder="مثال: العربية، الإنجليزية"></div>
  <div class="form-group"><label>نبذة قصيرة (للصفحة الرئيسية)</label><textarea name="bio_short" rows="3"><?= e($values['bio_short']) ?></textarea></div>
  <div class="form-group"><label>نبذة كاملة (لصفحة "عن تالا")</label><textarea name="bio_long" rows="6"><?= e($values['bio_long']) ?></textarea></div>

  <div class="form-group">
    <label>الصورة الشخصية</label>
    <?php if ($values['photo_path']): ?><img src="<?= e($values['photo_path']) ?>" alt="" style="width:90px; border-radius:12px; margin-bottom:8px;"><?php endif; ?>
    <input type="file" name="photo_file" accept="image/png, image/jpeg, image/webp">
    <p class="field-hint">JPG/PNG/WEBP، أقل من 5MB. ما رح تُنشر إلا بعد موافقة تالا.</p>
  </div>
  <div class="form-group"><label>رابط فيديو تعريفي (اختياري، يوتيوب/فيميو — فقط بعد موافقة تالا)</label><input type="text" name="intro_video_url" value="<?= e($values['intro_video_url']) ?>"></div>
  <div class="form-group"><label>ظهور إعلامي / مشاركات مهنية حقيقية (سطر لكل عنصر)</label><textarea name="media_mentions" rows="3"><?= e($values['media_mentions']) ?></textarea></div>

  <h3 style="margin-top:34px;">الموقع والتواصل</h3>
  <div class="form-row-2">
    <div class="form-group"><label>المدينة</label><input type="text" name="city" value="<?= e($values['city']) ?>"></div>
    <div class="form-group"><label>ساعات العمل</label><input type="text" name="working_hours" value="<?= e($values['working_hours']) ?>"></div>
  </div>
  <div class="form-group"><label>العنوان الكامل</label><input type="text" name="address" value="<?= e($values['address']) ?>"></div>
  <div class="form-group"><label><input type="checkbox" name="show_address" value="1" <?= $values['show_address'] === '1' ? 'checked' : '' ?> style="width:auto;"> اعرض العنوان علنًا بالموقع</label></div>
  <div class="form-group"><label>رابط خريطة مضمّنة (Google Maps embed URL) — يظهر فقط إذا فُعّل عرض العنوان</label><input type="text" name="map_embed_url" value="<?= e($values['map_embed_url']) ?>"></div>

  <div class="form-row-2">
    <div class="form-group"><label>الهاتف</label><input type="text" name="phone" value="<?= e($values['phone']) ?>"></div>
    <div class="form-group"><label>واتساب</label><input type="text" name="whatsapp" value="<?= e($values['whatsapp']) ?>"></div>
  </div>
  <div class="form-group"><label>البريد الإلكتروني</label><input type="email" name="email" value="<?= e($values['email']) ?>"></div>
  <div class="form-row-2">
    <div class="form-group"><label>فيسبوك (رابط، اختياري)</label><input type="text" name="facebook_url" value="<?= e($values['facebook_url']) ?>"></div>
    <div class="form-group"><label>انستغرام (رابط، اختياري)</label><input type="text" name="instagram_url" value="<?= e($values['instagram_url']) ?>"></div>
  </div>

  <div class="form-group"><label><input type="checkbox" name="offers_remote_sessions" value="1" <?= $values['offers_remote_sessions'] === '1' ? 'checked' : '' ?> style="width:auto;"> تالا بتقدّم جلسات عن بُعد</label></div>
  <div class="form-group"><label>مدة الرد المتوقعة (تظهر بصفحة «ابدأ من هون» وصفحة نجاح الحجز)</label><input type="text" name="response_time_note" value="<?= e($values['response_time_note']) ?>" placeholder="مثال: بنرد خلال يوم إلى يومين عمل"></div>

  <h3 style="margin-top:34px;">السياسات والنصوص القانونية</h3>
  <div class="form-group"><label>سياسة إلغاء المواعيد</label><textarea name="cancellation_policy" rows="3"><?= e($values['cancellation_policy']) ?></textarea></div>
  <div class="form-group"><label>تاريخ آخر تحديث لسياسة الخصوصية</label><input type="date" name="privacy_last_updated" value="<?= e($values['privacy_last_updated']) ?>"></div>
  <div class="form-group"><label>رسالة نجاح نموذج الحجز</label><textarea name="booking_notice" rows="2"><?= e($values['booking_notice']) ?></textarea></div>
  <div class="form-group"><label>تنبيه الطوارئ (يظهر بصفحات الحجز والتواصل والتذييل)</label><textarea name="emergency_notice" rows="2"><?= e($values['emergency_notice']) ?></textarea></div>

  <h3 style="margin-top:34px;">قسم الفعاليات والمحتوى</h3>
  <div class="form-group">
    <label><input type="checkbox" name="events_section_enabled" value="1" <?= $values['events_section_enabled'] === '1' ? 'checked' : '' ?> style="width:auto;"> فعّل قسم "محاضرات وفعاليات" بالتنقل العام</label>
    <p class="field-hint">فعّل هاد بس بعد ما يكون في فعالية حقيقية منشورة من <a href="/admin/events.php">إدارة الفعاليات</a>.</p>
  </div>
  <div class="form-group"><label>تنبيه بعد كم يوم يُعتبر المقال المنشور بحاجة مراجعة</label><input type="number" name="article_stale_after_days" value="<?= e($values['article_stale_after_days']) ?>" style="max-width:120px;"></div>
  <div class="form-group"><label><input type="checkbox" name="analytics_enabled" value="1" <?= $values['analytics_enabled'] === '1' ? 'checked' : '' ?> style="width:auto;"> فعّل عدادات الزيارات المجهولة (بدون أي بيانات شخصية)</label></div>

  <h3 style="margin-top:34px;">العيادة: الحجز الذاتي والفوترة</h3>
  <div class="form-row-2">
    <div class="form-group"><label>أقل مدة إشعار مسبق قبل الموعد (ساعات)</label><input type="number" name="min_notice_hours" min="0" value="<?= e($values['min_notice_hours']) ?>"></div>
    <div class="form-group"><label>أفق الحجز الذاتي (كم يوم قدّام يظهر بالتقويم العام)</label><input type="number" name="booking_horizon_days" min="1" max="60" value="<?= e($values['booking_horizon_days']) ?>"></div>
  </div>
  <div class="form-group"><label><input type="checkbox" name="billing_enabled" value="1" <?= $values['billing_enabled'] === '1' ? 'checked' : '' ?> style="width:auto;"> فعّل قسم الفوترة والمدفوعات اليدوية</label></div>
  <div class="form-group"><label><input type="checkbox" name="privacy_policy_reviewed" value="1" <?= $values['privacy_policy_reviewed'] === '1' ? 'checked' : '' ?> style="width:auto;"> راجعت/اعتمدت سياسة الخصوصية الحالية (لشاشة جاهزية التشغيل)</label></div>

  <button type="submit" class="btn btn-primary" style="margin-top:14px;">حفظ كل الإعدادات</button>
</form>

<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
