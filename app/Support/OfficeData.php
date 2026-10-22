<?php

namespace App\Support;

use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Http\Request;
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
            $actions = [
                ['label' => 'New admission', 'note' => 'Enroll a student', 'href' => '/app/students/new'],
                ['label' => 'Mark attendance', 'note' => "Open today's register", 'href' => '/app/attendance'],
                ['label' => 'Collect fee', 'note' => 'Record a payment', 'href' => '/app/fees'],
                ['label' => 'Publish notice', 'note' => 'Notify the school community', 'href' => '/app/notices'],
            ];
        }

        $pulse = self::pulse();

        return [
            'firstName' => explode(' ', $user->name)[0],
            'roleLabel' => Permissions::label($user->role),
            'session' => '2026',
            'today' => now()->format('l, F j, Y'),
            'metrics' => [
                ['label' => 'Active students', 'value' => (string) $pulse['students'], 'note' => 'Currently enrolled', 'tone' => 'indigo', 'href' => '/app/students'],
                ['label' => 'Active staff', 'value' => (string) $pulse['staff'], 'note' => 'Teachers and employees', 'tone' => 'emerald', 'href' => $admin ? '/app/staff' : '/app'],
                ['label' => 'Attendance progress', 'value' => $pulse['progress'].'%', 'note' => $pulse['pendingCount'].' sections pending', 'tone' => 'sky', 'href' => '/app/attendance'],
                ['label' => 'Unpaid invoices', 'value' => (string) $pulse['unpaid'], 'note' => 'Unpaid or partially paid', 'tone' => 'amber', 'href' => $admin ? '/app/fees/dues' : '/app'],
            ],
            'snapshot' => array_values(array_filter([
                ['label' => 'Collected today', 'value' => 'Tk '.number_format((int) DB::table('fee_payments')->whereDate('paid_on', today())->sum('amount')), 'note' => 'Payments received today', 'href' => '/app/fees/payments'],
                $admin ? ['label' => 'Admission queue', 'value' => '3', 'note' => 'Applications awaiting review', 'href' => '/app/students/new'] : null,
                $admin ? ['label' => 'Website enquiries', 'value' => '0', 'note' => 'Unread messages', 'href' => '/app/website/messages'] : null,
                ['label' => 'Overdue books', 'value' => '5', 'note' => 'Loans past their due date', 'href' => '/app/library/fines'],
            ])),
            'actions' => $actions,
            'fees' => [
                'collected' => 'Tk '.number_format($pulse['collected']),
                'best' => 'Tk '.number_format($pulse['best']),
                'empty' => $pulse['collected'] === 0,
                'href' => $admin ? '/app/fees/payments' : '/app',
            ],
            'classes' => $pulse['classes'],
            'pendingCount' => $pulse['pendingCount'],
            'pending' => array_slice($pulse['pending'], 0, 5),
            'activity' => [
                ['actor' => 'Super Admin', 'action' => 'updated Setting', 'when' => '71 days ago'],
                ['actor' => 'Super Admin', 'action' => 'updated Setting', 'when' => '71 days ago'],
                ['actor' => 'Super Admin', 'action' => 'updated Setting', 'when' => '71 days ago'],
                ['actor' => 'Super Admin', 'action' => 'published Notice', 'when' => '86 days ago'],
                ['actor' => 'Super Admin', 'action' => 'enrolled Student', 'when' => '90 days ago'],
            ],
            'notices' => DB::table('school_notices')
                ->where(function ($query) {
                    $query->whereNull('expires_on')->orWhere('expires_on', '>=', now()->toDateString());
                })
                ->orderByDesc('published_on')
                ->limit(3)
                ->get()
                ->map(fn ($row) => [
                    'title' => $row->title,
                    'meta' => 'Published '.$row->published_on.($row->expires_on ? ' · Expires '.$row->expires_on : ''),
                ])
                ->all(),
        ];
    }

    public static function module(string $path, User $user, Request $request): array
    {
        if (preg_match('#/students/(\d+)$#', $path, $matches)) {
            return self::profile((int) $matches[1]);
        }

        if (str_ends_with($path, '/students/new')) {
            return self::admission();
        }

        if ($path === '/app/students' || str_contains($path, '/students/')) {
            return self::studentDirectory($request);
        }

        if ($path === '/app/attendance') {
            return self::register($user, $request);
        }

        if ($path === '/app/notices') {
            return self::noticeBoard($request);
        }

        if ($path === '/app/homework') {
            return self::homeworkBoard($request);
        }

        if ($path === '/app/leave' || $path === '/app/leave/applications') {
            return self::leaveBoard($user);
        }

        if (str_starts_with($path, '/app/fees')) {
            return self::feeBoard();
        }

        $title = OfficeMenu::find('admin', $path)['label']
            ?? OfficeMenu::find('staff', $path)['label']
            ?? 'Module';

        [$headers, $rows, $subtitle] = match (true) {
            str_starts_with($path, '/app/settings') || in_array($path, ['/app/sms/settings', '/app/system/audit', '/app/system/roles'], true) => [['Setting', 'Value'], [], 'Nothing has been configured here yet.'],
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

    private static function pulse(): array
    {
        $today = now()->toDateString();
        $students = DB::table('student_profiles')->where('status', 'active')->get(['user_id', 'class_name', 'section']);
        $marks = DB::table('attendance_days')->where('date', $today)->get(['user_id', 'status'])->keyBy('user_id');
        $byClass = [];
        $sections = [];

        foreach ($students as $student) {
            $byClass[$student->class_name]['total'] = ($byClass[$student->class_name]['total'] ?? 0) + 1;
            $byClass[$student->class_name]['present'] = ($byClass[$student->class_name]['present'] ?? 0) + ((($marks[$student->user_id]->status ?? null) === 'present') ? 1 : 0);
            $key = $student->class_name.'-'.$student->section;
            $sections[$key]['total'] = ($sections[$key]['total'] ?? 0) + 1;
            $sections[$key]['done'] = ($sections[$key]['done'] ?? 0) + (isset($marks[$student->user_id]) ? 1 : 0);
        }

        $classes = [];
        foreach (range(1, 10) as $class) {
            $name = "Class {$class}";
            $total = $byClass[$name]['total'] ?? 0;
            $present = $byClass[$name]['present'] ?? 0;
            $classes[] = ['name' => $name, 'percent' => $total === 0 ? 0 : (int) round($present / $total * 100)];
        }

        $pending = [];
        foreach ($sections as $name => $section) {
            if ($section['done'] < $section['total']) {
                $pending[] = $name;
            }
        }
        sort($pending, SORT_NATURAL);

        $sectionCount = max(count($sections), 1);
        $payments = DB::table('fee_payments')
            ->where('paid_on', '>=', now()->subDays(30)->toDateString())
            ->selectRaw('paid_on, sum(amount) as total')
            ->groupBy('paid_on')
            ->get();

        return [
            'students' => $students->count(),
            'staff' => User::query()->whereIn('role', Permissions::EMPLOYEE_ROLES)->where('is_active', true)->count(),
            'unpaid' => DB::table('fee_invoices')->whereIn('status', ['unpaid', 'partial'])->count(),
            'progress' => (int) round((count($sections) - count($pending)) / $sectionCount * 100),
            'pendingCount' => count($pending),
            'pending' => $pending,
            'classes' => $classes,
            'collected' => (int) $payments->sum('total'),
            'best' => (int) $payments->max('total'),
        ];
    }

    private static function studentDirectory(Request $request): array
    {
        $class = (string) $request->query('class', '');
        $section = (string) $request->query('section', '');
        $status = (string) $request->query('status', 'active');
        $session = (string) $request->query('session', '2026');
        $search = trim((string) $request->query('q', ''));
        $page = max(1, (int) $request->query('page', 1));

        $query = User::query()
            ->where('users.role', 'student')
            ->join('student_profiles', 'student_profiles.user_id', '=', 'users.id')
            ->select('users.*');

        if ($status !== 'all') {
            $query->where('student_profiles.status', $status === '' ? 'active' : $status);
        }
        if ($session !== '' && $session !== 'all') {
            $query->where('student_profiles.session', $session);
        }
        if ($class !== '') {
            $query->where('student_profiles.class_name', $class);
        }
        if ($section !== '') {
            $query->where('student_profiles.section', $section);
        }
        if ($search !== '') {
            $query->where(function ($inner) use ($search) {
                $inner->where('users.name', 'like', "%{$search}%")
                    ->orWhere('student_profiles.admission_no', 'like', "%{$search}%")
                    ->orWhere('student_profiles.guardian_mobile', 'like', "%{$search}%");
            });
        }

        if ($request->user() !== null) {
            TeacherScope::restrict($query, $request->user());
        }

        $total = (clone $query)->count();
        $rows = $query->with('profile')->orderBy('student_profiles.admission_no')->forPage($page, 50)->get();

        return [
            'kind' => 'students',
            'title' => 'Students',
            'subtitle' => 'Search, filter and manage the student body.',
            'canAdmit' => $request->user()?->role === 'admin',
            'filters' => ['q' => $search, 'session' => $session, 'class' => $class, 'section' => $section, 'status' => $status, 'page' => $page],
            'classes' => collect(range(1, 10))->map(fn ($n) => "Class {$n}")->all(),
            'total' => $total,
            'from' => $total === 0 ? 0 : (($page - 1) * 50) + 1,
            'to' => min($page * 50, $total),
            'lastPage' => max(1, (int) ceil($total / 50)),
            'rows' => $rows->map(function (User $user) {
                $profile = $user->profile;

                return [
                    'id' => $user->id,
                    'name' => $user->name,
                    'photo' => $user->photo_url,
                    'admissionNo' => $profile->admission_no ?? '—',
                    'class' => trim(str_replace('Class ', '', (string) $profile->class_name).'-'.$profile->section, '-'),
                    'roll' => $profile->roll_no ?? '—',
                    'gender' => $profile->gender ?? '—',
                    'dob' => $profile->date_of_birth?->format('d-m-Y') ?? '—',
                    'shift' => $profile->shift ?? '—',
                    'mobile' => $profile->guardian_mobile ?? '—',
                    'status' => ucfirst((string) ($profile->status ?? 'active')),
                ];
            })->all(),
        ];
    }

    private static function profile(int $id): array
    {
        $user = User::query()->where('role', 'student')->with('profile')->findOrFail($id);
        $profile = $user->profile;
        abort_if($profile === null, 404);

        return [
            'kind' => 'profile',
            'title' => 'Student Profile',
            'subtitle' => $profile->admission_no.' · Class '.trim(str_replace('Class ', '', (string) $profile->class_name).'-'.$profile->section).', Roll '.$profile->roll_no.' · Session '.$profile->session,
            'student' => [
                'name' => $user->name,
                'status' => ucfirst((string) $profile->status),
                'dob' => $profile->date_of_birth?->format('d-m-Y') ?? '—',
                'gender' => $profile->gender ?? '—',
                'religion' => $profile->religion ?? '—',
                'mobile' => $user->phone ?? '—',
                'email' => $user->email,
                'address' => $profile->address ?? '—',
                'username' => $user->username,
                'guardian' => $profile->guardian_name ?? '—',
                'guardianMobile' => $profile->guardian_mobile ?? '—',
                'enrollment' => $profile->session.' — Class '.trim(str_replace('Class ', '', (string) $profile->class_name).'-'.$profile->section).', Roll '.$profile->roll_no,
            ],
        ];
    }

    private static function admission(): array
    {
        return [
            'kind' => 'admission',
            'title' => 'New admission',
            'subtitle' => 'Enroll a student into the active session.',
            'classes' => collect(range(1, 10))->map(fn ($n) => "Class {$n}")->all(),
            'sections' => ['A', 'B'],
        ];
    }

    private static function register(User $user, Request $request): array
    {
        $sections = self::sectionOptions($user);
        $section = (string) $request->query('section', $sections[0] ?? '');
        if (! in_array($section, $sections, true)) {
            $section = $sections[0] ?? '';
        }
        $date = (string) $request->query('date', now()->toDateString());
        [$class, $letter] = array_pad(explode('-', str_replace('Class ', '', $section), 2), 2, '');
        $className = $class === '' ? '' : 'Class '.$class;

        $students = $section === '' ? collect() : User::query()
            ->where('role', 'student')
            ->whereHas('profile', fn ($query) => $query->where('class_name', $className)->where('section', $letter)->where('status', 'active'))
            ->with('profile')
            ->get()
            ->sortBy(fn (User $student) => (int) $student->profile->roll_no)
            ->values();

        $marks = DB::table('attendance_days')
            ->where('date', $date)
            ->whereIn('user_id', $students->pluck('id'))
            ->pluck('status', 'user_id');

        return [
            'kind' => 'attendance',
            'title' => 'Mark Attendance',
            'subtitle' => 'Pick your section and date — everyone starts Present, just flag the exceptions.',
            'sections' => $sections,
            'section' => $section,
            'date' => $date,
            'students' => $students->map(fn (User $student) => [
                'id' => $student->id,
                'roll' => $student->profile->roll_no,
                'name' => $student->name,
                'status' => $marks[$student->id] ?? 'present',
            ])->all(),
        ];
    }

    /**
     * The sections a user can take attendance for: every section for an admin, and for a
     * teacher the sections they have been assigned to teach (a blank section means both).
     *
     * @return list<string>
     */
    public static function sectionOptions(User $user): array
    {
        if ($user->role !== 'admin') {
            $sections = [];
            foreach (DB::table('teaching_assignments')->where('user_id', $user->id)->get(['class_name', 'section']) as $a) {
                foreach ($a->section ? [$a->section] : ['A', 'B'] as $letter) {
                    $sections[$a->class_name.'-'.$letter] = ((int) filter_var($a->class_name, FILTER_SANITIZE_NUMBER_INT)) * 10 + ($letter === 'B' ? 1 : 0);
                }
            }
            asort($sections);

            return array_keys($sections);
        }

        return StudentProfile::query()
            ->where('status', 'active')
            ->select('class_name', 'section')
            ->distinct()
            ->get()
            ->sortBy(fn ($row) => [((int) filter_var($row->class_name, FILTER_SANITIZE_NUMBER_INT)) * 10 + ($row->section === 'B' ? 1 : 0)])
            ->map(fn ($row) => $row->class_name.'-'.$row->section)
            ->values()
            ->all();
    }

    private static function noticeBoard(Request $request): array
    {
        $search = trim((string) $request->query('q', ''));
        $notices = DB::table('school_notices')
            ->when($search !== '', fn ($query) => $query->where('title', 'like', "%{$search}%"))
            ->orderByDesc('published_on')
            ->get()
            ->map(fn ($row) => [
                'id' => $row->id,
                'title' => $row->title,
                'body' => $row->body,
                'published' => $row->published_on,
                'expires' => $row->expires_on,
                'active' => $row->expires_on === null || $row->expires_on >= now()->toDateString(),
            ])
            ->all();

        return [
            'kind' => 'notices',
            'title' => 'Notices',
            'subtitle' => 'Announcements currently on the notice board.',
            'q' => $search,
            'notices' => $notices,
        ];
    }

    private static function homeworkBoard(Request $request): array
    {
        $search = trim((string) $request->query('q', ''));
        $rows = DB::table('homework_items')
            ->join('student_profiles', 'student_profiles.user_id', '=', 'homework_items.user_id')
            ->when($search !== '', fn ($query) => $query->where('homework_items.title', 'like', "%{$search}%"))
            ->orderByDesc('homework_items.due_on')
            ->limit(300)
            ->get(['homework_items.title', 'homework_items.subject', 'homework_items.teacher', 'homework_items.due_on', 'homework_items.status', 'student_profiles.class_name', 'student_profiles.section'])
            ->unique(fn ($row) => $row->title.'|'.$row->class_name.'|'.$row->section)
            ->take(40)
            ->values()
            ->map(fn ($row) => [
                'title' => $row->title,
                'teacher' => $row->teacher ?? '—',
                'class' => trim($row->class_name.' '.$row->section),
                'subject' => $row->subject,
                'due' => $row->due_on,
                'status' => $row->status,
            ])
            ->all();

        return [
            'kind' => 'homework',
            'title' => 'Homework',
            'subtitle' => 'Assignments published to a class section.',
            'q' => $search,
            'rows' => $rows,
            'classes' => collect(range(1, 10))->map(fn ($n) => "Class {$n}")->all(),
            'sections' => ['A', 'B'],
            'subjects' => ['Bangla', 'English', 'Mathematics', 'Science', 'Bangladesh & Global Studies', 'Religion & Moral Education', 'ICT'],
        ];
    }

    private static function leaveBoard(?User $viewer = null): array
    {
        $rows = DB::table('leave_requests')
            ->join('users', 'users.id', '=', 'leave_requests.user_id')
            ->when($viewer && TeacherScope::applies($viewer), function ($q) use ($viewer) {
                $q->join('student_profiles', 'student_profiles.user_id', '=', 'users.id');
                TeacherScope::restrict($q, $viewer);
            })
            ->orderByDesc('leave_requests.start_date')
            ->select('leave_requests.*', 'users.name')
            ->get()
            ->map(fn ($row) => [
                'id' => $row->id,
                'name' => $row->name,
                'type' => $row->leave_type,
                'dates' => $row->start_date.' – '.$row->end_date,
                'reason' => $row->reason,
                'status' => $row->status,
            ])
            ->all();

        return [
            'kind' => 'leave',
            'title' => 'Leave applications',
            'subtitle' => 'Approve or reject requests from students and staff.',
            'rows' => $rows,
        ];
    }

    private static function feeBoard(): array
    {
        $rows = DB::table('fee_invoices')
            ->join('users', 'users.id', '=', 'fee_invoices.user_id')
            ->leftJoin('student_profiles', 'student_profiles.user_id', '=', 'users.id')
            ->orderBy('fee_invoices.status')
            ->orderBy('users.name')
            ->limit(300)
            ->get()
            ->map(fn ($row) => [
                'id' => $row->id,
                'student' => $row->name,
                'admissionNo' => $row->admission_no,
                'class' => $row->class_name,
                'section' => $row->section,
                'label' => $row->label,
                'number' => $row->invoice_no,
                'payable' => (int) $row->payable,
                'paid' => (int) $row->paid,
                'lateFee' => FeePayments::lateFee($row),
                'status' => $row->status,
            ])
            ->all();

        return [
            'kind' => 'fees',
            'title' => 'Fee Collection',
            'subtitle' => 'Find a student, take the payment, print the receipt.',
            'collectedToday' => (int) DB::table('fee_payments')->whereDate('paid_on', today())->sum('amount'),
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
