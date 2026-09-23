<?php
require_once __DIR__ . '/includes/functions.php';
require_once __DIR__ . '/includes/i18n.php';

$pageTitle = get_setting('display_title');
$locale = current_locale();
$pageDescription = $locale === 'en'
    ? 'Tala, psychotherapist in Homs, Syria. A safe space to talk, and a simple first step toward support that fits you.'
    : 'تالا معالجة نفسية في حمص، سوريا. مساحة آمنة للحديث، وخطوة أولى بسيطة نحو دعم نفسي يناسبك.';
require __DIR__ . '/includes/header.php';

$services = published_services();
$articles = db()->query("SELECT " . articles_public_select() . ", c.name AS category_name, c.slug AS category_slug
    FROM " . articles_public_from() . "
    WHERE a.published_content IS NOT NULL ORDER BY a.published_at DESC LIMIT 3")->fetchAll();
$faqs = array_slice(published_faqs(), 0, 4);
$bioShort = get_setting('bio_short');
$photo = get_setting('photo_path');
$specialties = get_setting('specialties');
$yearsExp = get_setting('years_experience');
$credentials = get_setting('credentials');
?>

<!-- Hero -->
<section class="hero container">
  <div class="hero-content">
    <?php if ($locale === 'en'): ?>
      <span class="eyebrow">Tala | Psychotherapy — Homs</span>
      <h1>A safe space to talk, understand yourself, and take a new step</h1>
      <p style="font-size:1.08rem;">If you feel like you need someone to listen without judgment, or you want to understand more about what you're going through, you can start with a simple step. Tala is here to walk alongside you, at the space and pace that fits you.</p>
      <div class="hero-actions">
        <a href="/book.php" class="btn btn-primary">Book an Appointment</a>
        <a href="/how-it-works.php" class="btn btn-outline">How it works</a>
      </div>
      <p class="hero-note">This site is not for emergencies. If you or someone else is in immediate danger, contact local emergency services right away or go to the nearest emergency room.</p>
    <?php else: ?>
      <span class="eyebrow">تالا | معالجة نفسية — حمص</span>
      <h1>مساحة آمنة لتحكي، تفهم حالك، وتبلّش خطوة جديدة</h1>
      <p style="font-size:1.08rem;">إذا حاسس إنك محتاج حدا يسمعك من دون حكم، أو بدك تفهم أكتر شو عم يصير معك، فيك تبدأ بخطوة بسيطة. تالا هون لترافقك بالمساحة والوقت يلي بيناسبك.</p>
      <div class="hero-actions">
        <a href="/book.php" class="btn btn-primary">اطلب موعد</a>
        <a href="/how-it-works.php" class="btn btn-outline">تعرّف على طريقة العمل</a>
      </div>
      <p class="hero-note">هالموقع مو مخصص للطوارئ. بحال في خطر مباشر عليك أو على حدا تاني، تواصل فورًا مع خدمات الطوارئ المحلية أو أقرب قسم إسعاف.</p>
    <?php endif; ?>
  </div>
  <div class="hero-art hero-portrait">
    <img src="/assets/images/therapy-room.png"
         alt="<?= $locale === 'en' ? 'Illustration of a calm, sunlit counseling room' : 'صورة تعبيرية لغرفة جلسات هادئة بإضاءة طبيعية' ?>"
         width="1254" height="1254" fetchpriority="high">
  </div>
</section>

<!-- About teaser -->
<section class="section section--alt">
  <div class="container split-layout split-layout--intro">
    <div>
      <?php if ($photo): ?>
        <img src="<?= e($photo) ?>" alt="<?= $locale === 'en' ? 'Photo of Tala' : 'صورة تالا' ?>" style="border-radius: var(--radius-lg); box-shadow: var(--shadow-card);">
      <?php else: ?>
        <div style="background:var(--color-sage-light); border-radius:var(--radius-lg); aspect-ratio:1/1; display:flex; align-items:center; justify-content:center; color:var(--color-sage-dark); font-size:3rem; font-weight:800;">ت</div>
      <?php endif; ?>
    </div>
    <div>
      <?php if ($locale === 'en'): ?>
        <span class="eyebrow">Meet Tala</span>
        <h2>A psychotherapist who walks with you, without judgment</h2>
        <?php if ($bioShort || $credentials || $specialties): ?>
          <?php content_translation_notice('/about.php'); ?>
        <?php else: ?>
          <p>We're still preparing Tala's full introduction — her qualifications, experience, and areas of work will be added here once confirmed.</p>
        <?php endif; ?>
        <a href="/about.php?lang=en" class="btn btn-outline">Read more about Tala</a>
      <?php else: ?>
        <span class="eyebrow">تعرّف على تالا</span>
        <h2>معالجة نفسية بترافقك بلا أحكام</h2>
        <?php if ($bioShort): ?>
          <p><?= e($bioShort) ?></p>
        <?php else: ?>
          <p>لسا عم نجهّز التعريف الكامل عن تالا — مؤهلاتها وخبرتها ومجالات عملها رح تُضاف هون فور ما تأكدها.</p>
        <?php endif; ?>
        <?php if ($credentials): ?><p><strong>المؤهلات:</strong> <?= e($credentials) ?></p><?php endif; ?>
        <?php if ($yearsExp): ?><p><strong>سنوات الخبرة:</strong> <?= e($yearsExp) ?></p><?php endif; ?>
        <?php if ($specialties): ?><p><strong>الاختصاصات:</strong> <?= e($specialties) ?></p><?php endif; ?>
        <a href="/about.php" class="btn btn-outline">اقرأ المزيد عن تالا</a>
      <?php endif; ?>
    </div>
  </div>
</section>

<!-- Can we help -->
<section class="section">
  <div class="container">
    <?php if ($locale === 'en'): ?>
      <div class="section-head center">
        <span class="eyebrow">We may be able to help if...</span>
        <h2>Situations many people come to talk about</h2>
        <p>These are general examples of topics many people talk about — not a diagnosis, and not a confirmation that all these areas are within the current scope of practice. Exact details are on the Services page.</p>
      </div>
      <div class="grid grid-4">
        <div class="card"><div class="card-icon">🌿</div><h3>Daily life pressures</h3><p>When work, study, or life circumstances get heavier than your capacity.</p></div>
        <div class="card"><div class="card-icon">💬</div><h3>Understanding emotions</h3><p>When you feel a buildup of emotions and don't know how to name or handle them.</p></div>
        <div class="card"><div class="card-icon">🤝</div><h3>Relationships & communication</h3><p>Improving how you talk and connect with people close to you.</p></div>
        <div class="card"><div class="card-icon">🪑</div><h3>A space to talk</h3><p>Sometimes you just need someone to listen calmly, without judging or analyzing quickly.</p></div>
      </div>
    <?php else: ?>
      <div class="section-head center">
        <span class="eyebrow">يمكن نساعدك إذا...</span>
        <h2>حالات كتير بتيجي ع بالها تحكي فيها</h2>
        <p>هاي أمثلة عامة عن المواضيع يلي ممكن يحكي فيها ناس كتير — مو تشخيص، ومو تأكيد إن كل هالمجالات ضمن نطاق العمل الحالي. التفاصيل الدقيقة موجودة بصفحة الخدمات.</p>
      </div>
      <div class="grid grid-4">
        <div class="card"><div class="card-icon">🌿</div><h3>ضغوط الحياة اليومية</h3><p>لما الشغل، الدراسة، أو ظروف الحياة تصير تقيلة أكتر من طاقتك.</p></div>
        <div class="card"><div class="card-icon">💬</div><h3>فهم المشاعر</h3><p>لما بتحس بمشاعر متراكمة وما عارف كيف تسمّيها أو تتعامل معها.</p></div>
        <div class="card"><div class="card-icon">🤝</div><h3>العلاقات والتواصل</h3><p>تحسين طريقة الحكي والتواصل مع الناس المقربين منك.</p></div>
        <div class="card"><div class="card-icon">🪑</div><h3>مساحة للحديث</h3><p>أحيانًا بس بدك حدا يسمعك بهدوء، من دون ما يحكم أو يحلل بسرعة.</p></div>
      </div>
    <?php endif; ?>
  </div>
</section>

<!-- Services -->
<section class="section section--sage">
  <div class="container">
    <div class="section-head center">
      <span class="eyebrow"><?= t('nav_services') ?></span>
      <h2><?= $locale === 'en' ? 'What can you book?' : 'شو بتقدر تحجز؟' ?></h2>
    </div>
    <?php if (empty($services)): ?>
      <div class="card text-center" style="max-width:560px; margin:0 auto;">
        <?php if ($locale === 'en'): ?>
          <p>We're still preparing the full services page (session types, duration, and price), pending Tala's confirmation. You can reach out directly and we'll explain what's currently available.</p>
          <a href="/contact.php?lang=en" class="btn btn-primary">Contact us to ask</a>
        <?php else: ?>
          <p>لسا عم نجهّز صفحة الخدمات بالتفاصيل الكاملة (نوع الجلسات، المدة، والسعر) بانتظار تأكيد تالا. فيك تتواصل معنا مباشرة نوضح لك الإمكانيات المتاحة حاليًا.</p>
          <a href="/contact.php" class="btn btn-primary">تواصل للاستفسار</a>
        <?php endif; ?>
      </div>
    <?php elseif ($locale === 'en'): ?>
      <?php content_translation_notice('/services.php'); ?>
    <?php else: ?>
      <div class="grid grid-3">
        <?php foreach ($services as $i => $s): ?>
          <div class="card">
            <?php render_card_thumb(($i % 6) + 1); ?>
            <h3><?= e($s['name']) ?></h3>
            <?php if ($s['summary']): ?><p><?= e($s['summary']) ?></p><?php endif; ?>
            <a href="/service.php?slug=<?= urlencode($s['slug']) ?>" class="btn btn-outline">التفاصيل</a>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>
</section>

<!-- How it starts -->
<section class="section">
  <div class="container">
    <?php if ($locale === 'en'): ?>
      <div class="section-head center"><span class="eyebrow">How it starts</span><h2>Four simple steps</h2></div>
      <div class="steps">
        <div class="step"><div class="step-number">1</div><h3>Send a request</h3><p>Fill the "Book an Appointment" form with simple info and your preferred contact method.</p></div>
        <div class="step"><div class="step-number">2</div><h3>We reach out</h3><p>We get in touch to arrange details and answer any questions you have.</p></div>
        <div class="step"><div class="step-number">3</div><h3>Confirm the appointment</h3><p>We agree together on the time and place (or a remote session, if available).</p></div>
        <div class="step"><div class="step-number">4</div><h3>Start the session</h3><p>Your first session begins in a calm space — you don't need to prepare yourself perfectly.</p></div>
      </div>
    <?php else: ?>
      <div class="section-head center"><span class="eyebrow">كيف بتبلّش؟</span><h2>أربع خطوات بسيطة</h2></div>
      <div class="steps">
        <div class="step"><div class="step-number">1</div><h3>إرسال طلب</h3><p>تعبّي نموذج «اطلب موعد» بمعلومات بسيطة ووسيلة التواصل يلي بتفضّلها.</p></div>
        <div class="step"><div class="step-number">2</div><h3>التواصل معك</h3><p>منتواصل معك لنرتّب التفاصيل ونجاوب على أي سؤال عندك.</p></div>
        <div class="step"><div class="step-number">3</div><h3>تأكيد الموعد</h3><p>بنتفق سوا على الوقت والمكان (أو الجلسة عن بُعد إذا كانت متاحة).</p></div>
        <div class="step"><div class="step-number">4</div><h3>بدء الجلسة</h3><p>بتبلّش أول جلسة بمساحة هادئة، وما إلك داعي تحضّر حالك بشكل مثالي.</p></div>
      </div>
    <?php endif; ?>
  </div>
</section>

<!-- Privacy -->
<section class="section section--alt">
  <div class="container" style="max-width:820px;">
    <?php if ($locale === 'en'): ?>
      <span class="eyebrow">Privacy</span>
      <h2>Your information is kept private</h2>
      <p>We collect the least amount of information possible, and only use it to arrange your appointment and contact you. Access to booking requests is restricted to authorized people only. For more details, read the full <a href="/privacy.php?lang=en" style="color:var(--color-sage-dark); font-weight:700;">Privacy Policy</a>.</p>
    <?php else: ?>
      <span class="eyebrow">الخصوصية</span>
      <h2>معلوماتك محفوظة وخاصة</h2>
      <p>بنجمع أقل قدر ممكن من معلوماتك، وما منستخدمها إلا لترتيب موعدك والتواصل معك. الوصول لطلبات الحجز محصور بالأشخاص المصرّح لهم فقط. لأي تفاصيل إضافية، فيك تقرأ <a href="/privacy.php" style="color:var(--color-sage-dark); font-weight:700;">سياسة الخصوصية</a> كاملة.</p>
    <?php endif; ?>
  </div>
</section>

<!-- Articles -->
<?php if (!empty($articles)): ?>
<section class="section">
  <div class="container">
    <div class="section-head center">
      <span class="eyebrow"><?= $locale === 'en' ? 'From the library' : 'من المكتبة الإرشادية' ?></span>
      <h2><?= $locale === 'en' ? 'Featured articles' : 'مقالات مختارة' ?></h2>
    </div>
    <?php if ($locale === 'en'): ?>
      <?php content_translation_notice('/articles/index.php'); ?>
    <?php else: ?>
      <div class="grid grid-3">
        <?php foreach ($articles as $a): ?>
          <article class="card article-card">
            <?php render_card_thumb(category_art_variant($a['category_slug'] ?? null)); ?>
            <?php if ($a['category_name']): ?><span class="cat-tag"><?= category_icon($a['category_slug']) ?> <?= e($a['category_name']) ?></span><?php endif; ?>
            <h3><a href="/articles/article.php?slug=<?= urlencode($a['slug']) ?>"><?= e($a['title']) ?></a></h3>
            <p><?= e($a['excerpt']) ?></p>
            <div class="meta"><span>⏱ <?= (int)$a['read_minutes'] ?> دقايق قراءة</span></div>
          </article>
        <?php endforeach; ?>
      </div>
      <div class="text-center" style="margin-top:30px;">
        <a href="/articles/index.php" class="btn btn-outline">شوف كل المقالات</a>
      </div>
    <?php endif; ?>
  </div>
</section>
<?php endif; ?>

<!-- FAQ -->
<?php if (!empty($faqs)): ?>
<section class="section section--sage">
  <div class="container" style="max-width:800px;">
    <div class="section-head center">
      <span class="eyebrow"><?= t('nav_faq') ?></span>
      <h2><?= $locale === 'en' ? 'You might have these questions' : 'ممكن يكون عندك هالأسئلة' ?></h2>
    </div>
    <?php if ($locale === 'en'): ?>
      <?php content_translation_notice('/faq.php'); ?>
    <?php else: ?>
      <div>
        <?php foreach ($faqs as $f): ?>
          <details class="faq-item">
            <summary><?= e($f['question']) ?></summary>
            <div class="faq-answer"><?= e($f['answer']) ?></div>
          </details>
        <?php endforeach; ?>
      </div>
      <div class="text-center" style="margin-top:26px;">
        <a href="/faq.php" class="btn btn-outline">كل الأسئلة الشائعة</a>
      </div>
    <?php endif; ?>
  </div>
</section>
<?php endif; ?>

<!-- Final CTA -->
<section class="section">
  <div class="container text-center" style="max-width:680px;">
    <?php if ($locale === 'en'): ?>
      <h2>Ready to start?</h2>
      <p>Get in touch and we'll arrange the appointment that fits you. No pressure, and you can ask any question before deciding.</p>
      <a href="/book.php" class="btn btn-primary">Book Now</a>
    <?php else: ?>
      <h2>جاهز تبلّش؟</h2>
      <p>احكي معنا لنرتّب الموعد المناسب إلك. ما في ضغط، وفيك تسأل أي سؤال قبل ما تقرر.</p>
      <a href="/book.php" class="btn btn-primary">اطلب موعد الآن</a>
    <?php endif; ?>
  </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
