<?php

namespace Database\Seeders;

use App\Support\Grading;
use App\Support\TransportSync;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

/**
 * Realistic Bangladeshi school data: 10 classes x 2 sections of students with
 * families, a clash-free timetable, teachers, fees, attendance, one published exam,
 * library, transport and generated avatars. Small when running tests.
 */
class BangladeshSeeder extends Seeder
{
    private const MALE = ['Mohammad', 'Md. Rakibul', 'Tanvir', 'Arif', 'Sabbir', 'Imran', 'Rafi', 'Fahim', 'Nayeem', 'Shakib', 'Tahmid', 'Mehedi', 'Rifat', 'Ashik', 'Jubayer', 'Zihad', 'Raihan', 'Sazzad', 'Mahin', 'Tawhid', 'Ayaan', 'Rayhan', 'Nahid', 'Sajid', 'Hasan', 'Faisal', 'Anik', 'Sohan', 'Rubel', 'Tamim'];
    private const FEMALE = ['Nusrat', 'Farhana', 'Sumaiya', 'Tasnim', 'Mim', 'Jannatul', 'Sadia', 'Maliha', 'Rumana', 'Afia', 'Nabila', 'Tanjila', 'Ritu', 'Mahiya', 'Ishrat', 'Labiba', 'Samiha', 'Humaira', 'Anika', 'Sharmin', 'Priya', 'Nadia', 'Tahsin', 'Raisa', 'Aisha', 'Mehjabin', 'Fariha', 'Shanta', 'Lamia', 'Nazia'];
    private const SURNAMES = ['Ahmed', 'Hossain', 'Rahman', 'Islam', 'Khan', 'Chowdhury', 'Uddin', 'Karim', 'Mia', 'Sarker', 'Akter', 'Begum', 'Siddique', 'Molla', 'Talukder', 'Bhuiyan', 'Sheikh', 'Miah', 'Alam', 'Haque'];
    private const HINDU_MALE = ['Sourav', 'Anirban', 'Rajib', 'Prosenjit', 'Debashish', 'Subho', 'Arnab', 'Tapan'];
    private const HINDU_FEMALE = ['Moushumi', 'Shreya', 'Puja', 'Tithi', 'Anwesha', 'Sraboni', 'Mitali', 'Riya'];
    private const HINDU_SURNAMES = ['Das', 'Roy', 'Saha', 'Paul', 'Ghosh', 'Biswas', 'Mondal', 'Chakraborty'];
    private const AREAS = ['Mirpur-10, Dhaka', 'Uttara Sector 7, Dhaka', 'Dhanmondi 27, Dhaka', 'Mohammadpur, Dhaka', 'Farmgate, Dhaka', 'Badda, Dhaka', 'Banani, Dhaka', 'Rampura, Dhaka', 'Kazipara, Dhaka', 'Shyamoli, Dhaka', 'Tejgaon, Dhaka', 'Malibagh, Dhaka'];
    private const SUBJECTS = ['Bangla', 'English', 'Mathematics', 'Science', 'Social Science', 'Religion'];
    private const PHONE_PREFIX = ['017', '018', '019', '016', '015', '013'];

    private string $hash;
    private int $perSection;
    private bool $avatars;
    private array $used = [];

    public function run(): void
    {
        mt_srand(2026);
        $this->hash = Hash::make('Demo@1234');
        $testing = app()->runningUnitTests();
        $this->perSection = $testing ? 3 : 30;
        $this->avatars = ! $testing;

        $this->wipe();
        if ($this->avatars) {
            Storage::disk('public')->deleteDirectory('photos');
        }

        $rooms = $this->rooms();
        $teachers = $this->employees();
        $this->assignments($teachers);
        $this->routine($teachers, $rooms);
        $students = $this->students();
        $this->attendance($students);
        $this->fees($students);
        $this->exam($students, $rooms);
        $this->homework($students, $teachers);
        $this->library($students);
        $this->transport($students);
        $this->campus($students);
        $this->website();
    }

    // ---------------------------------------------------------------- people

    private function wipe(): void
    {
        foreach ([
            'users', 'student_profiles', 'staff_profiles', 'guardian_student', 'teaching_assignments', 'class_periods', 'attendance_days',
            'period_attendance', 'fee_invoices', 'fee_payments', 'exam_results', 'exam_subjects', 'exam_publications', 'exams', 'homework_items',
            'library_books', 'library_members', 'library_loans', 'library_reservations', 'bus_assignments', 'transport_vehicles', 'transport_stops',
            'transport_routes', 'school_notices', 'calendar_events', 'leave_requests', 'contact_messages', 'admission_applications', 'student_notes',
            'student_documents', 'certificates', 'online_transactions', 'sms_logs', 'substitutions', 'staff_attendance', 'punches', 'rooms',
            'testimonials', 'website_events', 'faqs', 'homework_items', 'fee_waivers',
        ] as $table) {
            DB::table($table)->delete();
        }
    }

    private function rooms(): array
    {
        $now = now();
        $names = [];
        $rows = [];
        for ($n = 1; $n <= 20; $n++) {
            $names[] = $rows[] = ['name' => 'Room '.(100 + $n), 'kind' => 'classroom', 'capacity' => 40, 'active' => 1, 'created_at' => $now, 'updated_at' => $now];
        }
        foreach ([['Science Lab', 'lab', 30], ['Computer Lab', 'lab', 30], ['Exam Hall', 'hall', 120]] as [$name, $kind, $capacity]) {
            $rows[] = ['name' => $name, 'kind' => $kind, 'capacity' => $capacity, 'active' => 1, 'created_at' => $now, 'updated_at' => $now];
        }
        DB::table('rooms')->insert($rows);

        return array_column($rows, 'name');
    }

    /** @return array<int, array{id:int, name:string}> */
    private function employees(): array
    {
        // 24 teachers: subject j, group g (sections 6g..6g+5) -> index j*4+g.
        $male = ['Md. Abdul Karim', 'Md. Jahangir Alam', 'Rafiqul Islam', 'Anwar Hossain', 'Shamsul Haque', 'Golam Mostafa', 'Nurul Amin', 'Mizanur Rahman', 'Abul Kalam', 'Habibur Rahman', 'Kamrul Hasan', 'Mahbubur Rahman'];
        $female = ['Farida Yasmin', 'Rokeya Sultana', 'Shirin Akter', 'Nasrin Jahan', 'Salma Khatun', 'Rehana Parvin', 'Mahmuda Begum', 'Rubina Akter', 'Shahnaz Parvin', 'Lutfun Nahar', 'Ayesha Siddika', 'Taslima Nasrin'];
        $names = [];
        for ($i = 0; $i < 12; $i++) {
            $names[] = [$male[$i], 'Male'];
            $names[] = [$female[$i], 'Female'];
        }
        // index 10 = Mathematics, group 2 teaches Class 8 A: the demo teacher.
        $demo = 10;
        $taken = array_search('Md. Jahangir Alam', array_column($names, 0), true);
        [$names[$demo], $names[$taken]] = [$names[$taken], $names[$demo]];

        $out = [];
        $now = now();
        foreach ($names as $i => [$name, $gender]) {
            $username = $i === $demo ? 'jsmith' : 'teacher'.str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT);
            $id = $this->user($name, $username, 'teacher', $gender, $this->phone(), 'adult');
            $out[$i] = ['id' => $id, 'name' => $name];
            DB::table('staff_profiles')->insert([
                'user_id' => $id, 'employee_no' => 'EMP-'.str_pad((string) ($i + 1), 3, '0', STR_PAD_LEFT),
                'designation' => $i % 5 === 0 ? 'Senior Teacher' : 'Assistant Teacher', 'department' => self::SUBJECTS[intdiv($i, 4)],
                'joined_on' => (2008 + $i % 16).'-0'.(1 + $i % 9).'-15', 'address' => self::AREAS[$i % count(self::AREAS)],
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        $office = [
            ['accountant', 'Shafiqul Islam', 'Male', 'Accountant', 'Accounts'],
            ['headmaster', 'Dr. Mohammad Abdur Razzak', 'Male', 'Head Master', 'Administration'],
            ['librarian', 'Rawshan Ara Begum', 'Female', 'Librarian', 'Library'],
            ['office1', 'Mosharraf Hossain', 'Male', 'Office Assistant', 'Administration'],
            ['office2', 'Shirin Sultana', 'Female', 'Admission Officer', 'Administration'],
            ['office3', 'Delwar Hossain', 'Male', 'Office Assistant', 'Administration'],
        ];
        foreach ($office as $i => [$username, $name, $gender, $designation, $department]) {
            $id = $this->user($name, $username, 'staff', $gender, $this->phone(), 'adult');
            DB::table('staff_profiles')->insert([
                'user_id' => $id, 'employee_no' => 'EMP-'.str_pad((string) (101 + $i), 3, '0', STR_PAD_LEFT), 'designation' => $designation,
                'department' => $department, 'joined_on' => (2010 + $i).'-03-01', 'address' => self::AREAS[$i], 'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        $this->user('Super Admin', 'superadmin', 'admin', 'Male', '01700000000', 'adult');

        return $out;
    }

    private function assignments(array $teachers): void
    {
        $subjects = DB::table('academic_subjects')->orderBy('id')->pluck('id', 'name');
        $now = now();
        $rows = [];
        foreach ($this->sections() as $s => [$class, $section]) {
            foreach (self::SUBJECTS as $j => $subject) {
                $rows[] = [
                    'user_id' => $teachers[$j * 4 + intdiv($s, 6)]['id'], 'class_name' => $class, 'section' => $section,
                    'subject_id' => $subjects[$subject], 'created_at' => $now, 'updated_at' => $now,
                ];
            }
        }
        DB::table('teaching_assignments')->insert($rows);
    }

    private function routine(array $teachers, array $rooms): void
    {
        $slots = [[1, '1st Period', '08:00 – 08:45'], [2, '2nd Period', '08:45 – 09:30'], [3, '3rd Period', '09:30 – 10:15'], [4, 'Break', '10:15 – 10:45'], [5, '4th Period', '10:45 – 11:30'], [6, '5th Period', '11:30 – 12:15'], [7, '6th Period', '12:15 – 13:00']];
        $rows = [];
        foreach ($this->sections() as $s => [$class, $section]) {
            foreach (range(0, 4) as $day) {
                $lesson = 0;
                foreach ($slots as [$sort, $label, $time]) {
                    $break = $sort === 4;
                    $j = ($s + $day + $lesson) % 6;
                    $rows[] = [
                        'class_name' => $class, 'section' => $section, 'day' => $day, 'sort' => $sort, 'label' => $label, 'time_range' => $time,
                        'is_break' => $break, 'subject' => $break ? null : self::SUBJECTS[$j], 'teacher' => $break ? null : $teachers[$j * 4 + intdiv($s, 6)]['name'],
                        'room' => $break ? null : $rooms[$s],
                    ];
                    if (! $break) {
                        $lesson++;
                    }
                }
            }
        }
        foreach (array_chunk($rows, 200) as $chunk) {
            DB::table('class_periods')->insert($chunk);
        }
    }

    /** @return list<array{0:string,1:string}> */
    private function sections(): array
    {
        $out = [];
        for ($c = 1; $c <= 10; $c++) {
            foreach (['A', 'B'] as $section) {
                $out[] = ["Class $c", $section];
            }
        }

        return $out;
    }

    /** @return list<array{id:int, class:string, section:string, roll:int, gender:string}> */
    private function students(): array
    {
        $now = now();
        $students = [];
        $families = [];
        $admission = 0;
        $demoRoll = min(12, $this->perSection);

        foreach ($this->sections() as [$class, $section]) {
            $number = (int) substr($class, 6);
            for ($roll = 1; $roll <= $this->perSection; $roll++) {
                $admission++;
                $gender = mt_rand(0, 1) ? 'Male' : 'Female';
                $hindu = mt_rand(1, 10) === 1;
                $demo = ($class === 'Class 8' && $section === 'A' && $roll === 1) ? 'student' : (($class === 'Class 5' && $section === 'B' && $roll === $demoRoll) ? 'nusrat' : null);

                // Roughly one in eight children joins a family already in school (a sibling in another class).
                $family = null;
                if ($demo === null && mt_rand(1, 8) === 1) {
                    foreach ($families as $k => $f) {
                        if ($f['count'] === 1 && $f['class'] !== $class && ! $f['demo']) {
                            $family = $k;
                            break;
                        }
                    }
                }
                if ($family === null) {
                    $surname = $hindu ? $this->pick(self::HINDU_SURNAMES) : $this->pick(self::SURNAMES);
                    $fatherName = $hindu ? $this->pick(self::HINDU_MALE) : 'Md. '.$this->pick(['Abdul', 'Nurul', 'Shahid', 'Kamal', 'Jashim', 'Rafiq', 'Anisur', 'Mostafa', 'Habib', 'Delwar', 'Sirajul', 'Alamgir']);
                    $fatherName .= ' '.$surname;
                    $families[] = ['father' => $fatherName, 'surname' => $surname, 'hindu' => $hindu, 'phone' => $this->phone(), 'area' => $this->pick(self::AREAS), 'count' => 0, 'class' => $class, 'parent_id' => null, 'demo' => $demo !== null];
                    $family = array_key_last($families);
                }
                $f = &$families[$family];
                $hindu = $f['hindu'];
                $first = $hindu ? $this->pick($gender === 'Male' ? self::HINDU_MALE : self::HINDU_FEMALE) : $this->pick($gender === 'Male' ? self::MALE : self::FEMALE);
                $name = $first.' '.$f['surname'];
                if ($demo === 'student') {
                    $name = 'Tanvir Ahmed';
                    $gender = 'Male';
                }
                if ($demo === 'nusrat') {
                    $name = 'Nusrat Jahan';
                    $gender = 'Female';
                }

                $username = $demo ?? 's'.str_pad((string) $admission, 5, '0', STR_PAD_LEFT);
                $id = $this->user($name, $username, 'student', $gender, $this->phone(), 'child');
                $age = 5 + $number;
                $dob = sprintf('%d-%02d-%02d', 2026 - $age - (mt_rand(0, 1)), mt_rand(1, 12), mt_rand(1, 28));

                DB::table('student_profiles')->insert([
                    'user_id' => $id, 'admission_no' => sprintf('2026-%05d', $admission), 'class_name' => $class, 'section' => $section, 'roll_no' => (string) $roll,
                    'session' => '2026', 'gender' => $gender, 'date_of_birth' => $dob, 'shift' => 'Morning', 'guardian_name' => $f['father'], 'guardian_mobile' => $f['phone'],
                    'status' => 'active', 'religion' => $hindu ? 'Hinduism' : 'Islam', 'address' => 'House '.mt_rand(1, 80).', Road '.mt_rand(1, 20).', '.$f['area'],
                    'attendance_present' => 0, 'attendance_working' => 0, 'dues_total' => 0, 'dues_count' => 0, 'created_at' => $now, 'updated_at' => $now,
                ]);

                if ($f['parent_id'] === null) {
                    $parentUser = $demo === 'student' ? 'parent' : 'p'.str_pad((string) $admission, 5, '0', STR_PAD_LEFT);
                    $parentName = $demo === 'student' ? 'Md. Kamal Uddin Ahmed' : $f['father'];
                    $f['parent_id'] = $this->user($parentName, $parentUser, 'parent', 'Male', $f['phone'], 'adult');
                }
                $f['count']++;
                DB::table('guardian_student')->insert(['guardian_id' => $f['parent_id'], 'student_id' => $id]);
                unset($f);

                $students[] = ['id' => $id, 'class' => $class, 'section' => $section, 'roll' => $roll, 'gender' => $gender, 'demo' => $demo];
            }
        }

        return $students;
    }

    private function user(string $name, string $username, string $role, string $gender, string $phone, string $kind): int
    {
        $photo = null;
        if ($this->avatars && $role !== 'admin') {
            $photo = "photos/seed/{$username}.svg";
            Storage::disk('public')->put($photo, $this->avatar($name, $gender, $kind));
        }

        return DB::table('users')->insertGetId([
            'name' => $name, 'username' => $username, 'email' => $username.'@borno.test', 'role' => $role, 'phone' => $phone,
            'password' => $this->hash, 'is_active' => 1, 'photo' => $photo, 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    private function phone(): string
    {
        do {
            $number = $this->pick(self::PHONE_PREFIX).str_pad((string) mt_rand(0, 99999999), 8, '0', STR_PAD_LEFT);
        } while (isset($this->used[$number]));
        $this->used[$number] = true;

        return $number;
    }

    private function pick(array $list): string
    {
        return $list[mt_rand(0, count($list) - 1)];
    }

    private function avatar(string $name, string $gender, string $kind): string
    {
        $skins = ['#f1c9a5', '#e0ac82', '#c68642', '#a9744f', '#8d5524'];
        $hairs = ['#1c1209', '#2b1b10', '#3a2416', '#0f0f0f'];
        $bgs = ['#dbeafe', '#fce7f3', '#dcfce7', '#fef3c7', '#e0e7ff', '#ffedd5', '#ccfbf1'];
        $shirts = ['#2563eb', '#b31299', '#16a34a', '#d97706', '#4f46e5', '#0d9488', '#dc2626', '#475569'];
        $seed = crc32($name.$gender);
        $skin = $skins[$seed % 5];
        $hair = $hairs[($seed >> 3) % 4];
        $bg = $bgs[($seed >> 5) % 7];
        $shirt = $kind === 'child' ? $shirts[($seed >> 7) % 8] : '#334155';
        $female = $gender === 'Female';
        $scale = $kind === 'child' ? 0.92 : 1.0;

        $hairShape = $female
            ? "<path d='M62 108c-4-52 22-70 38-70s42 18 38 70c-6-24-18-34-38-34s-32 10-38 34z' fill='{$hair}'/><rect x='56' y='100' width='14' height='60' rx='7' fill='{$hair}'/><rect x='130' y='100' width='14' height='60' rx='7' fill='{$hair}'/>"
            : "<path d='M68 98c-6-40 14-56 32-56s38 16 32 56c-4-14-12-22-32-22S72 84 68 98z' fill='{$hair}'/>";
        $cap = ($kind === 'adult' && ! $female && ($seed % 4 === 0)) ? "<path d='M72 82c2-18 14-26 28-26s26 8 28 26z' fill='#f8fafc'/><rect x='70' y='80' width='60' height='6' rx='3' fill='#e2e8f0'/>" : '';
        $glasses = ($kind === 'adult' && ($seed % 3 === 0)) ? "<g fill='none' stroke='#1e293b' stroke-width='2.5'><circle cx='86' cy='108' r='9'/><circle cx='114' cy='108' r='9'/><path d='M95 108h10'/></g>" : '';

        return "<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 200 200' width='400' height='400'><rect width='200' height='200' fill='{$bg}'/>"
            ."<g transform='translate(100 100) scale({$scale}) translate(-100 -100)'>"
            ."<path d='M30 200c4-44 32-62 70-62s66 18 70 62z' fill='{$shirt}'/><path d='M84 138h32l-16 20z' fill='#fff' opacity='.85'/>"
            ."<rect x='88' y='118' width='24' height='26' rx='9' fill='{$skin}'/><ellipse cx='100' cy='104' rx='30' ry='34' fill='{$skin}'/>"
            ."{$hairShape}{$cap}<circle cx='87' cy='106' r='3.2' fill='#1e293b'/><circle cx='113' cy='106' r='3.2' fill='#1e293b'/>"
            ."<path d='M90 122q10 8 20 0' fill='none' stroke='#7f1d1d' stroke-width='3' stroke-linecap='round'/>{$glasses}</g></svg>";
    }

    // ---------------------------------------------------------------- activity

    private function attendance(array $students): void
    {
        $days = [];
        $date = today()->subDay();
        while (count($days) < 30) {
            if (! in_array($date->dayOfWeek, [5, 6], true)) {
                $days[] = $date->toDateString();
            }
            $date = $date->subDay();
        }

        $rows = [];
        $totals = [];
        foreach ($students as $st) {
            $habit = mt_rand(0, 99);
            $present = 0;
            foreach ($days as $day) {
                $r = mt_rand(0, 99);
                $status = $habit < 8 ? ($r < 70 ? 'present' : ($r < 90 ? 'absent' : 'late')) : ($r < 90 ? 'present' : ($r < 94 ? 'late' : ($r < 98 ? 'absent' : 'leave')));
                $present += in_array($status, ['present', 'late'], true) ? 1 : 0;
                $rows[] = ['user_id' => $st['id'], 'date' => $day, 'status' => $status];
            }
            $totals[$st['id']] = $present;
        }
        foreach (array_chunk($rows, 400) as $chunk) {
            DB::table('attendance_days')->insert($chunk);
        }
        foreach ($totals as $id => $present) {
            DB::table('student_profiles')->where('user_id', $id)->update(['attendance_present' => $present, 'attendance_working' => count($days)]);
        }
    }

    private function fees(array $students): void
    {
        $months = [['August', '2026-08-10', 0.97], ['September', '2026-09-10', 0.88], ['October', '2026-10-10', 0.58]];
        $methods = ['Cash', 'Cash', 'bKash', 'bKash', 'Nagad', 'Card'];
        $dues = [];
        $inv = 0;
        $pay = 0;
        $invoices = [];
        $payments = [];

        foreach ($students as $st) {
            $fee = 700 + 100 * (int) substr($st['class'], 6);
            foreach ($months as [$month, $due, $rate]) {
                $inv++;
                $paid = mt_rand(0, 999) / 1000 < $rate;
                $partial = ! $paid && mt_rand(1, 6) === 1;
                $amount = $partial ? (int) ($fee / 2) : ($paid ? $fee : 0);
                $invoices[] = [
                    'user_id' => $st['id'], 'invoice_no' => 'INV-'.str_pad((string) $inv, 6, '0', STR_PAD_LEFT), 'label' => "Tuition Fee – $month 2026",
                    'due_on' => $due, 'payable' => $fee, 'paid' => $amount, 'status' => $paid ? 'paid' : ($partial ? 'partial' : 'unpaid'), 'late_fee' => 0,
                ];
                if ($amount > 0) {
                    $pay++;
                    $payments[] = [
                        'user_id' => $st['id'], 'receipt_no' => 'RCPT-'.str_pad((string) $pay, 6, '0', STR_PAD_LEFT),
                        'paid_on' => date('Y-m-d', strtotime($due) - mt_rand(0, 8) * 86400), 'method' => $this->pick($methods), 'amount' => $amount, 'late_fee' => 0, 'status' => 'posted',
                    ];
                }
                if ($amount < $fee) {
                    $dues[$st['id']] = [($dues[$st['id']][0] ?? 0) + $fee - $amount, ($dues[$st['id']][1] ?? 0) + 1];
                }
            }
        }
        foreach (array_chunk($invoices, 300) as $chunk) {
            DB::table('fee_invoices')->insert($chunk);
        }
        foreach (array_chunk($payments, 300) as $chunk) {
            DB::table('fee_payments')->insert($chunk);
        }
        foreach ($dues as $id => [$total, $count]) {
            DB::table('student_profiles')->where('user_id', $id)->update(['dues_total' => $total, 'dues_count' => $count, 'dues_due_date' => '2026-11-10']);
        }
    }

    private function exam(array $students, array $rooms): void
    {
        $now = now();
        $half = DB::table('exams')->insertGetId(['name' => 'Half Yearly Examination 2026', 'starts_on' => '2026-06-15', 'ends_on' => '2026-06-25', 'created_at' => $now, 'updated_at' => $now]);
        $annual = DB::table('exams')->insertGetId(['name' => 'Annual Examination 2026', 'starts_on' => '2026-12-06', 'ends_on' => '2026-12-17', 'created_at' => $now, 'updated_at' => $now]);
        $subjects = DB::table('academic_subjects')->orderBy('id')->get(['id', 'name']);
        Grading::flush();

        $schedule = [];
        foreach ($this->sections() as $s => [$class, $section]) {
            if ($section !== 'A') {
                continue;
            }
            foreach ($subjects as $k => $subject) {
                $schedule[] = ['exam_id' => $half, 'class_name' => $class, 'subject_id' => $subject->id, 'exam_date' => date('Y-m-d', strtotime('2026-06-15') + ($k * 2 + intdiv($s, 8)) * 86400), 'starts_at' => '10:00', 'full_marks' => 100, 'pass_marks' => 33, 'room' => 'Exam Hall', 'created_at' => $now, 'updated_at' => $now];
                $schedule[] = ['exam_id' => $annual, 'class_name' => $class, 'subject_id' => $subject->id, 'exam_date' => date('Y-m-d', strtotime('2026-12-06') + ($k * 2) * 86400), 'starts_at' => '10:00', 'full_marks' => 100, 'pass_marks' => 33, 'room' => 'Exam Hall', 'created_at' => $now, 'updated_at' => $now];
            }
        }
        // Each class sits in the hall on its own days, so give each class its own date offset.
        foreach ($schedule as $i => $row) {
            $classNo = (int) substr($row['class_name'], 6);
            $schedule[$i]['exam_date'] = date('Y-m-d', strtotime($row['exam_date']) + (($classNo - 1) % 2) * 86400);
            $schedule[$i]['room'] = $rooms[$classNo - 1];
        }
        foreach (array_chunk($schedule, 100) as $chunk) {
            DB::table('exam_subjects')->insert($chunk);
        }
        foreach ($this->classNames() as $class) {
            DB::table('exam_publications')->insert(['exam_id' => $half, 'class_name' => $class, 'published_at' => '2026-07-05 10:00:00']);
        }

        $rows = [];
        $totals = [];
        foreach ($students as $st) {
            $ability = 45 + mt_rand(0, 45);
            if ($st['demo'] === 'student') {
                $ability = 86;
            }
            $points = [];
            $sum = 0;
            foreach ($subjects as $subject) {
                $marks = max(12, min(100, $ability + mt_rand(-14, 12)));
                $g = Grading::subject($marks, 100, 33, false);
                $points[] = $g;
                $sum += $marks;
                $rows[] = ['user_id' => $st['id'], 'exam' => 'Half Yearly Examination 2026', 'subject' => $subject->name, 'marks' => $marks, 'full_marks' => 100, 'letter_grade' => $g['grade'], 'failed' => $g['failed'], 'exam_id' => $half, 'subject_id' => $subject->id, 'absent' => 0, 'grade_point' => $g['point']];
            }
            $overall = Grading::overall($points);
            $totals[] = ['id' => $st['id'], 'class' => $st['class'], 'section' => $st['section'], 'sum' => $sum, 'gpa' => $overall['gpa'], 'failed' => $overall['failed']];
        }
        foreach (array_chunk($rows, 300) as $chunk) {
            DB::table('exam_results')->insert($chunk);
        }

        $byClass = collect($totals)->groupBy('class');
        foreach ($byClass as $list) {
            $ranked = $list->sortBy([['gpa', 'desc'], ['sum', 'desc']])->values();
            foreach ($ranked as $i => $row) {
                $sectionRank = $ranked->where('section', $row['section'])->search(fn ($x) => $x['id'] === $row['id']) + 1;
                DB::table('student_profiles')->where('user_id', $row['id'])->update([
                    'gpa' => $row['gpa'], 'exam_name' => 'Half Yearly Examination 2026', 'class_position' => $i + 1, 'section_position' => $sectionRank,
                    'promotion_status' => $row['failed'] ? 'Retain' : 'Pass',
                ]);
            }
        }
    }

    /** @return list<string> */
    private function classNames(): array
    {
        return array_map(fn ($c) => "Class $c", range(1, 10));
    }

    private function homework(array $students, array $teachers): void
    {
        $topics = [['Bangla', 'রচনা: আমার প্রিয় ঋতু', 0], ['English', 'Write a paragraph on "My School"', 1], ['Mathematics', 'Exercise 4.2, problems 1–10', 2], ['Science', 'Draw and label the water cycle', 3]];
        $rows = [];
        foreach ($students as $i => $st) {
            if ($i % 2 === 1) {
                continue;
            }
            $secIndex = ((int) substr($st['class'], 6) - 1) * 2 + ($st['section'] === 'B' ? 1 : 0);
            foreach (array_slice($topics, $i % 3, 2) as [$subject, $title, $j]) {
                $submitted = $st['demo'] === null && mt_rand(1, 3) > 1;
                $rows[] = [
                    'user_id' => $st['id'], 'title' => $title, 'subject' => $subject, 'teacher' => $teachers[$j * 4 + intdiv($secIndex, 6)]['name'], 'due_on' => today()->addDays(mt_rand(-5, 6))->toDateString(),
                    'status' => $submitted ? 'submitted' : 'todo', 'instructions' => 'Complete in your notebook and submit on time.', 'submitted_at' => $submitted ? now()->subDays(2) : null,
                    'created_at' => now(), 'updated_at' => now(),
                ];
            }
        }
        foreach (array_chunk($rows, 300) as $chunk) {
            DB::table('homework_items')->insert($chunk);
        }
    }

    private function library(array $students): void
    {
        $now = now();
        $books = [
            ['পথের পাঁচালী', 'বিভূতিভূষণ বন্দ্যোপাধ্যায়', 2], ['অপরাজিত', 'বিভূতিভূষণ বন্দ্যোপাধ্যায়', 2], ['দেবদাস', 'শরৎচন্দ্র চট্টোপাধ্যায়', 2], ['লালসালু', 'সৈয়দ ওয়ালীউল্লাহ', 2],
            ['আমার ছেলেবেলা', 'শেখ মুজিবুর রহমান', 2], ['অসমাপ্ত আত্মজীবনী', 'শেখ মুজিবুর রহমান', 2], ['কাবুলিওয়ালা', 'রবীন্দ্রনাথ ঠাকুর', 2], ['গীতাঞ্জলি', 'রবীন্দ্রনাথ ঠাকুর', 2],
            ['বিদ্রোহী ও অন্যান্য কবিতা', 'কাজী নজরুল ইসলাম', 2], ['হাজার বছর ধরে', 'জহির রায়হান', 2], ['দুরন্ত', 'হুমায়ূন আহমেদ', 2], ['মিসির আলি আছেন?', 'হুমায়ূন আহমেদ', 2],
            ['দেয়াল', 'হুমায়ূন আহমেদ', 2], ['তোতোচান', 'তেৎসুকো কুরোয়ানাগি', 2], ['ওয়ান্ডার', 'আর. জে. পালাসিও', 2], ['Wings of Fire', 'A. P. J. Abdul Kalam', 2],
            ['Mathematics Class 8', 'NCTB', 1], ['English for Today Class 8', 'NCTB', 1], ['General Science Class 8', 'NCTB', 1], ['Bangla Sahitya Class 8', 'NCTB', 1], ['Bangladesh & Global Studies Class 8', 'NCTB', 1],
            ['Mathematics Class 5', 'NCTB', 1], ['English for Today Class 5', 'NCTB', 1], ['Primary Science Class 5', 'NCTB', 1], ['Mathematics Class 10', 'NCTB', 1], ['Physics Class 10', 'NCTB', 3],
            ['Chemistry Class 10', 'NCTB', 3], ['Biology Class 10', 'NCTB', 3], ['The Story of the Universe', 'Dr. Muhammed Zafar Iqbal', 3], ['বিজ্ঞানের বিস্ময়', 'মুহম্মদ জাফর ইকবাল', 3],
        ];
        $rows = [];
        foreach ($books as $i => [$title, $author, $category]) {
            $rows[] = ['title' => $title, 'author' => $author, 'isbn' => '978-984-'.str_pad((string) (1000 + $i), 5, '0').'-0', 'category_id' => $category, 'copies' => 3 + $i % 4, 'shelf' => chr(65 + $i % 6).'-'.(1 + $i % 5), 'created_at' => $now, 'updated_at' => $now];
        }
        DB::table('library_books')->insert($rows);
        $bookIds = DB::table('library_books')->orderBy('id')->get(['id', 'title', 'author']);

        $members = [];
        $loans = [];
        foreach ($students as $i => $st) {
            if ($i % 5 !== 0) {
                continue;
            }
            $card = 'LIB-'.str_pad((string) (1000 + $i), 4, '0', STR_PAD_LEFT);
            $members[] = ['user_id' => $st['id'], 'card_no' => $card, 'active' => 1, 'created_at' => $now, 'updated_at' => $now];
            if ($i % 10 === 0) {
                $book = $bookIds[$i % $bookIds->count()];
                $issued = today()->subDays(mt_rand(2, 30));
                $due = $issued->copy()->addDays(14);
                $state = $i % 30 === 0 ? 'returned' : ($due->isPast() ? 'overdue' : 'on loan');
                $loans[] = [
                    'user_id' => $st['id'], 'title' => $book->title, 'author' => $book->author, 'card_no' => $card, 'member_since' => '2026-01-10', 'issued_on' => $issued->toDateString(),
                    'due_on' => $due->toDateString(), 'returned_on' => $state === 'returned' ? $issued->copy()->addDays(9)->toDateString() : null, 'fine' => $state === 'overdue' ? $due->diffInDays(today()) * 2 : 0,
                    'status' => $state, 'book_id' => $book->id, 'fine_paid' => 0,
                ];
            }
        }
        DB::table('library_members')->insert($members);
        DB::table('library_loans')->insert($loans);
    }

    private function transport(array $students): void
    {
        $now = now();
        $lines = [
            'Mirpur Line' => [1200, ['Mirpur-10', 'Near Mirpur-10 Metro Station', '06:50'], ['Kazipara', 'Near Kazipara Metro Station', '07:05'], ['Shewrapara', 'Shewrapara Bus Stand', '07:15']],
            'Uttara Line' => [1400, ['Uttara Sector 7', 'Sector 7 Park', '06:40'], ['Airport', 'Airport Road Crossing', '06:55'], ['Khilkhet', 'Khilkhet Bazar', '07:10']],
            'Dhanmondi Line' => [1300, ['Dhanmondi 27', 'Near Star Kabab', '07:00'], ['Shankar', 'Shankar Bus Stop', '07:08'], ['Kalabagan', 'Kalabagan Playground', '07:15']],
            'Mohammadpur Line' => [1100, ['Mohammadpur Bus Stand', 'Town Hall Market', '07:00'], ['Asad Gate', 'Asad Gate Crossing', '07:10'], ['Shyamoli', 'Shyamoli Square', '07:18']],
        ];
        $drivers = [['Karim Uddin', '01819000011'], ['Abdul Jabbar', '01819000012'], ['Rafiqul Islam', '01819000013'], ['Shahidul Haque', '01819000014']];
        $stops = [];
        $n = 0;
        foreach ($lines as $name => $line) {
            $fare = array_shift($line);
            $routeId = DB::table('transport_routes')->insertGetId(['name' => $name, 'fare' => $fare, 'active' => 1, 'created_at' => $now, 'updated_at' => $now]);
            $vehicleId = DB::table('transport_vehicles')->insertGetId(['name' => 'Bus 0'.($n + 1), 'plate' => 'DHAKA METRO BA '.(11 + $n).'-'.(3000 + 417 * $n), 'capacity' => 45, 'driver_name' => $drivers[$n][0], 'driver_phone' => $drivers[$n][1], 'route_id' => $routeId, 'active' => 1, 'created_at' => $now, 'updated_at' => $now]);
            foreach ($line as $k => [$stop, $landmark, $pickup]) {
                $stopId = DB::table('transport_stops')->insertGetId(['route_id' => $routeId, 'name' => $stop, 'landmark' => $landmark, 'pickup_time' => $pickup, 'drop_time' => '14:'.(30 + $k * 8), 'sort_order' => $k + 1, 'created_at' => $now, 'updated_at' => $now]);
                $stops[] = [$routeId, $stopId, $vehicleId];
            }
            $n++;
        }
        $rows = [];
        foreach ($students as $i => $st) {
            if ($st['demo'] === 'student' || ($st['demo'] === null && $i % 4 === 0)) {
                [$routeId, $stopId, $vehicleId] = $stops[$i % count($stops)];
                $rows[] = ['user_id' => $st['id'], 'route' => '', 'stop' => '', 'route_id' => $routeId, 'stop_id' => $stopId, 'vehicle_id' => $vehicleId, 'since' => 'January 2026'];
            }
        }
        DB::table('bus_assignments')->insert($rows);
        TransportSync::refresh();
    }

    private function campus(array $students): void
    {
        $now = now();
        DB::table('school_notices')->insert([
            ['title' => 'Admission for the next academic session is open', 'body' => 'Online admission for the 2027 academic session is now open. Apply from the Admission page.', 'published_on' => today()->subDays(10)->toDateString(), 'expires_on' => today()->addDays(60)->toDateString()],
            ['title' => 'Annual Examination routine published', 'body' => 'The Annual Examination begins on 6 December 2026. Check your admit card in the portal.', 'published_on' => today()->subDays(3)->toDateString(), 'expires_on' => today()->addDays(50)->toDateString()],
            ['title' => 'Victory Day programme', 'body' => 'Students are requested to attend in school uniform on 16 December for the Victory Day programme.', 'published_on' => today()->toDateString(), 'expires_on' => '2026-12-17'],
        ]);
        DB::table('calendar_events')->insert([
            ['date' => today()->addDays(4)->toDateString(), 'title' => 'Parents meeting', 'type' => 'meeting'],
            ['date' => today()->addDays(9)->toDateString(), 'title' => 'Sports day', 'type' => 'event'],
            ['date' => '2026-12-06', 'title' => 'Annual Examination begins', 'type' => 'exam'],
            ['date' => '2026-12-16', 'title' => 'Victory Day (holiday)', 'type' => 'holiday'],
        ]);

        $demo = collect($students)->firstWhere('demo', 'student');
        $leave = [[$demo['id'], 'Casual Leave', today()->addDays(5), 1, 'Family programme in the village', 'pending']];
        foreach (array_slice($students, 40, 6) as $k => $st) {
            $leave[] = [$st['id'], $k % 2 ? 'Sick Leave' : 'Casual Leave', today()->subDays($k + 2), 2, $k % 2 ? 'Fever and cold' : 'Wedding of a cousin', $k < 3 ? 'approved' : 'pending'];
        }
        foreach ($leave as [$id, $type, $start, $days, $reason, $status]) {
            DB::table('leave_requests')->insert(['user_id' => $id, 'leave_type' => $type, 'start_date' => $start->toDateString(), 'end_date' => $start->copy()->addDays($days - 1)->toDateString(), 'days' => $days, 'reason' => $reason, 'status' => $status, 'created_at' => $now, 'updated_at' => $now]);
        }

        $applicants = [['Shafin', 'Ahmed', 'Male', 'Class 6', 'Md. Alamgir Hossain', '01711223344'], ['Tasnia', 'Rahman', 'Female', 'Class 3', 'Abdur Rahman', '01811334455'], ['Arnab', 'Das', 'Male', 'Class 9', 'Tapan Kumar Das', '01911445566'], ['Maisha', 'Khan', 'Female', 'Class 1', 'Rashed Khan', '01611556677']];
        foreach ($applicants as $k => [$first, $last, $gender, $class, $guardian, $mobile]) {
            DB::table('admission_applications')->insert([
                'tracking_no' => 'ADM-2027-'.str_pad((string) ($k + 1), 4, '0', STR_PAD_LEFT), 'class_name' => $class, 'first_name' => $first, 'last_name' => $last, 'date_of_birth' => '2016-0'.($k + 2).'-1'.$k,
                'gender' => $gender, 'religion' => $k === 2 ? 'Hinduism' : 'Islam', 'address' => $this->pick(self::AREAS), 'guardian_name' => $guardian, 'relation' => 'Father', 'mobile' => $mobile,
                'status' => $k === 0 ? 'approved' : 'pending', 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
        $messages = [['Rashed Karim', 'rashed@example.com', '01711000111', 'আমার ছেলেকে Class 4-এ ভর্তি করতে চাই। কী কী কাগজপত্র লাগবে?'], ['Sultana Razia', 'razia@example.com', '01811000222', 'Is school transport available from Uttara Sector 10?'], ['Anwar Hossain', 'anwar@example.com', '01911000333', 'Please share the Annual Examination routine for Class 7.']];
        foreach ($messages as [$name, $email, $phone, $message]) {
            DB::table('contact_messages')->insert(['name' => $name, 'email' => $email, 'phone' => $phone, 'message' => $message, 'status' => 'new', 'created_at' => $now, 'updated_at' => $now]);
        }

        $admin = DB::table('users')->where('username', 'superadmin')->value('id');
        foreach (array_slice($students, 3, 4) as $k => $st) {
            DB::table('student_notes')->insert([
                'user_id' => $st['id'], 'kind' => $k % 2 ? 'commendation' : 'violation', 'severity' => $k % 2 ? 'low' : 'medium',
                'title' => $k % 2 ? 'Won the inter-class quiz' : 'Late to class repeatedly', 'details' => $k % 2 ? 'Represented the section and won first place.' : 'Arrived after the assembly three times this month.',
                'action_taken' => $k % 2 ? null : 'Guardian informed by phone', 'occurred_on' => today()->subDays(4 + $k)->toDateString(), 'visible_to_parent' => 1, 'created_by' => $admin, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }

    private function website(): void
    {
        $now = now();
        DB::table('testimonials')->insert([
            ['name' => 'Rokeya Begum', 'role' => 'Parent, Class 6', 'quote' => 'আমার মেয়ে এই স্কুলে এসে পড়াশোনায় অনেক আগ্রহী হয়েছে। শিক্ষকরা খুবই আন্তরিক।', 'rating' => 5, 'published' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Md. Habibur Rahman', 'role' => 'Parent, Class 9', 'quote' => 'The online portal keeps me updated about my son’s attendance and results every week.', 'rating' => 5, 'published' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Farzana Yasmin', 'role' => 'Former student', 'quote' => 'Borno School gave me the confidence to pursue science. I am forever grateful to my teachers.', 'rating' => 5, 'published' => 1, 'created_at' => $now, 'updated_at' => $now],
        ]);
        DB::table('website_events')->insert([
            ['title' => 'Annual Sports Day', 'venue' => 'School Field', 'starts_on' => today()->addDays(9)->toDateString(), 'body' => 'Races, relay and cultural performances for all classes.', 'published' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['title' => 'Science Fair', 'venue' => 'Science Lab', 'starts_on' => today()->addDays(25)->toDateString(), 'body' => 'Students from Class 6–10 present their projects.', 'published' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['title' => 'Victory Day Programme', 'venue' => 'Assembly Hall', 'starts_on' => '2026-12-16', 'body' => 'Cultural programme and flag raising.', 'published' => 1, 'created_at' => $now, 'updated_at' => $now],
        ]);
        DB::table('faqs')->insert([
            ['question' => 'How can I apply for admission?', 'answer' => 'Use the Online Admission page, fill in the form and note your tracking number.', 'sort_order' => 1, 'published' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['question' => 'Is school transport available?', 'answer' => 'Yes. We run four bus lines across Dhaka. Ask the office for stops and fares.', 'sort_order' => 2, 'published' => 1, 'created_at' => $now, 'updated_at' => $now],
            ['question' => 'How do I pay fees?', 'answer' => 'Pay at the accounts office, or online by bKash, Nagad or card from the parent portal.', 'sort_order' => 3, 'published' => 1, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }
}
