<?php
/**
 * Single source of truth for the admin navigation shell. Every admin page
 * keeps setting $activeNav exactly as it always has — this file just maps
 * each $activeNav to the top-level group it belongs to, the sibling tabs
 * shown alongside it, and which roles may see it. Only
 * admin/includes/layout_top.php reads this. No page URL changes, so no
 * existing bookmark or link breaks.
 *
 * Adding a new admin page later = one new entry in the right group's
 * 'tabs' array below. Nothing else in the shell needs to change.
 */

$NAV_GROUPS = [
    'overview' => [
        'label' => 'نظرة عامة', 'icon' => 'overview',
        'roles' => ['owner', 'editor', 'reviewer', 'bookings_manager', 'financial'],
        'tabs' => [
            'dashboard' => ['label' => 'نظرة عامة', 'href' => '/admin/dashboard.php', 'roles' => ['owner', 'editor', 'reviewer', 'bookings_manager', 'financial']],
        ],
    ],
    'appointments' => [
        'label' => 'المواعيد', 'icon' => 'calendar',
        'roles' => ['owner', 'bookings_manager'],
        'tabs' => [
            'calendar' => ['label' => 'التقويم', 'href' => '/admin/calendar.php', 'roles' => ['owner', 'bookings_manager']],
            'appointments' => ['label' => 'الطلبات', 'href' => '/admin/appointments.php', 'roles' => ['owner', 'bookings_manager']],
        ],
    ],
    'patients' => [
        'label' => 'المرضى', 'icon' => 'patients',
        'roles' => ['owner', 'bookings_manager', 'reviewer'],
        'tabs' => [
            'patients' => ['label' => 'المرضى', 'href' => '/admin/patients.php', 'roles' => ['owner', 'bookings_manager', 'reviewer']],
        ],
    ],
    'services' => [
        'label' => 'الخدمات', 'icon' => 'services',
        'roles' => ['owner', 'editor', 'reviewer'],
        'tabs' => [
            'services' => ['label' => 'الخدمات', 'href' => '/admin/services.php', 'roles' => ['owner', 'editor', 'reviewer']],
        ],
    ],
    'content' => [
        'label' => 'المحتوى', 'icon' => 'content',
        'roles' => ['owner', 'editor', 'reviewer'],
        'tabs' => [
            'review-queue' => ['label' => 'قائمة الاعتماد', 'href' => '/admin/review-queue.php', 'roles' => ['owner', 'reviewer']],
            'articles' => ['label' => 'المقالات والأدلة', 'href' => '/admin/articles.php', 'roles' => ['owner', 'editor', 'reviewer']],
            'categories' => ['label' => 'التصنيفات', 'href' => '/admin/categories.php', 'roles' => ['owner', 'editor', 'reviewer']],
            'faqs' => ['label' => 'الأسئلة الشائعة', 'href' => '/admin/faqs.php', 'roles' => ['owner', 'editor', 'reviewer']],
            'resources' => ['label' => 'مصادر وملفات', 'href' => '/admin/resources.php', 'roles' => ['owner', 'editor', 'reviewer']],
            'events' => ['label' => 'محاضرات وفعاليات', 'href' => '/admin/events.php', 'roles' => ['owner', 'editor', 'reviewer']],
        ],
    ],
    'operations' => [
        'label' => 'التشغيل', 'icon' => 'operations',
        'roles' => ['owner', 'bookings_manager'],
        'tabs' => [
            'clinic-hours' => ['label' => 'ساعات العمل والإجازات', 'href' => '/admin/clinic-hours.php', 'roles' => ['owner', 'bookings_manager']],
            'messages' => ['label' => 'رسائل التواصل', 'href' => '/admin/messages.php', 'roles' => ['owner', 'bookings_manager']],
            'templates' => ['label' => 'قوالب الردود', 'href' => '/admin/reply-templates.php', 'roles' => ['owner', 'bookings_manager']],
            'speaking' => ['label' => 'دعوات التحدث', 'href' => '/admin/speaking-inquiries.php', 'roles' => ['owner', 'bookings_manager']],
            'ops-tasks' => ['label' => 'مهام تشغيلية', 'href' => '/admin/ops-tasks.php', 'roles' => ['owner', 'bookings_manager']],
            'users' => ['label' => 'الفريق والصلاحيات', 'href' => '/admin/users.php', 'roles' => ['owner']],
            'notifications' => ['label' => 'الإشعارات والتكامل', 'href' => '/admin/notifications-settings.php', 'roles' => ['owner']],
            'clinical-settings' => ['label' => 'الملاحظات السريرية', 'href' => '/admin/clinical-settings.php', 'roles' => ['owner']],
            'subscribers' => ['label' => 'النشرة البريدية', 'href' => '/admin/subscribers.php', 'roles' => ['owner']],
        ],
    ],
    'analytics' => [
        'label' => 'التحليلات', 'icon' => 'analytics',
        'roles' => ['owner', 'bookings_manager', 'financial'],
        'tabs' => [
            'reports' => ['label' => 'التقارير', 'href' => '/admin/reports.php', 'roles' => ['owner', 'bookings_manager', 'financial']],
            'analytics' => ['label' => 'إحصاءات الزيارات', 'href' => '/admin/analytics.php', 'roles' => ['owner']],
        ],
    ],
    'financial' => [
        'label' => 'المالية', 'icon' => 'wallet',
        'roles' => ['owner', 'financial'],
        'require_setting' => 'billing_enabled', // hidden from the sidebar unless this setting is '1'
        'tabs' => [
            'billing' => ['label' => 'الفواتير والمدفوعات', 'href' => '/admin/billing.php', 'roles' => ['owner', 'financial']],
        ],
    ],
];

// Shown separately at the bottom of the sidebar, visually distinct from the main groups above.
$NAV_BOTTOM = [
    'settings' => [
        'label' => 'الإعدادات', 'icon' => 'settings',
        'roles' => ['owner'],
        'tabs' => [
            'settings' => ['label' => 'الإعدادات العامة', 'href' => '/admin/settings.php', 'roles' => ['owner']],
            'readiness' => ['label' => 'جاهزية التشغيل', 'href' => '/admin/readiness.php', 'roles' => ['owner']],
        ],
    ],
    'account' => [
        'label' => 'حسابي', 'icon' => 'account',
        'roles' => ['owner', 'editor', 'reviewer', 'bookings_manager', 'financial'],
        'tabs' => [
            'account' => ['label' => 'حسابي', 'href' => '/admin/account.php', 'roles' => ['owner', 'editor', 'reviewer', 'bookings_manager', 'financial']],
        ],
    ],
];

// Reverse lookup built purely from the tabs above: $activeNav => ['group' => key, 'section' => main|bottom].
$NAV_PAGE_TO_GROUP = [];
foreach ($NAV_GROUPS as $groupKey => $group) {
    foreach ($group['tabs'] as $tabKey => $tab) {
        $NAV_PAGE_TO_GROUP[$tabKey] = ['group' => $groupKey, 'section' => 'main'];
    }
}
foreach ($NAV_BOTTOM as $groupKey => $group) {
    foreach ($group['tabs'] as $tabKey => $tab) {
        $NAV_PAGE_TO_GROUP[$tabKey] = ['group' => $groupKey, 'section' => 'bottom'];
    }
}

function nav_user_can(array $roles, ?array $user): bool
{
    return $user && in_array($user['role'], $roles, true);
}
