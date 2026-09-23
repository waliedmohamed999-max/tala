<?php
require_once __DIR__ . '/../includes/auth.php';
$adminUser = require_content_access();
$activeNav = 'articles';
$isReviewer = can_review($adminUser);

$id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$article = [
    'id' => null, 'title' => '', 'excerpt' => '', 'content_raw' => '', 'category_id' => '',
    'content_type' => 'article', 'author_name' => 'فريق تالا', 'reviewer_name' => '',
    'last_reviewed_at' => null, 'needs_update_note' => '', 'review_note' => '', 'sources' => '',
    'workflow_status' => 'draft', 'version' => 0, 'published_content' => null,
];
if ($id) {
    $stmt = db()->prepare('SELECT * FROM articles WHERE id = ?');
    $stmt->execute([$id]);
    $found = $stmt->fetch();
    if (!$found) { flash_set('error', 'المقالة غير موجودة.'); redirect('/admin/articles.php'); }
    $article = $found;
}

$categories = db()->query('SELECT * FROM categories ORDER BY sort_order, name')->fetchAll();
$errors = [];
$conflict = null;
// 'published' is intentionally NOT selectable here — publishing snapshots the
// working copy and must be a deliberate, separate action (see
// admin/articles.php and admin/review-queue.php) so editing text never
// silently changes what a visitor sees.
$workflowOptions = $isReviewer
    ? ['draft' => 'مسودة', 'in_review' => 'بانتظار المراجعة', 'approved' => 'معتمدة (بانتظار النشر)', 'needs_update' => 'بحاجة تحديث', 'archived' => 'مؤرشفة']
    : ['draft' => 'مسودة', 'in_review' => 'بانتظار المراجعة'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_verify()) {
        $errors[] = 'في مشكلة تقنية. جرّب من جديد.';
    }

    // --- conflict detection: did someone else save a real change since this editor loaded the page? ---
    $submittedBaseVersion = (int)($_POST['base_version'] ?? 0);
    if ($id && empty($errors)) {
        $vcheck = db()->prepare('SELECT * FROM articles WHERE id = ?');
        $vcheck->execute([$id]);
        $currentRow = $vcheck->fetch();
        if ((int)$currentRow['version'] !== $submittedBaseVersion) {
            $conflict = $currentRow; // show both sides, save nothing
            $errors[] = 'CONFLICT';
        }
    }

    $article['title'] = trim($_POST['title'] ?? '');
    $article['excerpt'] = trim($_POST['excerpt'] ?? '');
    $article['content_raw'] = trim($_POST['content_raw'] ?? '');
    $article['category_id'] = $_POST['category_id'] ?: null;
    $article['content_type'] = $_POST['content_type'] ?? 'article';
    $article['author_name'] = trim($_POST['author_name'] ?? '') ?: 'فريق تالا';
    $article['sources'] = trim($_POST['sources'] ?? '');
    $requestedStatus = $_POST['workflow_status'] ?? 'draft';
    $needsUpdateNote = trim($_POST['needs_update_note'] ?? '');

    if (!$conflict) {
        if ($article['title'] === '') { $errors[] = 'عنوان المقالة مطلوب.'; }
        if ($article['content_raw'] === '') { $errors[] = 'محتوى المقالة مطلوب.'; }
        if (!in_array($article['content_type'], ['article', 'guide', 'faq_extended'], true)) { $errors[] = 'نوع محتوى غير صحيح.'; }
        if (!array_key_exists($requestedStatus, $workflowOptions)) {
            $errors[] = 'ما عندك صلاحية تحدد هالحالة.';
        }
    }

    $realErrors = array_filter($errors, fn($e) => $e !== 'CONFLICT');
    if (empty($realErrors) && !$conflict) {
        $contentHtml = simple_text_to_html($article['content_raw']);
        $readMinutes = estimate_read_minutes($contentHtml);

        $reviewerName = $article['reviewer_name'];
        $lastReviewedAt = $article['last_reviewed_at'];
        $reviewNote = $article['review_note'] ?? null;

        $priorRow = null;
        if ($id) {
            $priorStmt = db()->prepare('SELECT * FROM articles WHERE id = ?');
            $priorStmt->execute([$id]);
            $priorRow = $priorStmt->fetch();
            $reviewerName = $priorRow['reviewer_name'];
            $lastReviewedAt = $priorRow['last_reviewed_at'];
        }

        if ($isReviewer && $requestedStatus === 'approved') {
            $reviewerName = $adminUser['name'];
            $lastReviewedAt = date('Y-m-d H:i:s');
        }
        if ($requestedStatus !== 'needs_update') {
            $needsUpdateNote = '';
        }

        // Content-change + auto-revert rule: editing an article that is
        // currently 'approved' (about to be published) or already live
        // (has a published snapshot) always sends it back to in_review —
        // regardless of what the dropdown says — because the reviewer
        // needs to see the new text before it can go out under their name.
        $contentChanged = $priorRow && (
            $priorRow['title'] !== $article['title'] ||
            $priorRow['content_raw'] !== $article['content_raw'] ||
            $priorRow['excerpt'] !== $article['excerpt'] ||
            (string)$priorRow['category_id'] !== (string)$article['category_id'] ||
            $priorRow['content_type'] !== $article['content_type'] ||
            $priorRow['sources'] !== $article['sources']
        );
        $wasLiveOrApproved = $priorRow && ($priorRow['workflow_status'] === 'approved' || $priorRow['published_content'] !== null);
        $autoRevertedToReview = false;
        if ($contentChanged && $wasLiveOrApproved) {
            $requestedStatus = 'in_review';
            $reviewNote = null; // fresh edit clears any old "changes requested" note
            $autoRevertedToReview = true;
        }

        $slugSource = $article['title'];
        if ($id) {
            // Snapshot the PRE-edit state to history before overwriting it.
            create_article_revision($id, (int)$adminUser['id'], $adminUser['name']);

            $slug = unique_slug('articles', slugify($slugSource), $id);
            $upd = db()->prepare("UPDATE articles SET slug=?, title=?, excerpt=?, content=?, content_raw=?, category_id=?, content_type=?, author_name=?, reviewer_name=?, last_reviewed_at=?, needs_update_note=?, review_note=?, sources=?, read_minutes=?, workflow_status=?, updated_at=datetime('now'), updated_by=?, version=version+1 WHERE id=?");
            $upd->execute([$slug, $article['title'], $article['excerpt'], $contentHtml, $article['content_raw'], $article['category_id'], $article['content_type'], $article['author_name'], $reviewerName, $lastReviewedAt, $needsUpdateNote, $reviewNote, $article['sources'], $readMinutes, $requestedStatus, $adminUser['name'], $id]);
            db()->prepare('DELETE FROM article_autosaves WHERE article_id = ?')->execute([$id]);

            flash_set('success', $autoRevertedToReview
                ? 'تم حفظ التعديلات. بما إنه هالمقال منشور/معتمد، رجع لحالة «بانتظار المراجعة» — النسخة المنشورة الحالية ضلت ظاهرة للزوار زي ما هي لحد ما تُعتمد وتُنشر النسخة الجديدة.'
                : 'تم حفظ المحتوى.');
        } else {
            $slug = unique_slug('articles', slugify($slugSource));
            $ins = db()->prepare('INSERT INTO articles (slug, title, excerpt, content, content_raw, category_id, content_type, author_name, reviewer_name, last_reviewed_at, needs_update_note, sources, read_minutes, workflow_status, updated_by, version) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,1)');
            $ins->execute([$slug, $article['title'], $article['excerpt'], $contentHtml, $article['content_raw'], $article['category_id'], $article['content_type'], $article['author_name'], $reviewerName, $lastReviewedAt, $needsUpdateNote, $article['sources'], $readMinutes, $requestedStatus, $adminUser['name']]);
            $newId = (int)db()->lastInsertId();
            create_article_revision($newId, (int)$adminUser['id'], $adminUser['name']);
            flash_set('success', 'تمت إضافة المحتوى.');
        }
        redirect('/admin/articles.php');
    }
}

$pageTitle = $id ? 'تعديل محتوى' : 'محتوى جديد';
require __DIR__ . '/includes/layout_top.php';
?>

<div class="admin-page-head">
  <h1><?= $id ? 'تعديل محتوى' : 'محتوى جديد' ?></h1>
  <?php if ($id): ?><a href="/admin/article-history.php?id=<?= $id ?>" class="btn btn-outline">🕓 سجل النسخ</a><?php endif; ?>
</div>

<?php foreach ($errors as $err): if ($err === 'CONFLICT') continue; ?><div class="alert alert-error"><?= e($err) ?></div><?php endforeach; ?>

<?php if ($conflict): ?>
  <div class="alert alert-error" role="alert">
    <strong>⚠️ في تعارض!</strong> حدا تاني (<?= e($conflict['updated_by'] ?: 'مستخدم آخر') ?>) حفظ تعديلات على هالمقالة من وقت ما فتحتها إنت، بتاريخ <?= e(date('Y/m/d H:i', strtotime($conflict['updated_at']))) ?>.
    تعديلاتك ما انحفظت لتجنّب استبدال شغل حدا تاني بصمت. قارن النسختين تحت، وبعدين حدّث الصفحة (F5) لتبلّش من النسخة الحالية وتعيد تطبيق تعديلاتك.
  </div>
  <div class="grid grid-2" style="margin-bottom:24px;">
    <div class="admin-card">
      <h3>النسخة الحالية بقاعدة البيانات (المحفوظة)</h3>
      <p><strong><?= e($conflict['title']) ?></strong></p>
      <div style="white-space:pre-wrap; font-size:.88rem; max-height:340px; overflow:auto;"><?= e($conflict['content_raw']) ?></div>
    </div>
    <div class="admin-card">
      <h3>تعديلاتك يلي ما انحفظت (انسخها قبل ما تحدّث الصفحة)</h3>
      <p><strong><?= e($article['title']) ?></strong></p>
      <div style="white-space:pre-wrap; font-size:.88rem; max-height:340px; overflow:auto;"><?= e($article['content_raw']) ?></div>
    </div>
  </div>
<?php endif; ?>

<?php if (!$isReviewer && !$conflict): ?>
  <div class="notice-inline" style="margin-bottom:20px;">إنت بدور محرر محتوى — فيك تحفظ كمسودة أو ترسل للمراجعة المهنية، وما فيك تعتمد أو تنشر مباشرة.</div>
<?php endif; ?>

<?php if (!$conflict && $article['workflow_status'] === 'changes_requested' && $article['review_note']): ?>
  <div class="notice-banner" style="margin-bottom:20px;">
    <strong>المراجع طلب تعديلات:</strong> <?= e($article['review_note']) ?>
  </div>
<?php endif; ?>

<?php if (!$conflict && $article['published_content'] !== null): ?>
  <div class="notice-inline" style="margin-bottom:20px;">
    📢 هالمقال منشور حاليًا وظاهر للزوار. أي تعديل هون رح يرجعه «بانتظار المراجعة» — النسخة المنشورة ما بتتغيّر إلا بعد اعتماد ونشر النسخة الجديدة. <a href="/articles/article.php?slug=<?= urlencode($article['slug']) ?>" target="_blank">شوف النسخة المنشورة حاليًا</a>
  </div>
<?php endif; ?>

<div class="admin-card" id="editorCard">
  <div id="autosaveStatus" class="field-hint" style="margin-bottom:14px;"></div>
  <div id="localBackupNotice" class="alert alert-info" style="display:none;"></div>
  <div id="otherEditorNotice" class="alert alert-error" style="display:none;"></div>

  <form method="post" class="form-narrow" style="max-width:760px;" id="articleForm" data-article-id="<?= (int)$id ?>" data-timed>
    <?= csrf_field() ?>
    <input type="hidden" name="base_version" id="baseVersion" value="<?= (int)($conflict ? $article['version'] : ($article['version'] ?? 0)) ?>">
    <div class="form-group"><label>العنوان *</label><input type="text" name="title" id="f_title" required value="<?= e($article['title']) ?>"></div>
    <div class="form-group"><label>مقتطف مختصر (يظهر بالبطاقات والبحث)</label><textarea name="excerpt" id="f_excerpt" rows="2"><?= e($article['excerpt']) ?></textarea></div>

    <div class="form-row-2">
      <div class="form-group">
        <label>التصنيف</label>
        <select name="category_id" id="f_category_id">
          <option value="">بدون تصنيف</option>
          <?php foreach ($categories as $c): ?>
            <option value="<?= (int)$c['id'] ?>" <?= (string)$article['category_id'] === (string)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="form-group">
        <label>نوع المحتوى</label>
        <select name="content_type" id="f_content_type">
          <option value="article" <?= $article['content_type'] === 'article' ? 'selected' : '' ?>>مقالة</option>
          <option value="guide" <?= $article['content_type'] === 'guide' ? 'selected' : '' ?>>دليل خطوة بخطوة</option>
          <option value="faq_extended" <?= $article['content_type'] === 'faq_extended' ? 'selected' : '' ?>>أسئلة وأجوبة موسعة</option>
        </select>
      </div>
    </div>

    <div class="form-group">
      <label>الحالة</label>
      <select name="workflow_status">
        <?php foreach ($workflowOptions as $val => $label): ?>
          <option value="<?= e($val) ?>" <?= $article['workflow_status'] === $val ? 'selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
      <?php if ($article['reviewer_name']): ?>
        <p class="field-hint">آخر اعتماد: <?= e($article['reviewer_name']) ?> — <?= e(date('Y/m/d', strtotime($article['last_reviewed_at']))) ?></p>
      <?php endif; ?>
      <p class="field-hint">لنشر المقال فعليًا للزوار بعد اعتماده، استخدم زر «نشر» من صفحة المقالات أو قائمة الاعتماد.</p>
    </div>

    <?php if ($isReviewer): ?>
    <div class="form-group">
      <label>ملاحظة "بحاجة تحديث" (تظهر بلوحة الإدارة فقط، تُستخدم إذا اخترت هالحالة فوق)</label>
      <textarea name="needs_update_note" rows="2"><?= e($article['needs_update_note']) ?></textarea>
    </div>
    <?php endif; ?>

    <div class="form-group">
      <label>محتوى المقالة *</label>
      <textarea name="content_raw" id="f_content_raw" rows="16" required><?= e($article['content_raw']) ?></textarea>
      <p class="field-hint">افصل بين الفقرات بسطر فاضي. سطر يبلّش بـ "## " رح يصير عنوان فرعي (وبيتحول تلقائيًا لجدول محتويات بالمقالة المنشورة). الأسطر المتتالية يلي تبلّش بـ "- " رح تتحول لقائمة نقطية.</p>
    </div>

    <div class="form-row-2">
      <div class="form-group"><label>اسم الكاتب</label><input type="text" name="author_name" value="<?= e($article['author_name']) ?>"></div>
      <div class="form-group">
        <label>القراءة التقديرية</label>
        <input type="text" value="<?= (int)($article['read_minutes'] ?? 4) ?> دقايق (تُحسب تلقائيًا عند الحفظ)" disabled>
      </div>
    </div>

    <div class="form-group">
      <label>المصادر (سطر لكل مصدر، اختياري)</label>
      <textarea name="sources" id="f_sources" rows="3"><?= e($article['sources']) ?></textarea>
    </div>

    <button type="submit" class="btn btn-primary">حفظ</button>
  </form>
</div>

<script>
(function () {
  var articleId = <?= (int)$id ?>;
  var csrfToken = document.querySelector('input[name="csrf_token"]').value;
  var form = document.getElementById('articleForm');
  var statusEl = document.getElementById('autosaveStatus');
  var localNotice = document.getElementById('localBackupNotice');
  var otherEditorNotice = document.getElementById('otherEditorNotice');
  var fields = ['title', 'excerpt', 'content_raw', 'category_id', 'content_type', 'sources'];
  var localKey = 'tala_admin_autosave_' + (articleId || 'new');
  var dirty = false;
  var saveTimer = null;

  function fieldEl(name) { return document.getElementById('f_' + name); }

  // Offer to restore a browser-local backup if it's different from what's loaded
  // (covers the "connection was down when autosave tried to reach the server" case).
  try {
    var backup = JSON.parse(localStorage.getItem(localKey) || 'null');
    if (backup && backup.content_raw && backup.content_raw !== fieldEl('content_raw').value) {
      localNotice.style.display = 'block';
      localNotice.innerHTML = 'في نسخة محفوظة محليًا بمتصفحك من ' + new Date(backup.savedAt).toLocaleString('ar') +
        ' مختلفة عن اللي معروض هلق. <button type="button" id="restoreLocalBtn" class="btn btn-outline btn-sm">استرجعها</button> <button type="button" id="dismissLocalBtn" class="btn btn-outline btn-sm">تجاهل</button>';
      document.getElementById('restoreLocalBtn').addEventListener('click', function () {
        fields.forEach(function (f) { if (backup[f] !== undefined && fieldEl(f)) fieldEl(f).value = backup[f]; });
        localNotice.style.display = 'none';
        dirty = true;
      });
      document.getElementById('dismissLocalBtn').addEventListener('click', function () {
        localStorage.removeItem(localKey);
        localNotice.style.display = 'none';
      });
    }
  } catch (e) {}

  function saveLocalBackup() {
    try {
      var data = { savedAt: Date.now() };
      fields.forEach(function (f) { if (fieldEl(f)) data[f] = fieldEl(f).value; });
      localStorage.setItem(localKey, JSON.stringify(data));
    } catch (e) {}
  }

  function autosaveToServer() {
    if (!articleId) { saveLocalBackup(); return; }
    var body = new URLSearchParams();
    body.set('csrf_token', csrfToken);
    body.set('id', articleId);
    body.set('base_version', document.getElementById('baseVersion').value);
    fields.forEach(function (f) { if (fieldEl(f)) body.set(f, fieldEl(f).value); });

    statusEl.textContent = 'عم نحفظ تلقائيًا...';
    fetch('/admin/article-autosave.php', { method: 'POST', body: body })
      .then(function (r) { if (!r.ok) throw new Error('bad status'); return r.json(); })
      .then(function (data) {
        saveLocalBackup();
        if (data.success) {
          dirty = false;
          var time = new Date().toLocaleTimeString('ar');
          statusEl.textContent = '✓ آخر حفظ تلقائي: ' + time;
          if (data.other_editor_active) {
            otherEditorNotice.style.display = 'block';
            otherEditorNotice.textContent = '⚠️ ' + data.other_editor_name + ' عم يحرر هالمقالة هلق كمان — احكوا مع بعض قبل ما تحفظوا، تجنّبًا لتعارض.';
          } else {
            otherEditorNotice.style.display = 'none';
          }
        } else {
          statusEl.textContent = '⚠️ تعذّر الحفظ التلقائي على السيرفر — تعديلاتك محفوظة بمتصفحك مؤقتًا بس.';
        }
      })
      .catch(function () {
        saveLocalBackup();
        statusEl.textContent = '⚠️ ما في اتصال — تعديلاتك محفوظة بمتصفحك مؤقتًا، رح نحاول نحفظ عالسيرفر أول ما يرجع الاتصال.';
      });
  }

  fields.forEach(function (f) {
    var el = fieldEl(f);
    if (!el) return;
    el.addEventListener('input', function () {
      dirty = true;
      clearTimeout(saveTimer);
      saveTimer = setTimeout(autosaveToServer, 3000);
    });
  });
  setInterval(function () { if (dirty) autosaveToServer(); }, 20000);

  window.addEventListener('beforeunload', function (e) {
    if (!dirty) return;
    saveLocalBackup();
    e.preventDefault();
    e.returnValue = '';
  });

  form.addEventListener('submit', function () {
    dirty = false;
    try { localStorage.removeItem(localKey); } catch (e) {}
  });
})();
</script>

<?php require __DIR__ . '/includes/layout_bottom.php'; ?>
