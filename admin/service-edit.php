<?php
require_once __DIR__ . '/../includes/auth.php';
require_content_access();
$activeNav = 'services';

$id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$service = ['id' => null, 'name' => '', 'audience' => '', 'format' => '', 'duration' => '', 'price' => '', 'summary' => '', 'description' => '', 'is_published' => 0, 'sort_order' => 0];
if ($id) {
    $stmt = db()->prepare('SELECT * FROM services WHERE id = ?');
    $stmt->execute([$id]);
    $found = $stmt->fetch();
    if (!$found) { flash_set('error', 'الخدمة غير موجودة.'); redirect('/admin/services.php'); }
    $service = $found;
}

$errors = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $errors[] = 'في مشكلة تقنية. جرّب من جديد.';
    }
    $service['name'] = trim($_POST['name'] ?? '');
    $service['audience'] = trim($_POST['audience'] ?? '');
    $service['format'] = trim($_POST['format'] ?? '');
    $service['duration'] = trim($_POST['duration'] ?? '');
    $service['price'] = trim($_POST['price'] ?? '');
    $service['summary'] = trim($_POST['summary'] ?? '');
    $descriptionRaw = trim($_POST['description'] ?? '');
    $service['sort_order'] = (int)($_POST['sort_order'] ?? 0);
    $service['is_published'] = !empty($_POST['is_published']) ? 1 : 0;

    if ($service['name'] === '') { $errors[] = 'اسم الخدمة مطلوب.'; }

    if (empty($errors)) {
        $descriptionHtml = simple_text_to_html($descriptionRaw);
        if ($id) {
            $slug = unique_slug('services', slugify($service['name']), $id);
            $upd = db()->prepare("UPDATE services SET slug=?, name=?, audience=?, format=?, duration=?, price=?, summary=?, description=?, is_published=?, sort_order=?, updated_at=datetime('now') WHERE id=?");
            $upd->execute([$slug, $service['name'], $service['audience'], $service['format'], $service['duration'], $service['price'], $service['summary'], $descriptionHtml, $service['is_published'], $service['sort_order'], $id]);
            flash_set('success', 'تم حفظ الخدمة.');
        } else {
            $slug = unique_slug('services', slugify($service['name']));
            $ins = db()->prepare('INSERT INTO services (slug, name, audience, format, duration, price, summary, description, is_published, sort_order) VALUES (?,?,?,?,?,?,?,?,?,?)');
            $ins->execute([$slug, $service['name'], $service['audience'], $service['format'], $service['duration'], $service['price'], $service['summary'], $descriptionHtml, $service['is_published'], $service['sort_order']]);
            flash_set('success', 'تمت إضافة الخدمة.');
        }
        redirect('/admin/services.php');
    }
}

$pageTitle = $id ? 'تعديل خدمة' : 'خدمة جديدة';
require __DIR__ . '/includes/layout_top.php';
?>

<div class="admin-page-head"><h1><?= $id ? 'تعديل خدمة' : 'خدمة جديدة' ?></h1></div>

<?php foreach ($errors as $err): ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>

<div class="admin-card">
  <form method="post" class="form-narrow">
    <?= csrf_field() ?>
    <div class="form-group"><label>اسم الخدمة *</label><input type="text" name="name" required value="<?= e($service['name']) ?>"></div>
    <div class="form-group"><label>وصف مختصر (يظهر بالبطاقات)</label><textarea name="summary"><?= e($service['summary']) ?></textarea></div>
    <div class="form-row-2">
      <div class="form-group"><label>لمن بتناسب؟</label><input type="text" name="audience" value="<?= e($service['audience']) ?>"></div>
      <div class="form-group"><label>شكل الجلسة</label><input type="text" name="format" value="<?= e($service['format']) ?>" placeholder="حضوري / عن بُعد"></div>
    </div>
    <div class="form-row-2">
      <div class="form-group"><label>المدة</label><input type="text" name="duration" value="<?= e($service['duration']) ?>" placeholder="مثال: ٥٠ دقيقة"></div>
      <div class="form-group"><label>السعر</label><input type="text" name="price" value="<?= e($service['price']) ?>" placeholder="اتركه فاضي لحد ما يتأكد"></div>
    </div>
    <div class="form-group">
      <label>وصف تفصيلي</label>
      <textarea name="description" rows="8"><?= e($service['id'] ? strip_tags(str_replace(['<br>', '</p>', '<li>'], ["\n", "\n\n", "- "], $service['description'])) : '') ?></textarea>
      <p class="field-hint">فقرة بكل سطر منفصل بسطر فاضي. سطر يبلّش بـ "- " رح يتحول لنقطة بقائمة.</p>
    </div>
    <div class="form-row-2">
      <div class="form-group"><label>الترتيب</label><input type="number" name="sort_order" value="<?= (int)$service['sort_order'] ?>"></div>
      <div class="form-group">
        <label><input type="checkbox" name="is_published" value="1" <?= (int)$service['is_published'] === 1 ? 'checked' : '' ?> style="width:auto;"> منشورة (تظهر للزوار)</label>
      </div>
    </div>
    <button type="submit" class="btn btn-primary">حفظ الخدمة</button>
  </form>
</div>

<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
