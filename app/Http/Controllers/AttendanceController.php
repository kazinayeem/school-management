<?php

namespace App\Http\Controllers;

use App\Support\Audit;
use App\Support\OfficeMenu;
use App\Support\SiteSettings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class AttendanceController extends Controller
{
    public function register(Request $request): Response
    {
        $role = $request->user()->role;
        abort_unless(in_array($role, ['admin', 'staff'], true) && OfficeMenu::allows($role, '/app/attendance/register'), 403);

        $classes = DB::table('academic_classes')->orderBy('sort_order')->pluck('name')->all();
        $class = in_array($request->query('class'), $classes, true) ? (string) $request->query('class') : ($classes[0] ?? 'Class 1');
        $section = in_array($request->query('section'), ['A', 'B'], true) ? (string) $request->query('section') : '';
        $from = $this->date($request->query('from'), today()->startOfMonth()->toDateString());
        $to = $this->date($request->query('to'), today()->toDateString());
        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        $min = SiteSettings::int('attendance_min_percent', 75);
        $students = DB::table('users')->join('student_profiles', 'student_profiles.user_id', '=', 'users.id')
            ->where('student_profiles.status', 'active')->where('student_profiles.class_name', $class)
            ->when($section !== '', fn ($q) => $q->where('student_profiles.section', $section))
            ->orderBy('student_profiles.section')->orderByRaw('cast(student_profiles.roll_no as integer)')
            ->get(['users.id', 'users.name', 'student_profiles.roll_no', 'student_profiles.section']);

        $days = DB::table('attendance_days')->whereIn('user_id', $students->pluck('id'))->whereBetween('date', [$from, $to])
            ->selectRaw('user_id, status, count(*) as total')->groupBy('user_id', 'status')->get()->groupBy('user_id');

        $below = $marked = 0;
        $percents = [];
        $rows = $students->map(function ($s) use ($days, $min, &$below, &$marked, &$percents) {
            $c = ($days[$s->id] ?? collect())->pluck('total', 'status');
            [$present, $late, $absent, $leave] = [(int) ($c['present'] ?? 0), (int) ($c['late'] ?? 0), (int) ($c['absent'] ?? 0), (int) ($c['leave'] ?? 0)];
            $counted = $present + $late + $absent;
            $percent = $counted > 0 ? (int) round(($present + $late) / $counted * 100) : null;
            $isBelow = $percent !== null && $percent < $min;
            $isBelow && $below++;
            $counted > 0 && $marked++;
            $percent !== null && $percents[] = $percent;

            return ['roll' => $s->roll_no, 'name' => $s->name, 'section' => $s->section, 'present' => $present, 'late' => $late, 'absent' => $absent, 'leave' => $leave,
                'percent' => $percent === null ? '—' : $percent.'%', 'flag' => $isBelow ? 'Below the minimum' : ''];
        })->all();

        return Inertia::render('office/report', [
            'navigation' => OfficeMenu::for($role),
            'roleLabel' => $role === 'admin' ? 'Super Admin' : 'Teacher',
            'title' => 'Register & Reports',
            'subtitle' => 'Attendance for a class over a period. Students under the minimum are flagged.',
            'filters' => [
                ['name' => 'class', 'label' => 'Class', 'type' => 'select', 'options' => array_map(fn ($c) => ['value' => $c, 'label' => $c], $classes)],
                ['name' => 'section', 'label' => 'Section', 'type' => 'select', 'options' => [['value' => '', 'label' => 'All sections'], ['value' => 'A', 'label' => 'A'], ['value' => 'B', 'label' => 'B']]],
                ['name' => 'from', 'label' => 'From', 'type' => 'date', 'with' => ['name' => 'to', 'label' => 'To', 'type' => 'date']],
            ],
            'values' => ['class' => $class, 'section' => $section, 'from' => $from, 'to' => $to],
            'sections' => [[
                'title' => null,
                'columns' => [
                    ['key' => 'roll', 'label' => 'Roll'], ['key' => 'name', 'label' => 'Student'], ['key' => 'section', 'label' => 'Section'], ['key' => 'present', 'label' => 'Present', 'num' => true],
                    ['key' => 'late', 'label' => 'Late', 'num' => true], ['key' => 'absent', 'label' => 'Absent', 'num' => true], ['key' => 'leave', 'label' => 'Leave', 'num' => true],
                    ['key' => 'percent', 'label' => 'Attendance', 'num' => true], ['key' => 'flag', 'label' => 'Flag'],
                ],
                'rows' => $rows,
            ]],
            'summary' => [
                ['label' => 'Students', 'value' => count($rows)],
                ['label' => 'Below the minimum', 'value' => $below],
                ['label' => 'Average attendance %', 'value' => $percents ? (int) round(array_sum($percents) / count($percents)) : 0],
                ['label' => 'Minimum attendance %', 'value' => $min],
            ],
            'actions' => $role === 'admin' ? [['label' => 'Mark from the punch log', 'url' => '/app/attendance/punches', 'data' => ['date' => $to], 'confirm' => 'Mark attendance for {date} from the punch log?']] : [],
        ]);
    }

    /**
     * Turn the day's first "in" punch of each student into present or late,
     * using the "late after" time from Settings → Attendance Rules. Marks a
     * teacher already entered are never overwritten.
     */
    public function syncPunches(Request $request): RedirectResponse
    {
        abort_unless($request->user()->role === 'admin', 403);
        $data = $request->validate(['date' => ['required', 'date']]);
        $lateAfter = SiteSettings::get('attendance_late_after', '08:15');

        $first = DB::table('punches')->join('users', 'users.id', '=', 'punches.user_id')->where('users.role', 'student')
            ->where('punches.direction', 'in')->whereDate('punches.punched_at', $data['date'])
            ->selectRaw('punches.user_id, min(punches.punched_at) as first_in')->groupBy('punches.user_id')->get();
        $existing = DB::table('attendance_days')->where('date', $data['date'])->whereIn('user_id', $first->pluck('user_id'))->pluck('user_id')->flip();

        $present = $late = $kept = 0;
        DB::transaction(function () use ($first, $existing, $data, $lateAfter, &$present, &$late, &$kept) {
            foreach ($first as $row) {
                if (isset($existing[$row->user_id])) {
                    $kept++;

                    continue;
                }
                $isLate = substr((string) $row->first_in, 11, 5) > $lateAfter;
                DB::table('attendance_days')->insert(['user_id' => $row->user_id, 'date' => $data['date'], 'status' => $isLate ? 'late' : 'present']);
                $isLate ? $late++ : $present++;
            }
        });
        Audit::log('other', 'Attendance', "Marked from punches for {$data['date']}: {$present} present, {$late} late");

        return back()->with('status', 'Attendance marked from the punch log.');
    }

    // ---- staff attendance

    public function staff(Request $request): Response
    {
        $this->adminOnly($request);
        $date = $this->date($request->query('date'), today()->toDateString());

        $staff = DB::table('users')->leftJoin('staff_profiles', 'staff_profiles.user_id', '=', 'users.id')->where('users.role', 'staff')->where('users.is_active', true)
            ->orderBy('users.name')->get(['users.id', 'users.name', 'staff_profiles.employee_no', 'staff_profiles.designation']);
        $existing = DB::table('staff_attendance')->where('date', $date)->get()->keyBy('user_id');
        $onLeave = DB::table('leave_requests')->where('status', 'approved')->where('start_date', '<=', $date)->where('end_date', '>=', $date)->pluck('user_id')->flip();

        $rows = $staff->map(fn ($u) => [
            'id' => $u->id, 'name' => $u->name, 'employee_no' => $u->employee_no, 'designation' => $u->designation,
            'status' => $existing[$u->id]->status ?? (isset($onLeave[$u->id]) ? 'leave' : 'present'),
            'note' => $existing[$u->id]->note ?? '', 'marked' => isset($existing[$u->id]), 'on_leave' => isset($onLeave[$u->id]),
        ])->values();

        $monthStart = substr($date, 0, 8).'01';
        $monthEnd = date('Y-m-t', strtotime($date));
        $counts = DB::table('staff_attendance')->whereBetween('date', [$monthStart, $monthEnd])->selectRaw('user_id, status, count(*) as total')->groupBy('user_id', 'status')->get()->groupBy('user_id');
        $month = $staff->map(function ($u) use ($counts) {
            $c = ($counts[$u->id] ?? collect())->pluck('total', 'status');
            [$present, $late, $absent, $leave] = [(int) ($c['present'] ?? 0), (int) ($c['late'] ?? 0), (int) ($c['absent'] ?? 0), (int) ($c['leave'] ?? 0)];
            $counted = $present + $late + $absent;

            return ['name' => $u->name, 'present' => $present, 'late' => $late, 'absent' => $absent, 'leave' => $leave, 'percent' => $counted > 0 ? (int) round(($present + $late) / $counted * 100) : null];
        })->values();

        return Inertia::render('office/staff-attendance', [
            'navigation' => OfficeMenu::for('admin'),
            'roleLabel' => 'Super Admin',
            'date' => $date,
            'rows' => $rows,
            'month' => $month,
            'monthLabel' => date('F Y', strtotime($date)),
            'lateAfter' => SiteSettings::get('attendance_late_after', '08:15'),
        ]);
    }

    public function saveStaff(Request $request): RedirectResponse
    {
        $this->adminOnly($request);
        $data = $request->validate([
            'date' => ['required', 'date'],
            'marks' => ['required', 'array', 'min:1', 'max:500'],
            'marks.*.user_id' => ['required', 'integer', \Illuminate\Validation\Rule::exists('users', 'id')->where('role', 'staff')],
            'marks.*.status' => ['required', 'in:present,absent,late,leave'],
            'marks.*.note' => ['nullable', 'string', 'max:150'],
        ]);

        DB::transaction(function () use ($data) {
            foreach ($data['marks'] as $m) {
                DB::table('staff_attendance')->updateOrInsert(['user_id' => $m['user_id'], 'date' => $data['date']], ['status' => $m['status'], 'note' => ($m['note'] ?? null) ?: null, 'updated_at' => now(), 'created_at' => now()]);
            }
        });
        Audit::log('updated', 'Staff attendance', $data['date']);

        return back()->with('status', 'Attendance saved.');
    }

    /** Same rule as for students: the first "in" punch decides present or late, and saved marks are kept. */
    public function syncStaffPunches(Request $request): RedirectResponse
    {
        $this->adminOnly($request);
        $data = $request->validate(['date' => ['required', 'date']]);
        $lateAfter = SiteSettings::get('attendance_late_after', '08:15');

        $first = DB::table('punches')->join('users', 'users.id', '=', 'punches.user_id')->where('users.role', 'staff')
            ->where('punches.direction', 'in')->whereDate('punches.punched_at', $data['date'])
            ->selectRaw('punches.user_id, min(punches.punched_at) as first_in')->groupBy('punches.user_id')->get();
        $existing = DB::table('staff_attendance')->where('date', $data['date'])->pluck('user_id')->flip();

        DB::transaction(function () use ($first, $existing, $data, $lateAfter) {
            foreach ($first as $row) {
                if (! isset($existing[$row->user_id])) {
                    DB::table('staff_attendance')->insert(['user_id' => $row->user_id, 'date' => $data['date'], 'status' => substr((string) $row->first_in, 11, 5) > $lateAfter ? 'late' : 'present', 'created_at' => now(), 'updated_at' => now()]);
                }
            }
        });
        Audit::log('other', 'Staff attendance', "Marked from punches for {$data['date']}");

        return back()->with('status', 'Attendance marked from the punch log.');
    }

    private function adminOnly(Request $request): void
    {
        abort_unless($request->user()->role === 'admin', 403);
    }

    private function date(mixed $value, string $default): string
    {
        return is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) && strtotime($value) ? $value : $default;
    }
}
