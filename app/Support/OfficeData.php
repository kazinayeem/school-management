<?php

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\DB;

class OfficeData
{
    public static function home(User $user): array
    {
        $admin = $user->role === 'admin';

        $actions = [
            ['label' => 'Mark attendance', 'note' => "Open today's register", 'href' => '/app/attendance'],
            ['label' => 'Publish notice', 'note' => 'Notify the school community', 'href' => '/app/notices'],
        ];

        if ($admin) {
            array_unshift($actions, ['label' => 'New admission', 'note' => 'Enroll a student', 'href' => '/app/students/new']);
            $actions[] = ['label' => 'Collect fee', 'note' => 'Record a payment', 'href' => '/app/fees'];
        }

        $classes = [];
        foreach (range(1, 10) as $class) {
            $classes[] = ['name' => "Class {$class}", 'percent' => 0];
        }

        return [
            'firstName' => explode(' ', $user->name)[0],
            'roleLabel' => $admin ? 'Super Admin' : 'Teacher',
            'session' => '2026',
            'today' => now()->format('l, F j, Y'),
            'metrics' => [
                ['label' => 'Active students', 'value' => '190', 'note' => 'Currently enrolled', 'tone' => 'indigo', 'href' => '/app/students'],
                ['label' => 'Active staff', 'value' => '27', 'note' => 'Teachers and employees', 'tone' => 'emerald', 'href' => $admin ? '/app/staff' : '/app'],
                ['label' => 'Attendance progress', 'value' => '0%', 'note' => '20 sections pending', 'tone' => 'sky', 'href' => '/app/attendance'],
                ['label' => 'Unpaid invoices', 'value' => '104', 'note' => 'Unpaid or partially paid', 'tone' => 'amber', 'href' => $admin ? '/app/fees/dues' : '/app'],
            ],
            'snapshot' => array_values(array_filter([
                ['label' => 'Overdue books', 'value' => '5', 'note' => 'Loans past their due date', 'href' => '/app/library/fines'],
                $admin ? ['label' => 'Admission queue', 'value' => '3', 'note' => 'Applications awaiting review', 'href' => '/app/students/new'] : null,
            ])),
            'actions' => $actions,
            'fees' => ['collected' => 'Tk 0', 'best' => 'Tk 0', 'empty' => true, 'href' => $admin ? '/app/fees/payments' : '/app'],
            'classes' => $classes,
            'pendingCount' => 20,
            'pending' => ['Class 6-A', 'Class 6-B', 'Class 7-A', 'Class 7-B', 'Class 8-A'],
            'activity' => [
                ['actor' => 'Super Admin', 'action' => 'updated Setting', 'when' => '71 days ago'],
                ['actor' => 'Super Admin', 'action' => 'updated Setting', 'when' => '71 days ago'],
                ['actor' => 'Super Admin', 'action' => 'updated Setting', 'when' => '71 days ago'],
                ['actor' => 'Super Admin', 'action' => 'published Notice', 'when' => '86 days ago'],
                ['actor' => 'Super Admin', 'action' => 'enrolled Student', 'when' => '90 days ago'],
            ],
            'notices' => [
                ['title' => 'Admission for the upcoming academic session is open', 'meta' => 'Published 2026-07-28 · Expires 2026-10-26'],
                ...collect(DB::table('school_notices')->orderByDesc('published_on')->limit(3)->get())->map(fn ($row) => [
                    'title' => $row->title,
                    'meta' => 'Published '.$row->published_on,
                ])->all(),
            ],
        ];
    }

    public static function module(string $path): array
    {
        $title = OfficeMenu::find('admin', $path)['label']
            ?? OfficeMenu::find('staff', $path)['label']
            ?? 'Module';

        [$headers, $rows, $subtitle] = match (true) {
            str_contains($path, 'students') => self::students(),
            str_contains($path, 'staff') || str_contains($path, 'parents') => self::people($path),
            str_contains($path, 'attendance') => self::attendance(),
            str_contains($path, 'homework') => self::homework(),
            str_contains($path, 'exam') => self::results(),
            str_contains($path, 'fees') || str_contains($path, 'payroll') => self::fees(),
            str_contains($path, 'library') => self::library(),
            str_contains($path, 'transport') => self::transport(),
            str_contains($path, 'notice') || str_contains($path, 'sms') => self::notices(),
            str_contains($path, 'leave') => self::leaves(),
            str_contains($path, 'timetable') => self::timetable(),
            str_contains($path, 'academic') => self::academic($path),
            default => [['Name', 'Detail'], [], 'Nothing has been recorded here yet.'],
        };

        return [
            'title' => $title,
            'subtitle' => $subtitle,
            'headers' => $headers,
            'rows' => $rows,
        ];
    }

    private static function students(): array
    {
        $rows = User::query()->where('role', 'student')->with('profile')->orderBy('name')->get()->map(fn (User $user) => [
            $user->name,
            trim(($user->profile->class_name ?? '').' '.($user->profile->section ?? '')),
            $user->profile->roll_no ?? '—',
            $user->username,
        ])->all();

        return [['Student', 'Class', 'Roll', 'Username'], $rows, 'Search, filter and manage enrolled students.'];
    }

    private static function people(string $path): array
    {
        $role = str_contains($path, 'parents') ? 'parent' : 'staff';
        $rows = User::query()->where('role', $role)->orderBy('name')->get()->map(fn (User $user) => [
            $user->name,
            $user->username,
            $user->email,
            $role === 'staff' ? 'Teacher' : 'Parent',
        ])->all();

        return [['Name', 'Username', 'Email', 'Role'], $rows, 'Search, filter and manage all staff members.'];
    }

    private static function attendance(): array
    {
        $rows = collect(['Class 6-A', 'Class 6-B', 'Class 7-A', 'Class 7-B', 'Class 8-A'])->map(fn ($name) => [
            $name,
            'Pending',
            'Not marked',
        ])->all();

        return [['Section', 'Status', 'Register'], $rows, 'Sections that still need attention today.'];
    }

    private static function homework(): array
    {
        $rows = DB::table('homework_items')->orderBy('due_on')->limit(12)->get()->map(fn ($row) => [
            $row->title,
            $row->subject,
            $row->teacher ?? '—',
            $row->due_on,
            $row->status,
        ])->all();

        return [['Title', 'Subject', 'Teacher', 'Due', 'Status'], $rows, 'Assignments currently open for students.'];
    }

    private static function results(): array
    {
        $rows = DB::table('exam_results')
            ->join('users', 'users.id', '=', 'exam_results.user_id')
            ->orderBy('users.name')
            ->limit(20)
            ->get(['users.name', 'exam_results.subject', 'exam_results.marks', 'exam_results.letter_grade'])
            ->map(fn ($row) => [$row->name, $row->subject, (string) $row->marks, $row->letter_grade])
            ->all();

        return [['Student', 'Subject', 'Marks', 'Grade'], $rows, 'Half Yearly Examination 2026.'];
    }

    private static function fees(): array
    {
        $rows = DB::table('fee_invoices')
            ->join('users', 'users.id', '=', 'fee_invoices.user_id')
            ->orderBy('fee_invoices.due_on')
            ->get()
            ->map(fn ($row) => [$row->name, $row->label, $row->invoice_no, 'Tk '.number_format($row->payable), $row->status])
            ->all();

        return [['Student', 'Invoice', 'Number', 'Payable', 'Status'], $rows, 'Unpaid and partially paid invoices.'];
    }

    private static function library(): array
    {
        $rows = DB::table('library_loans')
            ->join('users', 'users.id', '=', 'library_loans.user_id')
            ->orderByDesc('library_loans.issued_on')
            ->get()
            ->map(fn ($row) => [$row->name, $row->title, $row->due_on, $row->status])
            ->all();

        return [['Student', 'Book', 'Due', 'Status'], $rows, 'Books currently on loan.'];
    }

    private static function transport(): array
    {
        $rows = DB::table('bus_assignments')
            ->join('users', 'users.id', '=', 'bus_assignments.user_id')
            ->get()
            ->map(fn ($row) => [$row->name, $row->route, $row->stop, $row->vehicle ?? '—'])
            ->all();

        return [['Student', 'Route', 'Stop', 'Vehicle'], $rows, 'Students assigned to a school bus.'];
    }

    private static function notices(): array
    {
        $rows = DB::table('school_notices')->orderByDesc('published_on')->get()->map(fn ($row) => [
            $row->title,
            $row->published_on,
            $row->body,
        ])->all();

        array_unshift($rows, ['Admission for the upcoming academic session is open', '2026-07-28', 'Expires 2026-10-26']);

        return [['Notice', 'Published', 'Detail'], $rows, 'Currently visible announcements.'];
    }

    private static function leaves(): array
    {
        $rows = DB::table('leave_requests')
            ->join('users', 'users.id', '=', 'leave_requests.user_id')
            ->orderByDesc('leave_requests.start_date')
            ->get()
            ->map(fn ($row) => [$row->name, $row->leave_type, $row->start_date.' – '.$row->end_date, $row->status])
            ->all();

        return [['Person', 'Type', 'Dates', 'Status'], $rows, 'Leave applications waiting for a decision.'];
    }

    private static function timetable(): array
    {
        $rows = DB::table('class_periods')
            ->where('is_break', false)
            ->where('day', 0)
            ->orderBy('sort')
            ->limit(8)
            ->get()
            ->map(fn ($row) => [$row->class_name.' '.$row->section, $row->label, $row->subject, $row->teacher])
            ->all();

        return [['Section', 'Period', 'Subject', 'Teacher'], $rows, "Sunday's published class routine."];
    }

    private static function academic(string $path): array
    {
        if (str_contains($path, 'classes') || str_contains($path, 'sections')) {
            $rows = collect(range(1, 10))->map(fn ($n) => ["Class {$n}", 'A, B', 'Session 2026'])->all();

            return [['Class', 'Sections', 'Session'], $rows, 'Classes and sections for the active session.'];
        }

        $rows = [
            ['Bangla', 'Compulsory'],
            ['English', 'Compulsory'],
            ['Mathematics', 'Compulsory'],
            ['Science', 'Compulsory'],
            ['Bangladesh & Global Studies', 'Compulsory'],
            ['Religion & Moral Education', 'Compulsory'],
            ['ICT', 'Compulsory'],
        ];

        return [['Subject', 'Type'], $rows, 'Subjects taught in the active session.'];
    }
}
