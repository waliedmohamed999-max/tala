<?php
require_once __DIR__ . '/config.php';

function db(): PDO
{
    static $pdo = null;
    if ($pdo !== null) {
        return $pdo;
    }

    if (!is_dir(DATA_DIR)) {
        mkdir(DATA_DIR, 0775, true);
    }

    $isNew = !file_exists(DB_PATH);
    $pdo = new PDO('sqlite:' . DB_PATH);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    $pdo->exec('PRAGMA foreign_keys = ON');
    // Immediate-lock transactions (used by create_appointment_atomically()) wait
    // up to 5s for a concurrent writer instead of failing instantly with SQLITE_BUSY.
    $pdo->exec('PRAGMA busy_timeout = 5000');

    migrate($pdo);
    if ($isNew) {
        seed($pdo);
    }

    return $pdo;
}

function migrate(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS settings (
        `key` TEXT PRIMARY KEY,
        value TEXT
    )");

    // role: owner | editor | reviewer | bookings_manager
    //   owner            -> everything (settings, users, content, bookings)
    //   editor           -> create/edit content, can only move it to in_review
    //   reviewer         -> approve + publish content (professional review), can also edit
    //   bookings_manager -> appointments + contact messages only
    $pdo->exec("CREATE TABLE IF NOT EXISTS admin_users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        email TEXT NOT NULL UNIQUE,
        password_hash TEXT NOT NULL,
        role TEXT NOT NULL DEFAULT 'editor',
        is_active INTEGER NOT NULL DEFAULT 1,
        created_at TEXT NOT NULL DEFAULT (datetime('now')),
        last_login_at TEXT
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS categories (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        slug TEXT NOT NULL UNIQUE,
        name TEXT NOT NULL,
        description TEXT NOT NULL DEFAULT '',
        sort_order INTEGER NOT NULL DEFAULT 0
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS services (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        slug TEXT NOT NULL UNIQUE,
        name TEXT NOT NULL,
        audience TEXT,
        format TEXT,
        duration TEXT,
        price TEXT,
        summary TEXT,
        description TEXT,
        is_published INTEGER NOT NULL DEFAULT 0,
        review_note TEXT,
        reviewed_by TEXT,
        reviewed_at TEXT,
        sort_order INTEGER NOT NULL DEFAULT 0,
        created_at TEXT NOT NULL DEFAULT (datetime('now')),
        updated_at TEXT NOT NULL DEFAULT (datetime('now'))
    )");

    // workflow_status: draft -> in_review -> (changes_requested -> draft) | approved -> published
    //   needs_update can be set on any published/approved article that's grown stale.
    //   archived removes it from the public site without deleting it.
    // content_type: article | guide | faq_extended  (all share the same table/rendering;
    //   content_type just changes labeling/filtering and, for guides, that steps render as a numbered list)
    // locale/translation_of: an English row is a separate article with locale='en' and
    //   translation_of pointing at the Arabic original — it goes through its own workflow
    //   independently, so an approved Arabic article does not imply an approved English one.
    $pdo->exec("CREATE TABLE IF NOT EXISTS articles (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        slug TEXT NOT NULL UNIQUE,
        title TEXT NOT NULL,
        excerpt TEXT,
        content TEXT NOT NULL,
        content_raw TEXT NOT NULL DEFAULT '',
        category_id INTEGER REFERENCES categories(id),
        content_type TEXT NOT NULL DEFAULT 'article',
        locale TEXT NOT NULL DEFAULT 'ar',
        translation_of INTEGER REFERENCES articles(id),
        author_name TEXT NOT NULL DEFAULT 'فريق تالا',
        reviewer_name TEXT,
        last_reviewed_at TEXT,
        needs_update_note TEXT,
        review_note TEXT,
        sources TEXT,
        read_minutes INTEGER NOT NULL DEFAULT 4,
        workflow_status TEXT NOT NULL DEFAULT 'draft',
        published_at TEXT,
        updated_at TEXT NOT NULL DEFAULT (datetime('now')),
        updated_by TEXT,
        version INTEGER NOT NULL DEFAULT 1,
        created_at TEXT NOT NULL DEFAULT (datetime('now')),

        -- The LIVE snapshot the public site actually renders. Separate from
        -- the working copy above (title/content/etc) so editing a published
        -- article never changes what a visitor sees until a reviewer
        -- explicitly re-publishes — see publish_article() in functions.php.
        -- Visibility rule for public pages: published_content IS NOT NULL.
        published_content TEXT,
        published_content_raw TEXT,
        published_title TEXT,
        published_excerpt TEXT,
        published_read_minutes INTEGER,
        published_reviewer_name TEXT,
        published_reviewed_at TEXT,
        published_sources TEXT,
        published_category_id INTEGER,
        published_content_type TEXT
    )");

    // Full snapshots kept forever for history/diff/restore. One row is
    // inserted every time an editor saves real changes (not autosave).
    $pdo->exec("CREATE TABLE IF NOT EXISTS article_revisions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        article_id INTEGER NOT NULL REFERENCES articles(id) ON DELETE CASCADE,
        title TEXT NOT NULL,
        excerpt TEXT,
        content_raw TEXT NOT NULL,
        category_id INTEGER,
        content_type TEXT,
        sources TEXT,
        workflow_status TEXT,
        editor_name TEXT,
        editor_id INTEGER,
        restored_from_revision_id INTEGER,
        created_at TEXT NOT NULL DEFAULT (datetime('now'))
    )");

    // One row per article: the latest autosaved draft. Overwritten on every
    // autosave tick — NOT part of the permanent history (article_revisions is).
    // base_version is the articles.version the editor started from, used to
    // detect a second person's changes landing while this session was open.
    $pdo->exec("CREATE TABLE IF NOT EXISTS article_autosaves (
        article_id INTEGER PRIMARY KEY REFERENCES articles(id) ON DELETE CASCADE,
        title TEXT,
        excerpt TEXT,
        content_raw TEXT,
        category_id INTEGER,
        content_type TEXT,
        sources TEXT,
        base_version INTEGER NOT NULL,
        saved_by_id INTEGER,
        saved_by_name TEXT,
        saved_at TEXT NOT NULL DEFAULT (datetime('now'))
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS faqs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        question TEXT NOT NULL,
        answer TEXT NOT NULL,
        sort_order INTEGER NOT NULL DEFAULT 0,
        is_published INTEGER NOT NULL DEFAULT 1
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS appointment_requests (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        reference_code TEXT,
        name TEXT NOT NULL,
        contact_method TEXT NOT NULL,
        contact_value TEXT NOT NULL,
        service_id INTEGER REFERENCES services(id),
        location_pref TEXT,
        time_pref TEXT,
        notes TEXT,
        privacy_consent INTEGER NOT NULL DEFAULT 0,
        status TEXT NOT NULL DEFAULT 'new',
        ip_hash TEXT,
        created_at TEXT NOT NULL DEFAULT (datetime('now')),
        updated_at TEXT NOT NULL DEFAULT (datetime('now'))
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS appointment_status_log (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        request_id INTEGER NOT NULL REFERENCES appointment_requests(id) ON DELETE CASCADE,
        old_status TEXT,
        new_status TEXT NOT NULL,
        changed_by TEXT,
        changed_at TEXT NOT NULL DEFAULT (datetime('now'))
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS contact_messages (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        contact_method TEXT NOT NULL,
        contact_value TEXT NOT NULL,
        subject TEXT,
        message TEXT NOT NULL,
        status TEXT NOT NULL DEFAULT 'new',
        ip_hash TEXT,
        created_at TEXT NOT NULL DEFAULT (datetime('now'))
    )");

    // Admin-editable canned reply templates for booking statuses. Never sent
    // automatically — a human copies/sends them, or a future integration
    // could use them once real SMTP is configured.
    $pdo->exec("CREATE TABLE IF NOT EXISTS reply_templates (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        `key` TEXT NOT NULL UNIQUE,   -- received | confirmed | reschedule | unavailable
        label TEXT NOT NULL,
        body TEXT NOT NULL,
        updated_at TEXT NOT NULL DEFAULT (datetime('now'))
    )");

    // "مصادر مفيدة" — vetted external links, reviewed periodically.
    $pdo->exec("CREATE TABLE IF NOT EXISTS external_resources (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        title TEXT NOT NULL,
        url TEXT NOT NULL,
        description TEXT,
        is_published INTEGER NOT NULL DEFAULT 0,
        last_checked_at TEXT,
        sort_order INTEGER NOT NULL DEFAULT 0,
        created_at TEXT NOT NULL DEFAULT (datetime('now'))
    )");

    // Downloadable files / video / audio resources, published only after Tala approves.
    $pdo->exec("CREATE TABLE IF NOT EXISTS media_resources (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        title TEXT NOT NULL,
        description TEXT,
        media_type TEXT NOT NULL DEFAULT 'file', -- file | video_url | audio_url
        file_path TEXT,
        external_url TEXT,
        is_published INTEGER NOT NULL DEFAULT 0,
        sort_order INTEGER NOT NULL DEFAULT 0,
        created_at TEXT NOT NULL DEFAULT (datetime('now'))
    )");

    // Events/speaking section — built but kept fully unpublished (see
    // settings.events_section_enabled) until Tala has a real confirmed event.
    $pdo->exec("CREATE TABLE IF NOT EXISTS events (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        slug TEXT NOT NULL UNIQUE,
        title TEXT NOT NULL,
        topic_summary TEXT,
        audience TEXT,
        event_date TEXT,
        location_or_stream TEXT,
        description TEXT,
        is_published INTEGER NOT NULL DEFAULT 0,
        created_at TEXT NOT NULL DEFAULT (datetime('now')),
        updated_at TEXT NOT NULL DEFAULT (datetime('now'))
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS speaking_inquiries (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        organization TEXT,
        contact_value TEXT NOT NULL,
        event_details TEXT NOT NULL,
        status TEXT NOT NULL DEFAULT 'new',
        created_at TEXT NOT NULL DEFAULT (datetime('now'))
    )");

    // Newsletter is entirely separate from the booking form — a visitor who
    // requests an appointment is NEVER auto-subscribed to this.
    // status: pending (double opt-in email sent, not yet confirmed) |
    //         subscribed | unsubscribed
    $pdo->exec("CREATE TABLE IF NOT EXISTS newsletter_subscribers (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        email TEXT NOT NULL UNIQUE,
        status TEXT NOT NULL DEFAULT 'pending',
        consent_source TEXT,       -- page path the opt-in checkbox was submitted from
        confirmed_at TEXT,
        unsubscribe_token TEXT NOT NULL,
        confirm_token TEXT,
        created_at TEXT NOT NULL DEFAULT (datetime('now'))
    )");

    // Lightweight, privacy-respecting page-view / event counter — no per-visitor
    // identifiers, no free-text ever recorded here (see includes/analytics.php).
    $pdo->exec("CREATE TABLE IF NOT EXISTS analytics_events (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        event_type TEXT NOT NULL,  -- page_view | booking_form_started | booking_form_completed | contact_form_completed
        path TEXT,
        day TEXT NOT NULL,         -- Y-m-d, for cheap daily aggregation
        created_at TEXT NOT NULL DEFAULT (datetime('now'))
    )");

    // Every attempted send (visitor templates, admin alerts, test messages)
    // is logged here — success or failure — so nothing is ever silently
    // resent, and the admin UI can show real delivery state, not a guess.
    $pdo->exec("CREATE TABLE IF NOT EXISTS notification_log (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        type TEXT NOT NULL,  -- admin_new_booking | received | confirmed | reschedule | unavailable | test
        appointment_request_id INTEGER REFERENCES appointment_requests(id) ON DELETE SET NULL,
        recipient TEXT NOT NULL,
        subject TEXT,
        status TEXT NOT NULL,  -- sent | failed
        error_message TEXT,
        sent_by TEXT,
        created_at TEXT NOT NULL DEFAULT (datetime('now'))
    )");

    // Media items attached to an event — only ever shown if the parent event
    // itself is published AND the events section is enabled (see events/*.php).
    $pdo->exec("CREATE TABLE IF NOT EXISTS event_media (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        event_id INTEGER NOT NULL REFERENCES events(id) ON DELETE CASCADE,
        media_type TEXT NOT NULL DEFAULT 'image', -- image | video_file | video_url
        file_path TEXT,
        external_url TEXT,
        alt_text TEXT,
        caption TEXT,
        sort_order INTEGER NOT NULL DEFAULT 0,
        created_at TEXT NOT NULL DEFAULT (datetime('now'))
    )");

    $pdo->exec("CREATE TABLE IF NOT EXISTS rate_limits (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        bucket TEXT NOT NULL,
        created_at TEXT NOT NULL DEFAULT (datetime('now'))
    )");

    migrate_clinic_ops($pdo);

    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_articles_workflow ON articles(workflow_status, published_at)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_articles_category ON articles(category_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_articles_locale ON articles(locale, translation_of)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_revisions_article ON article_revisions(article_id, created_at)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_notification_log_type ON notification_log(type, created_at)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_event_media_event ON event_media(event_id, sort_order)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_rate_limits_bucket ON rate_limits(bucket, created_at)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_analytics_day ON analytics_events(day, event_type)");
}

/** Adds a column to an existing table only if it isn't already there — safe to call on every request. */
function ensure_column(PDO $pdo, string $table, string $column, string $definition): void
{
    static $cache = [];
    if (!isset($cache[$table])) {
        $cache[$table] = [];
        foreach ($pdo->query("PRAGMA table_info(\"{$table}\")") as $col) {
            $cache[$table][$col['name']] = true;
        }
    }
    if (isset($cache[$table][$column])) {
        return;
    }
    $pdo->exec("ALTER TABLE {$table} ADD COLUMN {$column} {$definition}");
    $cache[$table][$column] = true;
}

/**
 * Clinic operations schema: calendar/scheduling, patients, billing, clinical
 * notes (built but disabled by default — see settings.clinical_notes_enabled),
 * ops tasks, and access auditing. Kept in its own function since it's a large
 * addition layered on top of the original content/booking schema above —
 * everything here is additive (CREATE TABLE IF NOT EXISTS / ensure_column),
 * never destructive.
 */
function migrate_clinic_ops(PDO $pdo): void
{
    // --- Single provider for now (Tala). Every clinic-ops table below carries
    // a provider_id so a second provider can be added later WITHOUT a schema
    // change, but the UI never shows a provider picker while only one exists —
    // we must never display a provider that doesn't really exist.
    $pdo->exec("CREATE TABLE IF NOT EXISTS providers (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name TEXT NOT NULL,
        is_active INTEGER NOT NULL DEFAULT 1
    )");
    if ((int)$pdo->query('SELECT COUNT(*) FROM providers')->fetchColumn() === 0) {
        $pdo->exec("INSERT INTO providers (id, name, is_active) VALUES (1, 'تالا', 1)");
    }

    // --- Working hours + closures. A weekday row with service_id set overrides
    // the general (service_id IS NULL) rows for that weekday for that service —
    // no deeper merge logic than that, see compute_available_slots().
    $pdo->exec("CREATE TABLE IF NOT EXISTS clinic_hours (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        provider_id INTEGER NOT NULL DEFAULT 1 REFERENCES providers(id),
        weekday INTEGER NOT NULL,              -- 0=Sunday .. 6=Saturday
        start_time TEXT NOT NULL,               -- 'HH:MM', local clinic time
        end_time TEXT NOT NULL,
        service_id INTEGER REFERENCES services(id) ON DELETE CASCADE,
        location_mode TEXT,                     -- in_person | remote | NULL(both)
        is_active INTEGER NOT NULL DEFAULT 1
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS clinic_closures (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        provider_id INTEGER NOT NULL DEFAULT 1 REFERENCES providers(id),
        date_from TEXT NOT NULL,                -- 'YYYY-MM-DD'
        date_to TEXT NOT NULL,
        reason TEXT,
        created_by TEXT,
        created_at TEXT NOT NULL DEFAULT (datetime('now'))
    )");

    // --- Services: extend the existing simple is_published boolean with a
    // fuller workflow + the structured fields a real calendar needs (duration,
    // buffers, price) — see set_service_workflow_status() in functions.php,
    // which keeps is_published in sync so published_services() (used all over
    // the public site) never needs to change.
    ensure_column($pdo, 'services', 'workflow_status', "TEXT NOT NULL DEFAULT 'draft'"); // draft|pending_review|published|paused
    ensure_column($pdo, 'services', 'category_id', 'INTEGER REFERENCES categories(id)');
    ensure_column($pdo, 'services', 'location_mode', 'TEXT'); // in_person|remote|both
    ensure_column($pdo, 'services', 'duration_minutes', 'INTEGER');
    ensure_column($pdo, 'services', 'buffer_before_minutes', 'INTEGER NOT NULL DEFAULT 0');
    ensure_column($pdo, 'services', 'buffer_after_minutes', 'INTEGER NOT NULL DEFAULT 0');
    ensure_column($pdo, 'services', 'price_amount', 'REAL');
    ensure_column($pdo, 'services', 'price_currency', 'TEXT');
    ensure_column($pdo, 'services', 'cancellation_policy', 'TEXT');
    ensure_column($pdo, 'services', 'show_on_website', 'INTEGER NOT NULL DEFAULT 0');
    ensure_column($pdo, 'services', 'bookable_online', 'INTEGER NOT NULL DEFAULT 0');
    ensure_column($pdo, 'services', 'name_en', 'TEXT');
    ensure_column($pdo, 'services', 'summary_en', 'TEXT');
    ensure_column($pdo, 'services', 'description_en', 'TEXT');
    // Backfill: services already published under the old boolean-only model
    // keep their visibility (workflow_status='published', shown on the
    // website) but stay OUT of online booking until Tala sets a real
    // duration and confirms hours for them — bookable_online defaults 0.
    $pdo->exec("UPDATE services SET workflow_status = 'published', show_on_website = 1 WHERE is_published = 1 AND workflow_status = 'draft'");

    // --- Session hardening: force_logout_at lets an owner invalidate a
    // user's active sessions from admin/user-edit.php without full 2FA.
    ensure_column($pdo, 'admin_users', 'force_logout_at', 'TEXT');
    ensure_column($pdo, 'admin_users', 'sidebar_collapsed', 'INTEGER NOT NULL DEFAULT 0');
    ensure_column($pdo, 'admin_users', 'role', "TEXT NOT NULL DEFAULT 'editor'"); // no-op if already present; documents the 'financial' addition

    // --- Appointment requests: extend the status machine + link fields.
    ensure_column($pdo, 'appointment_requests', 'assigned_to', 'INTEGER REFERENCES admin_users(id)');
    ensure_column($pdo, 'appointment_requests', 'converted_appointment_id', 'INTEGER');

    // --- Patients: a deliberately minimal ADMINISTRATIVE record, created
    // only by an explicit staff action (never automatically from a booking
    // request) — see functions.php's create_patient_from_request(). Fully
    // separate from clinical_notes below, which needs a higher permission.
    $pdo->exec("CREATE TABLE IF NOT EXISTS patients (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        file_number TEXT NOT NULL UNIQUE,
        full_name TEXT NOT NULL,
        contact_method TEXT NOT NULL,
        contact_value TEXT NOT NULL,
        contact_preference TEXT,
        status TEXT NOT NULL DEFAULT 'active', -- active|archived
        created_by TEXT,
        created_at TEXT NOT NULL DEFAULT (datetime('now')),
        archived_at TEXT,
        archived_reason TEXT
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS patient_consents (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        patient_id INTEGER NOT NULL REFERENCES patients(id) ON DELETE CASCADE,
        consent_type TEXT NOT NULL,
        file_path TEXT,
        signed_at TEXT,
        uploaded_by TEXT,
        created_at TEXT NOT NULL DEFAULT (datetime('now'))
    )");

    // --- The real calendar. starts_at/ends_at are stored as UTC ISO
    // ('YYYY-MM-DD HH:MM:SS'); every admin/public display converts to
    // SITE_TIMEZONE. status!='cancelled' rows are what block a slot.
    $pdo->exec("CREATE TABLE IF NOT EXISTS appointments (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        provider_id INTEGER NOT NULL DEFAULT 1 REFERENCES providers(id),
        service_id INTEGER REFERENCES services(id),
        patient_id INTEGER REFERENCES patients(id),
        request_id INTEGER REFERENCES appointment_requests(id),
        starts_at TEXT NOT NULL,
        ends_at TEXT NOT NULL,
        location_mode TEXT,
        status TEXT NOT NULL DEFAULT 'pending_confirmation', -- pending_confirmation|confirmed|rescheduled|cancelled|no_show|completed
        actual_duration_minutes INTEGER,
        meeting_link TEXT,
        operational_notes TEXT,   -- administrative only — never clinical content, see admin UI hint
        cancel_reason TEXT,
        created_by TEXT,
        updated_by TEXT,
        created_at TEXT NOT NULL DEFAULT (datetime('now')),
        updated_at TEXT NOT NULL DEFAULT (datetime('now'))
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS appointment_change_log (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        appointment_id INTEGER NOT NULL REFERENCES appointments(id) ON DELETE CASCADE,
        action TEXT NOT NULL,     -- created|rescheduled|cancelled|status_changed|note_updated
        old_values TEXT,
        new_values TEXT,
        changed_by TEXT,
        changed_at TEXT NOT NULL DEFAULT (datetime('now'))
    )");

    // --- Clinical notes module: built in full (schema + revisions +
    // attachments) but every read/write path must check
    // setting_bool('clinical_notes_enabled') FIRST — see require_clinical_module_enabled()
    // in functions.php. Stays '0' (off) until Tala confirms hosting,
    // retention, backup, and consent — see admin/clinical-settings.php.
    // Files referenced by clinical_note_attachments.file_path live under
    // DATA_DIR (never public_uploads/) and are only ever streamed through
    // admin/clinical-file.php after a permission + audit-log check.
    $pdo->exec("CREATE TABLE IF NOT EXISTS clinical_notes (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        patient_id INTEGER NOT NULL REFERENCES patients(id) ON DELETE CASCADE,
        appointment_id INTEGER REFERENCES appointments(id),
        author_id INTEGER REFERENCES admin_users(id),
        author_name TEXT,
        content TEXT NOT NULL DEFAULT '',
        created_at TEXT NOT NULL DEFAULT (datetime('now')),
        updated_at TEXT NOT NULL DEFAULT (datetime('now')),
        locked_at TEXT,
        locked_by TEXT
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS clinical_note_revisions (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        note_id INTEGER NOT NULL REFERENCES clinical_notes(id) ON DELETE CASCADE,
        content TEXT NOT NULL,
        editor_id INTEGER REFERENCES admin_users(id),
        editor_name TEXT,
        created_at TEXT NOT NULL DEFAULT (datetime('now'))
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS clinical_note_attachments (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        note_id INTEGER NOT NULL REFERENCES clinical_notes(id) ON DELETE CASCADE,
        file_path TEXT NOT NULL,
        original_name TEXT,
        mime_type TEXT NOT NULL,
        size_bytes INTEGER NOT NULL,
        uploaded_by TEXT,
        created_at TEXT NOT NULL DEFAULT (datetime('now'))
    )");

    // --- Billing: manual bookkeeping only (no payment gateway) — see
    // settings.billing_enabled. Receipts are printable HTML, not a PDF lib.
    $pdo->exec("CREATE TABLE IF NOT EXISTS invoices (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        patient_id INTEGER REFERENCES patients(id),
        appointment_id INTEGER REFERENCES appointments(id),
        service_id INTEGER REFERENCES services(id),
        amount REAL NOT NULL DEFAULT 0,
        currency TEXT,
        status TEXT NOT NULL DEFAULT 'not_required', -- not_required|due|partially_paid|paid|refunded
        receipt_number TEXT,
        notes TEXT,
        created_by TEXT,
        created_at TEXT NOT NULL DEFAULT (datetime('now')),
        updated_at TEXT NOT NULL DEFAULT (datetime('now'))
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS invoice_payments (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        invoice_id INTEGER NOT NULL REFERENCES invoices(id) ON DELETE CASCADE,
        amount REAL NOT NULL,
        method TEXT,
        reference TEXT,
        recorded_by TEXT,
        recorded_at TEXT NOT NULL DEFAULT (datetime('now'))
    )");

    // --- Daily-ops task list + access auditing (view/edit/export/archive/
    // delete on sensitive records — action + actor + timestamp only, NEVER
    // the field content itself).
    $pdo->exec("CREATE TABLE IF NOT EXISTS ops_tasks (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        title TEXT NOT NULL,
        description TEXT,
        related_type TEXT,   -- appointment_request|appointment|patient|general
        related_id INTEGER,
        assigned_to INTEGER REFERENCES admin_users(id),
        status TEXT NOT NULL DEFAULT 'open', -- open|done
        due_at TEXT,
        created_by TEXT,
        created_at TEXT NOT NULL DEFAULT (datetime('now')),
        done_at TEXT
    )");
    $pdo->exec("CREATE TABLE IF NOT EXISTS access_audit_log (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        actor_id INTEGER,
        actor_name TEXT,
        action TEXT NOT NULL,      -- view|edit|export|archive|delete
        entity_type TEXT NOT NULL, -- patient|clinical_note|invoice
        entity_id INTEGER,
        created_at TEXT NOT NULL DEFAULT (datetime('now'))
    )");

    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_appointments_provider_time ON appointments(provider_id, starts_at)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_appointments_patient ON appointments(patient_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_appointments_status ON appointments(status)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_clinic_hours_weekday ON clinic_hours(weekday, service_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_patients_status ON patients(status)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_clinical_notes_patient ON clinical_notes(patient_id)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_invoices_patient ON invoices(patient_id, status)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_ops_tasks_status ON ops_tasks(status, due_at)");
    $pdo->exec("CREATE INDEX IF NOT EXISTS idx_audit_entity ON access_audit_log(entity_type, entity_id)");

    // --- New settings, inserted only if missing (existing seed() only runs
    // on a brand-new database — this runs on every request so upgrades an
    // already-seeded site too).
    $newSettings = [
        'clinic_timezone_display' => 'Asia/Damascus',
        'billing_enabled' => '0',
        'min_notice_hours' => '24',
        'booking_horizon_days' => '30',
        'clinical_notes_enabled' => '0',
        'clinical_notes_hosting_note' => '',
        'clinical_notes_retention_days' => '',
        'clinical_notes_backup_plan' => '',
        'clinical_notes_consent_note' => '',
        'privacy_policy_reviewed' => '0',
    ];
    $check = $pdo->prepare('SELECT COUNT(*) FROM settings WHERE `key` = ?');
    $insert = $pdo->prepare('INSERT INTO settings (`key`, value) VALUES (?, ?)');
    foreach ($newSettings as $k => $v) {
        $check->execute([$k]);
        if ((int)$check->fetchColumn() === 0) {
            $insert->execute([$k, $v]);
        }
    }
}

function seed(PDO $pdo): void
{
    $settings = [
        'site_name' => 'تالا | معالجة نفسية',
        'display_title' => 'تالا | معالجة نفسية',
        'full_professional_name' => '',
        'professional_title' => 'معالجة نفسية',
        'credentials' => '',
        'license_info' => '',
        'years_experience' => '',
        'specialties' => '',
        'languages' => '',                    // اللغات التي تُقدَّم فيها الجلسات — بانتظار التأكيد
        'bio_short' => '',
        'bio_long' => '',
        'photo_path' => '/public_uploads/tala-photo.png',
        'intro_video_url' => '',              // فيديو تعريفي — يُضاف فقط إذا وافقت تالا
        'media_mentions' => '',               // ظهور إعلامي حقيقي، سطر لكل عنصر — بانتظار التأكيد
        'city' => 'حمص',
        'address' => '',
        'show_address' => '0',
        'map_embed_url' => '',
        'phone' => '',
        'whatsapp' => '',
        'email' => '',
        'working_hours' => '',
        'offers_remote_sessions' => '0',
        'cancellation_policy' => '',
        'privacy_last_updated' => date('Y-m-d'),
        'booking_notice' => 'وصلنا طلبك. رح نتواصل معك لنأكد التفاصيل. إرسال الطلب لحاله ما بيعني إن الموعد تأكّد.',
        'emergency_notice' => 'هالموقع مو مخصص للطوارئ أو الاستجابة الفورية. إذا في خطر مباشر عليك أو على شخص تاني، تواصل فورًا مع خدمات الطوارئ المحلية أو توجّه لأقرب قسم إسعاف.',
        'response_time_note' => '',           // مدة الرد المتوقعة — تُعرض فقط إذا حددتها تالا
        'facebook_url' => '',
        'instagram_url' => '',
        'analytics_snippet' => '',
        'events_section_enabled' => '0',      // القسم مبني لكن مخفي عن التنقل حتى تفعّله تالا
        'article_stale_after_days' => '180',  // بعد كم يوم يُعتبر المقال بحاجة مراجعة بلوحة الإدارة
        'analytics_enabled' => '1',           // عدادات مجهولة (زيارات صفحات، إكمال نماذج) — بدون أي نص أو بيانات شخصية

        // --- SMTP (all empty until the owner configures + tests it from
        // /admin/notifications-settings.php — see includes/mailer.php) ---
        'smtp_enabled' => '0',
        'smtp_host' => '',
        'smtp_port' => '587',
        'smtp_encryption' => 'tls',  // tls | ssl | none
        'smtp_username' => '',
        'smtp_password_enc' => '',   // encrypted at rest, see includes/mailer.php
        'smtp_from_email' => '',
        'smtp_from_name' => 'تالا | معالجة نفسية',
        'admin_alert_email' => '',   // where the "new booking" internal alert goes
    ];
    $stmt = $pdo->prepare('INSERT INTO settings (`key`, value) VALUES (:k, :v)');
    foreach ($settings as $k => $v) {
        $stmt->execute([':k' => $k, ':v' => $v]);
    }

    $categories = [
        ['قلق-وتوتر', 'القلق والتوتر', 'مقالات عن التعامل مع التوتر اليومي ونوبات القلق والأفكار المتسارعة.'],
        ['مشاعر-ومزاج', 'المشاعر والمزاج', 'فهم المشاعر، تسميتها، والتعامل مع تقلبات المزاج بشكل عام.'],
        ['نوم-وروتين', 'النوم والروتين', 'عادات النوم اليومية وعلاقتها بالحالة النفسية.'],
        ['علاقات-وتواصل', 'العلاقات والتواصل', 'تحسين التواصل مع الشريك، العيلة، والأصدقاء.'],
        ['حدود-شخصية', 'الحدود الشخصية', 'كيف تحدد مساحتك الشخصية باحترام دون شعور بالذنب.'],
        ['ضغوط-الحياة', 'ضغوط الحياة', 'التعامل مع ضغط الشغل، الدراسة، والتغييرات الكبيرة.'],
        ['البدء-بالعلاج-النفسي', 'البدء بالعلاج النفسي', 'كل شي يخص خطوة البدء: شو تتوقع، وكيف تختار المختص المناسب.'],
        ['العناية-بالنفس', 'العناية بالنفس', 'إعطاء نفسك المساحة والوقت اللازمين دون شعور بالذنب.'],
    ];
    $stmt = $pdo->prepare('INSERT INTO categories (slug, name, description, sort_order) VALUES (?, ?, ?, ?)');
    foreach ($categories as $i => $c) {
        $stmt->execute([$c[0], $c[1], $c[2], $i]);
    }

    $faqs = [
        ['كيف بقدر أطلب موعد؟', 'فيك تعبّي نموذج «اطلب موعد» بالموقع، وبنتواصل معك لنرتّب التفاصيل ونأكد الموعد المناسب.'],
        ['خلال قديش بترجعوا علي بعد ما أرسل الطلب؟', 'رح نوضح لك التفاصيل وقت التواصل، وبنحاول نرجع لك بأقرب وقت ممكن.'],
        ['وين بتصير الجلسات؟', 'رح نوضح لك التفاصيل وقت التواصل.'],
        ['في جلسات أونلاين؟', 'رح نوضح لك التفاصيل وقت التواصل — إذا كان هالخيار متوفر رح يظهر بوضوح ضمن صفحة الخدمات.'],
        ['شو الأسعار؟', 'رح نوضح لك التفاصيل وقت التواصل.'],
        ['قديش مدة الجلسة؟', 'رح نوضح لك التفاصيل وقت التواصل.'],
        ['معلوماتي رح تضل سرية؟', 'بنتعامل مع معلوماتك بخصوصية عالية ونجمع أقل قدر ممكن منها. فيك تقرأ التفاصيل كاملة بصفحة سياسة الخصوصية.'],
        ['فيني غيّر موعدي إذا تأكد؟', 'أكيد، تواصل معنا وقت ما بدك وبنرتب التغيير حسب الإمكانية المتاحة.'],
        ['مين فيه يستفيد من الجلسات؟', 'رح نوضح لك التفاصيل وقت التواصل حسب الفئات والخدمات المتاحة فعليًا.'],
    ];
    $stmt = $pdo->prepare('INSERT INTO faqs (question, answer, sort_order, is_published) VALUES (?, ?, ?, 1)');
    foreach ($faqs as $i => $f) {
        $stmt->execute([$f[0], $f[1], $i]);
    }

    $templates = [
        ['received', 'استلام الطلب', "أهلًا [الاسم]،\n\nوصلنا طلب موعدك، شكرًا إلك. رح نراجعه ونرجعلك قريبًا لنرتب التفاصيل والوقت المناسب.\n\nتحياتي،\nتالا"],
        ['confirmed', 'تأكيد الموعد', "أهلًا [الاسم]،\n\nموعدك تأكد يوم [اليوم] الساعة [الوقت]. إذا في أي تغيير من جهتك، خبرني بأقرب وقت ممكن.\n\nبانتظارك،\nتالا"],
        ['reschedule', 'طلب تغيير الوقت', "أهلًا [الاسم]،\n\nللأسف الوقت يلي طلبته مو متاح حاليًا. فيك تقترح وقت أو فترة تانية تناسبك وبنشوف الإمكانية؟\n\nتحياتي،\nتالا"],
        ['unavailable', 'الاعتذار عن عدم التوافر', "أهلًا [الاسم]،\n\nشاكرة تواصلك، بس للأسف ما في إمكانية أستقبل مواعيد جديدة حاليًا. رح أخبرك إذا تغير الوضع.\n\nتحياتي،\nتالا"],
    ];
    $stmt = $pdo->prepare('INSERT INTO reply_templates (`key`, label, body) VALUES (?, ?, ?)');
    foreach ($templates as $t) {
        $stmt->execute($t);
    }

    seed_articles($pdo);
    seed_example_services($pdo);
    seed_external_resources($pdo);
}

/**
 * Ready-to-edit SERVICE TEMPLATES, inserted as UNPUBLISHED drafts. These
 * exist so Tala has a starting structure to fill in and publish from
 * /admin/services.php — they are never shown on the public site as-is.
 * Prices/durations are left as explicit placeholder text, never invented
 * numbers, since real fees must come from Tala.
 */
function seed_example_services(PDO $pdo): void
{
    $services = [
        [
            'name' => 'جلسة فردية',
            'audience' => 'مثال: بالغون بدهم مساحة فردية للحديث والدعم النفسي. عدّلي هالنص حسب الفئة الفعلية يلي بتستقبليها.',
            'format' => 'مثال: حضوري بحمص، وجهًا لوجه.',
            'duration' => 'تُحدَّد من تالا',
            'price' => 'تُحدَّد من تالا',
            'summary' => 'جلسة فردية للحديث بمساحة خاصة وآمنة. — نص مثالي، عدّليه أو احذفيه قبل النشر.',
            'description' => "هاد نص افتراضي لخدمة الجلسة الفردية. عدّليه بالتفاصيل الفعلية: لمين موجهة الجلسة، شو بتغطي، وكيف بتسير.\n\n- عدّل هالنقطة بمعلومة حقيقية.\n- عدّل هالنقطة بمعلومة حقيقية.",
        ],
        [
            'name' => 'جلسة أزواج',
            'audience' => 'مثال: أزواج بدهم يحسّنوا تواصلهم أو يتعاملوا مع خلاف مستمر. عدّلي حسب الواقع.',
            'format' => 'مثال: حضوري بحمص، الشريكين سوا.',
            'duration' => 'تُحدَّد من تالا',
            'price' => 'تُحدَّد من تالا',
            'summary' => 'جلسة مشتركة للأزواج. — نص مثالي، فعّليه فقط إذا كانت هاي الخدمة متاحة فعليًا.',
            'description' => "هاد نص افتراضي لخدمة جلسات الأزواج. عدّليه أو احذفي هالخدمة بالكامل إذا مو ضمن نطاق عملك حاليًا.",
        ],
        [
            'name' => 'جلسة عن بُعد',
            'audience' => 'مثال: مين بيفضّل جلسة أونلاين بدل الحضور شخصيًا.',
            'format' => 'مثال: مكالمة فيديو أو صوت، حسب الأداة المعتمدة.',
            'duration' => 'تُحدَّد من تالا',
            'price' => 'تُحدَّد من تالا',
            'summary' => 'جلسة عن بُعد لمين ما بقدر يحضر بحمص. — فعّلي هالخدمة فقط إذا قررتِ تقديم جلسات أونلاين.',
            'description' => "هاد نص افتراضي لخدمة الجلسات عن بُعد. لا تنشريه قبل ما تأكدي إنك فعليًا بتقدّمي هالخيار، وحدّدي الأداة المستخدمة (مثلًا مكالمة فيديو) والخطوات التقنية البسيطة يلي بتحتاجها الشخص قبل الجلسة.",
        ],
        [
            'name' => 'جلسة للمراهقين',
            'audience' => 'مثال: مراهقين بعمر معين — حدّدي الفئة العمرية الدقيقة يلي بتستقبليها.',
            'format' => 'مثال: حضوري بحمص.',
            'duration' => 'تُحدَّد من تالا',
            'price' => 'تُحدَّد من تالا',
            'summary' => 'جلسة موجهة للمراهقين. — فعّلي فقط إذا هاي الفئة ضمن نطاق عملك.',
            'description' => "هاد نص افتراضي. عدّليه بمعلومات دقيقة عن طريقة العمل مع هالفئة العمرية تحديدًا، ودور الأهل إن وجد.",
        ],
    ];

    $stmt = $pdo->prepare('INSERT INTO services
        (slug, name, audience, format, duration, price, summary, description, is_published, sort_order)
        VALUES (:slug, :name, :audience, :format, :duration, :price, :summary, :description, 0, :sort_order)');

    foreach ($services as $i => $s) {
        $stmt->execute([
            ':slug' => unique_slug_seed($pdo, 'services', slugify($s['name'])),
            ':name' => $s['name'],
            ':audience' => $s['audience'],
            ':format' => $s['format'],
            ':duration' => $s['duration'],
            ':price' => $s['price'],
            ':summary' => $s['summary'],
            ':description' => simple_text_to_html($s['description']),
            ':sort_order' => $i,
        ]);
    }
}

/** A handful of well-known, generally reputable mental-health resources as an editable starting point — kept unpublished. */
function seed_external_resources(PDO $pdo): void
{
    $resources = [
        ['منظمة الصحة العالمية — الصحة النفسية', 'https://www.who.int/health-topics/mental-health', 'معلومات عامة موثوقة عن الصحة النفسية من منظمة الصحة العالمية.'],
        ['منظمة الصحة العالمية — التوتر', 'https://www.who.int/news-room/questions-and-answers/item/stress', 'أسئلة وأجوبة عامة حول التوتر وطرق التعامل معه.'],
    ];
    $stmt = $pdo->prepare('INSERT INTO external_resources (title, url, description, is_published, sort_order) VALUES (?, ?, ?, 0, ?)');
    foreach ($resources as $i => $r) {
        $stmt->execute([$r[0], $r[1], $r[2], $i]);
    }
}

/** Slug helper usable during seeding, before functions.php's unique_slug() sees any rows yet. */
function unique_slug_seed(PDO $pdo, string $table, string $baseSlug): string
{
    $slug = $baseSlug !== '' ? $baseSlug : 'item';
    $i = 1;
    while (true) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM {$table} WHERE slug = ?");
        $stmt->execute([$slug]);
        if ((int)$stmt->fetchColumn() === 0) {
            return $slug;
        }
        $i++;
        $slug = $baseSlug . '-' . $i;
    }
}

/**
 * Loads the 20 starter articles as DRAFTS (never auto-published — see
 * database/articles_seed.php). Tala (or whoever she authorizes) reviews and
 * publishes each one from /admin/articles.php.
 */
function seed_articles(PDO $pdo): void
{
    $categoryIds = [];
    foreach ($pdo->query('SELECT id, slug FROM categories') as $row) {
        $categoryIds[$row['slug']] = $row['id'];
    }

    $articles = require __DIR__ . '/../database/articles_seed.php';
    $stmt = $pdo->prepare('INSERT INTO articles
        (slug, title, excerpt, content, content_raw, category_id, content_type, author_name, reviewer_name, sources, read_minutes, workflow_status)
        VALUES (:slug, :title, :excerpt, :content, :content_raw, :category_id, :content_type, :author_name, :reviewer_name, :sources, :read_minutes, :workflow_status)');

    foreach ($articles as $a) {
        $contentHtml = simple_text_to_html($a['content']);
        $stmt->execute([
            ':slug' => $a['slug'],
            ':title' => $a['title'],
            ':excerpt' => $a['excerpt'],
            ':content' => $contentHtml,
            ':content_raw' => $a['content'],
            ':category_id' => $categoryIds[$a['category']] ?? null,
            ':content_type' => 'article',
            ':author_name' => 'فريق تالا',
            ':reviewer_name' => '',
            ':sources' => $a['sources'],
            ':read_minutes' => estimate_read_minutes($contentHtml),
            ':workflow_status' => 'draft',
        ]);
    }
}
