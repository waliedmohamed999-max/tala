// Shared privacy helpers for "مساحة إلك" tools — every key any tool writes
// to localStorage must be listed here so "clear everything" is complete.
window.TalaToolkitPrivacy = (function () {
  var ALL_KEYS = ['tala_journal_v1', 'tala_prep_v1', 'tala_reading_list_v1'];

  function clearAll() {
    var cleared = [];
    ALL_KEYS.forEach(function (k) {
      try {
        if (localStorage.getItem(k) !== null) { cleared.push(k); }
        localStorage.removeItem(k);
      } catch (e) {}
    });
    return cleared;
  }

  function bindClearAllButton(buttonId, statusId) {
    var btn = document.getElementById(buttonId);
    if (!btn) return;
    btn.addEventListener('click', function () {
      if (!confirm('متأكد إنك بدك تمسح كل بيانات أدوات الموقع المحفوظة على هالجهاز (رتّب أفكارك، قائمة التحضير، قائمة القراءة)؟ ما فيك ترجعها بعدين.')) return;
      clearAll();
      var status = statusId && document.getElementById(statusId);
      if (status) { status.textContent = 'تم مسح كل البيانات المحلية من هالجهاز ✓'; }
      if (typeof window.location.reload === 'function') {
        setTimeout(function () { window.location.reload(); }, 800);
      }
    });
  }

  return { clearAll: clearAll, bindClearAllButton: bindClearAllButton };
})();
