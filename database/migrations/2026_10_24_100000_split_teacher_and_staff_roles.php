<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private const DEFAULTS = [
        'teacher' => [
            'academic.view', 'timetable.view', 'attendance.view', 'attendance.edit', 'homework.view', 'homework.edit', 'homework.delete',
            'exams.view', 'exams.edit', 'students.view', 'leave.view', 'leave.edit', 'library.view', 'library.edit', 'notices.view', 'notices.edit',
        ],
        'staff' => [
            'students.view', 'admissions.view', 'admissions.edit', 'certificates.view', 'certificates.edit', 'fees.view', 'fees.edit',
            'library.view', 'library.edit', 'notices.view', 'notices.edit', 'leave.view', 'transport.view', 'website.view',
        ],
    ];

    public function up(): void
    {
        // Until now "staff" meant teacher. Teachers become their own role; "staff" is for office employees.
        DB::table('users')->where('role', 'staff')->update(['role' => 'teacher']);

        DB::table('role_permissions')->delete();
        foreach (self::DEFAULTS as $role => $permissions) {
            DB::table('role_permissions')->insert(array_map(fn ($p) => ['role' => $role, 'permission' => $p], $permissions));
        }
    }

    public function down(): void
    {
        DB::table('users')->whereIn('role', ['teacher', 'staff'])->update(['role' => 'staff']);
        DB::table('role_permissions')->delete();
    }
};
