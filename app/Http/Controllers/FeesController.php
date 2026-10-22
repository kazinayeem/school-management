<?php

namespace App\Http\Controllers;

use App\Support\Audit;
use App\Support\FeePayments;
use App\Support\SmsGateway;
use App\Support\OfficeMenu;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class FeesController extends Controller
{
    private const GATEWAYS = ['bKash', 'Nagad', 'SSLCommerz', 'Card', 'Other'];

    public function generateForm(Request $request): Response
    {
        $this->admin($request);

        return Inertia::render('office/generate', [
            ...$this->shared(),
            'heads' => DB::table('fee_heads')->where('active', true)->orderBy('name')->get(['id', 'name', 'class_name', 'amount']),
            'classes' => $this->classes(),
            'result' => $request->session()->get('generated'),
        ]);
    }

    public function generate(Request $request): RedirectResponse
    {
        $this->admin($request);
        $data = $request->validate([
            'fee_head_id' => ['required', 'integer', Rule::exists('fee_heads', 'id')->where('active', true)],
            'class_name' => ['nullable', Rule::in($this->classes())],
            'section' => ['nullable', Rule::in(['A', 'B'])],
            'label' => ['required', 'string', 'max:100'],
            'due_on' => ['required', 'date'],
        ]);

        $head = DB::table('fee_heads')->find($data['fee_head_id']);
        $class = $head->class_name ?: ($data['class_name'] ?? null);

        $students = DB::table('student_profiles')
            ->where('status', 'active')
            ->when($class, fn ($q) => $q->where('class_name', $class))
            ->when($data['section'] ?? null, fn ($q, $section) => $q->where('section', $section))
            ->get(['user_id']);

        $waivers = DB::table('fee_waivers')->where('active', true)->get()->groupBy('user_id');
        $existing = DB::table('fee_invoices')->where('label', $data['label'])->pluck('user_id')->flip();
        $next = (int) DB::table('fee_invoices')->max('id');
        $created = $skipped = $waived = 0;

        DB::transaction(function () use ($students, $waivers, $existing, $head, $data, &$next, &$created, &$skipped, &$waived) {
            foreach ($students as $student) {
                if (isset($existing[$student->user_id])) {
                    $skipped++;

                    continue;
                }

                $payable = (int) $head->amount;
                foreach ($waivers[$student->user_id] ?? [] as $waiver) {
                    $payable -= $waiver->kind === 'percent' ? (int) round($head->amount * $waiver->value / 100) : (int) $waiver->value;
                }
                $payable = max(0, $payable);
                if ($payable < (int) $head->amount) {
                    $waived++;
                }

                DB::table('fee_invoices')->insert([
                    'user_id' => $student->user_id,
                    'invoice_no' => 'INV-'.now()->format('ym').'-'.str_pad((string) (++$next), 5, '0', STR_PAD_LEFT),
                    'label' => $data['label'],
                    'due_on' => $data['due_on'],
                    'payable' => $payable,
                    'paid' => 0,
                    'status' => $payable === 0 ? 'paid' : 'unpaid',
                ]);

                if ($payable > 0) {
                    DB::table('student_profiles')->where('user_id', $student->user_id)->update([
                        'dues_total' => DB::raw('dues_total + '.$payable),
                        'dues_count' => DB::raw('dues_count + 1'),
                        'dues_due_date' => $data['due_on'],
                    ]);
                }
                $created++;
            }
        });

        return back()->with('generated', ['created' => $created, 'skipped' => $skipped, 'waived' => $waived]);
    }

    public function waivers(Request $request): Response
    {
        $this->admin($request);

        $rows = DB::table('fee_waivers')
            ->join('users', 'users.id', '=', 'fee_waivers.user_id')
            ->leftJoin('student_profiles', 'student_profiles.user_id', '=', 'users.id')
            ->orderByDesc('fee_waivers.id')
            ->get(['fee_waivers.*', 'users.name', 'student_profiles.admission_no', 'student_profiles.class_name']);

        return Inertia::render('office/waivers', [...$this->shared(), 'rows' => $rows]);
    }

    public function storeWaiver(Request $request): RedirectResponse
    {
        $this->admin($request);
        $data = $request->validate([
            'student' => ['required', 'string', 'max:100'],
            'kind' => ['required', Rule::in(['percent', 'fixed'])],
            'value' => ['required', 'integer', 'min:1'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        if ($data['kind'] === 'percent' && $data['value'] > 100) {
            return back()->withErrors(['value' => 'A percentage cannot be more than 100.']);
        }

        $userId = DB::table('student_profiles')
            ->join('users', 'users.id', '=', 'student_profiles.user_id')
            ->where(fn ($q) => $q->where('student_profiles.admission_no', $data['student'])->orWhere('users.username', $data['student']))
            ->value('users.id');

        if ($userId === null) {
            return back()->withErrors(['student' => 'No student found with that admission number or username.']);
        }

        DB::table('fee_waivers')->insert([
            'user_id' => $userId, 'kind' => $data['kind'], 'value' => $data['value'], 'reason' => $data['reason'] ?? null,
            'active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);

        return back()->with('status', 'Waiver added.');
    }

    public function toggleWaiver(Request $request, int $id): RedirectResponse
    {
        $this->admin($request);
        $active = DB::table('fee_waivers')->where('id', $id)->value('active');
        abort_if($active === null, 404);
        DB::table('fee_waivers')->where('id', $id)->update(['active' => ! $active, 'updated_at' => now()]);

        return back()->with('status', 'Waiver updated.');
    }

    public function destroyWaiver(Request $request, int $id): RedirectResponse
    {
        $this->admin($request);
        DB::table('fee_waivers')->where('id', $id)->delete();

        return back()->with('status', 'Entry deleted.');
    }

    public function online(Request $request): Response
    {
        $this->admin($request);
        $status = (string) $request->query('status', '');

        $rows = DB::table('online_transactions')
            ->join('users', 'users.id', '=', 'online_transactions.user_id')
            ->leftJoin('fee_invoices', 'fee_invoices.id', '=', 'online_transactions.invoice_id')
            ->when(in_array($status, ['pending', 'success', 'failed'], true), fn ($q) => $q->where('online_transactions.status', $status))
            ->orderByDesc('online_transactions.id')
            ->limit(300)
            ->get(['online_transactions.*', 'users.name', 'fee_invoices.invoice_no']);

        return Inertia::render('office/online', [
            ...$this->shared(),
            'rows' => $rows,
            'counts' => DB::table('online_transactions')->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
            'filters' => ['status' => $status],
            'gateways' => self::GATEWAYS,
        ]);
    }

    public function storeOnline(Request $request): RedirectResponse
    {
        $this->admin($request);
        $data = $request->validate([
            'invoice_no' => ['required', 'string'],
            'gateway' => ['required', Rule::in(self::GATEWAYS)],
            'reference' => ['required', 'string', 'max:100', 'unique:online_transactions,reference'],
            'amount' => ['required', 'integer', 'min:1'],
        ]);

        $invoice = DB::table('fee_invoices')->where('invoice_no', $data['invoice_no'])->first();
        if ($invoice === null) {
            return back()->withErrors(['invoice_no' => 'No invoice with that number.']);
        }
        if ($data['amount'] > (int) $invoice->payable - (int) $invoice->paid) {
            return back()->withErrors(['amount' => 'The amount is more than the invoice still owes.']);
        }

        DB::table('online_transactions')->insert([
            'user_id' => $invoice->user_id, 'invoice_id' => $invoice->id, 'gateway' => $data['gateway'],
            'reference' => $data['reference'], 'amount' => $data['amount'], 'status' => 'pending',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return back()->with('status', 'Transaction recorded.');
    }

    public function reconcile(Request $request, int $id): RedirectResponse
    {
        $this->admin($request);
        $data = $request->validate(['status' => ['required', Rule::in(['success', 'failed'])]]);
        $tx = DB::table('online_transactions')->where('id', $id)->first();
        abort_if($tx === null, 404);

        if ($tx->status !== 'pending') {
            return back()->with('error', 'This transaction was already settled.');
        }

        if ($data['status'] === 'success') {
            $invoice = DB::table('fee_invoices')->find($tx->invoice_id);
            if ($invoice === null || FeePayments::collect($invoice, (int) $tx->amount, $tx->gateway) === null) {
                return back()->with('error', 'The invoice is already fully paid.');
            }
        }

        DB::table('online_transactions')->where('id', $id)->update(['status' => $data['status'], 'updated_at' => now()]);

        return back()->with('status', 'Transaction updated.');
    }

    // ---- due list

    public function dues(Request $request): Response
    {
        $this->admin($request);
        $f = $this->dueFilters($request);
        $rows = $this->dueRows($f);

        $overdue = $rows->where('days', '>', 0);

        return Inertia::render('office/report', [
            ...$this->shared(),
            'title' => 'Due List',
            'subtitle' => 'Invoices that still have a balance, with any late fee that would be added today.',
            'filters' => [
                ['name' => 'class', 'label' => 'Class', 'type' => 'select', 'options' => [['value' => '', 'label' => 'All classes'], ...array_map(fn ($c) => ['value' => $c, 'label' => $c], $this->classes())]],
                ['name' => 'section', 'label' => 'Section', 'type' => 'select', 'options' => [['value' => '', 'label' => 'All sections'], ['value' => 'A', 'label' => 'A'], ['value' => 'B', 'label' => 'B']]],
                ['name' => 'overdue', 'label' => 'Show', 'type' => 'select', 'options' => [['value' => '', 'label' => 'All dues'], ['value' => '1', 'label' => 'Overdue only']]],
                ['name' => 'search', 'label' => 'Search', 'type' => 'text'],
            ],
            'values' => $f,
            'sections' => [[
                'title' => null,
                'columns' => [
                    ['key' => 'student', 'label' => 'Student'], ['key' => 'class', 'label' => 'Class'], ['key' => 'invoice_no', 'label' => 'Invoice'], ['key' => 'label', 'label' => 'Fee'],
                    ['key' => 'due_on', 'label' => 'Due date'], ['key' => 'days', 'label' => 'Days overdue', 'num' => true], ['key' => 'balance', 'label' => 'Balance', 'money' => true],
                    ['key' => 'late', 'label' => 'Late fee', 'money' => true], ['key' => 'total', 'label' => 'Pay now', 'money' => true],
                ],
                'rows' => $rows->map(fn ($r) => array_merge($r, ['days' => max(0, $r['days'])]))->values()->all(),
                'footer' => ['student' => 'Total', 'balance' => (int) $rows->sum('balance'), 'late' => (int) $rows->sum('late'), 'total' => (int) $rows->sum('total')],
            ]],
            'summary' => [
                ['label' => 'Students with dues', 'value' => $rows->pluck('user_id')->unique()->count()],
                ['label' => 'Outstanding balance', 'value' => (int) $rows->sum('balance')],
                ['label' => 'Overdue balance', 'value' => (int) $overdue->sum('balance')],
                ['label' => 'Late fees to be added', 'value' => (int) $rows->sum('late')],
            ],
            'actions' => $rows->isEmpty() ? [] : [['label' => 'Text a reminder to these guardians', 'url' => '/app/fees/dues/remind', 'data' => $f, 'confirm' => 'Queue a fee reminder for every guardian in this list?']],
        ]);
    }

    public function remindDues(Request $request): RedirectResponse
    {
        $this->admin($request);
        $rows = $this->dueRows($this->dueFilters($request, $request->input()))->groupBy('user_id');

        $body = DB::table('sms_templates')->where('name', 'Fee due reminder')->where('active', true)->value('body')
            ?? 'Dear {guardian}, the fee for {student} ({class}) is due. Please pay at the school office.';

        $queued = $skipped = 0;
        foreach ($rows as $userId => $invoices) {
            $first = $invoices->first();
            $phone = DB::table('student_profiles')->where('user_id', $userId)->value('guardian_mobile');
            $message = strtr($body, ['{guardian}' => $first['guardian'] ?: 'Guardian', '{student}' => $first['student'], '{class}' => $first['class']]).' Amount due: '.number_format((int) $invoices->sum('total')).'.';

            $already = $phone && DB::table('sms_logs')->where('phone', $phone)->where('message', $message)->whereDate('created_at', today())->exists();
            if (blank($phone) || $already) {
                $skipped++;

                continue;
            }
            SmsGateway::queue($phone, $first['guardian'], $message, $request->user()->id);
            $queued++;
        }
        Audit::log('other', 'Fee reminders', "{$queued} queued, {$skipped} skipped");

        return back()->with('status', 'Reminders queued.');
    }

    /** @return array{class: string, section: string, overdue: string, search: string} */
    private function dueFilters(Request $request, ?array $source = null): array
    {
        $in = $source ?? $request->query();

        return [
            'class' => in_array($in['class'] ?? '', $this->classes(), true) ? (string) $in['class'] : '',
            'section' => in_array($in['section'] ?? '', ['A', 'B'], true) ? (string) $in['section'] : '',
            'overdue' => ($in['overdue'] ?? '') === '1' ? '1' : '',
            'search' => trim((string) ($in['search'] ?? '')),
        ];
    }

    /** @return \Illuminate\Support\Collection<int, array<string, mixed>> */
    private function dueRows(array $f): \Illuminate\Support\Collection
    {
        return DB::table('fee_invoices')->join('users', 'users.id', '=', 'fee_invoices.user_id')->join('student_profiles', 'student_profiles.user_id', '=', 'users.id')
            ->whereColumn('fee_invoices.payable', '>', 'fee_invoices.paid')->where('student_profiles.status', 'active')
            ->when($f['class'] !== '', fn ($q) => $q->where('student_profiles.class_name', $f['class']))
            ->when($f['section'] !== '', fn ($q) => $q->where('student_profiles.section', $f['section']))
            ->when($f['overdue'] === '1', fn ($q) => $q->where('fee_invoices.due_on', '<', today()->toDateString()))
            ->when($f['search'] !== '', fn ($q) => $q->where(fn ($q) => $q->where('users.name', 'like', "%{$f['search']}%")->orWhere('student_profiles.admission_no', 'like', "%{$f['search']}%")->orWhere('fee_invoices.invoice_no', 'like', "%{$f['search']}%")))
            ->orderBy('fee_invoices.due_on')->orderBy('users.name')->limit(500)
            ->get(['fee_invoices.*', 'users.name as student', 'student_profiles.class_name', 'student_profiles.section', 'student_profiles.guardian_name'])
            ->map(function ($r) {
                $balance = (int) $r->payable - (int) $r->paid;
                $late = FeePayments::lateFee($r);

                return [
                    'user_id' => (int) $r->user_id, 'student' => $r->student, 'guardian' => $r->guardian_name, 'class' => trim($r->class_name.' '.$r->section), 'invoice_no' => $r->invoice_no, 'label' => $r->label,
                    'due_on' => $r->due_on, 'days' => $r->due_on ? (int) \Illuminate\Support\Carbon::parse($r->due_on)->startOfDay()->diffInDays(today(), false) : 0,
                    'balance' => $balance, 'late' => $late, 'total' => $balance + $late,
                ];
            });
    }

    // ---- collection report

    public function payments(Request $request): Response
    {
        $this->admin($request);
        $f = $this->paymentFilters($request);
        $rows = $this->paymentRows($f);
        $byMethod = $rows->groupBy('method')->map(fn ($g, $m) => ['method' => $m, 'receipts' => $g->count(), 'amount' => (int) $g->sum('amount')])->sortByDesc('amount')->values();

        $methods = DB::table('fee_payments')->distinct()->orderBy('method')->pluck('method')->all();
        $query = http_build_query(array_filter($f));

        return Inertia::render('office/report', [
            ...$this->shared(),
            'title' => 'Collection Report',
            'subtitle' => 'Fees collected in a period, by payment method.',
            'filters' => [
                ['name' => 'from', 'label' => 'From', 'type' => 'date', 'with' => ['name' => 'to', 'label' => 'To', 'type' => 'date']],
                ['name' => 'method', 'label' => 'Method', 'type' => 'select', 'options' => [['value' => '', 'label' => 'All methods'], ...array_map(fn ($m) => ['value' => $m, 'label' => $m], $methods)]],
                ['name' => 'class', 'label' => 'Class', 'type' => 'select', 'options' => [['value' => '', 'label' => 'All classes'], ...array_map(fn ($c) => ['value' => $c, 'label' => $c], $this->classes())]],
            ],
            'values' => $f,
            'sections' => [
                [
                    'title' => 'Payments',
                    'columns' => [
                        ['key' => 'paid_on', 'label' => 'Date'], ['key' => 'receipt_no', 'label' => 'Receipt'], ['key' => 'student', 'label' => 'Student'], ['key' => 'class', 'label' => 'Class'],
                        ['key' => 'method', 'label' => 'Method'], ['key' => 'late_fee', 'label' => 'Late fee', 'money' => true], ['key' => 'amount', 'label' => 'Amount', 'money' => true],
                    ],
                    'rows' => $rows->all(),
                    'footer' => ['student' => 'Total', 'late_fee' => (int) $rows->sum('late_fee'), 'amount' => (int) $rows->sum('amount')],
                ],
                [
                    'title' => 'By method',
                    'columns' => [['key' => 'method', 'label' => 'Method'], ['key' => 'receipts', 'label' => 'Receipts', 'num' => true], ['key' => 'amount', 'label' => 'Amount', 'money' => true]],
                    'rows' => $byMethod->all(),
                ],
            ],
            'summary' => [
                ['label' => 'Collected', 'value' => (int) $rows->sum('amount')],
                ['label' => 'Receipts', 'value' => $rows->count()],
                ['label' => 'Late fees included', 'value' => (int) $rows->sum('late_fee')],
                ['label' => 'Average receipt', 'value' => $rows->isEmpty() ? 0 : (int) round($rows->avg('amount'))],
            ],
            'actions' => [['label' => 'Download CSV', 'href' => '/app/fees/payments/export'.($query ? '?'.$query : '')]],
        ]);
    }

    public function exportPayments(Request $request): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $this->admin($request);
        $rows = $this->paymentRows($this->paymentFilters($request));

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Date', 'Receipt', 'Student', 'Class', 'Method', 'Late fee', 'Amount']);
            foreach ($rows as $r) {
                fputcsv($out, [$r['paid_on'], $r['receipt_no'], $r['student'], $r['class'], $r['method'], $r['late_fee'], $r['amount']]);
            }
            fclose($out);
        }, 'collection-report.csv', ['Content-Type' => 'text/csv']);
    }

    /** @return array{from: string, to: string, method: string, class: string} */
    private function paymentFilters(Request $request): array
    {
        $date = fn ($v, $d) => is_string($v) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $v) && strtotime($v) ? $v : $d;
        $from = $date($request->query('from'), today()->startOfMonth()->toDateString());
        $to = $date($request->query('to'), today()->toDateString());
        if ($from > $to) {
            [$from, $to] = [$to, $from];
        }

        return [
            'from' => $from, 'to' => $to, 'method' => trim((string) $request->query('method', '')),
            'class' => in_array($request->query('class'), $this->classes(), true) ? (string) $request->query('class') : '',
        ];
    }

    /** @return \Illuminate\Support\Collection<int, array<string, mixed>> */
    private function paymentRows(array $f): \Illuminate\Support\Collection
    {
        return DB::table('fee_payments')->join('users', 'users.id', '=', 'fee_payments.user_id')->leftJoin('student_profiles', 'student_profiles.user_id', '=', 'users.id')
            ->whereBetween('fee_payments.paid_on', [$f['from'], $f['to']])
            ->when($f['method'] !== '', fn ($q) => $q->where('fee_payments.method', $f['method']))
            ->when($f['class'] !== '', fn ($q) => $q->where('student_profiles.class_name', $f['class']))
            ->orderByDesc('fee_payments.paid_on')->orderByDesc('fee_payments.id')->limit(2000)
            ->get(['fee_payments.paid_on', 'fee_payments.receipt_no', 'fee_payments.method', 'fee_payments.amount', 'fee_payments.late_fee', 'users.name as student', 'student_profiles.class_name', 'student_profiles.section'])
            ->map(fn ($r) => ['paid_on' => $r->paid_on, 'receipt_no' => $r->receipt_no, 'student' => $r->student, 'class' => trim(($r->class_name ?? '').' '.($r->section ?? '')), 'method' => $r->method, 'late_fee' => (int) $r->late_fee, 'amount' => (int) $r->amount]);
    }

    /** @return list<string> */
    private function classes(): array
    {
        return array_map(fn ($n) => "Class {$n}", range(1, 10));
    }

    private function shared(): array
    {
        return ['navigation' => OfficeMenu::for('admin'), 'roleLabel' => 'Super Admin'];
    }

    private function admin(Request $request): void
    {
        abort_unless($request->user()->role === 'admin', 403);
    }
}
