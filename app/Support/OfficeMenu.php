<?php

namespace App\Support;

class OfficeMenu
{
    /** @return list<array{caption: ?string, items: list<array<string, mixed>>}> */
    public static function for(string $role): array
    {
        return $role === 'admin' ? self::admin() : self::staff();
    }

    public static function allows(string $role, string $path): bool
    {
        foreach (self::flat($role) as $item) {
            if ($item['href'] === $path) {
                return true;
            }
        }

        return false;
    }

    public static function find(string $role, string $path): ?array
    {
        foreach (self::flat($role) as $item) {
            if ($item['href'] === $path) {
                return $item;
            }
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

    private static function staff(): array
    {
        return [
            ['caption' => null, 'items' => [
                ['label' => 'Dashboard', 'href' => '/app', 'icon' => 'LayoutGrid'],
            ]],
            ['caption' => 'Academic', 'items' => [
                ['label' => 'Academic Structure', 'icon' => 'School', 'children' => [
                    ['label' => 'Classes', 'href' => '/app/academic/classes'],
                    ['label' => 'Sections', 'href' => '/app/academic/sections'],
                    ['label' => 'Subjects', 'href' => '/app/academic/subjects'],
                ]],
                ['label' => 'Timetable', 'icon' => 'CalendarDays', 'children' => [
                    ['label' => 'Class Routine', 'href' => '/app/timetable'],
                    ['label' => 'Teacher Routine', 'href' => '/app/timetable/teacher'],
                ]],
                ['label' => 'Attendance', 'icon' => 'CalendarCheck', 'children' => [
                    ['label' => 'Mark Attendance', 'href' => '/app/attendance'],
                    ['label' => 'Register & Reports', 'href' => '/app/attendance/register'],
                ]],
                ['label' => 'Homework', 'href' => '/app/homework', 'icon' => 'BookOpen'],
                ['label' => 'Exams & Results', 'icon' => 'GraduationCap', 'children' => [
                    ['label' => 'Marks Entry', 'href' => '/app/exams/marks'],
                    ['label' => 'Results', 'href' => '/app/exams/results'],
                ]],
            ]],
            ['caption' => 'People', 'items' => [
                ['label' => 'Students', 'icon' => 'Users', 'children' => [
                    ['label' => 'Student List', 'href' => '/app/students'],
                ]],
                ['label' => 'Leave', 'icon' => 'CalendarOff', 'children' => [
                    ['label' => 'Applications', 'href' => '/app/leave'],
                ]],
            ]],
            ['caption' => 'Campus', 'items' => [
                ['label' => 'Library', 'icon' => 'BookOpen', 'children' => [
                    ['label' => 'Give a Book', 'href' => '/app/library'],
                    ['label' => 'Overdue & Fines', 'href' => '/app/library/fines'],
                ]],
            ]],
            ['caption' => 'Communication', 'items' => [
                ['label' => 'Notices', 'href' => '/app/notices', 'icon' => 'Megaphone'],
            ]],
        ];
    }

    private static function admin(): array
    {
        return [
            ['caption' => null, 'items' => [
                ['label' => 'Dashboard', 'href' => '/app', 'icon' => 'LayoutGrid'],
            ]],
            ['caption' => 'Academic', 'items' => [
                ['label' => 'Academic Structure', 'icon' => 'School', 'children' => [
                    ['label' => 'Sessions', 'href' => '/app/academic/sessions'],
                    ['label' => 'Classes', 'href' => '/app/academic/classes'],
                    ['label' => 'Sections', 'href' => '/app/academic/sections'],
                    ['label' => 'Subjects', 'href' => '/app/academic/subjects'],
                    ['label' => 'Teaching Assignments', 'href' => '/app/academic/assignments'],
                ]],
                ['label' => 'Timetable', 'icon' => 'CalendarDays', 'children' => [
                    ['label' => 'Class Routine', 'href' => '/app/timetable'],
                    ['label' => 'Teacher Routine', 'href' => '/app/timetable/teacher'],
                    ['label' => 'Substitutions', 'href' => '/app/timetable/substitutions'],
                    ['label' => 'Bell Schedule', 'href' => '/app/timetable/periods'],
                ]],
                ['label' => 'Attendance', 'icon' => 'CalendarCheck', 'children' => [
                    ['label' => 'Mark Attendance', 'href' => '/app/attendance'],
                    ['label' => 'Register & Reports', 'href' => '/app/attendance/register'],
                    ['label' => 'Staff Attendance', 'href' => '/app/attendance/staff'],
                ]],
                ['label' => 'Homework', 'href' => '/app/homework', 'icon' => 'BookOpen'],
                ['label' => 'Exams & Results', 'icon' => 'GraduationCap', 'children' => [
                    ['label' => 'Exam Setup', 'href' => '/app/exams'],
                    ['label' => 'Admit Cards', 'href' => '/app/exams/admit-cards'],
                    ['label' => 'Marks Entry', 'href' => '/app/exams/marks'],
                    ['label' => 'Results', 'href' => '/app/exams/results'],
                ]],
            ]],
            ['caption' => 'People', 'items' => [
                ['label' => 'Students', 'icon' => 'Users', 'children' => [
                    ['label' => 'Student List', 'href' => '/app/students'],
                    ['label' => 'New Admission', 'href' => '/app/students/new'],
                    ['label' => 'Roll Numbers', 'href' => '/app/students/roll-numbers'],
                    ['label' => 'ID Cards', 'href' => '/app/students/id-cards'],
                ]],
                ['label' => 'Teachers & Staff', 'icon' => 'Users', 'children' => [
                    ['label' => 'Staff List', 'href' => '/app/staff'],
                    ['label' => 'Add Staff', 'href' => '/app/staff/new'],
                    ['label' => 'Parent Accounts', 'href' => '/app/parents'],
                ]],
                ['label' => 'Leave', 'icon' => 'CalendarOff', 'children' => [
                    ['label' => 'Applications', 'href' => '/app/leave'],
                    ['label' => 'Leave Types', 'href' => '/app/leave/types'],
                ]],
            ]],
            ['caption' => 'Finance', 'items' => [
                ['label' => 'Fees', 'icon' => 'Wallet', 'children' => [
                    ['label' => 'Fee Collection', 'href' => '/app/fees'],
                    ['label' => 'Due List', 'href' => '/app/fees/dues'],
                    ['label' => 'Collection Report', 'href' => '/app/fees/payments'],
                    ['label' => 'Fee Structure', 'href' => '/app/fees/structure'],
                ]],
                ['label' => 'Payroll', 'icon' => 'Wallet', 'children' => [
                    ['label' => 'Payroll Runs', 'href' => '/app/payroll'],
                    ['label' => 'Salary Structures', 'href' => '/app/payroll/structures'],
                ]],
            ]],
            ['caption' => 'Campus', 'items' => [
                ['label' => 'Library', 'icon' => 'BookOpen', 'children' => [
                    ['label' => 'Give a Book', 'href' => '/app/library'],
                    ['label' => 'Overdue & Fines', 'href' => '/app/library/fines'],
                    ['label' => 'All Books', 'href' => '/app/library/books'],
                ]],
                ['label' => 'Transport', 'icon' => 'Bus', 'children' => [
                    ['label' => 'Who Rides', 'href' => '/app/transport'],
                    ['label' => 'Routes & Stops', 'href' => '/app/transport/routes'],
                    ['label' => 'Vehicles', 'href' => '/app/transport/vehicles'],
                ]],
            ]],
            ['caption' => 'Communication', 'items' => [
                ['label' => 'Notices', 'href' => '/app/notices', 'icon' => 'Megaphone'],
                ['label' => 'SMS', 'icon' => 'Megaphone', 'children' => [
                    ['label' => 'Send SMS', 'href' => '/app/sms'],
                    ['label' => 'Delivery Log', 'href' => '/app/sms/log'],
                ]],
            ]],
            ['caption' => 'System', 'items' => [
                ['label' => 'User Accounts', 'href' => '/app/system/users', 'icon' => 'Settings'],
                ['label' => 'Settings', 'icon' => 'Settings', 'children' => [
                    ['label' => 'General', 'href' => '/app/settings/general'],
                    ['label' => 'Grading', 'href' => '/app/settings/grading'],
                    ['label' => 'Attendance Rules', 'href' => '/app/settings/attendance'],
                ]],
            ]],
        ];
    }
}
