<?php
require_once __DIR__ . '/../includes/auth.php';
require_content_access();
$activeNav = 'events';

$id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$event = ['id' => null, 'title' => '', 'topic_summary' => '', 'audience' => '', 'event_date' => '', 'location_or_stream' => '', 'description' => '', 'is_published' => 0];
if ($id) {
    $stmt = db()->prepare('SELECT * FROM events WHERE id = ?');
    $stmt->execute([$id]);
    $found = $stmt->fetch();
    if (!$found) { flash_set('error', 'الفعالية غير موجودة.'); redirect('/admin/events.php'); }
    $event = $found;
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'event') {
    if (!csrf_verify()) { $errors[] = 'في مشكلة تقنية. جرّب من جديد.'; }
    $event['title'] = trim($_POST['title'] ?? '');
    $event['topic_summary'] = trim($_POST['topic_summary'] ?? '');
    $event['audience'] = trim($_POST['audience'] ?? '');
    $event['event_date'] = trim($_POST['event_date'] ?? '');
    $event['location_or_stream'] = trim($_POST['location_or_stream'] ?? '');
    $descriptionRaw = trim($_POST['description'] ?? '');
    $event['is_published'] = !empty($_POST['is_published']) ? 1 : 0;

    if ($event['title'] === '') { $errors[] = 'عنوان الفعالية مطلوب.'; }

    if (empty($errors)) {
        $descriptionHtml = simple_text_to_html($descriptionRaw);
        if ($id) {
            $slug = unique_slug('events', slugify($event['title']), $id);
            $upd = db()->prepare("UPDATE events SET slug=?, title=?, topic_summary=?, audience=?, event_date=?, location_or_stream=?, description=?, is_published=?, updated_at=datetime('now') WHERE id=?");
            $upd->execute([$slug, $event['title'], $event['topic_summary'], $event['audience'], $event['event_date'], $event['location_or_stream'], $descriptionHtml, $event['is_published'], $id]);
            flash_set('success', 'تم حفظ الفعالية.');
            redirect('/admin/event-edit.php?id=' . $id);
        } else {
            $slug = unique_slug('events', slugify($event['title']));
            $ins = db()->prepare('INSERT INTO events (slug, title, topic_summary, audience, event_date, location_or_stream, description, is_published) VALUES (?,?,?,?,?,?,?,?)');
            $ins->execute([$slug, $event['title'], $event['topic_summary'], $event['audience'], $event['event_date'], $event['location_or_stream'], $descriptionHtml, $event['is_published']]);
            flash_set('success', 'تمت إضافة الفعالية. فيك هلق تضيف صور أو فيديوهات إلها.');
            redirect('/admin/event-edit.php?id=' . db()->lastInsertId());
        }
    }
}

// --- media gallery actions (only meaningful once the event has an id) ---
if ($id && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['form'] ?? '') === 'media' && csrf_verify()) {
    $action = $_POST['action'] ?? '';
    if ($action === 'add') {
        $mediaType = $_POST['media_type'] ?? 'image';
        $altText = trim($_POST['alt_text'] ?? '');
        $caption = trim($_POST['caption'] ?? '');
        $externalUrl = trim($_POST['external_url'] ?? '');
        $filePath = null;

        if (!in_array($mediaType, ['image', 'video_file', 'video_url'], true)) {
            flash_set('error', 'نوع الوسائط غير صحيح.');
            redirect('/admin/event-edit.php?id=' . $id);
        }
        if ($mediaType === 'image' && $altText === '') {
            flash_set('error', 'النص البديل (alt text) مطلوب للصور — لإمكانية الوصول ولوصف المحتوى.');
            redirect('/admin/event-edit.php?id=' . $id);
        }

        if (in_array($mediaType, ['image', 'video_file'], true)) {
            if (empty($_FILES['file']['name']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
                flash_set('error', 'اختر ملف للرفع.');
                redirect('/admin/event-edit.php?id=' . $id);
            }
            $allowedImg = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/webp' => 'webp'];
            $allowedVideo = ['video/mp4' => 'mp4', 'video/webm' => 'webm'];
            $allowed = $mediaType === 'image' ? $allowedImg : $allowedVideo;
            $maxSize = $mediaType === 'image' ? 5 * 1024 * 1024 : 50 * 1024 * 1024;
            $mime = mime_content_type($_FILES['file']['tmp_name']);
            if (!isset($allowed[$mime]) || $_FILES['file']['size'] > $maxSize) {
                flash_set('error', 'صيغة أو حجم الملف غير مدعوم.');
                redirect('/admin/event-edit.php?id=' . $id);
            }
            if (!is_dir(UPLOADS_DIR)) { mkdir(UPLOADS_DIR, 0775, true); }
            $filename = 'event-' . bin2hex(random_bytes(6)) . '.' . $allowed[$mime];
            move_uploaded_file($_FILES['file']['tmp_name'], UPLOADS_DIR . '/' . $filename);
            $filePath = UPLOADS_URL . '/' . $filename;
        } elseif (!filter_var($externalUrl, FILTER_VALIDATE_URL)) {
            flash_set('error', 'رابط الفيديو غير صحيح.');
            redirect('/admin/event-edit.php?id=' . $id);
        }

        $maxOrder = (int)db()->query('SELECT COALESCE(MAX(sort_order),0) FROM event_media WHERE event_id = ' . (int)$id)->fetchColumn();
        $ins = db()->prepare('INSERT INTO event_media (event_id, media_type, file_path, external_url, alt_text, caption, sort_order) VALUES (?,?,?,?,?,?,?)');
        $ins->execute([$id, $mediaType, $filePath, $mediaType === 'video_url' ? $externalUrl : null, $altText, $caption, $maxOrder + 1]);
        flash_set('success', 'تمت إضافة العنصر للمعرض. رح يظهر للزوار فقط لما الفعالية تكون منشورة.');
    } elseif ($action === 'delete') {
        $mediaId = (int)($_POST['media_id'] ?? 0);
        db()->prepare('DELETE FROM event_media WHERE id = ? AND event_id = ?')->execute([$mediaId, $id]);
        flash_set('success', 'تم حذف العنصر.');
    } elseif ($action === 'reorder') {
        $mediaId = (int)($_POST['media_id'] ?? 0);
        $direction = $_POST['direction'] ?? '';
        $current = db()->prepare('SELECT sort_order FROM event_media WHERE id = ? AND event_id = ?');
        $current->execute([$mediaId, $id]);
        $currentOrder = $current->fetchColumn();
        if ($currentOrder !== false) {
            $swapWith = db()->prepare('SELECT id, sort_order FROM event_media WHERE event_id = ? AND sort_order ' . ($direction === 'up' ? '<' : '>') . ' ? ORDER BY sort_order ' . ($direction === 'up' ? 'DESC' : 'ASC') . ' LIMIT 1');
            $swapWith->execute([$id, $currentOrder]);
            $target = $swapWith->fetch();
            if ($target) {
                db()->prepare('UPDATE event_media SET sort_order = ? WHERE id = ?')->execute([$target['sort_order'], $mediaId]);
                db()->prepare('UPDATE event_media SET sort_order = ? WHERE id = ?')->execute([$currentOrder, $target['id']]);
            }
        }
    }
    redirect('/admin/event-edit.php?id=' . $id);
}

$mediaItems = $id ? db()->query('SELECT * FROM event_media WHERE event_id = ' . (int)$id . ' ORDER BY sort_order')->fetchAll() : [];
$mediaTypeLabels = ['image' => 'صورة', 'video_file' => 'فيديو (ملف)', 'video_url' => 'فيديو (رابط)'];

$pageTitle = $id ? 'تعديل فعالية' : 'فعالية جديدة';
require __DIR__ . '/includes/layout_top.php';
?>

<?php foreach ($errors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>

<div class="admin-card" style="margin-bottom:24px;">
  <form method="post" class="form-narrow">
    <?= csrf_field() ?>
    <input type="hidden" name="form" value="event">
    <div class="form-group"><label>العنوان *</label><input type="text" name="title" required value="<?= e($event['title']) ?>"></div>
    <div class="form-group"><label>ملخص الموضوع</label><textarea name="topic_summary" rows="2"><?= e($event['topic_summary']) ?></textarea></div>
    <div class="form-row-2">
      <div class="form-group"><label>الجمهور المستهدف</label><input type="text" name="audience" value="<?= e($event['audience']) ?>"></div>
      <div class="form-group">
        <label>التاريخ</label>
        <input type="date" name="event_date" value="<?= e($event['event_date']) ?>">
        <p class="field-hint">بيتحدد تلقائيًا إذا الفعالية «قادمة» أو «سابقة» بالموقع بناءً على هالتاريخ.</p>
      </div>
    </div>
    <div class="form-group"><label>المكان أو رابط البث</label><input type="text" name="location_or_stream" value="<?= e($event['location_or_stream']) ?>"></div>
    <div class="form-group"><label>تفاصيل إضافية</label><textarea name="description" rows="8"><?= e($event['id'] ? strip_tags(str_replace(['<br>', '</p>'], ["\n", "\n\n"], $event['description'])) : '') ?></textarea></div>
    <div class="form-group">
      <label><input type="checkbox" name="is_published" value="1" <?= (int)$event['is_published'] === 1 ? 'checked' : '' ?> style="width:auto;"> منشورة (تظهر بالموقع العام فقط إذا كان قسم الفعاليات مفعّل)</label>
    </div>
    <div style="display:flex; gap:10px;">
      <button type="submit" class="btn btn-primary">حفظ</button>
      <?php if ($id): ?><a href="/admin/preview-event.php?id=<?= $id ?>" target="_blank" class="btn btn-outline">👁️ معاينة قبل النشر</a><?php endif; ?>
    </div>
  </form>
</div>

<?php if ($id): ?>
<div class="admin-card">
  <h3 style="margin-top:0;">معرض الصور والفيديوهات</h3>

  <form method="post" enctype="multipart/form-data" class="form-narrow" style="margin-bottom:20px;">
    <?= csrf_field() ?>
    <input type="hidden" name="form" value="media">
    <input type="hidden" name="action" value="add">
    <div class="form-row-2">
      <div class="form-group">
        <label>نوع العنصر</label>
        <select name="media_type" onchange="
          document.getElementById('mediaFileField').style.display = this.value !== 'video_url' ? 'block' : 'none';
          document.getElementById('mediaUrlField').style.display = this.value === 'video_url' ? 'block' : 'none';
        ">
          <option value="image">صورة</option>
          <option value="video_file">فيديو (رفع ملف)</option>
          <option value="video_url">فيديو (رابط يوتيوب/فيميو)</option>
        </select>
      </div>
      <div class="form-group"><label>النص البديل (Alt text) — مطلوب للصور</label><input type="text" name="alt_text" placeholder="وصف قصير للصورة"></div>
    </div>
    <div class="form-group" id="mediaFileField"><label>الملف</label><input type="file" name="file" accept="image/png,image/jpeg,image/webp,video/mp4,video/webm"></div>
    <div class="form-group" id="mediaUrlField" style="display:none;"><label>رابط الفيديو</label><input type="text" name="external_url" placeholder="https://..."></div>
    <div class="form-group"><label>تعليق توضيحي (اختياري)</label><input type="text" name="caption"></div>
    <button type="submit" class="btn btn-primary">إضافة للمعرض</button>
  </form>

  <?php if (empty($mediaItems)): ?>
    <p class="field-hint">ما في صور أو فيديوهات مضافة لسا. أضف بس محتوى حقيقي من الفعالية بعد التأكد من حق استخدامه.</p>
  <?php else: ?>
    <table class="data-table">
      <thead><tr><th>معاينة</th><th>النوع</th><th>النص البديل / التعليق</th><th>الترتيب</th><th></th></tr></thead>
      <tbody>
        <?php foreach ($mediaItems as $m): ?>
          <tr>
            <td>
              <?php if ($m['media_type'] === 'image'): ?>
                <img src="<?= e($m['file_path']) ?>" alt="" style="width:70px; height:50px; object-fit:cover; border-radius:6px;">
              <?php else: ?>
                🎬
              <?php endif; ?>
            </td>
            <td><?= e($mediaTypeLabels[$m['media_type']]) ?></td>
            <td><?= e($m['alt_text'] ?: '—') ?><?= $m['caption'] ? '<br><span class="field-hint">' . e($m['caption']) . '</span>' : '' ?></td>
            <td>
              <form method="post" style="display:inline;"><?= csrf_field() ?><input type="hidden" name="form" value="media"><input type="hidden" name="action" value="reorder"><input type="hidden" name="direction" value="up"><input type="hidden" name="media_id" value="<?= (int)$m['id'] ?>"><button type="submit">▲</button></form>
              <form method="post" style="display:inline;"><?= csrf_field() ?><input type="hidden" name="form" value="media"><input type="hidden" name="action" value="reorder"><input type="hidden" name="direction" value="down"><input type="hidden" name="media_id" value="<?= (int)$m['id'] ?>"><button type="submit">▼</button></form>
            </td>
            <td>
              <form method="post" onsubmit="return confirm('حذف هالعنصر؟');"><?= csrf_field() ?><input type="hidden" name="form" value="media"><input type="hidden" name="action" value="delete"><input type="hidden" name="media_id" value="<?= (int)$m['id'] ?>"><button type="submit" class="action-links"><span class="danger">حذف</span></button></form>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  <?php endif; ?>
</div>
<?php endif; ?>

<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
