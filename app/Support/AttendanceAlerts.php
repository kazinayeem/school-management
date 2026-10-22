<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

class AttendanceAlerts
{
    /** Text the guardians of newly absent students, when Settings → Attendance Rules says to. */
    public static function notify(int $actorId, string $date, array $studentIds): void
    {
        if ($studentIds === [] || SiteSettings::get('attendance_notify_absent', '0') !== '1') {
            return;
        }

        $students = DB::table('users')->join('student_profiles', 'student_profiles.user_id', '=', 'users.id')->whereIn('users.id', $studentIds)
            ->get(['users.name', 'student_profiles.guardian_name', 'student_profiles.guardian_mobile', 'student_profiles.class_name', 'student_profiles.section']);

        foreach ($students as $s) {
            if (filled($s->guardian_mobile)) {
                SmsGateway::queue($s->guardian_mobile, $s->guardian_name, 'Dear '.($s->guardian_name ?: 'Guardian').", {$s->name} (".trim($s->class_name.' '.$s->section).") was absent on {$date}.", $actorId);
            }
        }
    }
}
