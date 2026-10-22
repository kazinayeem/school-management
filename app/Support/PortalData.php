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
            ...\Illuminate\Support\Arr::except(\App\Support\ProfileData::for($user), ['id', 'name', 'email', 'phone', 'photo']),
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

    /** Attendance for any date range (this month by default), as a calendar when it fits one month and always as a list. */
    public static function attendance(User $user, ?string $from = null, ?string $to = null): array
    {
        $ok = fn (?string $d) => $d && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && strtotime($d) !== false;
        $start = $ok($from) ? Carbon::parse($from)->startOfDay() : now()->startOfMonth();
        $end = $ok($to) ? Carbon::parse($to)->startOfDay() : $start->copy()->endOfMonth()->startOfDay();
        if ($end->lt($start)) {
            [$start, $end] = [$end, $start];
        }
        if ($start->diffInDays($end) > 366) {
            $end = $start->copy()->addDays(366);
        }

        $rows = DB::table('attendance_days')->where('user_id', $user->id)->whereBetween('date', [$start->toDateString(), $end->toDateString()])->orderBy('date')->pluck('status', 'date');
        $counts = ['present' => 0, 'late' => 0, 'absent' => 0, 'leave' => 0];
        foreach ($rows as $status) {
            if (isset($counts[$status])) {
                $counts[$status]++;
            }
        }

        $days = [];
        if ($start->isSameMonth($end)) {
            $monthStart = $start->copy()->startOfMonth();
            for ($cursor = $monthStart->copy(); $cursor->month === $monthStart->month; $cursor->addDay()) {
                $days[] = ['date' => $cursor->toDateString(), 'day' => $cursor->day, 'weekday' => $cursor->dayOfWeek, 'status' => $cursor->between($start, $end) ? ($rows[$cursor->toDateString()] ?? null) : null];
            }
        }

        $marked = array_sum($counts);

        return [
            'month' => $start->isSameMonth($end) && $start->day === 1 && $end->isSameDay($end->copy()->endOfMonth()) ? $start->format('F Y') : $start->format('j M Y').' – '.$end->format('j M Y'),
            'from' => $start->toDateString(),
            'to' => $end->toDateString(),
            'days' => $days,
            'counts' => $counts,
            'percent' => $marked > 0 ? (int) round(($counts['present'] + $counts['late']) / $marked * 100) : null,
            'records' => $rows->map(fn ($status, $date) => ['date' => $date, 'weekday' => Carbon::parse($date)->format('l'), 'status' => $status])->values()->all(),
            'student' => $user->name,
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
                    'grade' => $row->grade,
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
        $items = DB::table('fee_invoice_items')->whereIn('invoice_id', $invoices->pluck('id'))->get()->groupBy('invoice_id');
        $payments = DB::table('fee_payments')->where('user_id', $user->id)->orderByDesc('paid_on')->get();
        $outstanding = $invoices->sum(fn ($row) => max(0, $row->payable - $row->paid));
        $oldestDue = $invoices->filter(fn ($row) => $row->payable > $row->paid)->last()?->due_on;

        return [
            'total_outstanding' => (int) $outstanding,
            'unpaid_count' => $invoices->filter(fn ($row) => $row->payable > $row->paid)->count(),
            'oldest_due_date' => $oldestDue ? Carbon::parse($oldestDue)->format('d-m-Y') : null,
            'pay' => self::payMethods(),
            'submissions' => DB::table('online_transactions')->where('user_id', $user->id)->orderByDesc('id')->limit(20)->get()->map(fn ($t) => [
                'id' => $t->id, 'invoice_id' => $t->invoice_id, 'method' => $t->gateway, 'reference' => $t->reference, 'amount' => (int) $t->amount, 'status' => $t->status,
                'paid_on' => $t->paid_on, 'submitted_on' => substr((string) $t->created_at, 0, 10), 'review_note' => $t->review_note, 'proof_url' => $t->proof_path ? "/payment-proofs/{$t->id}" : null,
            ])->all(),
            'plan' => self::feePlan($user),
            'waivers' => DB::table('fee_waivers')->where('user_id', $user->id)->where('active', true)->orderByDesc('id')->get(['kind', 'value', 'applies_to', 'type', 'reason'])->map(fn ($w) => (array) $w + ['type_label' => FeePlanner::WAIVER_TYPES[$w->type] ?? 'Discount'])->all(),
            'invoices' => $invoices->map(fn ($row) => [
                'id' => $row->id,
                'items' => ($items[$row->id] ?? collect())->map(fn ($x) => ['label' => $x->label, 'category' => $x->category, 'amount' => (int) $x->amount, 'discount' => (int) $x->discount, 'net' => (int) $x->net, 'note' => $x->discount_note])->values()->all(),
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

    /** Where to send money: only the methods the school has filled in. */
    public static function payMethods(): array
    {
        $get = fn (string $k) => trim((string) SiteSettings::get($k, ''));
        $methods = [];
        $get('pay_bkash_number') !== '' && $methods[] = ['method' => 'bKash', 'lines' => [['Number', $get('pay_bkash_number')], ['Type', $get('pay_bkash_type') ?: 'Personal']]];
        $get('pay_nagad_number') !== '' && $methods[] = ['method' => 'Nagad', 'lines' => [['Number', $get('pay_nagad_number')], ['Type', $get('pay_nagad_type') ?: 'Personal']]];
        $get('pay_bank_account_number') !== '' && $methods[] = ['method' => 'Bank Transfer', 'lines' => array_values(array_filter([
            ['Bank', $get('pay_bank_name')], ['Branch', $get('pay_bank_branch')], ['Account name', $get('pay_bank_account_name')], ['Account number', $get('pay_bank_account_number')],
        ], fn ($l) => $l[1] !== ''))];

        return ['methods' => $methods, 'instructions' => $get('gateway_instructions')];
    }

    /** What this student is charged under the school's fee structure, with their discounts. */
    public static function feePlan(User $user): array
    {
        $class = $user->profile?->class_name;
        if ($class === null) {
            return [];
        }

        return array_map(fn ($i) => $i + ['frequency' => DB::table('fee_heads')->where('id', $i['fee_head_id'])->value('frequency')], FeePlanner::items(
            $class, DB::table('fee_heads')->where('active', true)->get(), FeePlanner::waiversFor($user->id), FeePlanner::assignedFor($user->id),
        ));
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
