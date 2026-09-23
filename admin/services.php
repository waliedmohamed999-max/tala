<?php
require_once __DIR__ . '/../includes/auth.php';
$adminUser = require_content_access();
$activeNav = 'services';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify() && ($_POST['action'] ?? '') === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    $inUse = db()->prepare('SELECT COUNT(*) FROM appointment_requests WHERE service_id = ?');
    $inUse->execute([$id]);
    $inUse2 = db()->prepare('SELECT COUNT(*) FROM appointments WHERE service_id = ?');
    $inUse2->execute([$id]);
    if ((int)$inUse->fetchColumn() > 0 || (int)$inUse2->fetchColumn() > 0) {
        flash_set('error', 'ما فيك تحذف خدمة مرتبطة بطلبات مواعيد أو مواعيد فعلية. فيك توقفها بدل الحذف.');
    } else {
        $del = db()->prepare('DELETE FROM services WHERE id = ?');
        $del->execute([$id]);
        flash_set('success', 'تم حذف الخدمة.');
    }
    redirect('/admin/services.php');
}

// Editors/reviewers/owner can submit a draft (or a paused service) for
// review — this never publishes anything by itself, only reviewer/owner can
// actually publish, from /admin/review-queue.php or the "نشر" action below.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify() && ($_POST['action'] ?? '') === 'submit_review') {
    $id = (int)($_POST['id'] ?? 0);
    set_service_workflow_status($id, 'pending_review');
    flash_set('success', 'تم إرسال الخدمة لقائمة الاعتماد.');
    redirect('/admin/services.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify() && ($_POST['action'] ?? '') === 'publish' && can_review($adminUser)) {
    $id = (int)($_POST['id'] ?? 0);
    set_service_workflow_status($id, 'published', $adminUser['name']);
    flash_set('success', 'تم نشر الخدمة.');
    redirect('/admin/services.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify() && ($_POST['action'] ?? '') === 'pause' && can_review($adminUser)) {
    $id = (int)($_POST['id'] ?? 0);
    set_service_workflow_status($id, 'paused', $adminUser['name']);
    flash_set('success', 'تم إيقاف نشر الخدمة.');
    redirect('/admin/services.php');
}

$allServices = db()->query('SELECT * FROM services ORDER BY sort_order, name')->fetchAll();
$statusLabels = [
    'draft' => ['مسودة', 'status-new'],
    'pending_review' => ['بانتظار اعتماد تالا', 'status-contacting'],
    'published' => ['منشورة', 'status-confirmed'],
    'paused' => ['موقوفة', 'status-paused'],
];
$locationLabels = ['in_person' => 'حضوري', 'remote' => 'عن بُعد', 'both' => 'حضوري أو عن بُعد'];

$tabCounts = ['all' => count($allServices), 'draft' => 0, 'pending_review' => 0, 'published' => 0, 'paused' => 0];
foreach ($allServices as $s) { $tabCounts[$s['workflow_status']] = ($tabCounts[$s['workflow_status']] ?? 0) + 1; }
$tabs = ['all' => 'الكل', 'published' => 'منشورة', 'pending_review' => 'بانتظار المراجعة', 'draft' => 'مسودات', 'paused' => 'موقوفة'];

$statusFilter = $_GET['status'] ?? 'all';
if (!array_key_exists($statusFilter, $tabs)) { $statusFilter = 'all'; }
$services = $statusFilter === 'all' ? $allServices : array_values(array_filter($allServices, fn($s) => $s['workflow_status'] === $statusFilter));

$primaryAction = ['label' => 'خدمة جديدة', 'href' => '/admin/service-edit.php'];
$pageTitle = 'الخدمات';
require __DIR__ . '/includes/layout_top.php';
?>

<div class="notice-banner" style="margin-bottom:var(--admin-sp-4);">
  <strong>تذكير</strong>
  لا تنشر أي خدمة قبل ما تتأكد التفاصيل (المدة، السعر، وطريقة الحضور) من تالا. الخدمة غير المنشورة ما بتظهر للزوار إطلاقًا، والحجز الذاتي يحتاج نشر + مدة بالدقائق + ساعات عمل مضبوطة.
</div>

<div style="display:flex; gap:6px; margin-bottom:var(--admin-sp-4); flex-wrap:wrap;">
  <?php foreach ($tabs as $key => $label): ?>
    <a href="/admin/services.php?status=<?= $key ?>" class="btn <?= $statusFilter === $key ? 'btn-primary' : 'btn-outline' ?> btn-sm"><?= e($label) ?> (<?= (int)($tabCounts[$key] ?? 0) ?>)</a>
  <?php endforeach; ?>
</div>

<?php if (empty($services)): ?>
  <div class="admin-card empty-state">
    <p><?= $statusFilter === 'all' ? 'ما في خدمات مضافة لسا.' : 'ما في خدمات بهاي الحالة حاليًا.' ?></p>
    <?php if ($statusFilter === 'all'): ?><a href="/admin/service-edit.php" class="btn btn-primary btn-sm">+ خدمة جديدة</a><?php endif; ?>
  </div>
<?php else: ?>
  <div class="admin-card" style="padding:0; overflow:hidden;">
    <table class="data-table">
      <thead><tr><th>الخدمة</th><th>الحالة</th><th>طريقة الحضور</th><th>المدة والسعر</th><th>الظهور</th><th>آخر تعديل</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($services as $s): ?>
          <?php
          [$label, $cls] = $statusLabels[$s['workflow_status']] ?? ['—', 'status-new'];
          $durationPrice = [];
          if ($s['duration_minutes']) { $durationPrice[] = (int)$s['duration_minutes'] . ' د'; } elseif ($s['duration']) { $durationPrice[] = e($s['duration']); }
          if ($s['price_amount']) { $durationPrice[] = e((string)$s['price_amount']) . ' ' . e((string)$s['price_currency']); } elseif ($s['price']) { $durationPrice[] = e($s['price']); }
          $menuId = 'svc-menu-' . $s['id'];
          ?>
          <tr>
            <td><a href="/admin/service-edit.php?id=<?= (int)$s['id'] ?>" style="font-weight:700;"><?= e($s['name']) ?></a></td>
            <td><span class="status-badge <?= $cls ?>"><?= $label ?></span></td>
            <td class="field-hint"><?= $s['location_mode'] ? e($locationLabels[$s['location_mode']] ?? $s['location_mode']) : 'لم يُحدّد بعد' ?></td>
            <td class="field-hint"><?= !empty($durationPrice) ? implode(' · ', $durationPrice) : 'لم يُحدّد بعد' ?></td>
            <td class="field-hint">
              <?= $s['show_on_website'] ? '🌐 الموقع' : '' ?>
              <?= ($s['show_on_website'] && $s['bookable_online']) ? ' · ' : '' ?>
              <?= $s['bookable_online'] ? '📅 حجز ذاتي' : '' ?>
              <?= (!$s['show_on_website'] && !$s['bookable_online']) ? 'غير ظاهرة' : '' ?>
            </td>
            <td class="field-hint"><?= e(date('Y/m/d', strtotime($s['updated_at']))) ?></td>
            <td>
              <div class="context-menu-wrap">
                <button type="button" class="icon-btn" data-menu-trigger="<?= $menuId ?>" aria-label="إجراءات"><?php admin_icon('dots') ?></button>
                <div class="context-menu" id="<?= $menuId ?>" hidden>
                  <a href="/admin/service-edit.php?id=<?= (int)$s['id'] ?>">تعديل</a>
                  <?php if (in_array($s['workflow_status'], ['draft', 'paused'], true)): ?>
                    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="submit_review"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>"><button type="submit">أرسل للاعتماد</button></form>
                  <?php endif; ?>
                  <?php if (can_review($adminUser) && in_array($s['workflow_status'], ['pending_review', 'paused'], true)): ?>
                    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="publish"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>"><button type="submit">نشر</button></form>
                  <?php endif; ?>
                  <?php if (can_review($adminUser) && $s['workflow_status'] === 'published'): ?>
                    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="pause"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>"><button type="submit">إيقاف</button></form>
                  <?php endif; ?>
                  <form method="post" onsubmit="return confirm('حذف هالخدمة نهائيًا؟');"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>"><button type="submit" class="danger">حذف</button></form>
                </div>
              </div>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>

<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
