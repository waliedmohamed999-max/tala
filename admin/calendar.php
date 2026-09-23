<?php
require_once __DIR__ . '/../includes/auth.php';
require_clinic_ops_access();
$activeNav = 'calendar';

$weekdays = ['الأحد', 'الاثنين', 'الثلاثاء', 'الأربعاء', 'الخميس', 'الجمعة', 'السبت'];
$statusLabels = [
    'pending_confirmation' => ['بانتظار التأكيد', 'status-new'],
    'confirmed' => ['مؤكد', 'status-confirmed'],
    'rescheduled' => ['أُعيدت جدولته', 'status-contacting'],
    'cancelled' => ['أُلغي', 'status-cancelled'],
    'no_show' => ['لم يحضر', 'status-noshow'],
    'completed' => ['انعقد', 'status-done'],
];

$anchor = isset($_GET['date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date']) ? $_GET['date'] : date('Y-m-d');
$anchorTs = strtotime($anchor);
$weekStartTs = $anchorTs - ((int)date('w', $anchorTs) * 86400);
$weekStart = date('Y-m-d', $weekStartTs);
$weekEnd = date('Y-m-d', $weekStartTs + (6 * 86400));

$stmt = db()->prepare("SELECT a.*, s.name AS service_name, p.full_name AS patient_name, p.file_number
    FROM appointments a
    LEFT JOIN services s ON s.id = a.service_id
    LEFT JOIN patients p ON p.id = a.patient_id
    WHERE a.provider_id = 1 AND a.starts_at >= ? AND a.starts_at < ?
    ORDER BY a.starts_at");
$stmt->execute([$weekStart . ' 00:00:00', date('Y-m-d 00:00:00', $weekStartTs + (7 * 86400))]);
$appointments = $stmt->fetchAll();

$byDay = [];
foreach ($appointments as $a) {
    $day = substr($a['starts_at'], 0, 10);
    $byDay[$day][] = $a;
}

$prevWeek = date('Y-m-d', $weekStartTs - (7 * 86400));
$nextWeek = date('Y-m-d', $weekStartTs + (7 * 86400));

$pageTitle = 'التقويم';
require __DIR__ . '/includes/layout_top.php';
?>

<div class="admin-page-head">
  <h1>التقويم</h1>
  <a href="/admin/appointment-edit.php?date=<?= e($anchor) ?>" class="btn btn-primary">+ موعد جديد / حظر فترة</a>
</div>

<div class="admin-card" style="margin-bottom:20px; display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:12px;">
  <a href="/admin/calendar.php?date=<?= $prevWeek ?>" class="btn btn-outline btn-sm">← الأسبوع السابق</a>
  <strong><?= e($weekStart) ?> — <?= e($weekEnd) ?></strong>
  <a href="/admin/calendar.php?date=<?= $nextWeek ?>" class="btn btn-outline btn-sm">الأسبوع التالي →</a>
</div>

<div class="calendar-week">
  <?php for ($i = 0; $i < 7; $i++): $day = date('Y-m-d', $weekStartTs + ($i * 86400)); ?>
    <div class="calendar-day">
      <div class="calendar-day-head"><?= e($weekdays[$i]) ?><br><?= e($day) ?></div>
      <?php if (empty($byDay[$day])): ?>
        <p class="field-hint" style="font-size:.78rem;">لا مواعيد</p>
      <?php else: ?>
        <?php foreach ($byDay[$day] as $a): [$label, $cls] = $statusLabels[$a['status']] ?? ['—', 'status-new']; ?>
          <a href="/admin/appointment-edit.php?id=<?= (int)$a['id'] ?>" class="calendar-slot is-busy" style="display:block;">
            <strong dir="ltr" style="unicode-bidi:isolate;"><?= e(substr($a['starts_at'], 11, 5)) ?>–<?= e(substr($a['ends_at'], 11, 5)) ?></strong><br>
            <?= e($a['service_name'] ?? 'حظر فترة') ?><br>
            <?php if ($a['patient_name']): ?><?= e($a['patient_name']) ?><br><?php endif; ?>
            <span class="status-badge <?= $cls ?>" style="font-size:.72rem;"><?= $label ?></span>
          </a>
        <?php endforeach; ?>
      <?php endif; ?>
    </div>
  <?php endfor; ?>
</div>

<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
