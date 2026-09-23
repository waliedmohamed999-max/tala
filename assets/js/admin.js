(function () {
  'use strict';

  function csrfToken() {
    var meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.content : '';
  }

  // --- Sidebar collapse (desktop) -----------------------------------
  var shell = document.getElementById('adminShell');
  var collapseBtn = document.getElementById('collapseBtn');
  if (collapseBtn && shell) {
    collapseBtn.addEventListener('click', function () {
      var willCollapse = !shell.classList.contains('is-collapsed');
      shell.classList.toggle('is-collapsed', willCollapse);
      var token = csrfToken();
      fetch('/admin/ui-pref.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'csrf_token=' + encodeURIComponent(token) + '&pref=sidebar_collapsed&value=' + (willCollapse ? '1' : '0'),
      }).catch(function () {});
    });
  }

  // --- Mobile drawer ---------------------------------------------------
  var sidebar = document.getElementById('adminSidebar');
  var backdrop = document.getElementById('sidebarBackdrop');
  var mobileMenuBtn = document.getElementById('mobileMenuBtn');

  function openDrawer() {
    if (sidebar) sidebar.classList.add('is-open');
    if (backdrop) backdrop.hidden = false;
  }
  function closeDrawer() {
    if (sidebar) sidebar.classList.remove('is-open');
    if (backdrop) backdrop.hidden = true;
  }
  if (mobileMenuBtn) mobileMenuBtn.addEventListener('click', openDrawer);
  if (backdrop) backdrop.addEventListener('click', closeDrawer);

  // --- Generic popovers (notifications, user menu) ----------------------
  function wirePopover(btnId, panelId) {
    var btn = document.getElementById(btnId);
    var panel = document.getElementById(panelId);
    if (!btn || !panel) return;
    btn.addEventListener('click', function (e) {
      e.stopPropagation();
      var isHidden = panel.hidden;
      document.querySelectorAll('.topbar-popover').forEach(function (p) { p.hidden = true; });
      panel.hidden = !isHidden;
    });
    panel.addEventListener('click', function (e) { e.stopPropagation(); });
  }
  wirePopover('notifBtn', 'notifPanel');
  wirePopover('userMenuBtn', 'userMenuPanel');
  document.addEventListener('click', function () {
    document.querySelectorAll('.topbar-popover').forEach(function (p) { p.hidden = true; });
  });

  // --- Context menus (⋯ dropdowns inside tables/cards) -------------------
  document.querySelectorAll('[data-menu-trigger]').forEach(function (btn) {
    var menu = document.getElementById(btn.getAttribute('data-menu-trigger'));
    if (!menu) return;
    btn.addEventListener('click', function (e) {
      e.stopPropagation();
      var isHidden = menu.hidden;
      document.querySelectorAll('.context-menu').forEach(function (m) { m.hidden = true; });
      menu.hidden = !isHidden;
    });
    menu.addEventListener('click', function (e) { e.stopPropagation(); });
  });
  document.addEventListener('click', function () {
    document.querySelectorAll('.context-menu').forEach(function (m) { m.hidden = true; });
  });

  // --- Command palette --------------------------------------------------
  var paletteBackdrop = document.getElementById('paletteBackdrop');
  var paletteInput = document.getElementById('paletteInput');
  var paletteResults = document.getElementById('paletteResults');
  var searchBtn = document.getElementById('searchBtn');
  var destinations = window.__ADMIN_NAV_DESTINATIONS__ || [];
  var searchDebounce = null;

  function renderResults(navMatches, dataGroups) {
    var html = '';
    if (navMatches.length) {
      html += '<div class="palette-group-label">صفحات</div>';
      navMatches.forEach(function (d) {
        html += '<a class="palette-item" href="' + d.href + '">' + escapeHtml(d.title) + '</a>';
      });
    }
    (dataGroups || []).forEach(function (g) {
      html += '<div class="palette-group-label">' + escapeHtml(g.label) + '</div>';
      g.items.forEach(function (it) {
        html += '<a class="palette-item" href="' + it.href + '">' + escapeHtml(it.title) + (it.subtitle ? ' <span class="palette-item-sub">' + escapeHtml(it.subtitle) + '</span>' : '') + '</a>';
      });
    });
    paletteResults.innerHTML = html || '<p class="topbar-popover-empty">ما في نتائج.</p>';
  }

  function escapeHtml(s) {
    return String(s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

  function openPalette() {
    if (!paletteBackdrop) return;
    paletteBackdrop.hidden = false;
    paletteInput.value = '';
    renderResults(destinations.slice(0, 8), []);
    setTimeout(function () { paletteInput.focus(); }, 30);
  }
  function closePalette() {
    if (paletteBackdrop) paletteBackdrop.hidden = true;
  }

  if (searchBtn) searchBtn.addEventListener('click', openPalette);
  if (paletteBackdrop) {
    paletteBackdrop.addEventListener('click', function (e) {
      if (e.target === paletteBackdrop) closePalette();
    });
  }

  if (paletteInput) {
    paletteInput.addEventListener('input', function () {
      var q = paletteInput.value.trim();
      var navMatches = q === '' ? destinations.slice(0, 8) : destinations.filter(function (d) {
        return d.title.indexOf(q) !== -1;
      });
      renderResults(navMatches, []);

      clearTimeout(searchDebounce);
      if (q.length < 2) return;
      searchDebounce = setTimeout(function () {
        fetch('/admin/search.php?q=' + encodeURIComponent(q))
          .then(function (r) { return r.json(); })
          .then(function (data) { renderResults(navMatches, data.groups || []); })
          .catch(function () {});
      }, 200);
    });
  }

  document.addEventListener('keydown', function (e) {
    var isMac = navigator.platform.toUpperCase().indexOf('MAC') !== -1;
    if ((isMac ? e.metaKey : e.ctrlKey) && e.key.toLowerCase() === 'k') {
      e.preventDefault();
      openPalette();
    } else if (e.key === 'Escape') {
      closePalette();
      closeDrawer();
      document.querySelectorAll('.topbar-popover, .context-menu').forEach(function (p) { p.hidden = true; });
    }
  });
})();
