<?php
require_once __DIR__ . '/../includes/auth.php';
require_content_access();
$activeNav = 'services';

$id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$service = [
    'id' => null, 'name' => '', 'audience' => '', 'format' => '', 'duration' => '', 'price' => '',
    'summary' => '', 'description' => '', 'is_published' => 0, 'sort_order' => 0,
    'workflow_status' => 'draft', 'category_id' => '', 'location_mode' => '',
    'duration_minutes' => '', 'buffer_before_minutes' => 0, 'buffer_after_minutes' => 0,
    'price_amount' => '', 'price_currency' => '', 'cancellation_policy' => '',
    'show_on_website' => 0, 'bookable_online' => 0,
    'name_en' => '', 'summary_en' => '', 'description_en' => '',
];
if ($id) {
    $stmt = db()->prepare('SELECT * FROM services WHERE id = ?');
    $stmt->execute([$id]);
    $found = $stmt->fetch();
    if (!$found) { flash_set('error', 'الخدمة غير موجودة.'); redirect('/admin/services.php'); }
    $service = $found;
}
$categories = published_categories();

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

    $service['category_id'] = $_POST['category_id'] !== '' ? (int)$_POST['category_id'] : null;
    $service['location_mode'] = in_array($_POST['location_mode'] ?? '', ['in_person', 'remote', 'both'], true) ? $_POST['location_mode'] : null;
    $service['duration_minutes'] = $_POST['duration_minutes'] !== '' ? max(5, (int)$_POST['duration_minutes']) : null;
    $service['buffer_before_minutes'] = max(0, (int)($_POST['buffer_before_minutes'] ?? 0));
    $service['buffer_after_minutes'] = max(0, (int)($_POST['buffer_after_minutes'] ?? 0));
    $service['price_amount'] = $_POST['price_amount'] !== '' ? (float)$_POST['price_amount'] : null;
    $service['price_currency'] = trim($_POST['price_currency'] ?? '') ?: null;
    $service['cancellation_policy'] = trim($_POST['cancellation_policy'] ?? '') ?: null;
    $service['show_on_website'] = !empty($_POST['show_on_website']) ? 1 : 0;
    $service['bookable_online'] = !empty($_POST['bookable_online']) ? 1 : 0;
    $service['name_en'] = trim($_POST['name_en'] ?? '') ?: null;
    $service['summary_en'] = trim($_POST['summary_en'] ?? '') ?: null;
    $descriptionEnRaw = trim($_POST['description_en'] ?? '');

    if ($service['name'] === '') { $errors[] = 'اسم الخدمة مطلوب.'; }
    if ($service['bookable_online'] && empty($service['duration_minutes'])) {
        $errors[] = 'ما فيك تفعّل «حجز ذاتي» بدون تحديد مدة الجلسة بالدقائق.';
    }

    if (empty($errors)) {
        $descriptionHtml = simple_text_to_html($descriptionRaw);
        $descriptionEnHtml = $descriptionEnRaw !== '' ? simple_text_to_html($descriptionEnRaw) : null;
        $params = [
            $service['name'], $service['audience'], $service['format'], $service['duration'], $service['price'],
            $service['summary'], $descriptionHtml, $service['sort_order'],
            $service['category_id'], $service['location_mode'], $service['duration_minutes'],
            $service['buffer_before_minutes'], $service['buffer_after_minutes'],
            $service['price_amount'], $service['price_currency'], $service['cancellation_policy'],
            $service['show_on_website'], $service['bookable_online'],
            $service['name_en'], $service['summary_en'], $descriptionEnHtml,
        ];
        if ($id) {
            $slug = unique_slug('services', slugify($service['name']), $id);
            $upd = db()->prepare("UPDATE services SET
                slug=?, name=?, audience=?, format=?, duration=?, price=?, summary=?, description=?, sort_order=?,
                category_id=?, location_mode=?, duration_minutes=?, buffer_before_minutes=?, buffer_after_minutes=?,
                price_amount=?, price_currency=?, cancellation_policy=?, show_on_website=?, bookable_online=?,
                name_en=?, summary_en=?, description_en=?, updated_at=datetime('now')
                WHERE id=?");
            $upd->execute(array_merge([$slug], $params, [$id]));
            flash_set('success', 'تم حفظ الخدمة.');
        } else {
            $slug = unique_slug('services', slugify($service['name']));
            $ins = db()->prepare('INSERT INTO services
                (slug, name, audience, format, duration, price, summary, description, sort_order,
                 category_id, location_mode, duration_minutes, buffer_before_minutes, buffer_after_minutes,
                 price_amount, price_currency, cancellation_policy, show_on_website, bookable_online,
                 name_en, summary_en, description_en)
                VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
            $ins->execute(array_merge([$slug], $params));
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

<?php if ($id && $service['workflow_status'] !== 'draft'): ?>
<div class="notice-inline" style="margin-bottom:20px;">
  الحالة الحالية: <strong><?= [
      'pending_review' => 'بانتظار اعتماد تالا',
      'published' => 'منشورة',
      'paused' => 'موقوفة',
  ][$service['workflow_status']] ?? $service['workflow_status'] ?></strong>
  — لتغيير حالة الاعتماد (نشر/إيقاف) استخدم <a href="/admin/services.php">قائمة الخدمات</a><?php if (can_review(require_content_access())): ?> أو <a href="/admin/review-queue.php">قائمة الاعتماد</a><?php endif; ?>.
</div>
<?php endif; ?>

<div class="admin-card">
  <form method="post" class="form-narrow">
    <?= csrf_field() ?>
    <div class="form-group"><label>اسم الخدمة *</label><input type="text" name="name" required value="<?= e($service['name']) ?>"></div>
    <div class="form-group"><label>وصف مختصر (يظهر بالبطاقات)</label><textarea name="summary"><?= e($service['summary']) ?></textarea></div>
    <div class="form-row-2">
      <div class="form-group"><label>لمن بتناسب؟</label><input type="text" name="audience" value="<?= e($service['audience']) ?>"></div>
      <div class="form-group">
        <label>التصنيف</label>
        <select name="category_id">
          <option value="">بدون تصنيف</option>
          <?php foreach ($categories as $c): ?>
            <option value="<?= (int)$c['id'] ?>" <?= (string)$service['category_id'] === (string)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <h3 style="margin-top:24px;">طريقة الحضور والمدة</h3>
    <div class="form-row-2">
      <div class="form-group">
        <label>طريقة الحضور</label>
        <select name="location_mode">
          <option value="">غير محدد بعد</option>
          <option value="in_person" <?= $service['location_mode'] === 'in_person' ? 'selected' : '' ?>>حضوري فقط</option>
          <option value="remote" <?= $service['location_mode'] === 'remote' ? 'selected' : '' ?>>عن بُعد فقط</option>
          <option value="both" <?= $service['location_mode'] === 'both' ? 'selected' : '' ?>>حضوري أو عن بُعد</option>
        </select>
      </div>
      <div class="form-group"><label>شكل الجلسة (نص وصفي، يظهر للزوار)</label><input type="text" name="format" value="<?= e($service['format']) ?>" placeholder="مثال: حضوري بحمص"></div>
    </div>
    <div class="form-row-2">
      <div class="form-group"><label>المدة (نص يظهر للزوار)</label><input type="text" name="duration" value="<?= e($service['duration']) ?>" placeholder="مثال: ٥٠ دقيقة"></div>
      <div class="form-group">
        <label>المدة بالدقائق (رقم دقيق — مطلوب لتفعيل الحجز الذاتي)</label>
        <input type="number" name="duration_minutes" min="5" step="5" value="<?= e((string)$service['duration_minutes']) ?>">
      </div>
    </div>
    <div class="form-row-2">
      <div class="form-group"><label>فاصل قبل الجلسة (دقائق)</label><input type="number" name="buffer_before_minutes" min="0" step="5" value="<?= (int)$service['buffer_before_minutes'] ?>"></div>
      <div class="form-group"><label>فاصل بعد الجلسة (دقائق)</label><input type="number" name="buffer_after_minutes" min="0" step="5" value="<?= (int)$service['buffer_after_minutes'] ?>"></div>
    </div>

    <h3 style="margin-top:24px;">السعر والسياسات</h3>
    <div class="form-row-2">
      <div class="form-group"><label>السعر (نص يظهر للزوار)</label><input type="text" name="price" value="<?= e($service['price']) ?>" placeholder="اتركه فاضي لحد ما يتأكد"></div>
      <div class="form-group"></div>
      <div class="form-group"><label>السعر (رقم، اختياري — للفوترة)</label><input type="number" name="price_amount" step="0.01" min="0" value="<?= e((string)$service['price_amount']) ?>"></div>
      <div class="form-group"><label>العملة</label><input type="text" name="price_currency" value="<?= e((string)$service['price_currency']) ?>" placeholder="مثال: USD"></div>
    </div>
    <div class="form-group"><label>سياسة الإلغاء/إعادة الجدولة</label><textarea name="cancellation_policy" rows="3"><?= e((string)$service['cancellation_policy']) ?></textarea></div>

    <div class="form-group">
      <label>وصف تفصيلي</label>
      <textarea name="description" rows="8"><?= e($service['id'] ? strip_tags(str_replace(['<br>', '</p>', '<li>'], ["\n", "\n\n", "- "], $service['description'])) : '') ?></textarea>
      <p class="field-hint">فقرة بكل سطر منفصل بسطر فاضي. سطر يبلّش بـ "- " رح يتحول لنقطة بقائمة.</p>
    </div>

    <h3 style="margin-top:24px;">النص الإنجليزي (اختياري)</h3>
    <p class="field-hint">اتركه فاضي لحد ما تتوفر ترجمة معتمدة — بهالحالة صفحة الخدمة بالإنجليزي بتعرض إشعار "غير متوفرة بالإنجليزي بعد" بدل نص غير دقيق.</p>
    <div class="form-group"><label>الاسم بالإنجليزي</label><input type="text" name="name_en" value="<?= e((string)$service['name_en']) ?>"></div>
    <div class="form-group"><label>الوصف المختصر بالإنجليزي</label><textarea name="summary_en"><?= e((string)$service['summary_en']) ?></textarea></div>
    <div class="form-group">
      <label>الوصف التفصيلي بالإنجليزي</label>
      <textarea name="description_en" rows="6"><?= e($service['id'] && !empty($service['description_en']) ? strip_tags(str_replace(['<br>', '</p>', '<li>'], ["\n", "\n\n", "- "], $service['description_en'])) : '') ?></textarea>
    </div>

    <h3 style="margin-top:24px;">الظهور</h3>
    <div class="form-row-2">
      <div class="form-group"><label>الترتيب</label><input type="number" name="sort_order" value="<?= (int)$service['sort_order'] ?>"></div>
      <div class="form-group"></div>
      <div class="form-group">
        <label><input type="checkbox" name="show_on_website" value="1" <?= (int)$service['show_on_website'] === 1 ? 'checked' : '' ?> style="width:auto;"> تظهر بصفحة الخدمات العامة</label>
      </div>
      <div class="form-group">
        <label><input type="checkbox" name="bookable_online" value="1" <?= (int)$service['bookable_online'] === 1 ? 'checked' : '' ?> style="width:auto;"> متاحة للحجز الذاتي المباشر</label>
        <p class="field-hint">بتحتاج مدة بالدقائق + ساعات عمل مضبوطة من <a href="/admin/clinic-hours.php">ساعات العمل</a> حتى تظهر فترات فعلية.</p>
      </div>
    </div>
    <p class="field-hint">التبديلان فوق مستقلّان عن بعض عن قصد، وكلاهما ما بيفعّل إلا بعد ما تعتمد تالا الخدمة (تصير «منشورة») من <a href="/admin/services.php">قائمة الخدمات</a><?php if (can_review(require_content_access())): ?> أو <a href="/admin/review-queue.php">قائمة الاعتماد</a><?php endif; ?>.</p>

    <button type="submit" class="btn btn-primary">حفظ الخدمة</button>
  </form>
</div>

<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
