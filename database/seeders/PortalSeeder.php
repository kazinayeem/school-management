<?php

namespace Database\Seeders;

use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class PortalSeeder extends Seeder
{
    public function run(): void
    {
        $student = User::where('username', 'student')->first();
        $parent = User::where('username', 'parent')->first();
        $nusrat = User::where('username', 'nusrat')->first();

        if ($student === null || $parent === null || $nusrat === null) {
            return;
        }

        $student->update(['name' => 'Ethan Parker', 'phone' => '01711000001']);
        $parent->update(['name' => 'David Parker', 'phone' => '01711000002']);
        $nusrat->update(['name' => 'Nusrat Jahan', 'phone' => '01711000003']);

        $this->profile($student, 'Class 8', 'A', '1', 0, 4, 2, 2000, 2, 'Retain', '2026-06-10');
        $this->profile($nusrat, 'Class 5', 'B', '12', 3.92, 11, 4, 800, 1);

        DB::table('guardian_student')->where('guardian_id', $parent->id)->delete();
        DB::table('guardian_student')->updateOrInsert(
            ['guardian_id' => $parent->id, 'student_id' => $student->id],
            [],
        );

        $this->routine('Class 8', 'A', 'Room 201');
        $this->routine('Class 5', 'B', 'Room 105');
        $this->attendance($student);
        $this->attendance($nusrat);
        $this->homework($student);
        $this->homework($nusrat);
        $this->results($student->id, [
            ['Bangla', 78, 'A'],
            ['English', 81, 'A'],
            ['Mathematics', 92, 'A+'],
            ['Science', 85, 'A'],
            ['Bangladesh & Global Studies', 74, 'A-'],
            ['Religion & Moral Education', 88, 'A'],
            ['ICT', 90, 'A+'],
        ]);
        $this->results($nusrat->id, [
            ['Bangla', 70, 'A-'],
            ['English', 66, 'B'],
            ['Mathematics', 80, 'A'],
            ['Science', 72, 'A-'],
        ]);
        $this->fees($student->id, 1500, 500, true);
        $this->fees($nusrat->id, 800, 0);
        $this->library($student->id, 'LIB-1044', true);
        $this->library($nusrat->id, 'LIB-2210');

        DB::table('bus_assignments')->updateOrInsert(['user_id' => $student->id], [
            'route' => 'Mirpur Line',
            'vehicle' => 'Bus 02',
            'stop' => 'Kazipara',
            'landmark' => 'Near Kazipara Metro Station',
            'pickup_time' => '07:15',
            'drop_time' => '14:30',
            'driver' => 'Karim Uddin',
            'driver_phone' => '01819000011',
            'fare' => 1200,
            'since' => 'January 2026',
        ]);

        $this->document($student->id, 'ID Card', 'ID-8A-001');
        $this->document($student->id, 'Testimonial', 'TST-2026-014');
        $this->document($nusrat->id, 'ID Card', 'ID-5B-012');

        DB::table('leave_requests')->whereIn('user_id', [$student->id, $nusrat->id])->delete();
        DB::table('leave_requests')->insert([
            'user_id' => $student->id,
            'leave_type' => 'Casual Leave',
            'start_date' => '2026-07-14',
            'end_date' => '2026-07-14',
            'days' => 1,
            'reason' => 'Family event',
            'status' => 'cancelled',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('calendar_events')->delete();
        DB::table('calendar_events')->insert([
            ['date' => now()->addDays(3)->toDateString(), 'title' => 'Half yearly practical', 'type' => 'exam'],
            ['date' => now()->addDays(8)->toDateString(), 'title' => 'School holiday', 'type' => 'holiday'],
            ['date' => now()->addDays(12)->toDateString(), 'title' => 'Annual sports', 'type' => 'event'],
            ['date' => now()->toDateString(), 'title' => 'Club meeting', 'type' => 'meeting'],
        ]);

        DB::table('school_notices')->delete();
        DB::table('school_notices')->insert([
            [
                'title' => 'Admission for the upcoming academic session is open',
                'body' => 'Online admission for the upcoming academic session is now open. Apply from the Admission page.',
                'published_on' => '2026-07-28',
                'expires_on' => '2026-10-26',
            ],
        ]);
    }

    private function profile(User $user, string $class, string $section, string $roll, float $gpa, int $classPos, int $sectionPos, int $dues, int $count, string $promotion = 'Pass', string $dueDate = '2026-11-10'): void
    {
        StudentProfile::updateOrCreate(['user_id' => $user->id], [
            'class_name' => $class,
            'section' => $section,
            'roll_no' => $roll,
            'session' => '2026',
            'attendance_present' => 131,
            'attendance_working' => 203,
            'gpa' => $gpa,
            'exam_name' => 'Half Yearly Examination 2026',
            'class_position' => $classPos,
            'section_position' => $sectionPos,
            'promotion_status' => $promotion,
            'dues_total' => $dues,
            'dues_count' => $count,
            'dues_due_date' => $dueDate,
        ]);
    }

    private function routine(string $class, string $section, string $room): void
    {
        DB::table('class_periods')->where('class_name', $class)->where('section', $section)->delete();

        $subjects = [
            ['Bangla', 'Rebecca Lawson'],
            ['English', 'Alisha Henry'],
            ['Mathematics', 'John Smith'],
            ['Science', 'Owen Gallagher'],
            ['Bangladesh & Global Studies', 'Rosalind Meyer'],
            ['Religion & Moral Education', 'Harold Jennings'],
            ['ICT', 'Naomi Pearce'],
        ];
        $slots = [
            [1, '1st Period', '08:00 – 08:45', false],
            [2, '2nd Period', '08:45 – 09:30', false],
            [3, '3rd Period', '09:30 – 10:15', false],
            [4, 'Break', '10:15 – 10:45', true],
            [5, '4th Period', '10:45 – 11:30', false],
            [6, '5th Period', '11:30 – 12:15', false],
            [7, '6th Period', '12:15 – 13:00', false],
        ];

        $rows = [];
        foreach (range(0, 4) as $day) {
            $lesson = 0;
            foreach ($slots as [$sort, $label, $time, $break]) {
                $subject = $break ? [null, null] : $subjects[($day + $lesson) % count($subjects)];
                if (! $break) {
                    $lesson++;
                }
                $rows[] = [
                    'class_name' => $class,
                    'section' => $section,
                    'day' => $day,
                    'sort' => $sort,
                    'label' => $label,
                    'time_range' => $time,
                    'is_break' => $break,
                    'subject' => $subject[0],
                    'teacher' => $subject[1],
                    'room' => $break ? null : $room,
                ];
            }
        }

        DB::table('class_periods')->insert($rows);
    }

    private function attendance(User $user): void
    {
        DB::table('attendance_days')->where('user_id', $user->id)->delete();
        $rows = [];
        $index = 0;
        $date = now()->startOfMonth();

        while ($date->lt(today())) {
            if (! in_array($date->dayOfWeek, [Carbon::FRIDAY, Carbon::SATURDAY], true)) {
                $status = match ($index % 11) {
                    0 => 'late',
                    1 => 'absent',
                    7 => 'leave',
                    default => 'present',
                };
                $rows[] = [
                    'user_id' => $user->id,
                    'date' => $date->toDateString(),
                    'status' => $status,
                ];
                $index++;
            }
            $date->addDay();
        }

        if ($rows !== []) {
            DB::table('attendance_days')->insert($rows);
        }
    }

    private function homework(User $user): void
    {
        DB::table('homework_items')->where('user_id', $user->id)->delete();
        DB::table('homework_items')->insert([
            [
                'user_id' => $user->id,
                'title' => 'Chapter 6 revision worksheet',
                'subject' => 'English',
                'teacher' => 'Alisha Henry',
                'due_on' => '2026-08-06',
                'status' => 'todo',
                'instructions' => 'Complete the revision worksheet and bring it to class.',
                'note' => null,
                'remarks' => null,
                'submitted_at' => null,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'user_id' => $user->id,
                'title' => 'Essay: A rainy day in Dhaka',
                'subject' => 'Bangladesh & Global Studies',
                'teacher' => 'Rosalind Meyer',
                'due_on' => '2026-08-02',
                'status' => 'submitted',
                'instructions' => 'Write at least 250 words about a rainy day in Dhaka.',
                'note' => 'Handed in late.',
                'remarks' => null,
                'submitted_at' => '2026-08-03 09:20:00',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'user_id' => $user->id,
                'title' => 'Exercise 4B — Questions 1 to 10',
                'subject' => 'Bangla',
                'teacher' => 'Rebecca Lawson',
                'due_on' => '2026-07-29',
                'status' => 'marked',
                'instructions' => 'Answer questions 1 to 10 in your exercise book.',
                'note' => 'Done late.',
                'remarks' => 'Good work',
                'submitted_at' => '2026-07-30 10:05:00',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);
    }

    private function results(int $userId, array $subjects): void
    {
        DB::table('exam_results')->where('user_id', $userId)->delete();
        DB::table('exam_results')->insert(array_map(fn ($row) => [
            'user_id' => $userId,
            'exam' => 'Half Yearly Examination 2026',
            'subject' => $row[0],
            'marks' => $row[1],
            'full_marks' => 100,
            'letter_grade' => $row[2],
            'failed' => false,
        ], $subjects));
    }

    private function fees(int $userId, int $tuition, int $exam, bool $targetDemo = false): void
    {
        DB::table('fee_invoices')->where('user_id', $userId)->delete();
        DB::table('fee_payments')->where('user_id', $userId)->delete();

        if ($targetDemo) {
            DB::table('fee_invoices')->insert([
                [
                    'user_id' => $userId,
                    'invoice_no' => 'INV-2026-000011',
                    'label' => 'July 2026',
                    'due_on' => '2026-07-10',
                    'payable' => 1900,
                    'paid' => 0,
                    'status' => 'unpaid',
                ],
                [
                    'user_id' => $userId,
                    'invoice_no' => 'INV-2026-000001',
                    'label' => 'June 2026',
                    'due_on' => '2026-06-10',
                    'payable' => 1900,
                    'paid' => 1800,
                    'status' => 'partial',
                ],
            ]);
            DB::table('fee_payments')->insert([
                'user_id' => $userId,
                'receipt_no' => 'RCP-2026-000001',
                'paid_on' => '2026-07-30',
                'method' => 'Cash',
                'amount' => 1900,
                'late_fee' => 0,
                'status' => 'paid',
            ]);

            return;
        }

        $rows = [[
            'user_id' => $userId,
            'invoice_no' => 'INV-'.$userId.'-01',
            'label' => 'Tuition fee',
            'due_on' => '2026-11-10',
            'payable' => $tuition,
            'paid' => 0,
            'status' => 'unpaid',
        ]];

        if ($exam > 0) {
            $rows[] = [
                'user_id' => $userId,
                'invoice_no' => 'INV-'.$userId.'-02',
                'label' => 'Exam fee',
                'due_on' => '2026-11-10',
                'payable' => $exam,
                'paid' => 0,
                'status' => 'unpaid',
            ];
        }

        DB::table('fee_invoices')->insert($rows);
        DB::table('fee_payments')->insert([
            'user_id' => $userId,
            'receipt_no' => 'RCP-'.$userId.'-09',
            'paid_on' => '2026-09-12',
            'method' => 'Cash',
            'amount' => 1500,
            'late_fee' => 0,
            'status' => 'posted',
        ]);
    }

    private function library(int $userId, string $card, bool $targetDemo = false): void
    {
        DB::table('library_loans')->where('user_id', $userId)->delete();

        if ($targetDemo) {
            DB::table('library_loans')->insert([
                [
                    'user_id' => $userId,
                    'title' => 'Ikigai',
                    'author' => 'Héctor García',
                    'card_no' => $card,
                    'member_since' => '2026-01-12',
                    'issued_on' => '2026-07-20',
                    'due_on' => '2026-08-03',
                    'returned_on' => null,
                    'fine' => 50,
                    'status' => 'overdue',
                ],
                [
                    'user_id' => $userId,
                    'title' => 'English Grammar in Use',
                    'author' => 'Raymond Murphy',
                    'card_no' => $card,
                    'member_since' => '2026-01-12',
                    'issued_on' => '2026-06-01',
                    'due_on' => '2026-06-15',
                    'returned_on' => '2026-06-14',
                    'fine' => 0,
                    'status' => 'returned',
                ],
            ]);

            return;
        }

        DB::table('library_loans')->insert([
            [
                'user_id' => $userId,
                'title' => 'A Brief History of Time',
                'author' => 'Stephen Hawking',
                'card_no' => $card,
                'member_since' => '2026-01-12',
                'issued_on' => now()->subDays(10)->toDateString(),
                'due_on' => now()->addDays(4)->toDateString(),
                'returned_on' => null,
                'fine' => 0,
                'status' => 'on loan',
            ],
            [
                'user_id' => $userId,
                'title' => 'English Grammar in Use',
                'author' => 'Raymond Murphy',
                'card_no' => $card,
                'member_since' => '2026-01-12',
                'issued_on' => '2026-09-01',
                'due_on' => '2026-09-15',
                'returned_on' => '2026-09-14',
                'fine' => 0,
                'status' => 'returned',
            ],
        ]);
    }

    private function document(int $userId, string $type, string $serial): void
    {
        DB::table('student_documents')->updateOrInsert(
            ['user_id' => $userId, 'serial_no' => $serial],
            [
                'type' => $type,
                'session' => '2026',
                'issue_date' => '2026-01-15',
            ],
        );
    }
}
