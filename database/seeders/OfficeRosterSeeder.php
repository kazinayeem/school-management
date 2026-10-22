<?php

namespace Database\Seeders;

use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class OfficeRosterSeeder extends Seeder
{
    public function run(): void
    {
        $ethan = User::where('username', 'student')->first();
        $nusrat = User::where('username', 'nusrat')->first();
        $hash = $ethan?->password;

        if ($ethan === null || $nusrat === null || $hash === null) {
            return;
        }

        User::where('username', 'like', 'mate-%')->orWhere('username', 'like', 'roster-%')->orWhere('username', 'like', 'teacher-%')->delete();

        $ethan->profile?->update([
            'admission_no' => '2026-00001',
            'gender' => 'Male',
            'date_of_birth' => '2012-08-11',
            'shift' => 'Morning',
            'guardian_name' => 'David Parker',
            'guardian_mobile' => '01711000001',
            'status' => 'active',
            'religion' => 'Islam',
            'address' => '221 Maple Street, Springfield',
        ]);
        $nusrat->profile?->update([
            'admission_no' => '2026-00090',
            'gender' => 'Female',
            'date_of_birth' => '2015-03-19',
            'shift' => 'Morning',
            'guardian_name' => 'Amelia Parker',
            'guardian_mobile' => '01711000003',
            'status' => 'active',
            'religion' => 'Islam',
            'address' => '14 Lake Road, Springfield',
        ]);

        $classmates = [
            ['sophia', 'Sophia Turner', 'Female', '2012-01-13', '2'],
            ['lucas', 'Lucas Bennett', 'Male', '2012-07-14', '3'],
            ['chloe', 'Chloe Mitchell', 'Female', '2012-09-14', '4'],
            ['noah', 'Noah Richardson', 'Male', '2012-03-11', '5'],
        ];

        $serial = 2;
        foreach ($classmates as [$slug, $name, $gender, $dob, $roll]) {
            $this->student($hash, "mate-{$slug}", $name, 'Class 8', 'A', $roll, $gender, $dob, sprintf('2026-%05d', $serial), '017110'.str_pad((string) $serial, 5, '0', STR_PAD_LEFT));
            $serial++;
        }

        $first = ['Ava', 'Liam', 'Grace', 'Thomas', 'Jack', 'Sophie', 'Amelia', 'Mason', 'Isabella', 'Owen', 'Hannah', 'Leo', 'Nora', 'Felix', 'Maya', 'Henry', 'Clara', 'Oscar', 'Ruby', 'Arthur'];
        $last = ['Coleman', 'Foster', 'Hayes', 'Cooper', 'Whitman', 'Morgan', 'Ward', 'Bailey', 'Brooks', 'Perry', 'Hughes', 'Cole', 'Reed', 'Bennett', 'Price', 'Long', 'Wood', 'Gray', 'West', 'Barnes'];
        $slots = $this->openSlots();
        $users = [];
        $profiles = [];
        $now = now();

        foreach ($slots as $index => [$class, $section, $roll]) {
            $number = $serial + $index;
            $username = 'roster-'.str_pad((string) $number, 5, '0', STR_PAD_LEFT);
            $name = $first[$index % count($first)].' '.$last[intdiv($index, count($first)) % count($last)];
            $gender = $index % 2 === 0 ? 'Female' : 'Male';
            $year = 2020 - (int) filter_var($class, FILTER_SANITIZE_NUMBER_INT);
            $users[] = [
                'name' => $name,
                'username' => $username,
                'email' => $username.'@borno.test',
                'phone' => '01712'.str_pad((string) $number, 5, '0', STR_PAD_LEFT),
                'role' => 'student',
                'password' => $hash,
                'created_at' => $now,
                'updated_at' => $now,
            ];
            $profiles[$username] = [
                'class_name' => $class,
                'section' => $section,
                'roll_no' => (string) $roll,
                'gender' => $gender,
                'date_of_birth' => sprintf('%d-%02d-%02d', $year, ($index % 12) + 1, ($index % 27) + 1),
                'shift' => 'Morning',
                'guardian_name' => $last[$index % count($last)].' family',
                'guardian_mobile' => '01713'.str_pad((string) $number, 5, '0', STR_PAD_LEFT),
                'status' => 'active',
                'religion' => 'Islam',
                'address' => 'Springfield',
                'admission_no' => sprintf('2026-%05d', $number),
                'session' => '2026',
                'attendance_present' => 0,
                'attendance_working' => 0,
                'gpa' => null,
                'exam_name' => null,
                'class_position' => null,
                'section_position' => null,
                'promotion_status' => null,
                'dues_total' => 0,
                'dues_count' => 0,
                'dues_due_date' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        foreach (array_chunk($users, 40) as $chunk) {
            User::insert($chunk);
        }

        $ids = User::query()->whereIn('username', array_keys($profiles))->pluck('id', 'username');
        $profileRows = [];
        foreach ($profiles as $username => $profile) {
            $profile['user_id'] = $ids[$username];
            $profileRows[] = $profile;
        }
        foreach (array_chunk($profileRows, 40) as $chunk) {
            StudentProfile::insert($chunk);
        }

        $this->invoices($hash);
        $this->staff($hash);
        $this->pendingLeave($ethan->id);
    }

    /** @return list<array{0: string, 1: string, 2: int}> */
    private function openSlots(): array
    {
        $taken = [
            'Class 8|A' => 5,
            'Class 5|B' => 1,
        ];
        $slots = [];

        foreach (range(1, 10) as $class) {
            foreach (['A', 'B'] as $section) {
                $target = $class <= 5 ? 10 : 9;
                if ($class === 8 && $section === 'A') {
                    $target = 5;
                }
                if ($class === 1 && $section === 'A') {
                    $target = 14;
                }
                $key = "Class {$class}|{$section}";
                $start = ($taken[$key] ?? 0) + 1;
                for ($roll = $start; $roll <= $target; $roll++) {
                    $slots[] = ["Class {$class}", $section, $roll];
                }
            }
        }

        return $slots;
    }

    private function student(string $hash, string $username, string $name, string $class, string $section, string $roll, string $gender, string $dob, string $admission, string $mobile): void
    {
        $user = User::create([
            'name' => $name,
            'username' => $username,
            'email' => $username.'@borno.test',
            'phone' => $mobile,
            'role' => 'student',
            'password' => $hash,
        ]);

        StudentProfile::create([
            'user_id' => $user->id,
            'admission_no' => $admission,
            'class_name' => $class,
            'section' => $section,
            'roll_no' => $roll,
            'gender' => $gender,
            'date_of_birth' => $dob,
            'shift' => 'Morning',
            'guardian_name' => 'Guardian of '.$name,
            'guardian_mobile' => $mobile,
            'status' => 'active',
            'religion' => 'Islam',
            'address' => 'Springfield',
            'session' => '2026',
        ]);
    }

    private function invoices(string $hash): void
    {
        $have = DB::table('fee_invoices')->whereIn('status', ['unpaid', 'partial'])->count();
        $needed = 104 - $have;

        if ($needed <= 0) {
            return;
        }

        $ids = User::query()
            ->where('role', 'student')
            ->whereDoesntHave('profile', fn ($query) => $query->whereIn('admission_no', ['2026-00001', '2026-00090']))
            ->orderBy('id')
            ->limit($needed)
            ->pluck('id');

        $rows = [];
        foreach ($ids as $index => $id) {
            $rows[] = [
                'user_id' => $id,
                'invoice_no' => 'INV-R-'.str_pad((string) ($index + 1), 4, '0', STR_PAD_LEFT),
                'label' => 'Tuition fee',
                'due_on' => '2026-11-10',
                'payable' => 1500,
                'paid' => 0,
                'status' => 'unpaid',
            ];
        }

        foreach (array_chunk($rows, 40) as $chunk) {
            DB::table('fee_invoices')->insert($chunk);
        }

        unset($hash);
    }

    private function staff(string $hash): void
    {
        $names = ['Alisha Henry', 'Rebecca Lawson', 'Owen Gallagher', 'Rosalind Meyer', 'Harold Jennings', 'Naomi Pearce', 'Helen Clarke', 'Simon Brooks', 'Priya Nair', 'Daniel Cho', 'Fatima Noor', 'Joseph Hale', 'Mina Rahman', 'Victor Lang', 'Sara Iqbal', 'Peter Walsh', 'Nadia Karim', 'Colin Fraser', 'Leah Morgan', 'Adam Yusuf', 'Ruth Begum', 'Ian Porter', 'Farah Ahmed', 'Mark Ellis', 'Chloe Grant', 'Hugo Patel'];
        $rows = [];
        $now = now();

        foreach ($names as $index => $name) {
            $username = 'teacher-'.str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT);
            $rows[] = [
                'name' => $name,
                'username' => $username,
                'email' => $username.'@borno.test',
                'role' => 'staff',
                'password' => $hash,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        User::insert($rows);
    }

    private function pendingLeave(int $userId): void
    {
        DB::table('leave_requests')->insert([
            'user_id' => $userId,
            'leave_type' => 'Casual Leave',
            'start_date' => '2026-10-27',
            'end_date' => '2026-10-27',
            'days' => 1,
            'reason' => 'Family programme',
            'status' => 'pending',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
