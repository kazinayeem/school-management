<?php

namespace App\Support;

use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Support\Str;

class StudentEnrollment
{
    /**
     * Create a student account and profile, giving them the next roll number
     * in their class and section and the next admission number of the year.
     *
     * @param array{name: string, class_name: string, section: string, gender: string, date_of_birth?: ?string, guardian_name?: ?string, guardian_mobile?: ?string, religion?: ?string, address?: ?string} $data
     */
    public static function enroll(array $data): User
    {
        $year = '2026';
        $username = Str::slug($data['name'], '.').'.'.random_int(1000, 9999);
        $roll = (int) StudentProfile::query()
            ->where('class_name', $data['class_name'])
            ->where('section', $data['section'])
            ->selectRaw('max(cast(roll_no as integer)) as roll')
            ->value('roll') + 1;
        $last = StudentProfile::query()->where('admission_no', 'like', "{$year}-%")->max('admission_no');
        $admission = $year.'-'.str_pad((string) (((int) substr((string) $last, 5)) + 1), 5, '0', STR_PAD_LEFT);

        $user = User::create([
            'name' => $data['name'],
            'username' => $username,
            'email' => $username.'@borno.test',
            'phone' => $data['guardian_mobile'] ?? null,
            'role' => 'student',
            'password' => 'Demo@1234',
        ]);

        $user->profile()->create([
            'admission_no' => $admission,
            'class_name' => $data['class_name'],
            'section' => $data['section'],
            'roll_no' => (string) $roll,
            'gender' => $data['gender'],
            'date_of_birth' => $data['date_of_birth'] ?? null,
            'shift' => 'Morning',
            'guardian_name' => $data['guardian_name'] ?? null,
            'guardian_mobile' => $data['guardian_mobile'] ?? null,
            'religion' => $data['religion'] ?? null,
            'address' => $data['address'] ?? null,
            'status' => 'active',
            'session' => $year,
        ]);

        return $user;
    }

    public static function nextRoll(string $class, ?string $section): int
    {
        return (int) StudentProfile::query()
            ->where('class_name', $class)
            ->where('section', $section)
            ->selectRaw('max(cast(roll_no as integer)) as roll')
            ->value('roll') + 1;
    }
}
