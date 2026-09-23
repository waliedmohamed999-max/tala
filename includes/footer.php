<?php
require_once __DIR__ . '/i18n.php';
$phone = get_setting('phone');
$whatsapp = get_setting('whatsapp');
$whatsappEnabled = setting_bool('whatsapp_enabled');
$email = get_setting('email');
$hours = get_setting('working_hours');
$city = get_setting('city', 'حمص');
$cityEn = get_setting('city_en', 'Homs');
$eventsEnabled = setting_bool('events_section_enabled');
$newsletterStatus = $_GET['newsletter'] ?? '';
$locale = current_locale();

// Safety-critical, so it always has a real English version (generic,
// license-free boilerplate) rather than depending on Tala's Arabic text
// being reviewed first — unlike clinical/professional content, an
// emergency notice being available only in Arabic could itself be unsafe.
$emergencyNoticeEn = "This site is not for emergencies or immediate crisis response. If you or someone else is in immediate danger, contact local emergency services right away or go to the nearest emergency room.";
?>
</main>

<?php if ($whatsapp && $whatsappEnabled):
    $waDigits = whatsapp_digits($whatsapp);
    $waMessage = get_setting('whatsapp_message');
    $waCta = get_setting('whatsapp_cta_text');
    $waHref = 'https://wa.me/' . $waDigits . ($waMessage !== '' ? '?text=' . rawurlencode($waMessage) : '');
?>
<div class="whatsapp-fab-wrap" id="whatsappFabWrap">
  <?php if ($waCta !== ''): ?>
    <div class="whatsapp-fab-bubble" id="whatsappFabBubble">
      <button type="button" class="whatsapp-fab-bubble-close" id="whatsappFabClose" aria-label="<?= $locale === 'en' ? 'Close' : 'إغلاق' ?>">&times;</button>
      <p><?= e($waCta) ?></p>
    </div>
  <?php endif; ?>
  <a href="<?= e($waHref) ?>" target="_blank" rel="noopener noreferrer" class="whatsapp-fab" aria-label="<?= $locale === 'en' ? 'Contact Tala on WhatsApp' : 'تواصل مع تالا عبر واتساب' ?>">
    <svg viewBox="0 0 32 32" width="28" height="28" fill="currentColor" aria-hidden="true"><path d="M16.02 3C9.4 3 4 8.4 4 15.02c0 2.23.6 4.34 1.65 6.15L4 29l8.02-1.6a11.98 11.98 0 0 0 4 .68c6.62 0 12.02-5.4 12.02-12.02C28.04 8.4 22.65 3 16.02 3Zm0 21.9c-1.86 0-3.6-.52-5.08-1.42l-.36-.22-4.36.87.88-4.25-.24-.37a9.86 9.86 0 0 1-1.53-5.29c0-5.47 4.45-9.92 9.93-9.92 5.47 0 9.92 4.45 9.92 9.92 0 5.48-4.45 9.68-9.16 9.68Zm5.44-7.42c-.3-.15-1.76-.87-2.03-.97-.27-.1-.47-.15-.67.15-.2.3-.77.97-.94 1.17-.17.2-.35.22-.65.07-.3-.15-1.26-.46-2.4-1.47a9 9 0 0 1-1.66-2.06c-.17-.3-.02-.46.13-.61.14-.14.3-.35.45-.52.15-.17.2-.3.3-.5.1-.2.05-.37-.02-.52-.07-.15-.67-1.6-.91-2.2-.24-.58-.49-.5-.67-.5-.17 0-.37-.02-.57-.02-.2 0-.52.07-.79.37-.27.3-1.04 1.02-1.04 2.47 0 1.46 1.06 2.87 1.21 3.07.15.2 2.09 3.2 5.07 4.48.71.3 1.26.49 1.69.63.71.22 1.35.19 1.86.12.57-.09 1.76-.72 2-1.41.25-.7.25-1.29.17-1.41-.07-.12-.27-.2-.57-.35Z"/></svg>
  </a>
</div>
<?php endif; ?>

<footer class="site-footer">
  <div class="container">

    <div class="newsletter-box">
      <div>
        <h4 style="margin-bottom:4px;"><?= $locale === 'en' ? 'Newsletter for new content' : 'نشرة بريدية بمحتوى جديد' ?></h4>
        <p style="margin:0; color:#B7C0BA; font-size:.9rem;"><?= $locale === 'en' ? 'A simple note whenever new content is published — no spam. Entirely separate from booking an appointment.' : 'رسالة بسيطة كل ما ينشر محتوى جديد، بدون إزعاج. منفصلة تمامًا عن طلب الموعد.' ?></p>
      </div>
      <form method="post" action="/newsletter-subscribe.php" class="newsletter-form">
        <?= csrf_field() ?>
        <input type="hidden" name="redirect_to" value="<?= e(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH)) ?>">
        <div class="hp-field" aria-hidden="true"><label for="nl_website">leave blank</label><input type="text" id="nl_website" name="website" tabindex="-1" autocomplete="off"></div>
        <input type="email" name="email" placeholder="<?= $locale === 'en' ? 'Your email' : 'بريدك الإلكتروني' ?>" required>
        <label class="newsletter-consent"><input type="checkbox" name="newsletter_consent" value="1" required> <?= $locale === 'en' ? 'I agree to receive emails, and can unsubscribe anytime.' : 'موافق/ة أستلم رسائل، وفيني ألغي اشتراكي أي وقت.' ?></label>
        <button type="submit" class="btn btn-light"><?= $locale === 'en' ? 'Subscribe' : 'اشترك' ?></button>
      </form>
      <?php if ($newsletterStatus === 'ok'): ?><p class="field-hint" style="color:#BFE3CC;"><?= $locale === 'en' ? 'Subscribed, thank you! ✓' : 'تم الاشتراك، شكرًا إلك! ✓' ?></p><?php endif; ?>
      <?php if ($newsletterStatus === 'pending'): ?><p class="field-hint" style="color:#BFE3CC;"><?= $locale === 'en' ? 'Almost there! Check your email and open the confirmation link.' : 'شبه خلص! تفقّد بريدك وافتح رابط التأكيد لإتمام الاشتراك.' ?></p><?php endif; ?>
      <?php if ($newsletterStatus === 'already'): ?><p class="field-hint" style="color:#BFE3CC;"><?= $locale === 'en' ? 'This email is already subscribed.' : 'هالبريد مشترك أصلًا بالنشرة.' ?></p><?php endif; ?>
      <?php if ($newsletterStatus === 'error'): ?><p class="field-hint" style="color:#E9B3A6;"><?= $locale === 'en' ? 'Something went wrong — check the email and try again.' : 'صار في مشكلة، تأكد من البريد وحاول كمان مرة.' ?></p><?php endif; ?>
    </div>

    <div class="footer-grid">
      <div>
        <h4>تالا <?= $locale === 'en' ? '| Psychotherapy' : '| معالجة نفسية' ?></h4>
        <p style="color:#B7C0BA; font-size:.92rem; max-width:280px;"><?= $locale === 'en' ? "A calm space to talk and get psychological support in {$cityEn}." : "مساحة هادئة للحديث والدعم النفسي في {$city}." ?></p>
        <div class="footer-emergency">
          <?= $locale === 'en' ? e($emergencyNoticeEn) : e(get_setting('emergency_notice')) ?>
        </div>
      </div>
      <div>
        <h4><?= t('footer_site_section') ?></h4>
        <ul>
          <li><a href="/index.php"><?= t('nav_home') ?></a></li>
          <li><a href="/about.php"><?= t('nav_about') ?></a></li>
          <li><a href="/services.php"><?= t('nav_services') ?></a></li>
          <li><a href="/how-it-works.php"><?= $locale === 'en' ? 'How Sessions Work' : 'كيف بتصير الجلسة؟' ?></a></li>
          <li><a href="/start-here.php"><?= t('nav_start_here') ?></a></li>
          <li><a href="/articles/index.php"><?= $locale === 'en' ? 'Articles & Guides' : 'المقالات والأدلة' ?></a></li>
          <li><a href="/resources/index.php"><?= $locale === 'en' ? 'Useful Resources' : 'مصادر مفيدة' ?></a></li>
          <li><a href="/faq.php"><?= t('nav_faq') ?></a></li>
          <?php if ($eventsEnabled): ?><li><a href="/events/index.php"><?= $locale === 'en' ? 'Talks & Events' : 'محاضرات وفعاليات' ?></a></li><?php endif; ?>
        </ul>
      </div>
      <div>
        <h4><?= t('footer_contact_section') ?></h4>
        <ul>
          <li><a href="/book.php"><?= t('nav_book') ?></a></li>
          <li><a href="/contact.php"><?= t('nav_contact') ?></a></li>
          <li><a href="/toolkit/index.php"><?= $locale === 'en' ? 'Your Space (tools)' : 'مساحة إلك (أدوات)' ?></a></li>
          <?php if ($phone): ?><li><?= $locale === 'en' ? 'Phone' : 'هاتف' ?>: <a href="tel:<?= e(phone_intl_href($phone)) ?>"><?= e($phone) ?></a></li><?php endif; ?>
          <?php if ($whatsapp && $whatsappEnabled): ?><li>WhatsApp: <a href="https://wa.me/<?= e(whatsapp_digits($whatsapp)) ?>" target="_blank" rel="noopener"><?= e($whatsapp) ?></a></li><?php endif; ?>
          <?php if ($email): ?><li><?= $locale === 'en' ? 'Email' : 'بريد' ?>: <a href="mailto:<?= e($email) ?>"><?= e($email) ?></a></li><?php endif; ?>
          <?php if ($hours): ?><li><?= $locale === 'en' ? 'Hours' : 'ساعات العمل' ?>: <?= e($hours) ?></li><?php endif; ?>
        </ul>
      </div>
      <div>
        <h4><?= t('footer_policies_section') ?></h4>
        <ul>
          <li><a href="/privacy.php"><?= t('footer_privacy') ?></a></li>
          <li><a href="/terms.php"><?= t('footer_terms') ?></a></li>
          <li><a href="/admin/index.php"><?= t('footer_admin_login') ?></a></li>
        </ul>
      </div>
    </div>
    <div class="footer-bottom">
      <span>© <?= date('Y') ?> تالا <?= $locale === 'en' ? '| Psychotherapy' : '| معالجة نفسية' ?> — <?= t('footer_rights') ?>.</span>
      <span><?= $locale === 'en' ? 'Homs, Syria' : 'حمص، سوريا' ?></span>
    </div>
  </div>
</footer>

<script src="/assets/js/main.js?v=<?= filemtime(__DIR__ . '/../assets/js/main.js') ?>"></script>
</body>
</html>
