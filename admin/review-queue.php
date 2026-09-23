<?php
require_once __DIR__ . '/../includes/auth.php';
$adminUser = require_review_access();
$activeNav = 'review-queue';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify()) {
    $action = $_POST['action'] ?? '';
    $id = (int)($_POST['id'] ?? 0);
    $note = trim($_POST['note'] ?? '');

    if ($action === 'article_approve') {
        $upd = db()->prepare("UPDATE articles SET workflow_status='approved', reviewer_name=?, last_reviewed_at=datetime('now'), review_note=NULL, updated_at=datetime('now') WHERE id=? AND workflow_status='in_review'");
        $upd->execute([$adminUser['name'], $id]);
        flash_set('success', 'تم اعتماد المقالة. جاهزة للنشر من صفحة المقالات.');
    } elseif ($action === 'article_request_changes') {
        if ($note === '') {
            flash_set('error', 'اكتب ملاحظة توضح شو محتاج تعديل.');
        } else {
            $upd = db()->prepare("UPDATE articles SET workflow_status='changes_requested', review_note=?, updated_at=datetime('now') WHERE id=? AND workflow_status='in_review'");
            $upd->execute([$note, $id]);
            flash_set('success', 'تم إرجاع المقالة للمحرر مع ملاحظتك.');
        }
    } elseif ($action === 'article_return_draft') {
        $upd = db()->prepare("UPDATE articles SET workflow_status='draft', updated_at=datetime('now') WHERE id=? AND workflow_status='in_review'");
        $upd->execute([$id]);
        flash_set('success', 'تم إرجاع المقالة كمسودة.');
    } elseif ($action === 'service_publish') {
        set_service_workflow_status($id, 'published', $adminUser['name']);
        flash_set('success', 'تم نشر الخدمة.');
    } elseif ($action === 'service_request_changes') {
        if ($note === '') {
            flash_set('error', 'اكتب ملاحظة توضح شو محتاج تعديل.');
        } else {
            set_service_workflow_status($id, 'draft', $adminUser['name'], $note);
            flash_set('success', 'تم إرجاع الخدمة كمسودة مع ملاحظتك.');
        }
    }
    redirect('/admin/review-queue.php');
}

$pendingArticles = db()->query("SELECT a.*, c.name AS category_name FROM articles a LEFT JOIN categories c ON c.id = a.category_id WHERE a.workflow_status = 'in_review' ORDER BY a.updated_at")->fetchAll();
$changesRequested = db()->query("SELECT a.* FROM articles a WHERE a.workflow_status = 'changes_requested' ORDER BY a.updated_at DESC")->fetchAll();
$pendingServices = db()->query("SELECT * FROM services WHERE workflow_status = 'pending_review' ORDER BY sort_order")->fetchAll();

$pageTitle = 'قائمة الاعتماد';
require __DIR__ . '/includes/layout_top.php';
?>

<div class="notice-inline" style="margin-bottom:24px;">
  هون كل المحتوى يلي بانتظار قرارك: اعتماد، طلب تعديل، أو إرجاعه مسودة. ما في شي بينشر أو يُعتمد تلقائيًا — كل قرار لازم يكون بضغطة منك.
</div>

<h2>مقالات وأدلة بانتظار المراجعة (<?= count($pendingArticles) ?>)</h2>
<?php if (empty($pendingArticles)): ?>
  <p class="field-hint" style="margin-bottom:30px;">ما في محتوى بانتظار المراجعة حاليًا.</p>
<?php else: ?>
  <?php foreach ($pendingArticles as $a): ?>
    <div class="admin-card" style="margin-bottom:16px;">
      <div class="admin-page-head" style="margin-bottom:12px;">
        <h3 style="margin:0;"><?= e($a['title']) ?></h3>
        <a href="/admin/preview-article.php?id=<?= (int)$a['id'] ?>" target="_blank" class="btn btn-outline btn-sm">👁️ معاينة كاملة</a>
      </div>
      <p class="field-hint"><?= e($a['category_name'] ?? 'بدون تصنيف') ?> · <?= (int)$a['read_minutes'] ?> دقايق · آخر تحديث <?= e(date('Y/m/d', strtotime($a['updated_at']))) ?></p>
      <p><?= e($a['excerpt']) ?></p>

      <div style="display:flex; gap:10px; flex-wrap:wrap; margin-top:14px;">
        <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="article_approve"><input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
          <button type="submit" class="btn btn-primary btn-sm">✓ اعتماد</button>
        </form>
        <form method="post" onsubmit="return confirm('إرجاع المقالة كمسودة بدون ملاحظة؟');"><?= csrf_field() ?><input type="hidden" name="action" value="article_return_draft"><input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
          <button type="submit" class="btn btn-outline btn-sm">إرجاع كمسودة</button>
        </form>
      </div>

      <details style="margin-top:12px;">
        <summary style="cursor:pointer; font-weight:700; font-size:.9rem;">طلب تعديل مع ملاحظة</summary>
        <form method="post" style="margin-top:10px; display:flex; gap:10px; align-items:end; flex-wrap:wrap;">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="article_request_changes">
          <input type="hidden" name="id" value="<?= (int)$a['id'] ?>">
          <textarea name="note" rows="2" style="flex:1; min-width:220px;" placeholder="شو محتاج يتعدّل؟" required></textarea>
          <button type="submit" class="btn btn-outline btn-sm">إرسال الملاحظة</button>
        </form>
      </details>
    </div>
  <?php endforeach; ?>
<?php endif; ?>

<?php if (!empty($changesRequested)): ?>
  <h2 style="margin-top:36px;">بانتظار تعديل المحرر (<?= count($changesRequested) ?>)</h2>
  <div class="admin-card" style="margin-bottom:30px;">
    <table class="data-table">
      <thead><tr><th>العنوان</th><th>ملاحظتك</th></tr></thead>
      <tbody>
        <?php foreach ($changesRequested as $a): ?>
          <tr><td><?= e($a['title']) ?></td><td><?= e($a['review_note']) ?></td></tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
<?php endif; ?>

<h2 style="margin-top:20px;">خدمات بانتظار الاعتماد (<?= count($pendingServices) ?>)</h2>
<?php if (empty($pendingServices)): ?>
  <p class="field-hint">ما في خدمات بانتظار الاعتماد حاليًا.</p>
<?php else: ?>
  <?php foreach ($pendingServices as $s): ?>
    <div class="admin-card" style="margin-bottom:16px;">
      <div class="admin-page-head" style="margin-bottom:12px;">
        <h3 style="margin:0;"><?= e($s['name']) ?></h3>
        <a href="/admin/service-edit.php?id=<?= (int)$s['id'] ?>" class="btn btn-outline btn-sm">تعديل التفاصيل</a>
      </div>
      <p><?= e($s['summary']) ?></p>
      <p class="field-hint">المدة: <?= e($s['duration'] ?: '—') ?> · السعر: <?= e($s['price'] ?: '—') ?></p>
      <?php if ($s['review_note']): ?><div class="alert alert-info">ملاحظة سابقة: <?= e($s['review_note']) ?></div><?php endif; ?>

      <div style="display:flex; gap:10px; flex-wrap:wrap; margin-top:14px;">
        <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="service_publish"><input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
          <button type="submit" class="btn btn-primary btn-sm">✓ نشر الخدمة</button>
        </form>
      </div>
      <details style="margin-top:12px;">
        <summary style="cursor:pointer; font-weight:700; font-size:.9rem;">تسجيل ملاحظة (بدون نشر)</summary>
        <form method="post" style="margin-top:10px; display:flex; gap:10px; align-items:end; flex-wrap:wrap;">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="service_request_changes">
          <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
          <textarea name="note" rows="2" style="flex:1; min-width:220px;" placeholder="شو محتاج يتأكد أو يتغيّر؟" required></textarea>
          <button type="submit" class="btn btn-outline btn-sm">حفظ الملاحظة</button>
        </form>
      </details>
    </div>
  <?php endforeach; ?>
<?php endif; ?>

<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
