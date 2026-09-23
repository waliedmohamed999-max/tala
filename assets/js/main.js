document.addEventListener('DOMContentLoaded', function () {
  var toggle = document.querySelector('.nav-toggle');
  var links = document.querySelector('.nav-links');
  if (toggle && links) {
    toggle.addEventListener('click', function () {
      var open = links.classList.toggle('open');
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
    function closeMenu() {
      links.classList.remove('open');
      toggle.setAttribute('aria-expanded', 'false');
    }
    document.addEventListener('click', function (event) {
      if (!links.contains(event.target) && !toggle.contains(event.target)) closeMenu();
    });
    document.addEventListener('keydown', function (event) {
      if (event.key === 'Escape' && links.classList.contains('open')) {
        closeMenu();
        toggle.focus();
      }
    });
    links.addEventListener('click', function (event) {
      if (event.target.closest('a')) closeMenu();
    });
  }

  // Track the time a booking/contact form has been open, to pair with the
  // server-side honeypot check as a light anti-spam signal.
  document.querySelectorAll('form[data-timed]').forEach(function (form) {
    var startedAt = Date.now();
    form.addEventListener('submit', function () {
      var field = form.querySelector('input[name="form_started_at"]');
      if (field) field.value = startedAt;
    });
  });

  // --- Floating WhatsApp button -----------------------------------------
  var waWrap = document.getElementById('whatsappFabWrap');
  var waBubble = document.getElementById('whatsappFabBubble');
  var waClose = document.getElementById('whatsappFabClose');
  if (waWrap) {
    // Remember the teaser bubble was dismissed, per browser — never sent anywhere.
    try {
      if (waBubble && localStorage.getItem('tala_whatsapp_bubble_dismissed') === '1') {
        waBubble.style.display = 'none';
      }
    } catch (e) {}
    if (waClose && waBubble) {
      waClose.addEventListener('click', function () {
        waBubble.style.display = 'none';
        try { localStorage.setItem('tala_whatsapp_bubble_dismissed', '1'); } catch (e) {}
      });
    }

    // Never let the button visually sit over a submit button or the privacy
    // consent checkbox — hide it while either is in view instead.
    var sensitiveEls = document.querySelectorAll('.consent-box, form button[type="submit"]');
    if (sensitiveEls.length && 'IntersectionObserver' in window) {
      var visibleCount = 0;
      var observer = new IntersectionObserver(function (entries) {
        entries.forEach(function (entry) {
          visibleCount += entry.isIntersecting ? 1 : -1;
        });
        waWrap.classList.toggle('is-hidden', visibleCount > 0);
      }, { threshold: 0.15 });
      sensitiveEls.forEach(function (el) { observer.observe(el); });
    }
  }
});
