document.addEventListener('DOMContentLoaded', function () {
  var toggle = document.querySelector('.nav-toggle');
  var links = document.querySelector('.nav-links');
  if (toggle && links) {
    toggle.addEventListener('click', function () {
      var open = links.classList.toggle('open');
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
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
});
