<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class Permissions
{
    public const ACTIONS = ['view', 'edit', 'delete'];

    /** Teachers and office staff. Admins always have full access and are not listed here. */
    public const EMPLOYEE_ROLES = ['teacher', 'staff'];

    /**
     * What an admin can hand out. Each module covers every page under its path prefixes.
     *
     * @var array<string, array{0: string, 1: list<string>}>
     */
    public const MODULES = [
        'academic' => ['Academic Structure', ['/app/academic']],
        'timetable' => ['Timetable', ['/app/timetable']],
        'attendance' => ['Attendance', ['/app/attendance']],
        'homework' => ['Homework', ['/app/homework']],
        'exams' => ['Exams & Results', ['/app/exams']],
        'students' => ['Students', ['/app/students']],
        'admissions' => ['Online Admission', ['/app/admissions']],
        'staff' => ['Teachers & Staff', ['/app/staff']],
        'parents' => ['Parent Accounts', ['/app/system/parents', '/app/system/students']],
        'leave' => ['Leave', ['/app/leave']],
        'certificates' => ['Certificates', ['/app/certificates']],
        'fees' => ['Fees', ['/app/fees']],
        'payroll' => ['Payroll', ['/app/payroll']],
        'accounting' => ['Accounting', ['/app/accounting']],
        'library' => ['Library', ['/app/library']],
        'transport' => ['Transport', ['/app/transport']],
        'notices' => ['Notices', ['/app/notices']],
        'sms' => ['SMS', ['/app/sms']],
        'website' => ['Website', ['/app/website']],
    ];

    /**
     * Pages only an admin may use, whatever a role has been granted. They change the
     * school's structure, publish results or touch security-sensitive settings.
     */
    private const ADMIN_ONLY = [
        '#^/app/academic/(sessions|groups|shifts|mediums|assignments)#',
        '#^/app/timetable/(periods|rooms|substitutions)#',
        '#^/app/exams$#', '#^/app/exams/\d+$#', '#^/app/exams/(admit-cards|schedule|publish|unpublish)#',
        '#^/app/attendance/(staff|punches)#',
        '#^/app/students/(import|promotion)#',
        '#^/app/admissions/settings#',
        '#^/app/fees/gateway#',
        '#^/app/library/settings#',
        '#^/app/sms/settings#',
        '#^/app/website/settings#',
    ];

    /** @var array<string, list<string>> */
    private static array $cache = [];

    public static function flush(): void
    {
        self::$cache = [];
    }

    public static function label(string $role): string
    {
        return match ($role) {
            'admin' => 'Super Admin',
            'teacher' => 'Teacher',
            'staff' => 'Staff',
            default => ucfirst($role),
        };
    }

    public static function isEmployee(string $role): bool
    {
        return in_array($role, self::EMPLOYEE_ROLES, true);
    }

    /** @return list<string> "module.action" strings granted to the role */
    public static function granted(string $role): array
    {
        if (isset(self::$cache[$role])) {
            return self::$cache[$role];
        }
        if (! Schema::hasTable('role_permissions')) {
            return [];
        }

        return self::$cache[$role] = DB::table('role_permissions')->where('role', $role)->pluck('permission')->all();
    }

    public static function canRole(string $role, string $module, string $action = 'view'): bool
    {
        return $role === 'admin' || in_array("{$module}.{$action}", self::granted($role), true);
    }

    public static function moduleOf(string $path): ?string
    {
        foreach (self::MODULES as $key => [, $prefixes]) {
            foreach ($prefixes as $prefix) {
                if ($path === $prefix || str_starts_with($path, $prefix.'/')) {
                    return $key;
                }
            }
        }

        return null;
    }

    public static function isAdminOnly(string $path): bool
    {
        foreach (self::ADMIN_ONLY as $pattern) {
            if (preg_match($pattern, $path)) {
                return true;
            }
        }

        return false;
    }

    /** Registering students is for admins and office staff only. */
    private static function isTeacherBlocked(string $path, string $method): bool
    {
        $read = in_array(strtoupper($method), ['GET', 'HEAD'], true);

        return preg_match('#^/app/(students/(new|roll-numbers|id-cards)|admissions)#', $path) === 1
            || ($path === '/app/students' && ! $read);
    }

    public static function actionFor(string $method): string
    {
        return match (strtoupper($method)) {
            'GET', 'HEAD' => 'view',
            'DELETE' => 'delete',
            default => 'edit',
        };
    }

    /** Whether a role may make this kind of request to an /app path. */
    public static function pathAllowed(string $role, string $path, string $method = 'GET'): bool
    {
        if ($role === 'admin') {
            return true;
        }
        if (! self::isEmployee($role)) {
            return false;
        }
        if ($path === '/app') {
            return true;
        }
        // Read-only lookups used by the student pickers on many pages.
        if (str_starts_with($path, '/app/lookup/')) {
            return in_array(strtoupper($method), ['GET', 'HEAD'], true);
        }
        if ($role === 'teacher' && self::isTeacherBlocked($path, $method)) {
            return false;
        }
        if ($path === '/app/timetable' && ! in_array(strtoupper($method), ['GET', 'HEAD'], true)) {
            return false; // anyone with Timetable access may look at the routine; only admins save it
        }
        if (self::isAdminOnly($path) || ($module = self::moduleOf($path)) === null) {
            return false;
        }

        return self::canRole($role, $module, self::actionFor($method));
    }

    /** Pages for admins and employees; the module check has already run in the middleware. */
    public static function office(Request $request): void
    {
        $role = $request->user()?->role;
        abort_unless($role === 'admin' || ($role !== null && self::isEmployee($role)), 403);
    }

    /** Turn the edit/delete boxes ticked in the matrix into a consistent set (delete needs edit needs view). */
    public static function normalize(array $permissions): array
    {
        $set = [];
        foreach ($permissions as $p) {
            [$module, $action] = array_pad(explode('.', (string) $p, 2), 2, '');
            if (! isset(self::MODULES[$module]) || ! in_array($action, self::ACTIONS, true)) {
                continue;
            }
            foreach (array_slice(self::ACTIONS, 0, array_search($action, self::ACTIONS, true) + 1) as $implied) {
                $set["{$module}.{$implied}"] = true;
            }
        }

        return array_keys($set);
    }
}
