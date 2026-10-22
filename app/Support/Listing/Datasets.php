<?php

namespace App\Support\Listing;

use App\Support\Permissions;
use App\Support\TeacherScope;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class Datasets
{
    /** @return array<string, Dataset> */
    public static function all(): array
    {
        return collect([new StudentsDataset, new StaffDataset, new ParentsDataset, new UsersDataset, new AdmissionsDataset])->keyBy(fn (Dataset $d) => $d->key())->all();
    }

    public static function find(string $key): ?Dataset
    {
        return self::all()[$key] ?? null;
    }
}

class StudentsDataset extends Dataset
{
    public function key(): string { return 'students'; }

    public function title(): string { return 'Students'; }

    public function columns(): array
    {
        return ['name' => 'Student', 'admission_no' => 'Admission no', 'class' => 'Class', 'section' => 'Section', 'roll' => 'Roll', 'gender' => 'Gender', 'dob' => 'Date of birth', 'religion' => 'Religion', 'shift' => 'Shift', 'guardian' => 'Guardian', 'mobile' => 'Guardian mobile', 'address' => 'Address', 'status' => 'Status'];
    }

    public function sorts(): array
    {
        return ['admission_no' => 'student_profiles.admission_no', 'name' => 'users.name', 'class' => 'student_profiles.class_name', 'roll' => 'student_profiles.roll_no', 'status' => 'student_profiles.status'];
    }

    public function authorize(Request $request): bool
    {
        return $request->user() !== null && Permissions::pathAllowed($request->user()->role, '/app/students');
    }

    public function query(Request $request): Builder
    {
        $status = (string) $request->input('status', 'active');
        $session = (string) $request->input('session', '2026');
        $class = (string) $request->input('class', '');
        $section = (string) $request->input('section', '');
        $q = trim((string) ($request->input('q', '')));

        $query = DB::table('users')->join('student_profiles', 'student_profiles.user_id', '=', 'users.id')->where('users.role', 'student')
            ->select('users.id', 'users.name', 'users.photo', 'student_profiles.admission_no', 'student_profiles.class_name', 'student_profiles.section', 'student_profiles.roll_no', 'student_profiles.gender',
                'student_profiles.date_of_birth', 'student_profiles.religion', 'student_profiles.shift', 'student_profiles.guardian_name', 'student_profiles.guardian_mobile', 'student_profiles.address', 'student_profiles.status');
        if ($status !== 'all') {
            $query->where('student_profiles.status', $status === '' ? 'active' : $status);
        }
        if ($session !== '' && $session !== 'all') {
            $query->where('student_profiles.session', $session);
        }
        $class !== '' && $query->where('student_profiles.class_name', $class);
        in_array($section, ['A', 'B'], true) && $query->where('student_profiles.section', $section);
        $q !== '' && $query->where(fn ($w) => $w->where('users.name', 'like', "%{$q}%")->orWhere('student_profiles.admission_no', 'like', "%{$q}%")->orWhere('student_profiles.guardian_mobile', 'like', "%{$q}%"));
        $request->user() && TeacherScope::restrict($query, $request->user());

        return $query;
    }

    public function map(object $r): array
    {
        return ['name' => $r->name, 'admission_no' => $r->admission_no, 'class' => $r->class_name, 'section' => $r->section, 'roll' => $r->roll_no, 'gender' => $r->gender, 'dob' => $r->date_of_birth,
            'religion' => $r->religion, 'shift' => $r->shift, 'guardian' => $r->guardian_name, 'mobile' => $r->guardian_mobile, 'address' => $r->address, 'status' => ucfirst((string) $r->status)];
    }

    public function describe(Request $request): string
    {
        return collect(['class' => $request->input('class'), 'section' => $request->input('section'), 'status' => $request->input('status', 'active'), 'search' => $request->input('q')])
            ->filter(fn ($v) => filled($v) && $v !== 'all')->map(fn ($v, $k) => ucfirst($k).': '.$v)->implode('  ·  ');
    }
}

class StaffDataset extends Dataset
{
    public function key(): string { return 'staff'; }

    public function title(): string { return 'Teachers & staff'; }

    public function columns(): array
    {
        return ['name' => 'Name', 'employee_no' => 'Employee no', 'type' => 'Account type', 'designation' => 'Designation', 'department' => 'Department', 'phone' => 'Phone', 'email' => 'Email', 'joined_on' => 'Joined', 'status' => 'Status'];
    }

    public function sorts(): array
    {
        return ['name' => 'users.name', 'employee_no' => 'staff_profiles.employee_no', 'designation' => 'staff_profiles.designation', 'type' => 'users.role'];
    }

    public function authorize(Request $request): bool
    {
        return $request->user() !== null && Permissions::pathAllowed($request->user()->role, '/app/staff');
    }

    public function query(Request $request): Builder
    {
        $search = trim((string) $request->input('search', ''));
        $status = (string) $request->input('status', '');
        $type = in_array($request->input('type'), ['teacher', 'staff'], true) ? (string) $request->input('type') : '';

        return DB::table('users')->leftJoin('staff_profiles', 'staff_profiles.user_id', '=', 'users.id')->whereIn('users.role', ['teacher', 'staff'])
            ->when($type !== '', fn ($q) => $q->where('users.role', $type))
            ->when($status === 'active', fn ($q) => $q->where('users.is_active', true))->when($status === 'inactive', fn ($q) => $q->where('users.is_active', false))
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q->where('users.name', 'like', "%{$search}%")->orWhere('users.username', 'like', "%{$search}%")->orWhere('staff_profiles.employee_no', 'like', "%{$search}%")->orWhere('staff_profiles.designation', 'like', "%{$search}%")))
            ->select('users.id', 'users.name', 'users.username', 'users.email', 'users.phone', 'users.is_active', 'users.role', 'users.photo', 'staff_profiles.employee_no', 'staff_profiles.designation', 'staff_profiles.department', 'staff_profiles.joined_on');
    }

    public function map(object $r): array
    {
        return ['name' => $r->name, 'employee_no' => $r->employee_no, 'type' => $r->role === 'teacher' ? 'Teacher' : 'Staff', 'designation' => $r->designation, 'department' => $r->department, 'phone' => $r->phone, 'email' => $r->email, 'joined_on' => $r->joined_on, 'status' => $r->is_active ? 'Active' : 'Inactive'];
    }

    public function describe(Request $request): string
    {
        return collect(['type' => $request->input('type'), 'status' => $request->input('status'), 'search' => $request->input('search')])->filter()->map(fn ($v, $k) => ucfirst($k).': '.$v)->implode('  ·  ');
    }
}

class ParentsDataset extends Dataset
{
    public function key(): string { return 'parents'; }

    public function title(): string { return 'Parent accounts'; }

    public function columns(): array
    {
        return ['name' => 'Parent', 'username' => 'Username', 'phone' => 'Phone', 'email' => 'Email', 'children' => 'Children', 'status' => 'Status'];
    }

    public function sorts(): array
    {
        return ['name' => 'users.name', 'username' => 'users.username', 'phone' => 'users.phone'];
    }

    public function authorize(Request $request): bool
    {
        return $request->user() !== null && Permissions::pathAllowed($request->user()->role, '/app/system/parents');
    }

    public function query(Request $request): Builder
    {
        $search = trim((string) $request->input('search', ''));

        return DB::table('users')->where('users.role', 'parent')
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q->where('users.name', 'like', "%{$search}%")->orWhere('users.username', 'like', "%{$search}%")->orWhere('users.phone', 'like', "%{$search}%")))
            ->select('users.id', 'users.name', 'users.username', 'users.email', 'users.phone', 'users.is_active');
    }

    public function map(object $r): array
    {
        $children = DB::table('guardian_student')->join('users', 'users.id', '=', 'guardian_student.student_id')->where('guardian_id', $r->id)->orderBy('users.name')->pluck('users.name')->implode(', ');

        return ['name' => $r->name, 'username' => $r->username, 'phone' => $r->phone, 'email' => $r->email, 'children' => $children, 'status' => $r->is_active ? 'Active' : 'Inactive'];
    }

    public function describe(Request $request): string
    {
        return filled($request->input('search')) ? 'Search: '.$request->input('search') : '';
    }
}

class UsersDataset extends Dataset
{
    public function key(): string { return 'users'; }

    public function title(): string { return 'User accounts'; }

    public function columns(): array
    {
        return ['name' => 'Name', 'username' => 'Username', 'email' => 'Email', 'role' => 'Role', 'status' => 'Status'];
    }

    public function sorts(): array
    {
        return ['role' => 'users.role', 'name' => 'users.name', 'username' => 'users.username'];
    }

    public function authorize(Request $request): bool
    {
        return $request->user()?->role === 'admin';
    }

    public function query(Request $request): Builder
    {
        $role = (string) $request->input('role', '');
        $status = (string) $request->input('status', '');
        $search = trim((string) $request->input('search', ''));

        return DB::table('users')
            ->when(in_array($role, ['admin', 'teacher', 'staff', 'student', 'parent'], true), fn ($q) => $q->where('users.role', $role))
            ->when($status === 'active', fn ($q) => $q->where('users.is_active', true))->when($status === 'inactive', fn ($q) => $q->where('users.is_active', false))
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q->where('users.name', 'like', "%{$search}%")->orWhere('users.username', 'like', "%{$search}%")->orWhere('users.email', 'like', "%{$search}%")))
            ->select('users.id', 'users.name', 'users.username', 'users.email', 'users.role', 'users.is_active');
    }

    public function map(object $r): array
    {
        return ['name' => $r->name, 'username' => $r->username, 'email' => $r->email, 'role' => ucfirst($r->role), 'status' => $r->is_active ? 'Active' : 'Inactive'];
    }

    public function describe(Request $request): string
    {
        return collect(['role' => $request->input('role'), 'status' => $request->input('status'), 'search' => $request->input('search')])->filter()->map(fn ($v, $k) => ucfirst($k).': '.$v)->implode('  ·  ');
    }
}

class AdmissionsDataset extends Dataset
{
    public function key(): string { return 'admissions'; }

    public function title(): string { return 'Admission applications'; }

    public function idColumn(): string { return 'admission_applications.id'; }

    public function defaultDir(): string { return 'desc'; }

    public function columns(): array
    {
        return ['tracking_no' => 'Tracking no', 'name' => 'Applicant', 'class' => 'Class', 'gender' => 'Gender', 'dob' => 'Date of birth', 'guardian' => 'Guardian', 'mobile' => 'Mobile', 'email' => 'Email', 'status' => 'Status', 'applied' => 'Applied on'];
    }

    public function sorts(): array
    {
        return ['applied' => 'admission_applications.id', 'name' => 'admission_applications.first_name', 'class' => 'admission_applications.class_name', 'status' => 'admission_applications.status'];
    }

    public function authorize(Request $request): bool
    {
        return $request->user() !== null && Permissions::pathAllowed($request->user()->role, '/app/admissions');
    }

    public function query(Request $request): Builder
    {
        $status = (string) $request->input('status', '');
        $search = trim((string) $request->input('search', ''));

        return DB::table('admission_applications')
            ->when(in_array($status, ['received', 'under_review', 'admitted', 'rejected'], true), fn ($q) => $q->where('status', $status))
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q->where('first_name', 'like', "%{$search}%")->orWhere('last_name', 'like', "%{$search}%")->orWhere('tracking_no', 'like', "%{$search}%")->orWhere('mobile', 'like', "%{$search}%")))
            ->select('admission_applications.*');
    }

    public function map(object $r): array
    {
        return ['tracking_no' => $r->tracking_no, 'name' => trim($r->first_name.' '.$r->last_name), 'class' => $r->class_name, 'gender' => $r->gender, 'dob' => $r->date_of_birth, 'guardian' => $r->guardian_name, 'mobile' => $r->mobile, 'email' => $r->email, 'status' => ucfirst(str_replace('_', ' ', $r->status)), 'applied' => substr((string) $r->created_at, 0, 10)];
    }

    public function describe(Request $request): string
    {
        return collect(['status' => $request->input('status'), 'search' => $request->input('search')])->filter()->map(fn ($v, $k) => ucfirst($k).': '.$v)->implode('  ·  ');
    }
}
