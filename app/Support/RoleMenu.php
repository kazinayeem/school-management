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
            ['Class Routine', 'routine', 'CalendarDays'],
            ['Attendance', 'attendance', 'CalendarCheck'],
            ['Homework', 'homework', 'BookOpen'],
            ['Results', 'results', 'ClipboardList'],
            ['Fees', 'fees', 'Wallet'],
            ['Notices', 'notices', 'Megaphone'],
        ],
        'parent' => [
            ['My Children', 'children', 'Users'],
            ['Attendance', 'attendance', 'CalendarCheck'],
            ['Results', 'results', 'ClipboardList'],
            ['Fees', 'fees', 'Wallet'],
            ['Notices', 'notices', 'Megaphone'],
        ],
    ];

    /** @return list<array{title: string, url: string, icon: string}> */
    public static function for(?string $role): array
    {
        return array_map(
            fn (array $i) => ['title' => $i[0], 'url' => "/m/{$i[1]}", 'icon' => $i[2]],
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
