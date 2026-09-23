    </main>
  </div>
</div>

<div class="command-palette-backdrop" id="paletteBackdrop" hidden>
  <div class="command-palette" role="dialog" aria-modal="true" aria-label="بحث وانتقال سريع">
    <div class="command-palette-input-row">
      <?php admin_icon('search') ?>
      <input type="text" id="paletteInput" placeholder="اكتب للبحث عن صفحة، مريض، طلب، مقالة..." autocomplete="off">
      <kbd>Esc</kbd>
    </div>
    <div class="command-palette-results" id="paletteResults"></div>
  </div>
</div>

<script>
  window.__ADMIN_NAV_DESTINATIONS__ = <?php
    $destinations = [];
    foreach (array_merge($NAV_GROUPS, $NAV_BOTTOM) as $group) {
        if (!nav_user_can($group['roles'], $adminUser)) { continue; }
        if (isset($group['require_setting']) && !setting_bool($group['require_setting'])) { continue; }
        foreach ($group['tabs'] as $tab) {
            if (nav_user_can($tab['roles'], $adminUser)) {
                $destinations[] = ['title' => $group['label'] === $tab['label'] ? $tab['label'] : $group['label'] . ' — ' . $tab['label'], 'href' => $tab['href']];
            }
        }
    }
    echo json_encode($destinations, JSON_UNESCAPED_UNICODE);
  ?>;
</script>
<script src="/assets/js/admin.js"></script>
</body>
</html>
