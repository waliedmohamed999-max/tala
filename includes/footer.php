<?php
require_once __DIR__ . '/i18n.php';
$phone = get_setting('phone');
$whatsapp = get_setting('whatsapp');
$email = get_setting('email');
$hours = get_setting('working_hours');
$city = get_setting('city', 'حمص');
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
        <p style="color:#B7C0BA; font-size:.92rem; max-width:280px;"><?= $locale === 'en' ? "A calm space to talk and get psychological support in {$city}, Syria." : "مساحة هادئة للحديث والدعم النفسي في {$city}، سوريا." ?></p>
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
          <?php if ($phone): ?><li><?= $locale === 'en' ? 'Phone' : 'هاتف' ?>: <a href="tel:<?= e($phone) ?>"><?= e($phone) ?></a></li><?php endif; ?>
          <?php if ($whatsapp): ?><li>WhatsApp: <a href="https://wa.me/<?= e(preg_replace('/[^0-9]/', '', $whatsapp)) ?>" target="_blank" rel="noopener"><?= e($whatsapp) ?></a></li><?php endif; ?>
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
