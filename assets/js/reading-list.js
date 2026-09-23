// Client-only "reading list" — stored in localStorage, never sent to the
// server. Shared by article pages (save button) and toolkit/reading-list.php.
window.TalaReadingList = (function () {
  var KEY = 'tala_reading_list_v1';

  function getAll() {
    try { return JSON.parse(localStorage.getItem(KEY) || '[]'); } catch (e) { return []; }
  }
  function has(slug) {
    return getAll().some(function (i) { return i.slug === slug; });
  }
  function add(item) {
    try {
      var list = getAll();
      if (!list.some(function (i) { return i.slug === item.slug; })) {
        list.unshift(item);
        localStorage.setItem(KEY, JSON.stringify(list));
      }
    } catch (e) {}
  }
  function remove(slug) {
    try {
      var list = getAll().filter(function (i) { return i.slug !== slug; });
      localStorage.setItem(KEY, JSON.stringify(list));
    } catch (e) {}
  }

  return { getAll: getAll, has: has, add: add, remove: remove };
})();

document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('[data-save-article]').forEach(function (btn) {
    var slug = btn.dataset.saveArticle;
    var title = btn.dataset.title;
    var url = btn.dataset.url;

    function refresh() {
      var saved = TalaReadingList.has(slug);
      btn.textContent = saved ? '✓ محفوظة بقائمتك' : '📌 احفظ للقراءة لاحقًا';
      btn.classList.toggle('saved', saved);
    }

    btn.addEventListener('click', function () {
      if (TalaReadingList.has(slug)) {
        TalaReadingList.remove(slug);
      } else {
        TalaReadingList.add({ slug: slug, title: title, url: url });
      }
      refresh();
    });
    refresh();
  });
});
