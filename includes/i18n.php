<?php
require_once __DIR__ . '/functions.php';

/**
 * Minimal, real i18n layer for interface chrome (nav, buttons, common
 * labels) — NOT for professional/clinical content, which stays Arabic-only
 * until a qualified person translates and approves it (see
 * content_translation_notice() below and articles.locale/translation_of in
 * includes/db.php). Locale is a simple cookie, no accounts needed.
 */

function current_locale(): string
{
    static $locale = null;
    if ($locale !== null) { return $locale; }

    if (isset($_GET['lang']) && in_array($_GET['lang'], ['ar', 'en'], true)) {
        $locale = $_GET['lang'];
        if (!headers_sent()) {
            setcookie('tala_locale', $locale, time() + 60 * 60 * 24 * 365, '/', '', false, true);
        }
    } elseif (isset($_COOKIE['tala_locale']) && in_array($_COOKIE['tala_locale'], ['ar', 'en'], true)) {
        $locale = $_COOKIE['tala_locale'];
    } else {
        $locale = 'ar';
    }
    return $locale;
}

function is_rtl(): bool
{
    return current_locale() === 'ar';
}

const UI_STRINGS = [
    'nav_home' => ['ar' => 'الرئيسية', 'en' => 'Home'],
    'nav_start_here' => ['ar' => 'ابدأ من هون', 'en' => 'Start Here'],
    'nav_about' => ['ar' => 'عن تالا', 'en' => 'About Tala'],
    'nav_services' => ['ar' => 'الخدمات', 'en' => 'Services'],
    'nav_articles' => ['ar' => 'المقالات', 'en' => 'Articles'],
    'nav_toolkit' => ['ar' => 'مساحة إلك', 'en' => 'Your Space'],
    'nav_faq' => ['ar' => 'الأسئلة الشائعة', 'en' => 'FAQ'],
    'nav_contact' => ['ar' => 'التواصل', 'en' => 'Contact'],
    'nav_book' => ['ar' => 'احجز موعد', 'en' => 'Book Appointment'],
    'footer_site_section' => ['ar' => 'الموقع', 'en' => 'Site'],
    'footer_contact_section' => ['ar' => 'تواصل واحجز', 'en' => 'Contact & Book'],
    'footer_policies_section' => ['ar' => 'سياسات', 'en' => 'Policies'],
    'footer_privacy' => ['ar' => 'سياسة الخصوصية', 'en' => 'Privacy Policy'],
    'footer_terms' => ['ar' => 'شروط الاستخدام', 'en' => 'Terms of Use'],
    'footer_admin_login' => ['ar' => 'دخول لوحة الإدارة', 'en' => 'Admin Login'],
    'footer_rights' => ['ar' => 'جميع الحقوق محفوظة', 'en' => 'All rights reserved'],
    'lang_switch_to_en' => ['ar' => 'English', 'en' => 'English'],
    'lang_switch_to_ar' => ['ar' => 'العربية', 'en' => 'العربية'],
    'btn_submit' => ['ar' => 'إرسال', 'en' => 'Submit'],
    'btn_send_booking' => ['ar' => 'إرسال طلب الموعد', 'en' => 'Send Appointment Request'],
    'btn_review_before_send' => ['ar' => 'راجع طلبك قبل الإرسال', 'en' => 'Review before sending'],
    'btn_back_to_edit' => ['ar' => 'رجوع للتعديل', 'en' => 'Back to edit'],
    'btn_confirm_send' => ['ar' => 'تأكيد وإرسال الطلب', 'en' => 'Confirm and send'],
    'form_name' => ['ar' => 'الاسم', 'en' => 'Name'],
    'form_contact_method' => ['ar' => 'وسيلة التواصل المفضلة', 'en' => 'Preferred contact method'],
    'form_contact_value' => ['ar' => 'بيانات وسيلة التواصل', 'en' => 'Contact details'],
    'form_phone' => ['ar' => 'هاتف', 'en' => 'Phone'],
    'form_whatsapp' => ['ar' => 'واتساب', 'en' => 'WhatsApp'],
    'form_email' => ['ar' => 'بريد إلكتروني', 'en' => 'Email'],
    'form_service' => ['ar' => 'نوع الجلسة', 'en' => 'Session type'],
    'form_notes' => ['ar' => 'ملاحظات (اختياري)', 'en' => 'Notes (optional)'],
    'form_privacy_consent' => ['ar' => 'موافق/ة على سياسة الخصوصية', 'en' => 'I agree to the privacy policy'],
    'page_book_title' => ['ar' => 'اطلب موعد', 'en' => 'Book an Appointment'],
    'page_contact_title' => ['ar' => 'تواصل معنا', 'en' => 'Contact Us'],
    'emergency_notice_label' => ['ar' => 'تنبيه', 'en' => 'Notice'],
];

function t(string $key): string
{
    return UI_STRINGS[$key][current_locale()] ?? UI_STRINGS[$key]['ar'] ?? $key;
}

function locale_url(string $locale): string
{
    $params = $_GET;
    $params['lang'] = $locale;
    return $_SERVER['PHP_SELF'] . '?' . http_build_query($params);
}

/**
 * The fallback shown INSTEAD OF Arabic-only, admin-authored professional
 * content when browsing in English — never a machine translation passed
 * off as final, per the requirement that clinical/professional content
 * needs a qualified human review before it's ever shown in English.
 */
function content_translation_notice(string $arabicUrl): void
{
    ?>
    <div class="notice-banner text-center">
      <strong>This content isn't available in English yet</strong>
      <p style="margin:8px 0 0;">This section was written by Tala in Arabic and hasn't been reviewed for an English version yet, so we're not showing a machine translation here. You can read it in Arabic, or get in touch and we'll help in English directly.</p>
      <div style="margin-top:14px; display:flex; gap:10px; justify-content:center; flex-wrap:wrap;">
        <a href="<?= e($arabicUrl) ?>" class="btn btn-outline">View Arabic version</a>
        <a href="/contact.php?lang=en" class="btn btn-primary">Contact us in English</a>
      </div>
    </div>
    <?php
}

/**
 * For whole pages that are entirely admin-authored Arabic content (about,
 * services, privacy policy, articles, etc.) — renders the standard header,
 * a translation-pending notice, and the footer, then the caller must exit().
 * Keeps the same nav/chrome a visitor would get anywhere else on the site.
 */
function show_untranslated_notice_page(string $pageTitleEn, string $arabicUrl): void
{
    global $pageTitle, $pageDescription, $noIndex;
    $pageTitle = $pageTitleEn;
    $pageDescription = 'This page is only available in Arabic right now.';
    require __DIR__ . '/header.php';
    ?>
    <section class="page-hero container">
      <span class="eyebrow">English</span>
      <h1><?= e($pageTitleEn) ?></h1>
    </section>
    <section class="section" style="padding-top:0;">
      <div class="container" style="max-width:640px;">
        <?php content_translation_notice($arabicUrl); ?>
      </div>
    </section>
    <?php
    require __DIR__ . '/footer.php';
}
