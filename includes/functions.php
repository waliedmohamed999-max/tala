<?php
require_once __DIR__ . '/db.php';

/** Escape for HTML output. Use on every dynamic value printed into markup. */
function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function get_setting(string $key, string $default = ''): string
{
    static $cache = null;
    if ($cache === null) {
        $cache = [];
        foreach (db()->query('SELECT `key`, value FROM settings') as $row) {
            $cache[$row['key']] = $row['value'];
        }
    }
    return $cache[$key] ?? $default;
}

function set_setting(string $key, string $value): void
{
    $stmt = db()->prepare('INSERT INTO settings (`key`, value) VALUES (:k, :v)
        ON CONFLICT(`key`) DO UPDATE SET value = :v');
    $stmt->execute([':k' => $key, ':v' => $value]);
}

function setting_bool(string $key): bool
{
    return get_setting($key) === '1';
}

function redirect(string $path): never
{
    header('Location: ' . $path);
    exit;
}

function base_url(string $path = ''): string
{
    return '/' . ltrim($path, '/');
}

function flash_set(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function flash_get_all(): array
{
    $items = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $items;
}

function slugify(string $text): string
{
    // Keep Arabic letters, latin letters, numbers; replace everything else with a dash.
    // Strip Arabic/Latin punctuation explicitly first — PCRE's \p{Arabic} property
    // also matches Arabic punctuation marks (e.g. U+061F ؟), which we don't want in a slug.
    $text = trim($text);
    $text = preg_replace('/[؟?!.,:;"\'«»()\[\]{}]/u', '', $text);
    $text = preg_replace('/[\s_]+/u', '-', $text);
    $text = preg_replace('/[^\p{Arabic}a-zA-Z0-9\-]/u', '', $text);
    $text = preg_replace('/-+/', '-', $text);
    return trim($text, '-');
}

function unique_slug(string $table, string $baseSlug, ?int $excludeId = null): string
{
    $pdo = db();
    $slug = $baseSlug !== '' ? $baseSlug : 'item';
    $i = 1;
    while (true) {
        $sql = "SELECT COUNT(*) FROM {$table} WHERE slug = :slug";
        $params = [':slug' => $slug];
        if ($excludeId !== null) {
            $sql .= ' AND id != :id';
            $params[':id'] = $excludeId;
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        if ((int)$stmt->fetchColumn() === 0) {
            return $slug;
        }
        $i++;
        $slug = $baseSlug . '-' . $i;
    }
}

function estimate_read_minutes(string $html): int
{
    $text = trim(strip_tags($html));
    $wordCount = $text === '' ? 0 : count(preg_split('/\s+/u', $text));
    return max(1, (int)ceil($wordCount / 140));
}

// --- CSRF -------------------------------------------------------------

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function csrf_verify(): bool
{
    $token = $_POST['csrf_token'] ?? '';
    return is_string($token) && !empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// --- Simple IP-bucketed rate limiting (no external service needed) ----

function client_ip_hash(): string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    return hash('sha256', $ip . '|tala-site-salt');
}

/**
 * Returns true if the action is allowed, false if the caller should be
 * throttled. Also records this attempt when allowed.
 */
function rate_limit_check(string $action, int $maxAttempts = 5, int $windowSeconds = 3600): bool
{
    $pdo = db();
    $bucket = $action . ':' . client_ip_hash();

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM rate_limits WHERE bucket = :b AND created_at >= datetime('now', :window)");
    $stmt->execute([':b' => $bucket, ':window' => '-' . $windowSeconds . ' seconds']);
    $count = (int)$stmt->fetchColumn();

    if ($count >= $maxAttempts) {
        return false;
    }

    $ins = $pdo->prepare('INSERT INTO rate_limits (bucket) VALUES (:b)');
    $ins->execute([':b' => $bucket]);
    return true;
}

// --- Very small, safe rich-text-to-HTML helper for article bodies -----
// Admin content is entered as plain text with blank-line paragraphs and
// "- " bullet lines; we convert that to safe HTML ourselves (no raw HTML
// from the textarea is ever trusted/echoed).
function simple_text_to_html(string $raw): string
{
    $raw = str_replace("\r\n", "\n", $raw);
    $blocks = preg_split('/\n\s*\n/', trim($raw));
    $html = '';
    foreach ($blocks as $block) {
        $lines = array_values(array_filter(array_map('trim', explode("\n", $block)), fn($l) => $l !== ''));
        if (empty($lines)) {
            continue;
        }
        $isList = true;
        foreach ($lines as $l) {
            if (!str_starts_with($l, '- ')) {
                $isList = false;
                break;
            }
        }
        if ($isList) {
            $html .= "<ul>\n";
            foreach ($lines as $l) {
                $html .= '<li>' . e(substr($l, 2)) . "</li>\n";
            }
            $html .= "</ul>\n";
        } elseif (preg_match('/^##\s+(.+)/u', $lines[0], $m)) {
            $html .= '<h3>' . e($m[1]) . "</h3>\n";
        } else {
            $html .= '<p>' . implode('<br>', array_map('e', $lines)) . "</p>\n";
        }
    }
    return $html;
}

/**
 * Renders a small abstract, brand-colored SVG illustration — used as a
 * calm topic accent on page headers instead of stock photography or any
 * image that could be mistaken for a real photo of Tala or her clinic.
 * $variant just picks a different shape/color arrangement (1-6) so pages
 * don't all look identical.
 */
function render_topic_art(int $variant = 1, string $size = '120px'): void
{
    $variants = [
        1 => ['#7C9885', '#5B6B7A', 'circles'],
        2 => ['#5B6B7A', '#7C9885', 'waves'],
        3 => ['#9DB6A4', '#7C9885', 'circles'],
        4 => ['#7C9885', '#8FA0AC', 'leaf'],
        5 => ['#8FA0AC', '#7C9885', 'waves'],
        6 => ['#5E7A68', '#9CAAB4', 'leaf'],
    ];
    [$c1, $c2, $shape] = $variants[$variant] ?? $variants[1];
    echo '<svg width="' . e($size) . '" height="' . e($size) . '" viewBox="0 0 120 120" fill="none" aria-hidden="true" style="display:block;">';
    echo '<circle cx="60" cy="60" r="58" fill="' . e($c1) . '" opacity="0.12"/>';
    if ($shape === 'circles') {
        echo '<circle cx="46" cy="50" r="22" fill="' . e($c1) . '" opacity="0.55"/>';
        echo '<circle cx="74" cy="68" r="28" fill="' . e($c2) . '" opacity="0.35"/>';
    } elseif ($shape === 'waves') {
        echo '<path d="M20 55 Q45 30 70 55 T120 55" stroke="' . e($c1) . '" stroke-width="6" fill="none" stroke-linecap="round" opacity="0.6"/>';
        echo '<path d="M10 78 Q40 58 70 78 T130 78" stroke="' . e($c2) . '" stroke-width="6" fill="none" stroke-linecap="round" opacity="0.4"/>';
    } else { // leaf
        echo '<path d="M60 20C85 30 90 60 65 85C55 95 35 90 30 75C25 55 40 25 60 20Z" fill="' . e($c1) . '" opacity="0.5"/>';
        echo '<path d="M60 30V80" stroke="' . e($c2) . '" stroke-width="3" opacity="0.6" stroke-linecap="round"/>';
    }
    echo '</svg>';
}

/**
 * Injects id="" anchors into each <h3> in rendered article HTML and returns
 * both the modified HTML and a flat table-of-contents array, so long
 * articles/guides get an auto-generated jump-to-section list.
 */
function build_table_of_contents(string $html): array
{
    $toc = [];
    $counter = 0;
    $withIds = preg_replace_callback('/<h3>(.*?)<\/h3>/u', function ($m) use (&$toc, &$counter) {
        $counter++;
        $text = trim(strip_tags($m[1]));
        $anchorId = 'section-' . $counter . '-' . mb_substr(slugify($text), 0, 40);
        $anchorId = $anchorId !== '' ? $anchorId : 'section-' . $counter;
        $toc[] = ['id' => $anchorId, 'text' => $text];
        return '<h3 id="' . e($anchorId) . '">' . $m[1] . '</h3>';
    }, $html);
    return [$withIds, $toc];
}

function content_type_label(string $type): string
{
    return [
        'article' => 'مقالة',
        'guide' => 'دليل خطوة بخطوة',
        'faq_extended' => 'أسئلة وأجوبة موسعة',
    ][$type] ?? 'مقالة';
}

/**
 * Copies the current working copy of an article into its published_* snapshot
 * columns — this is the ONLY thing that changes what the public site shows.
 * Editing the working copy afterward (title/content_raw/etc) never touches
 * these columns again until this function runs a second time.
 */
function publish_article(int $articleId, string $reviewerName): void
{
    $pdo = db();
    $stmt = $pdo->prepare('SELECT * FROM articles WHERE id = ?');
    $stmt->execute([$articleId]);
    $a = $stmt->fetch();
    if (!$a) { return; }

    $upd = $pdo->prepare("UPDATE articles SET
        workflow_status = 'published',
        published_at = COALESCE(published_at, datetime('now')),
        reviewer_name = ?, last_reviewed_at = datetime('now'),
        published_content = content, published_content_raw = content_raw,
        published_title = title, published_excerpt = excerpt,
        published_read_minutes = read_minutes, published_reviewer_name = ?,
        published_reviewed_at = datetime('now'),
        published_sources = sources, published_category_id = category_id,
        published_content_type = content_type,
        updated_at = datetime('now')
        WHERE id = ?");
    $upd->execute([$reviewerName, $reviewerName, $articleId]);
}

/**
 * Saves a permanent snapshot of an article's current working copy to the
 * revision history — called before every real (non-autosave) save, and
 * again after a restore (so restoring never erases history, it just adds
 * a new entry on top).
 */
function create_article_revision(int $articleId, ?int $editorId, string $editorName, ?int $restoredFromId = null): void
{
    $pdo = db();
    $stmt = $pdo->prepare('SELECT * FROM articles WHERE id = ?');
    $stmt->execute([$articleId]);
    $a = $stmt->fetch();
    if (!$a) { return; }

    $ins = $pdo->prepare('INSERT INTO article_revisions
        (article_id, title, excerpt, content_raw, category_id, content_type, sources, workflow_status, editor_name, editor_id, restored_from_revision_id)
        VALUES (?,?,?,?,?,?,?,?,?,?,?)');
    $ins->execute([
        $articleId, $a['title'], $a['excerpt'], $a['content_raw'], $a['category_id'],
        $a['content_type'], $a['sources'], $a['workflow_status'], $editorName, $editorId, $restoredFromId,
    ]);
}

/**
 * Shared SELECT/FROM fragments for every public article query. Selects the
 * published_* snapshot (aliased to the plain field names templates already
 * use) so a page never has to know about the snapshot mechanism — it just
 * reads $row['title'], $row['content'], etc. as before. Visibility is
 * always `published_content IS NOT NULL`, never workflow_status, since an
 * article can be mid-re-review (workflow_status = in_review) while its
 * last published snapshot is still what visitors see.
 */
function articles_public_select(): string
{
    return "a.id, a.slug, a.locale, a.translation_of, a.published_title AS title, a.published_excerpt AS excerpt,
        a.published_content AS content, a.published_content_raw AS content_raw,
        a.published_read_minutes AS read_minutes, a.published_reviewer_name AS reviewer_name,
        a.published_sources AS sources, a.published_category_id AS category_id,
        a.published_content_type AS content_type, a.author_name, a.published_reviewed_at AS last_reviewed_at,
        a.published_at, a.updated_at";
}

function articles_public_from(): string
{
    return "articles a LEFT JOIN categories c ON c.id = a.published_category_id";
}

/**
 * Small line-based diff (LCS algorithm) for comparing two article revisions
 * in the admin UI. Fine for article-length text; not meant for huge files.
 * Returns a flat list of ['type' => same|added|removed, 'text' => line].
 */
function simple_diff_lines(string $old, string $new): array
{
    $a = explode("\n", $old);
    $b = explode("\n", $new);
    $m = count($a);
    $n = count($b);

    $lcs = array_fill(0, $m + 1, array_fill(0, $n + 1, 0));
    for ($i = $m - 1; $i >= 0; $i--) {
        for ($j = $n - 1; $j >= 0; $j--) {
            $lcs[$i][$j] = $a[$i] === $b[$j] ? $lcs[$i + 1][$j + 1] + 1 : max($lcs[$i + 1][$j], $lcs[$i][$j + 1]);
        }
    }

    $result = [];
    $i = 0; $j = 0;
    while ($i < $m && $j < $n) {
        if ($a[$i] === $b[$j]) {
            $result[] = ['type' => 'same', 'text' => $a[$i]];
            $i++; $j++;
        } elseif ($lcs[$i + 1][$j] >= $lcs[$i][$j + 1]) {
            $result[] = ['type' => 'removed', 'text' => $a[$i]];
            $i++;
        } else {
            $result[] = ['type' => 'added', 'text' => $b[$j]];
            $j++;
        }
    }
    while ($i < $m) { $result[] = ['type' => 'removed', 'text' => $a[$i]]; $i++; }
    while ($j < $n) { $result[] = ['type' => 'added', 'text' => $b[$j]]; $j++; }

    return $result;
}

function category_icon(?string $slug): string
{
    $icons = [
        'قلق-وتوتر' => '🌊',
        'مشاعر-ومزاج' => '💬',
        'نوم-وروتين' => '🌙',
        'علاقات-وتواصل' => '🤝',
        'حدود-شخصية' => '🧭',
        'ضغوط-الحياة' => '🍃',
        'البدء-بالعلاج-النفسي' => '🌱',
        'العناية-بالنفس' => '🌿',
    ];
    return $icons[$slug] ?? '📝';
}

/**
 * Privacy-respecting analytics: records only an event type, the path, and
 * the day — never a visitor identifier, IP, or any free text a visitor
 * typed. Safe to call from public pages/forms.
 */
function record_analytics_event(string $eventType, ?string $path = null): void
{
    // Analytics is on by default (anonymous/aggregate counts only); respect
    // an explicit opt-out if the owner ever sets this setting to '0'.
    if (get_setting('analytics_enabled', '1') === '0') {
        return;
    }
    try {
        $stmt = db()->prepare('INSERT INTO analytics_events (event_type, path, day) VALUES (?, ?, ?)');
        $stmt->execute([$eventType, $path, date('Y-m-d')]);
    } catch (Throwable $e) {
        // Analytics must never break the page it's called from.
    }
}

function published_categories(): array
{
    return db()->query('SELECT * FROM categories ORDER BY sort_order, name')->fetchAll();
}

function published_services(): array
{
    return db()->query('SELECT * FROM services WHERE is_published = 1 ORDER BY sort_order, name')->fetchAll();
}

function published_faqs(): array
{
    return db()->query('SELECT * FROM faqs WHERE is_published = 1 ORDER BY sort_order')->fetchAll();
}
