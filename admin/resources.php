<?php
require_once __DIR__ . '/../includes/auth.php';
require_content_access();
$activeNav = 'resources';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_verify()) {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_link') {
        $title = trim($_POST['title'] ?? '');
        $url = trim($_POST['url'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        if ($title !== '' && filter_var($url, FILTER_VALIDATE_URL)) {
            $maxOrder = (int)db()->query('SELECT COALESCE(MAX(sort_order),0) FROM external_resources')->fetchColumn();
            $ins = db()->prepare('INSERT INTO external_resources (title, url, description, is_published, last_checked_at, sort_order) VALUES (?, ?, ?, 0, datetime("now"), ?)');
            $ins->execute([$title, $url, $desc, $maxOrder + 1]);
            flash_set('success', 'تمت إضافة الرابط.');
        } else {
            flash_set('error', 'تأكد من العنوان والرابط (لازم يبلّش بـ http:// أو https://).');
        }
    } elseif ($action === 'toggle_link') {
        $id = (int)($_POST['id'] ?? 0);
        $upd = db()->prepare('UPDATE external_resources SET is_published = 1 - is_published WHERE id = ?');
        $upd->execute([$id]);
    } elseif ($action === 'recheck_link') {
        $id = (int)($_POST['id'] ?? 0);
        $upd = db()->prepare('UPDATE external_resources SET last_checked_at = datetime("now") WHERE id = ?');
        $upd->execute([$id]);
        flash_set('success', 'تم تحديث تاريخ آخر مراجعة للرابط.');
    } elseif ($action === 'delete_link') {
        $id = (int)($_POST['id'] ?? 0);
        db()->prepare('DELETE FROM external_resources WHERE id = ?')->execute([$id]);
        flash_set('success', 'تم حذف الرابط.');
    } elseif ($action === 'add_media') {
        $title = trim($_POST['title'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        $mediaType = $_POST['media_type'] ?? 'file';
        $externalUrl = trim($_POST['external_url'] ?? '');
        $filePath = null;

        if (!in_array($mediaType, ['file', 'video_url', 'audio_url'], true)) {
            flash_set('error', 'نوع الوسائط غير صحيح.');
            redirect('/admin/resources.php');
        }

        if ($mediaType === 'file') {
            if (empty($_FILES['file']['name']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
                flash_set('error', 'اختر ملف للرفع.');
                redirect('/admin/resources.php');
            }
            $allowed = ['application/pdf' => 'pdf', 'application/msword' => 'doc',
                'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'docx'];
            $mime = mime_content_type($_FILES['file']['tmp_name']);
            if (!isset($allowed[$mime]) || $_FILES['file']['size'] > 10 * 1024 * 1024) {
                flash_set('error', 'صيغة الملف أو حجمه غير مدعوم (PDF/DOC/DOCX، أقل من 10MB).');
                redirect('/admin/resources.php');
            }
            if (!is_dir(UPLOADS_DIR)) { mkdir(UPLOADS_DIR, 0775, true); }
            $filename = 'doc-' . bin2hex(random_bytes(6)) . '.' . $allowed[$mime];
            move_uploaded_file($_FILES['file']['tmp_name'], UPLOADS_DIR . '/' . $filename);
            $filePath = UPLOADS_URL . '/' . $filename;
        } elseif (!filter_var($externalUrl, FILTER_VALIDATE_URL)) {
            flash_set('error', 'رابط الفيديو/الصوت غير صحيح.');
            redirect('/admin/resources.php');
        }

        if ($title !== '') {
            $maxOrder = (int)db()->query('SELECT COALESCE(MAX(sort_order),0) FROM media_resources')->fetchColumn();
            $ins = db()->prepare('INSERT INTO media_resources (title, description, media_type, file_path, external_url, is_published, sort_order) VALUES (?,?,?,?,?,0,?)');
            $ins->execute([$title, $desc, $mediaType, $filePath, $mediaType === 'file' ? null : $externalUrl, $maxOrder + 1]);
            flash_set('success', 'تمت إضافة المورد.');
        }
    } elseif ($action === 'toggle_media') {
        $id = (int)($_POST['id'] ?? 0);
        db()->prepare('UPDATE media_resources SET is_published = 1 - is_published WHERE id = ?')->execute([$id]);
    } elseif ($action === 'delete_media') {
        $id = (int)($_POST['id'] ?? 0);
        db()->prepare('DELETE FROM media_resources WHERE id = ?')->execute([$id]);
        flash_set('success', 'تم حذف المورد.');
    }
    redirect('/admin/resources.php');
}

$links = db()->query('SELECT * FROM external_resources ORDER BY sort_order')->fetchAll();
$media = db()->query('SELECT * FROM media_resources ORDER BY sort_order')->fetchAll();
$mediaTypeLabels = ['file' => 'ملف قابل للتنزيل', 'video_url' => 'فيديو (رابط)', 'audio_url' => 'تسجيل صوتي (رابط)'];

$pageTitle = 'مصادر وملفات';
require __DIR__ . '/includes/layout_top.php';
?>

<div class="admin-page-head"><h1>مصادر وملفات</h1></div>

<div class="notice-banner" style="margin-bottom:24px;">
  <strong>تذكير</strong>
  لا تنسخ أي دليل أو ملف أو صورة محمية بحقوق نشر — بس روابط لمصادر موثوقة، أو ملفات أصلية راجعتها تالا. راجع الروابط دوريًا للتأكد إنها لسا شغالة.
</div>

<h2>مصادر مفيدة (روابط خارجية)</h2>
<div class="admin-card" style="margin-bottom:20px;">
  <h3>إضافة رابط</h3>
  <form method="post" class="form-narrow">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="add_link">
    <div class="form-group"><label>العنوان</label><input type="text" name="title" required></div>
    <div class="form-group"><label>الرابط</label><input type="text" name="url" required placeholder="https://..."></div>
    <div class="form-group"><label>وصف قصير</label><textarea name="description" rows="2"></textarea></div>
    <button type="submit" class="btn btn-primary">إضافة</button>
  </form>
</div>

<div class="admin-card" style="margin-bottom:36px;">
  <table class="data-table">
    <thead><tr><th>العنوان</th><th>الرابط</th><th>آخر مراجعة</th><th>الحالة</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($links as $l): ?>
        <tr>
          <td><?= e($l['title']) ?></td>
          <td><a href="<?= e($l['url']) ?>" target="_blank" rel="noopener" style="font-size:.85rem;"><?= e(mb_substr($l['url'], 0, 40)) ?>…</a></td>
          <td><?= $l['last_checked_at'] ? e(date('Y/m/d', strtotime($l['last_checked_at']))) : '—' ?></td>
          <td><span class="status-badge <?= $l['is_published'] ? 'status-confirmed' : 'status-new' ?>"><?= $l['is_published'] ? 'منشور' : 'غير منشور' ?></span></td>
          <td class="action-links">
            <form method="post" style="display:inline;"><?= csrf_field() ?><input type="hidden" name="action" value="toggle_link"><input type="hidden" name="id" value="<?= (int)$l['id'] ?>"><button type="submit"><?= $l['is_published'] ? 'إلغاء النشر' : 'نشر' ?></button></form>
            <form method="post" style="display:inline;"><?= csrf_field() ?><input type="hidden" name="action" value="recheck_link"><input type="hidden" name="id" value="<?= (int)$l['id'] ?>"><button type="submit">تأكيد إنه لسا شغال</button></form>
            <form method="post" style="display:inline;" onsubmit="return confirm('حذف هالرابط؟');"><?= csrf_field() ?><input type="hidden" name="action" value="delete_link"><input type="hidden" name="id" value="<?= (int)$l['id'] ?>"><button type="submit" class="danger">حذف</button></form>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (empty($links)): ?><tr><td colspan="5">ما في روابط مضافة.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>

<h2>ملفات وفيديوهات وتسجيلات</h2>
<div class="admin-card" style="margin-bottom:20px;">
  <h3>إضافة مورد</h3>
  <form method="post" enctype="multipart/form-data" class="form-narrow">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="add_media">
    <div class="form-group"><label>العنوان</label><input type="text" name="title" required></div>
    <div class="form-group"><label>وصف قصير</label><textarea name="description" rows="2"></textarea></div>
    <div class="form-group">
      <label>النوع</label>
      <select name="media_type" id="mediaType" onchange="document.getElementById('fileField').style.display=this.value==='file'?'block':'none'; document.getElementById('urlField').style.display=this.value!=='file'?'block':'none';">
        <option value="file">ملف قابل للتنزيل (PDF/DOC)</option>
        <option value="video_url">فيديو (رابط خارجي)</option>
        <option value="audio_url">تسجيل صوتي (رابط خارجي)</option>
      </select>
    </div>
    <div class="form-group" id="fileField"><label>الملف</label><input type="file" name="file" accept=".pdf,.doc,.docx"></div>
    <div class="form-group" id="urlField" style="display:none;"><label>الرابط</label><input type="text" name="external_url" placeholder="https://..."></div>
    <button type="submit" class="btn btn-primary">إضافة</button>
  </form>
</div>

<div class="admin-card">
  <table class="data-table">
    <thead><tr><th>العنوان</th><th>النوع</th><th>الحالة</th><th></th></tr></thead>
    <tbody>
      <?php foreach ($media as $m): ?>
        <tr>
          <td><?= e($m['title']) ?></td>
          <td><?= e($mediaTypeLabels[$m['media_type']] ?? $m['media_type']) ?></td>
          <td><span class="status-badge <?= $m['is_published'] ? 'status-confirmed' : 'status-new' ?>"><?= $m['is_published'] ? 'منشور' : 'غير منشور' ?></span></td>
          <td class="action-links">
            <form method="post" style="display:inline;"><?= csrf_field() ?><input type="hidden" name="action" value="toggle_media"><input type="hidden" name="id" value="<?= (int)$m['id'] ?>"><button type="submit"><?= $m['is_published'] ? 'إلغاء النشر' : 'نشر' ?></button></form>
            <form method="post" style="display:inline;" onsubmit="return confirm('حذف هالمورد؟');"><?= csrf_field() ?><input type="hidden" name="action" value="delete_media"><input type="hidden" name="id" value="<?= (int)$m['id'] ?>"><button type="submit" class="danger">حذف</button></form>
          </td>
        </tr>
      <?php endforeach; ?>
      <?php if (empty($media)): ?><tr><td colspan="4">ما في موارد مضافة.</td></tr><?php endif; ?>
    </tbody>
  </table>
</div>

<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
