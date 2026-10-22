<?php

namespace App\Support;

class OfficeMenu
{
    /** @var array<string, string> */
    private const HIDDEN = [
        '/app/leave' => 'Leave Applications',
    ];

    /** @return list<array{caption: ?string, items: list<array<string, mixed>>}> */
    public static function for(string $role): array
    {
        $card = ['label' => 'My ID Card', 'href' => '/app/me/card', 'icon' => 'IdCard'];
        $talk = [['label' => 'Messages', 'href' => '/app/messages', 'icon' => 'Mail'], ['label' => 'Support Tickets', 'href' => '/app/tickets', 'icon' => 'LifeBuoy']];
        $profile = ['label' => 'My Profile', 'href' => '/app/profile', 'icon' => 'UserCog'];
        if ($role === 'admin') {
            $menu = self::admin();
            array_splice($menu[0]['items'], 1, 0, [$profile, $card, ...$talk]);

            return $menu;
        }

        $menu = self::filter($role, self::admin());
        $mine = [$profile, ['label' => 'My HR', 'href' => '/app/me', 'icon' => 'UserCog'], $card, ...$talk];
        if ($menu !== []) {
            array_splice($menu[0]['items'], 1, 0, $mine);
        } else {
            $menu[] = ['caption' => null, 'items' => $mine];
        }

        return $menu;
    }

    public static function allows(string $role, string $path): bool
    {
        if (preg_match('#^/app/students/\d+$#', $path)) {
            $path = '/app/students';
        }

        if ($role === 'admin') {
            if (isset(self::HIDDEN[$path])) {
                return true;
            }

            foreach (self::flat($role) as $item) {
                if ($item['href'] === $path) {
                    return true;
                }
            }

            return false;
        }

        return Permissions::pathAllowed($role, $path);
    }

    public static function find(string $role, string $path): ?array
    {
        foreach (self::flat($role) as $item) {
            if ($item['href'] === $path) {
                return $item;
            }
        }

        if (in_array($path, ['/app/me', '/app/me/card', '/app/profile', '/app/messages', '/app/tickets'], true)) {
            return ['label' => ['/app/me' => 'My HR', '/app/profile' => 'My Profile', '/app/messages' => 'Messages', '/app/tickets' => 'Support Tickets'][$path] ?? 'My ID Card', 'href' => $path];
        }

        if ($role === 'admin' && isset(self::HIDDEN[$path])) {
            return ['label' => self::HIDDEN[$path], 'href' => $path];
        }

        return null;
    }

    /** @return list<array{label: string, href: string}> */
    public static function flat(string $role): array
    {
        $items = [];

        foreach (self::for($role) as $group) {
            foreach ($group['items'] as $item) {
                if (isset($item['href'])) {
                    $items[] = ['label' => $item['label'], 'href' => $item['href']];
                }
                foreach ($item['children'] ?? [] as $child) {
                    $items[] = ['label' => $child['label'], 'href' => $child['href']];
                }
            }
        }

        return $items;
    }

    /** Keep only the pages this role may open: its modules with View, minus admin-only pages. */
    private static function filter(string $role, array $groups): array
    {
        $out = [];

        foreach ($groups as $group) {
            $items = [];
            foreach ($group['items'] as $item) {
                if (isset($item['children'])) {
                    $item['children'] = array_values(array_filter($item['children'], fn ($c) => Permissions::pathAllowed($role, $c['href'])));
                    if ($item['children'] === []) {
                        continue;
                    }
                } elseif (! isset($item['href']) || ! Permissions::pathAllowed($role, $item['href'])) {
                    continue;
                }
                $items[] = $item;
            }
            if ($items !== []) {
                $out[] = ['caption' => $group['caption'], 'items' => $items];
            }
        }

        return $out;
    }

    private static function admin(): array
    {
        $children = fn (array $pairs) => array_map(fn ($p) => ['label' => $p[0], 'href' => $p[1]], $pairs);

        return [
            ['caption' => null, 'items' => [
                ['label' => 'Dashboard', 'href' => '/app', 'icon' => 'LayoutGrid'],
            ]],
            ['caption' => 'Academic', 'items' => [
                ['label' => 'Academic Structure', 'icon' => 'School', 'children' => $children([
                    ['Sessions', '/app/academic/sessions'],
                    ['Classes', '/app/academic/classes'],
                    ['Sections', '/app/academic/sections'],
                    ['Groups', '/app/academic/groups'],
                    ['Shifts', '/app/academic/shifts'],
                    ['Mediums', '/app/academic/mediums'],
                    ['Subjects', '/app/academic/subjects'],
                    ['Teaching Assignments', '/app/academic/assignments'],
                ])],
                ['label' => 'Timetable', 'icon' => 'CalendarClock', 'children' => $children([
                    ['Class Routine', '/app/timetable'],
                    ['Teacher Routine', '/app/timetable/teacher'],
                    ['Free Rooms', '/app/timetable/free-rooms'],
                    ['Substitutions', '/app/timetable/substitutions'],
                    ['Bell Schedule', '/app/timetable/periods'],
                    ['Rooms', '/app/timetable/rooms'],
                ])],
                ['label' => 'Attendance', 'icon' => 'CalendarCheck', 'children' => $children([
                    ['Mark Attendance', '/app/attendance'],
                    ['Register & Reports', '/app/attendance/register'],
                    ['Staff Attendance', '/app/attendance/staff'],
                    ['Punch Devices', '/app/devices'],
                    ['Cards & Fingerprints', '/app/devices/people'],
                    ['Punch Log', '/app/devices/punches'],
                ])],
                ['label' => 'Homework', 'href' => '/app/homework', 'icon' => 'NotebookPen'],
                ['label' => 'Exams & Results', 'icon' => 'GraduationCap', 'children' => $children([
                    ['Exam Setup', '/app/exams'],
                    ['Admit Cards', '/app/exams/admit-cards'],
                    ['Marks Entry', '/app/exams/marks'],
                    ['Results', '/app/exams/results'],
                ])],
            ]],
            ['caption' => 'People', 'items' => [
                ['label' => 'Students', 'icon' => 'Users', 'children' => $children([
                    ['Student List', '/app/students'],
                    ['New Admission', '/app/students/new'],
                    ['Bulk Import', '/app/students/import'],
                    ['Roll Numbers', '/app/students/roll-numbers'],
                    ['ID Cards', '/app/students/id-cards'],
                    ['Promotion', '/app/students/promotion'],
                ])],
                ['label' => 'Online Admission', 'icon' => 'ClipboardList', 'children' => $children([
                    ['Applications', '/app/admissions'],
                    ['Portal Settings', '/app/admissions/settings'],
                ])],
                ['label' => 'Teachers & Staff', 'icon' => 'UserCog', 'children' => $children([
                    ['Staff List', '/app/staff'],
                    ['Add Staff', '/app/staff/new'],
                ])],
                ['label' => 'Parent Accounts', 'href' => '/app/system/parents', 'icon' => 'Contact'],
                ['label' => 'Leave', 'icon' => 'CalendarOff', 'children' => $children([
                    ['Applications', '/app/leave/applications'],
                    ['Leave Types', '/app/leave/types'],
                ])],
                ['label' => 'Certificates', 'icon' => 'Award', 'children' => $children([
                    ['Issue Certificate', '/app/certificates'],
                    ['Issued Register', '/app/certificates/history'],
                    ['Requests', '/app/certificates/requests'],
                    ['Templates', '/app/certificates/templates'],
                ])],
            ]],
            ['caption' => 'Finance', 'items' => [
                ['label' => 'Fees', 'icon' => 'Banknote', 'children' => $children([
                    ['Fee Collection', '/app/fees'],
                    ['Due List', '/app/fees/dues'],
                    ['Collection Report', '/app/fees/payments'],
                    ['Generate Invoices', '/app/fees/generate'],
                    ['Fee Structure', '/app/fees/structure'],
                    ['Student Fee Account', '/app/fees/student'],
                    ['Waivers & Discounts', '/app/fees/waivers'],
                ])],
                ['label' => 'Online Payments', 'icon' => 'CreditCard', 'children' => $children([
                    ['Transactions', '/app/fees/online'],
                    ['Payment Gateway', '/app/fees/gateway'],
                ])],
                ['label' => 'Payroll', 'icon' => 'Wallet', 'children' => $children([
                    ['Payroll Runs', '/app/payroll'],
                    ['Salary Structures', '/app/payroll/structures'],
                    ['Salary Components', '/app/payroll/components'],
                ])],
                ['label' => 'Accounting', 'icon' => 'BookOpen', 'children' => $children([
                    ['Voucher Entry', '/app/accounting'],
                    ['Voucher Register', '/app/accounting/register'],
                    ['Chart of Accounts', '/app/accounting/chart'],
                    ['Cash Book', '/app/accounting/cash-book'],
                    ['Trial Balance', '/app/accounting/trial-balance'],
                    ['Income & Expenditure', '/app/accounting/income-expense'],
                ])],
            ]],
            ['caption' => 'Campus', 'items' => [
                ['label' => 'Library', 'icon' => 'Library', 'children' => $children([
                    ['Give a Book', '/app/library'],
                    ['Take Books Back', '/app/library/desk'],
                    ['Overdue & Fines', '/app/library/fines'],
                    ['Reserved Books', '/app/library/reservations'],
                    ['All Books', '/app/library/books'],
                    ['Book Categories', '/app/library/categories'],
                    ['Library Cards', '/app/library/members'],
                    ['Settings', '/app/library/settings'],
                ])],
                ['label' => 'Transport', 'icon' => 'Bus', 'children' => $children([
                    ['Who Rides', '/app/transport'],
                    ['Daily Manifest', '/app/transport/manifest'],
                    ['Routes & Stops', '/app/transport/routes'],
                    ['Vehicles', '/app/transport/vehicles'],
                ])],
            ]],
            ['caption' => 'Communication', 'items' => [
                ['label' => 'Notices', 'href' => '/app/notices', 'icon' => 'Megaphone'],
                ['label' => 'SMS', 'icon' => 'MessageSquare', 'children' => $children([
                    ['Send SMS', '/app/sms'],
                    ['Templates', '/app/sms/templates'],
                    ['Delivery Log', '/app/sms/log'],
                ])],
                ['label' => 'Website', 'icon' => 'Globe', 'children' => $children([
                    ['Messages', '/app/website/messages'],
                    ['Pages', '/app/website/pages'],
                    ['Hero Banners', '/app/website/banners'],
                    ['Events', '/app/website/events'],
                    ['Gallery', '/app/website/gallery'],
                    ['Testimonials', '/app/website/testimonials'],
                    ['FAQs', '/app/website/faqs'],
                    ['Site Settings', '/app/website/settings'],
                ])],
            ]],
            ['caption' => 'System', 'items' => [
                ['label' => 'User Accounts', 'href' => '/app/system/users', 'icon' => 'UserCheck'],
                ['label' => 'Audit Log', 'href' => '/app/system/audit', 'icon' => 'History'],
                ['label' => 'Settings', 'icon' => 'Settings', 'children' => $children([
                    ['General', '/app/settings/general'],
                    ['Branding & Logo', '/app/settings/branding'],
                    ['Grading', '/app/settings/grading'],
                    ['Attendance Rules', '/app/settings/attendance'],
                    ['Fees & Late Fee', '/app/settings/fees'],
                    ['SMS Gateway', '/app/sms/settings'],
                    ['Email / SMTP', '/app/settings/mail'],
                    ['Appearance', '/app/settings/appearance'],
                    ['Languages', '/app/settings/languages'],
                    ['Roles & Permissions', '/app/system/roles'],
                    ['Backup & Restore', '/app/settings/backup'],
                ])],
            ]],
        ];
    }
}
