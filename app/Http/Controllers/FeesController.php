<?php

namespace App\Http\Controllers;

use App\Support\Audit;
use App\Support\FeePayments;
use App\Support\FeePlanner;
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
    private const GATEWAYS = ['bKash', 'Nagad', 'Bank Transfer', 'SSLCommerz', 'Card', 'Other'];

    public function generateForm(Request $request): Response
    {
        $this->admin($request);

        return Inertia::render('office/generate', [
            ...$this->shared(),
            'heads' => DB::table('fee_heads')->where('active', true)->orderBy('category')->orderBy('name')->get(['id', 'name', 'class_name', 'amount', 'category', 'frequency', 'optional']),
            'classes' => $this->classes(),
            'categories' => FeePlanner::CATEGORIES,
            'frequencies' => FeePlanner::FREQUENCIES,
            'result' => $request->session()->get('generated'),
        ]);
    }

    public function generate(Request $request): RedirectResponse
    {
        $this->admin($request);
        if ($request->filled('fee_head_id') && ! $request->has('head_ids')) {
            $request->merge(['head_ids' => [$request->input('fee_head_id')]]);
        }
        $data = $request->validate([
            'head_ids' => ['required', 'array', 'min:1'],
            'head_ids.*' => ['integer', Rule::exists('fee_heads', 'id')->where('active', true)],
            'class_name' => ['nullable', Rule::in($this->classes())],
            'section' => ['nullable', Rule::in(['A', 'B'])],
            'label' => ['required', 'string', 'max:100'],
            'due_on' => ['required', 'date'],
        ]);

        $heads = DB::table('fee_heads')->whereIn('id', $data['head_ids'])->get();
        $students = DB::table('student_profiles')->where('status', 'active')
            ->when($data['class_name'] ?? null, fn ($q, $c) => $q->where('class_name', $c))
            ->when($data['section'] ?? null, fn ($q, $s) => $q->where('section', $s))
            ->get(['user_id', 'class_name']);

        $existing = DB::table('fee_invoices')->where('label', $data['label'])->pluck('user_id')->flip();
        $next = (int) DB::table('fee_invoices')->max('id');
        $created = $skipped = $waived = $nothing = 0;

        DB::transaction(function () use ($students, $existing, $heads, $data, &$next, &$created, &$skipped, &$waived, &$nothing) {
            foreach ($students as $student) {
                if (isset($existing[$student->user_id])) {
                    $skipped++;

                    continue;
                }
                $items = FeePlanner::items($student->class_name, $heads, FeePlanner::waiversFor($student->user_id), FeePlanner::assignedFor($student->user_id));
                if ($items === []) {
                    $nothing++;

                    continue;
                }
                $gross = array_sum(array_column($items, 'amount'));
                $payable = array_sum(array_column($items, 'net'));
                $payable < $gross && $waived++;

                $id = DB::table('fee_invoices')->insertGetId([
                    'user_id' => $student->user_id, 'invoice_no' => 'INV-'.now()->format('ym').'-'.str_pad((string) (++$next), 5, '0', STR_PAD_LEFT),
                    'label' => $data['label'], 'due_on' => $data['due_on'], 'payable' => $payable, 'paid' => 0, 'status' => $payable === 0 ? 'paid' : 'unpaid',
                ]);
                DB::table('fee_invoice_items')->insert(array_map(fn ($i) => $i + ['invoice_id' => $id], $items));

                if ($payable > 0) {
                    DB::table('student_profiles')->where('user_id', $student->user_id)->update([
                        'dues_total' => DB::raw('dues_total + '.$payable), 'dues_count' => DB::raw('dues_count + 1'), 'dues_due_date' => $data['due_on'],
                    ]);
                }
                $created++;
            }
        });

        return back()->with('generated', ['created' => $created, 'skipped' => $skipped, 'waived' => $waived, 'nothing' => $nothing]);
    }

    // ---- fee structure (class-wise)

    public function structure(Request $request): Response
    {
        $this->admin($request);
        $heads = DB::table('fee_heads')->orderBy('category')->orderBy('name')->get();
        $rows = [];
        foreach ($heads->groupBy(fn ($h) => $h->name.'|'.$h->category.'|'.$h->frequency.'|'.(int) $h->optional) as $group) {
            $first = $group->first();
            $amounts = [];
            foreach ($group as $h) {
                $amounts[$h->class_name ?: '*'] = (int) $h->amount;
            }
            $rows[] = ['name' => $first->name, 'category' => $first->category, 'frequency' => $first->frequency, 'optional' => (bool) $first->optional, 'description' => $first->description, 'amounts' => (object) $amounts];
        }

        return Inertia::render('office/fee-structure', [
            ...$this->shared(),
            'rows' => $rows,
            'classes' => $this->classes(),
            'categories' => FeePlanner::CATEGORIES,
            'frequencies' => FeePlanner::FREQUENCIES,
        ]);
    }

    /** Save the whole grid: one row per fee, one amount per class. A blank amount removes that class from the fee. */
    public function saveStructure(Request $request): RedirectResponse
    {
        $this->admin($request);
        $data = $request->validate([
            'rows' => ['required', 'array', 'max:60'],
            'rows.*.name' => ['required', 'string', 'max:100', 'distinct:ignore_case'],
            'rows.*.category' => ['required', Rule::in(array_keys(FeePlanner::CATEGORIES))],
            'rows.*.frequency' => ['required', Rule::in(array_keys(FeePlanner::FREQUENCIES))],
            'rows.*.optional' => ['boolean'],
            'rows.*.description' => ['nullable', 'string', 'max:255'],
            'rows.*.amounts' => ['nullable', 'array'],
            'rows.*.amounts.*' => ['nullable', 'integer', 'min:0', 'max:10000000'],
        ]);

        $classes = $this->classes();
        $kept = [];
        DB::transaction(function () use ($data, $classes, &$kept) {
            foreach ($data['rows'] as $row) {
                foreach ($row['amounts'] ?? [] as $class => $amount) {
                    if (($class !== '*' && ! in_array($class, $classes, true)) || $amount === null || (int) $amount <= 0) {
                        continue;
                    }
                    $key = ['name' => $row['name'], 'class_name' => $class === '*' ? null : $class];
                    $exists = DB::table('fee_heads')->where($key)->first();
                    $values = ['amount' => (int) $amount, 'category' => $row['category'], 'frequency' => $row['frequency'], 'optional' => (bool) ($row['optional'] ?? false), 'description' => $row['description'] ?? null, 'active' => true, 'updated_at' => now()];
                    $exists ? DB::table('fee_heads')->where('id', $exists->id)->update($values) : DB::table('fee_heads')->insert($key + $values + ['created_at' => now()]);
                    $kept[] = $key['name'].'|'.($key['class_name'] ?? '*');
                }
            }
            foreach (DB::table('fee_heads')->get(['id', 'name', 'class_name']) as $h) {
                if (! in_array($h->name.'|'.($h->class_name ?? '*'), $kept, true)) {
                    DB::table('fee_heads')->where('id', $h->id)->delete(); // invoices keep their own item lines
                }
            }
        });
        Audit::log('updated', 'Fee structure', count($data['rows']).' fees');

        return back()->with('status', 'Fee structure saved.');
    }

    // ---- one student's fee account

    public function account(Request $request): Response
    {
        $this->admin($request);
        $student = null;
        if ($request->filled('student')) {
            $student = DB::table('users')->join('student_profiles', 'student_profiles.user_id', '=', 'users.id')->where('users.id', (int) $request->query('student'))
                ->first(['users.id', 'users.name', 'users.photo', 'student_profiles.class_name', 'student_profiles.section', 'student_profiles.roll_no', 'student_profiles.admission_no']);
        }
        $payload = null;
        if ($student) {
            $plan = FeePlanner::items($student->class_name, DB::table('fee_heads')->where('active', true)->get(), FeePlanner::waiversFor($student->id), FeePlanner::assignedFor($student->id));
            $payload = [
                'student' => ['id' => $student->id, 'name' => $student->name, 'photo' => \App\Support\Photos::url($student->photo), 'class_name' => $student->class_name, 'section' => $student->section, 'roll_no' => $student->roll_no, 'admission_no' => $student->admission_no],
                'plan' => $plan,
                'optional' => DB::table('fee_heads')->where('active', true)->where('optional', true)->where(fn ($q) => $q->whereNull('class_name')->orWhere('class_name', $student->class_name))->orderBy('name')->get(['id', 'name', 'amount', 'category', 'frequency'])
                    ->map(fn ($h) => (array) $h + ['on' => isset(FeePlanner::assignedFor($student->id)[$h->id])])->all(),
                'waivers' => DB::table('fee_waivers')->where('user_id', $student->id)->orderByDesc('id')->get(['id', 'kind', 'value', 'applies_to', 'type', 'reason', 'active']),
                'invoices' => self::invoicesWithItems($student->id),
            ];
        }

        return Inertia::render('office/student-fees', [...$this->shared(), 'account' => $payload, 'categories' => FeePlanner::CATEGORIES, 'frequencies' => FeePlanner::FREQUENCIES]);
    }

    public function assign(Request $request, int $id): RedirectResponse
    {
        $this->admin($request);
        $data = $request->validate(['fee_head_id' => ['required', 'integer', Rule::exists('fee_heads', 'id')->where('optional', true)], 'on' => ['required', 'boolean']]);
        abort_unless(DB::table('student_profiles')->where('user_id', $id)->exists(), 404);
        if ($data['on']) {
            DB::table('fee_assignments')->updateOrInsert(['user_id' => $id, 'fee_head_id' => $data['fee_head_id']], ['created_at' => now(), 'updated_at' => now()]);
        } else {
            DB::table('fee_assignments')->where('user_id', $id)->where('fee_head_id', $data['fee_head_id'])->delete();
        }

        return back()->with('status', 'Student fees updated.');
    }

    /** @return list<array<string, mixed>> */
    public static function invoicesWithItems(int $userId): array
    {
        $invoices = DB::table('fee_invoices')->where('user_id', $userId)->orderByDesc('due_on')->orderByDesc('id')->get();
        $items = DB::table('fee_invoice_items')->whereIn('invoice_id', $invoices->pluck('id'))->get()->groupBy('invoice_id');

        return $invoices->map(fn ($i) => [
            'id' => $i->id, 'invoice_no' => $i->invoice_no, 'label' => $i->label, 'due_on' => $i->due_on, 'payable' => (int) $i->payable, 'paid' => (int) $i->paid, 'status' => $i->status,
            'items' => ($items[$i->id] ?? collect())->map(fn ($x) => ['label' => $x->label, 'category' => $x->category, 'amount' => (int) $x->amount, 'discount' => (int) $x->discount, 'net' => (int) $x->net, 'note' => $x->discount_note, 'discount_note' => $x->discount_note])->values()->all(),
        ])->all();
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
            'applies_to' => ['nullable', Rule::in(['all', ...array_keys(FeePlanner::CATEGORIES)])],
            'type' => ['nullable', Rule::in(array_keys(FeePlanner::WAIVER_TYPES))],
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

        // One discount at a time: a second one has to wait until the first is paused or removed.
        if (DB::table('fee_waivers')->where('user_id', $userId)->where('active', true)->exists()) {
            return back()->withErrors(['student' => 'This student already has an active waiver. Pause or delete it before adding another.']);
        }

        DB::table('fee_waivers')->insert([
            'user_id' => $userId, 'kind' => $data['kind'], 'value' => $data['value'], 'reason' => $data['reason'] ?? null, 'applies_to' => $data['applies_to'] ?? 'tuition', 'type' => $data['type'] ?? 'other',
            'active' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);

        FeePlanner::reapply((int) $userId);

        return back()->with('status', 'Waiver added. Open invoices now show the new amount.');
    }

    public function toggleWaiver(Request $request, int $id): RedirectResponse
    {
        $this->admin($request);
        $waiver = DB::table('fee_waivers')->where('id', $id)->first(['user_id', 'active']);
        abort_if($waiver === null, 404);
        $active = $waiver->active;
        if (! $active && DB::table('fee_waivers')->where('user_id', $waiver->user_id)->where('active', true)->exists()) {
            return back()->with('error', 'This student already has another active waiver. Pause that one first.');
        }
        DB::table('fee_waivers')->where('id', $id)->update(['active' => ! $active, 'updated_at' => now()]);
        FeePlanner::reapply((int) DB::table('fee_waivers')->where('id', $id)->value('user_id'));

        return back()->with('status', 'Waiver updated.');
    }

    public function destroyWaiver(Request $request, int $id): RedirectResponse
    {
        $this->admin($request);
        $owner = (int) DB::table('fee_waivers')->where('id', $id)->value('user_id');
        DB::table('fee_waivers')->where('id', $id)->delete();
        $owner && FeePlanner::reapply($owner);

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
            ->leftJoin('users as submitter', 'submitter.id', '=', 'online_transactions.submitted_by')
            ->get(['online_transactions.*', 'users.name', 'fee_invoices.invoice_no', 'submitter.name as submitted_by_name'])
            ->map(function ($r) {
                $r->proof_url = $r->proof_path ? "/payment-proofs/{$r->id}" : null;
                unset($r->proof_path);

                return $r;
            });

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
        $data = $request->validate(['status' => ['required', Rule::in(['success', 'failed'])], 'review_note' => ['nullable', 'string', 'max:500']]);
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

        DB::table('online_transactions')->where('id', $id)->update(['status' => $data['status'], 'review_note' => $data['review_note'] ?? null, 'reviewed_by' => $request->user()->id, 'reviewed_at' => now(), 'updated_at' => now()]);
        Audit::log('other', 'Payment review', "{$tx->reference} · {$data['status']}");

        return back()->with('status', $data['status'] === 'success' ? 'Payment approved and posted to the invoice.' : 'Payment rejected.');
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
        return ['navigation' => OfficeMenu::for(request()->user()->role), 'roleLabel' => 'Super Admin'];
    }

    private function admin(Request $request): void
    {
        \App\Support\Permissions::office($request);
    }
}
