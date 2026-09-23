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

/** Pairs each category with one of render_topic_art()'s 6 variants, so card thumbnails feel consistent per topic instead of random. */
function category_art_variant(?string $slug): int
{
    $variants = [
        'قلق-وتوتر' => 2,
        'مشاعر-ومزاج' => 1,
        'نوم-وروتين' => 5,
        'علاقات-وتواصل' => 3,
        'حدود-شخصية' => 6,
        'ضغوط-الحياة' => 4,
        'البدء-بالعلاج-النفسي' => 1,
        'العناية-بالنفس' => 4,
    ];
    return $variants[$slug] ?? 1;
}

/**
 * A small rounded "card thumbnail" showing the same calm abstract art as
 * render_topic_art(), used everywhere a card would otherwise have no
 * visual at all (article cards, service cards, event cards without a
 * real photo yet). Never a stock/stereotypical photo — see the brand
 * guidance against sad-therapy imagery and against claiming a photo is
 * Tala's own until she provides and approves one.
 */
function render_card_thumb(int $variant = 1): void
{
    $variants = [
        1 => ['#7C9885', '#5B6B7A'],
        2 => ['#5B6B7A', '#7C9885'],
        3 => ['#9DB6A4', '#7C9885'],
        4 => ['#7C9885', '#8FA0AC'],
        5 => ['#8FA0AC', '#7C9885'],
        6 => ['#5E7A68', '#9CAAB4'],
    ];
    [$c1, $c2] = $variants[$variant] ?? $variants[1];
    ?>
    <div class="card-thumb" style="background: linear-gradient(135deg, <?= e($c1) ?>22, <?= e($c2) ?>22);">
        <?php render_topic_art($variant, '56px'); ?>
    </div>
    <?php
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

/** Services shown on the public marketing pages (services.php, service.php, homepage teaser). */
function published_services(): array
{
    return db()->query("SELECT * FROM services WHERE workflow_status = 'published' AND show_on_website = 1 ORDER BY sort_order, name")->fetchAll();
}

/**
 * Every published service, regardless of show_on_website — used by the
 * booking form's service dropdown, which is a separate surface from the
 * marketing pages (a service can be requestable without being publicly
 * listed, or listed without being requestable — see the plan's note on
 * show_on_website vs bookable_online being independent toggles).
 */
function bookable_form_services(): array
{
    return db()->query("SELECT * FROM services WHERE workflow_status = 'published' ORDER BY sort_order, name")->fetchAll();
}

function published_faqs(): array
{
    return db()->query('SELECT * FROM faqs WHERE is_published = 1 ORDER BY sort_order')->fetchAll();
}

// =========================================================================
// Clinic operations: calendar/scheduling, patients, billing, clinical notes,
// access auditing. See includes/db.php's migrate_clinic_ops() for schema.
// =========================================================================

/**
 * Services now go through a small workflow (draft -> pending_review ->
 * published -> paused) layered on top of the original is_published boolean,
 * which stays in sync here so published_services() (used across the public
 * site) never has to change. Falling out of 'published' also clears the two
 * visibility flags — a paused service can't stay listed or bookable.
 */
function set_service_workflow_status(int $serviceId, string $status, ?string $reviewedBy = null, ?string $reviewNote = null): void
{
    $valid = ['draft', 'pending_review', 'published', 'paused'];
    if (!in_array($status, $valid, true)) {
        return;
    }
    $isPublished = $status === 'published' ? 1 : 0;
    $pdo = db();
    $stmt = $pdo->prepare("UPDATE services SET
        workflow_status = :status, is_published = :is_published,
        review_note = :review_note, reviewed_by = :reviewed_by,
        reviewed_at = CASE WHEN :status2 = 'published' THEN datetime('now') ELSE reviewed_at END,
        updated_at = datetime('now')
        WHERE id = :id");
    $stmt->execute([
        ':status' => $status, ':status2' => $status, ':is_published' => $isPublished,
        ':review_note' => $reviewNote, ':reviewed_by' => $reviewedBy, ':id' => $serviceId,
    ]);
    if ($status !== 'published') {
        $pdo->prepare('UPDATE services SET show_on_website = 0, bookable_online = 0 WHERE id = ?')->execute([$serviceId]);
    }
}

/**
 * Working-hours rows for a service: an override (service_id = X) fully
 * replaces the general schedule (service_id IS NULL) for any weekday it
 * covers — no deeper per-day merge. Kept deliberately simple; see the plan's
 * note on clinic_hours for the reasoning.
 */
function clinic_effective_hours(int $serviceId): array
{
    $pdo = db();
    $override = $pdo->prepare('SELECT * FROM clinic_hours WHERE service_id = ? AND is_active = 1 ORDER BY weekday, start_time');
    $override->execute([$serviceId]);
    $rows = $override->fetchAll();
    if (!empty($rows)) {
        return $rows;
    }
    return $pdo->query('SELECT * FROM clinic_hours WHERE service_id IS NULL AND is_active = 1 ORDER BY weekday, start_time')->fetchAll();
}

/**
 * Computes real open slots for a service between two dates (inclusive),
 * respecting working hours, closures, the service's own duration/buffers,
 * already-booked appointments (any non-cancelled status), a minimum notice
 * window, and the booking horizon. This is a DISPLAY helper only — the
 * actual double-booking guard is create_appointment_atomically(), which
 * re-checks at the moment of insert regardless of what this returned.
 * Returns ['YYYY-MM-DD' => [['start' => 'HH:MM', 'starts_at' => ISO, 'ends_at' => ISO], ...]]
 */
function compute_available_slots(int $serviceId, string $dateFrom, string $dateTo): array
{
    $pdo = db();
    $svcStmt = $pdo->prepare('SELECT * FROM services WHERE id = ? AND workflow_status = \'published\' AND bookable_online = 1');
    $svcStmt->execute([$serviceId]);
    $service = $svcStmt->fetch();
    if (!$service || empty($service['duration_minutes']) || (int)$service['duration_minutes'] <= 0) {
        return [];
    }
    $duration = (int)$service['duration_minutes'];
    $bufferBefore = (int)$service['buffer_before_minutes'];
    $bufferAfter = (int)$service['buffer_after_minutes'];

    $hours = clinic_effective_hours($serviceId);
    if (empty($hours)) {
        return [];
    }
    $hoursByWeekday = [];
    foreach ($hours as $h) {
        $hoursByWeekday[(int)$h['weekday']][] = $h;
    }

    $closures = $pdo->query('SELECT date_from, date_to FROM clinic_closures')->fetchAll();

    // Existing appointments in range, joined to their own service's buffers so
    // a neighboring appointment's buffer is respected too, not just this one's.
    $existingStmt = $pdo->prepare("SELECT ap.starts_at, ap.ends_at,
            COALESCE(s.buffer_before_minutes, 0) AS buf_before, COALESCE(s.buffer_after_minutes, 0) AS buf_after
        FROM appointments ap LEFT JOIN services s ON s.id = ap.service_id
        WHERE ap.provider_id = 1 AND ap.status != 'cancelled'
        AND ap.starts_at < :to AND ap.ends_at > :from");
    $existingStmt->execute([':from' => $dateFrom . ' 00:00:00', ':to' => $dateTo . ' 23:59:59']);
    $busy = [];
    foreach ($existingStmt->fetchAll() as $row) {
        $busy[] = [
            strtotime($row['starts_at']) - ((int)$row['buf_before'] * 60),
            strtotime($row['ends_at']) + ((int)$row['buf_after'] * 60),
        ];
    }

    $minNoticeSeconds = (int)get_setting('min_notice_hours', '24') * 3600;
    $earliestAllowed = time() + $minNoticeSeconds;

    $result = [];
    $cursor = strtotime($dateFrom);
    $end = strtotime($dateTo);
    while ($cursor <= $end) {
        $dateStr = date('Y-m-d', $cursor);
        $weekday = (int)date('w', $cursor);

        $closed = false;
        foreach ($closures as $c) {
            if ($dateStr >= $c['date_from'] && $dateStr <= $c['date_to']) { $closed = true; break; }
        }
        if ($closed || empty($hoursByWeekday[$weekday])) {
            $cursor += 86400;
            continue;
        }

        $daySlots = [];
        foreach ($hoursByWeekday[$weekday] as $h) {
            $slotStart = strtotime($dateStr . ' ' . $h['start_time']);
            $windowEnd = strtotime($dateStr . ' ' . $h['end_time']);
            while ($slotStart + ($duration * 60) <= $windowEnd) {
                $slotEnd = $slotStart + ($duration * 60);
                $reservedFrom = $slotStart - ($bufferBefore * 60);
                $reservedTo = $slotEnd + ($bufferAfter * 60);

                $overlaps = false;
                foreach ($busy as [$bFrom, $bTo]) {
                    if ($reservedFrom < $bTo && $reservedTo > $bFrom) { $overlaps = true; break; }
                }
                if (!$overlaps && $slotStart >= $earliestAllowed) {
                    $daySlots[] = [
                        'start' => date('H:i', $slotStart),
                        'starts_at' => date('Y-m-d H:i:s', $slotStart),
                        'ends_at' => date('Y-m-d H:i:s', $slotEnd),
                    ];
                }
                $slotStart += $duration * 60;
            }
        }
        if (!empty($daySlots)) {
            $result[$dateStr] = $daySlots;
        }
        $cursor += 86400;
    }
    return $result;
}

/**
 * Inserts a new appointment inside an IMMEDIATE transaction so two requests
 * arriving at the same instant for the same slot can't both succeed — the
 * second one's overlap check will see the first's row (or, if truly
 * simultaneous, SQLite's own write lock serializes them; busy_timeout in
 * db() gives the loser up to 5s before giving up). This is the ONLY path
 * that may insert into appointments — both the public booking flow and the
 * admin calendar call this, never a raw INSERT.
 */
function create_appointment_atomically(array $data): array
{
    $pdo = db();
    try {
        $pdo->exec('BEGIN IMMEDIATE');

        $overlap = $pdo->prepare("SELECT COUNT(*) FROM appointments
            WHERE provider_id = :provider AND status != 'cancelled'
            AND starts_at < :ends_at AND ends_at > :starts_at");
        $overlap->execute([
            ':provider' => $data['provider_id'] ?? 1,
            ':starts_at' => $data['starts_at'],
            ':ends_at' => $data['ends_at'],
        ]);
        if ((int)$overlap->fetchColumn() > 0) {
            $pdo->exec('ROLLBACK');
            return ['success' => false, 'error' => 'للأسف هالفترة صارت محجوزة قبل ما توصل. اختر فترة تانية من فضلك.'];
        }

        $ins = $pdo->prepare('INSERT INTO appointments
            (provider_id, service_id, patient_id, request_id, starts_at, ends_at, location_mode, status, meeting_link, operational_notes, created_by, updated_by)
            VALUES (:provider_id, :service_id, :patient_id, :request_id, :starts_at, :ends_at, :location_mode, :status, :meeting_link, :operational_notes, :created_by, :created_by)');
        $ins->execute([
            ':provider_id' => $data['provider_id'] ?? 1,
            ':service_id' => $data['service_id'] ?? null,
            ':patient_id' => $data['patient_id'] ?? null,
            ':request_id' => $data['request_id'] ?? null,
            ':starts_at' => $data['starts_at'],
            ':ends_at' => $data['ends_at'],
            ':location_mode' => $data['location_mode'] ?? null,
            ':status' => $data['status'] ?? 'pending_confirmation',
            ':meeting_link' => $data['meeting_link'] ?? null,
            ':operational_notes' => $data['operational_notes'] ?? null,
            ':created_by' => $data['created_by'] ?? null,
        ]);
        $newId = (int)$pdo->lastInsertId();

        $log = $pdo->prepare("INSERT INTO appointment_change_log (appointment_id, action, new_values, changed_by) VALUES (?, 'created', ?, ?)");
        $log->execute([$newId, json_encode(['starts_at' => $data['starts_at'], 'ends_at' => $data['ends_at'], 'status' => $data['status'] ?? 'pending_confirmation']), $data['created_by'] ?? null]);

        $pdo->exec('COMMIT');
        return ['success' => true, 'appointment_id' => $newId];
    } catch (Throwable $e) {
        try { $pdo->exec('ROLLBACK'); } catch (Throwable $e2) { /* transaction may already be closed */ }
        return ['success' => false, 'error' => 'صار خطأ تقني وقت حجز الموعد. جرّب مرة تانية.'];
    }
}

/** Same double-booking guard as create_appointment_atomically(), but for moving an existing appointment (excludes itself from the overlap check). */
function reschedule_appointment_atomically(int $appointmentId, string $newStartsAt, string $newEndsAt, string $changedBy): array
{
    $pdo = db();
    try {
        $pdo->exec('BEGIN IMMEDIATE');

        $cur = $pdo->prepare('SELECT * FROM appointments WHERE id = ?');
        $cur->execute([$appointmentId]);
        $existing = $cur->fetch();
        if (!$existing) {
            $pdo->exec('ROLLBACK');
            return ['success' => false, 'error' => 'الموعد غير موجود.'];
        }

        $overlap = $pdo->prepare("SELECT COUNT(*) FROM appointments
            WHERE provider_id = :provider AND status != 'cancelled' AND id != :self_id
            AND starts_at < :ends_at AND ends_at > :starts_at");
        $overlap->execute([
            ':provider' => $existing['provider_id'], ':self_id' => $appointmentId,
            ':starts_at' => $newStartsAt, ':ends_at' => $newEndsAt,
        ]);
        if ((int)$overlap->fetchColumn() > 0) {
            $pdo->exec('ROLLBACK');
            return ['success' => false, 'error' => 'الفترة الجديدة متعارضة مع موعد آخر. اختر فترة تانية.'];
        }

        $upd = $pdo->prepare("UPDATE appointments SET starts_at = ?, ends_at = ?, updated_by = ?, updated_at = datetime('now') WHERE id = ?");
        $upd->execute([$newStartsAt, $newEndsAt, $changedBy, $appointmentId]);
        $log = $pdo->prepare("INSERT INTO appointment_change_log (appointment_id, action, old_values, new_values, changed_by) VALUES (?, 'rescheduled', ?, ?, ?)");
        $log->execute([$appointmentId, json_encode(['starts_at' => $existing['starts_at'], 'ends_at' => $existing['ends_at']]), json_encode(['starts_at' => $newStartsAt, 'ends_at' => $newEndsAt]), $changedBy]);

        $pdo->exec('COMMIT');
        return ['success' => true];
    } catch (Throwable $e) {
        try { $pdo->exec('ROLLBACK'); } catch (Throwable $e2) { /* transaction may already be closed */ }
        return ['success' => false, 'error' => 'صار خطأ تقني وقت إعادة الجدولة. جرّب مرة تانية.'];
    }
}

/** Cancels an appointment, freeing its slot, with a logged reason. */
function cancel_appointment(int $appointmentId, string $reason, string $changedBy): void
{
    $pdo = db();
    $upd = $pdo->prepare("UPDATE appointments SET status='cancelled', cancel_reason=?, updated_by=?, updated_at=datetime('now') WHERE id=?");
    $upd->execute([$reason, $changedBy, $appointmentId]);
    $log = $pdo->prepare("INSERT INTO appointment_change_log (appointment_id, action, new_values, changed_by) VALUES (?, 'cancelled', ?, ?)");
    $log->execute([$appointmentId, json_encode(['reason' => $reason]), $changedBy]);
}

// --- Patients -------------------------------------------------------------

/**
 * The only way a patient record is created — always an explicit staff
 * action (from a confirmed request, or a manual "new patient" form), never
 * automatic. file_number is derived from the row's own id after insert, so
 * there's no separate counter that could race or drift.
 */
function create_patient(array $data, string $createdBy): int
{
    $pdo = db();
    $ins = $pdo->prepare("INSERT INTO patients (file_number, full_name, contact_method, contact_value, contact_preference, created_by)
        VALUES ('', :full_name, :contact_method, :contact_value, :contact_preference, :created_by)");
    $ins->execute([
        ':full_name' => $data['full_name'],
        ':contact_method' => $data['contact_method'],
        ':contact_value' => $data['contact_value'],
        ':contact_preference' => $data['contact_preference'] ?? null,
        ':created_by' => $createdBy,
    ]);
    $newId = (int)$pdo->lastInsertId();
    $fileNumber = 'PT-' . str_pad((string)$newId, 6, '0', STR_PAD_LEFT);
    $pdo->prepare('UPDATE patients SET file_number = ? WHERE id = ?')->execute([$fileNumber, $newId]);
    return $newId;
}

/** Converts a booking request into a patient record without copying anything beyond name/contact. */
function create_patient_from_request(int $requestId, string $createdBy): ?int
{
    $stmt = db()->prepare('SELECT * FROM appointment_requests WHERE id = ?');
    $stmt->execute([$requestId]);
    $req = $stmt->fetch();
    if (!$req) {
        return null;
    }
    return create_patient([
        'full_name' => $req['name'],
        'contact_method' => $req['contact_method'],
        'contact_value' => $req['contact_value'],
    ], $createdBy);
}

// --- Clinical notes module (built, but gated) -----------------------------

/** True only once the required prerequisite fields are filled in — see admin/clinical-settings.php. */
function clinical_module_ready(): bool
{
    return get_setting('clinical_notes_hosting_note') !== ''
        && get_setting('clinical_notes_retention_days') !== ''
        && get_setting('clinical_notes_backup_plan') !== ''
        && get_setting('clinical_notes_consent_note') !== '';
}

function clinical_module_enabled(): bool
{
    return setting_bool('clinical_notes_enabled') && clinical_module_ready();
}

function create_clinical_note_revision(int $noteId, string $content, ?int $editorId, string $editorName): void
{
    $ins = db()->prepare('INSERT INTO clinical_note_revisions (note_id, content, editor_id, editor_name) VALUES (?,?,?,?)');
    $ins->execute([$noteId, $content, $editorId, $editorName]);
}

// --- Billing ---------------------------------------------------------------

function generate_receipt_number(int $invoiceId): string
{
    return 'INV-' . date('Y') . '-' . str_pad((string)$invoiceId, 5, '0', STR_PAD_LEFT);
}

function invoice_paid_total(int $invoiceId): float
{
    $stmt = db()->prepare('SELECT COALESCE(SUM(amount), 0) FROM invoice_payments WHERE invoice_id = ?');
    $stmt->execute([$invoiceId]);
    return (float)$stmt->fetchColumn();
}

/** Recomputes and stores an invoice's status from its recorded payments vs its amount. */
function refresh_invoice_status(int $invoiceId): void
{
    $stmt = db()->prepare('SELECT amount, status FROM invoices WHERE id = ?');
    $stmt->execute([$invoiceId]);
    $inv = $stmt->fetch();
    if (!$inv || $inv['status'] === 'not_required') {
        return;
    }
    $paid = invoice_paid_total($invoiceId);
    $amount = (float)$inv['amount'];
    if ($paid <= 0) {
        $status = 'due';
    } elseif ($paid < $amount) {
        $status = 'partially_paid';
    } else {
        $status = 'paid';
    }
    db()->prepare("UPDATE invoices SET status = ?, updated_at = datetime('now') WHERE id = ?")->execute([$status, $invoiceId]);
}

// --- Access auditing --------------------------------------------------------

/** Logs that a sensitive record was accessed/changed — action + actor + timestamp ONLY, never field content. */
function log_access(string $action, string $entityType, ?int $entityId, array $actor): void
{
    $ins = db()->prepare('INSERT INTO access_audit_log (actor_id, actor_name, action, entity_type, entity_id) VALUES (?,?,?,?,?)');
    $ins->execute([$actor['id'] ?? null, $actor['name'] ?? null, $action, $entityType, $entityId]);
}
