<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class Permissions
{
    /** @var array<string, string> permission key => label */
    public const MODULES = [
        'academic' => 'Academic Structure',
        'timetable' => 'Timetable',
        'attendance' => 'Attendance',
        'homework' => 'Homework',
        'exams' => 'Exams & Results',
        'students' => 'Students',
        'leave' => 'Leave',
        'library' => 'Library',
        'notices' => 'Notices',
    ];

    /** @return list<string> */
    public static function granted(string $role): array
    {
        if (! Schema::hasTable('role_permissions')) {
            return array_keys(self::MODULES);
        }

        return DB::table('role_permissions')->where('role', $role)->pluck('permission')->all();
    }

    public static function moduleOf(string $href): ?string
    {
        if (preg_match('#^/app/([a-z-]+)#', $href, $m) && isset(self::MODULES[$m[1]])) {
            return $m[1];
        }

        return null;
    }

    public static function allows(string $role, string $href): bool
    {
        $module = self::moduleOf($href);

        return $module === null || in_array($module, self::granted($role), true);
    }
}
