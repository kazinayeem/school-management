<?php

namespace App\Support;

class RoleMenu
{
    /** @var array<string, list<array{title: string, slug: string, icon: string}>> */
    private const ITEMS = [
        'admin' => [
            ['Students', 'students', 'GraduationCap'],
            ['Teachers & Staff', 'staff', 'Users'],
            ['Classes & Sections', 'classes', 'School'],
            ['Attendance', 'attendance', 'CalendarCheck'],
            ['Exams & Results', 'exams', 'ClipboardList'],
            ['Fees & Accounts', 'fees', 'Wallet'],
            ['Notices', 'notices', 'Megaphone'],
            ['Settings', 'settings', 'Settings'],
        ],
        'staff' => [
            ['My Classes', 'my-classes', 'School'],
            ['Attendance', 'attendance', 'CalendarCheck'],
            ['Homework', 'homework', 'BookOpen'],
            ['Marks Entry', 'marks', 'ClipboardList'],
            ['Leave', 'leave', 'CalendarOff'],
            ['Notices', 'notices', 'Megaphone'],
        ],
        'student' => [
            ['Home', 'home', 'LayoutGrid', '/student'],
            ['Class Routine', 'routine', 'CalendarDays', '/student/routine'],
            ['My Calendar', 'calendar', 'CalendarDays', '/student/calendar'],
            ['My Attendance', 'attendance', 'CalendarCheck', '/student/attendance'],
            ['My Homework', 'homework', 'BookOpen', '/student/homework'],
            ['My Results', 'results', 'GraduationCap', '/student/results'],
            ['My Fees', 'fees', 'Wallet', '/student/fees'],
            ['My Library', 'library', 'BookOpen', '/student/library'],
            ['My Bus', 'transport', 'Bus', '/student/transport'],
            ['My Documents', 'documents', 'FileText', '/student/documents'],
            ['My Leave', 'leave', 'CalendarOff', '/student/leave'],
            ['My Profile', 'profile', 'User', '/student/profile'],
        ],
        'parent' => [
            ['My Children', 'children', 'Users', '/parent'],
            ['Attendance', 'attendance', 'CalendarCheck', '/parent/attendance'],
            ['Class Routine', 'routine', 'CalendarDays', '/parent/routine'],
            ['Calendar', 'calendar', 'CalendarDays', '/parent/calendar'],
            ['Documents', 'documents', 'FileText', '/parent/documents'],
            ['Leave', 'leave', 'CalendarOff', '/parent/leave'],
            ['Homework', 'homework', 'BookOpen', '/parent/homework'],
            ['Library', 'library', 'BookOpen', '/parent/library'],
            ['School Bus', 'transport', 'Bus', '/parent/transport'],
            ['Conduct', 'conduct', 'FileText', '/parent/conduct'],
            ['My Profile', 'profile', 'User', '/parent/profile'],
        ],
    ];

    /** @return list<array{title: string, url: string, icon: string}> */
    public static function for(?string $role): array
    {
        return array_map(
            fn (array $i) => ['title' => $i[0], 'url' => $i[3] ?? "/m/{$i[1]}", 'icon' => $i[2]],
            self::ITEMS[$role] ?? [],
        );
    }

    public static function find(?string $role, string $slug): ?array
    {
        foreach (self::for($role) as $item) {
            if ($item['url'] === "/m/{$slug}") {
                return $item;
            }
        }

        return null;
    }
}
