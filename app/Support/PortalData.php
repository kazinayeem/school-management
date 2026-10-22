<?php

namespace App\Support;

use App\Models\StudentProfile;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class PortalData
{
    public static function profile(User $user): ?array
    {
        $profile = $user->profile;

        if ($profile === null) {
            return null;
        }

        $working = (int) $profile->attendance_working;
        $present = (int) $profile->attendance_present;

        return [
            'name' => $user->name,
            'photo' => $user->photo_url ?? asset('images/student.jpg'),
            'email' => $user->email,
            'phone' => $user->phone,
            'class' => $profile->class_name,
            'section' => $profile->section,
            'roll_no' => $profile->roll_no,
            'session' => $profile->session,
            'attendance' => [
                'percent' => $working > 0 ? (int) round($present / $working * 100) : null,
                'present' => $present,
                'working_days' => $working,
            ],
            'latest_result' => $profile->exam_name ? [
                'exam' => $profile->exam_name,
                'gpa' => number_format((float) $profile->gpa, 2),
                'class_position' => $profile->class_position,
                'section_position' => $profile->section_position,
                'promotion_status' => $profile->promotion_status,
            ] : null,
            'dues' => [
                'total' => (int) $profile->dues_total,
                'count' => (int) $profile->dues_count,
                'next_due_date' => $profile->dues_due_date?->format('d-m-Y'),
            ],
        ];
    }

    public static function routine(StudentProfile $profile): array
    {
        $rows = DB::table('class_periods')
            ->where('class_name', $profile->class_name)
            ->where('section', $profile->section)
            ->orderBy('sort')
            ->orderBy('day')
            ->get();

        $days = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday'];
        $periods = [];

        foreach ($rows as $row) {
            $periods[$row->sort] ??= [
                'label' => $row->label,
                'time' => $row->time_range,
                'is_break' => (bool) $row->is_break,
                'slots' => array_fill(0, 5, null),
            ];
            $periods[$row->sort]['slots'][$row->day] = $row->is_break ? null : [
                'subject' => $row->subject,
                'teacher' => $row->teacher,
                'room' => $row->room,
            ];
        }

        return ['days' => $days, 'periods' => array_values($periods)];
    }

    public static function attendance(User $user): array
    {
        $start = now()->startOfMonth();
        $rows = DB::table('attendance_days')
            ->where('user_id', $user->id)
            ->whereBetween('date', [$start->toDateString(), $start->copy()->endOfMonth()->toDateString()])
            ->pluck('status', 'date');

        $days = [];
        $counts = ['present' => 0, 'late' => 0, 'absent' => 0, 'leave' => 0];

        for ($cursor = $start->copy(); $cursor->month === $start->month; $cursor->addDay()) {
            $status = $rows[$cursor->toDateString()] ?? null;
            if ($status && isset($counts[$status])) {
                $counts[$status]++;
            }
            $days[] = [
                'date' => $cursor->toDateString(),
                'day' => $cursor->day,
                'weekday' => $cursor->dayOfWeek,
                'status' => $status,
            ];
        }

        $marked = array_sum($counts);

        return [
            'month' => $start->format('F Y'),
            'days' => $days,
            'counts' => $counts,
            'percent' => $marked > 0 ? (int) round(($counts['present'] + $counts['late']) / $marked * 100) : null,
        ];
    }

    public static function homework(User $user): array
    {
        return DB::table('homework_items')
            ->where('user_id', $user->id)
            ->orderByDesc('due_on')
            ->get()
            ->map(function ($row) {
                $due = Carbon::parse($row->due_on);
                $submitted = $row->submitted_at ? Carbon::parse($row->submitted_at) : null;

                return [
                    'id' => $row->id,
                    'title' => $row->title,
                    'subject' => $row->subject,
                    'teacher' => $row->teacher,
                    'due_on' => $due->format('d-m-Y'),
                    'status' => $row->status,
                    'late' => $submitted !== null && $submitted->gt($due->copy()->endOfDay()),
                    'instructions' => $row->instructions,
                    'note' => $row->note,
                    'remarks' => $row->remarks,
                    'submitted_at' => $submitted?->format('d-m-Y H:i'),
                ];
            })
            ->all();
    }

    public static function results(User $user): array
    {
        $profile = self::profile($user);
        $rows = DB::table('exam_results')->where('user_id', $user->id)->orderBy('subject')->get();

        return [
            'summary' => $profile['latest_result'] ?? null,
            'subjects' => $rows->map(fn ($row) => [
                'subject' => $row->subject,
                'marks' => $row->marks,
                'full_marks' => $row->full_marks,
                'letter_grade' => $row->letter_grade,
                'failed' => (bool) $row->failed,
            ])->all(),
        ];
    }

    public static function fees(User $user): array
    {
        $invoices = DB::table('fee_invoices')->where('user_id', $user->id)->orderByDesc('due_on')->get();
        $payments = DB::table('fee_payments')->where('user_id', $user->id)->orderByDesc('paid_on')->get();
        $outstanding = $invoices->sum(fn ($row) => max(0, $row->payable - $row->paid));
        $oldestDue = $invoices->filter(fn ($row) => $row->payable > $row->paid)->last()?->due_on;

        return [
            'total_outstanding' => (int) $outstanding,
            'unpaid_count' => $invoices->filter(fn ($row) => $row->payable > $row->paid)->count(),
            'oldest_due_date' => $oldestDue ? Carbon::parse($oldestDue)->format('d-m-Y') : null,
            'invoices' => $invoices->map(fn ($row) => [
                'invoice_no' => $row->invoice_no,
                'label' => $row->label,
                'due_date' => $row->due_on ? Carbon::parse($row->due_on)->format('d-m-Y') : null,
                'payable' => (int) $row->payable,
                'paid' => (int) $row->paid,
                'outstanding' => max(0, $row->payable - $row->paid),
                'status' => $row->status,
            ])->all(),
            'payments' => $payments->map(fn ($row) => [
                'receipt_no' => $row->receipt_no,
                'date' => Carbon::parse($row->paid_on)->format('d-m-Y'),
                'method' => $row->method,
                'amount' => (int) $row->amount,
                'late_fee' => (int) $row->late_fee,
                'status' => $row->status,
            ])->all(),
        ];
    }

    public static function library(User $user): array
    {
        $rows = DB::table('library_loans')->where('user_id', $user->id)->orderByDesc('issued_on')->get();
        $first = $rows->first();

        return [
            'card_no' => $first->card_no ?? null,
            'member_since' => $first?->member_since ? Carbon::parse($first->member_since)->format('d-m-Y') : null,
            'fine_due' => (int) $rows->sum('fine'),
            'loans' => $rows->map(fn ($row) => [
                'title' => $row->title,
                'author' => $row->author,
                'issued_on' => Carbon::parse($row->issued_on)->format('d-m-Y'),
                'due_on' => Carbon::parse($row->due_on)->format('d-m-Y'),
                'returned_on' => $row->returned_on ? Carbon::parse($row->returned_on)->format('d-m-Y') : null,
                'fine' => (int) $row->fine,
                'status' => $row->status,
                'overdue' => $row->returned_on === null && Carbon::parse($row->due_on)->lt(today()),
            ])->all(),
        ];
    }

    public static function bus(User $user): ?array
    {
        $row = DB::table('bus_assignments')->where('user_id', $user->id)->first();

        if ($row === null) {
            return null;
        }

        return [
            'route' => $row->route,
            'vehicle' => $row->vehicle,
            'stop' => $row->stop,
            'landmark' => $row->landmark,
            'pickup_time' => $row->pickup_time,
            'drop_time' => $row->drop_time,
            'driver' => $row->driver,
            'driver_phone' => $row->driver_phone,
            'fare' => (int) $row->fare,
            'since' => $row->since,
        ];
    }

    public static function documents(User $user): array
    {
        return DB::table('student_documents')->where('user_id', $user->id)->orderByDesc('issue_date')->get()->map(fn ($row) => [
            'type' => $row->type,
            'serial_no' => $row->serial_no,
            'session' => $row->session,
            'issue_date' => Carbon::parse($row->issue_date)->format('d-m-Y'),
        ])->all();
    }

    public static function leaves(User $user): array
    {
        return DB::table('leave_requests')->where('user_id', $user->id)->orderByDesc('start_date')->get()->map(fn ($row) => [
            'id' => $row->id,
            'leave_type' => $row->leave_type,
            'start_date' => Carbon::parse($row->start_date)->format('d-m-Y'),
            'end_date' => Carbon::parse($row->end_date)->format('d-m-Y'),
            'days' => $row->days,
            'reason' => $row->reason,
            'status' => $row->status,
        ])->all();
    }

    public static function calendar(): array
    {
        $start = now()->startOfMonth();
        $events = DB::table('calendar_events')
            ->whereBetween('date', [$start->toDateString(), $start->copy()->endOfMonth()->toDateString()])
            ->orderBy('date')
            ->get()
            ->groupBy(fn ($row) => Carbon::parse($row->date)->toDateString());

        $days = [];
        for ($cursor = $start->copy(); $cursor->month === $start->month; $cursor->addDay()) {
            $key = $cursor->toDateString();
            $days[] = [
                'date' => $key,
                'day' => $cursor->day,
                'weekday' => $cursor->dayOfWeek,
                'items' => ($events[$key] ?? collect())->map(fn ($row) => [
                    'title' => $row->title,
                    'type' => $row->type,
                ])->values()->all(),
            ];
        }

        return ['month' => $start->format('F Y'), 'days' => $days];
    }

    public static function notices(): array
    {
        return DB::table('school_notices')->orderByDesc('published_on')->limit(5)->get()->map(fn ($row) => [
            'title' => $row->title,
            'body' => $row->body,
            'date' => Carbon::parse($row->published_on)->format('d-m-Y'),
        ])->all();
    }
}
