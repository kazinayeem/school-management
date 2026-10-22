<?php

namespace App\Http\Controllers;

use App\Support\OfficeMenu;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class AccountingController extends Controller
{
    private const TYPES = ['receipt', 'payment', 'journal'];

    public function voucherForm(Request $request): Response
    {
        $this->admin($request);

        return Inertia::render('office/voucher', [
            ...$this->shared(),
            'accounts' => DB::table('accounts')->where('active', true)->orderBy('code')->get(['id', 'code', 'name', 'type']),
            'today' => today()->toDateString(),
        ]);
    }

    public function storeVoucher(Request $request): RedirectResponse
    {
        $this->admin($request);
        $data = $request->validate([
            'date' => ['required', 'date'],
            'type' => ['required', Rule::in(self::TYPES)],
            'narration' => ['nullable', 'string', 'max:255'],
            'lines' => ['required', 'array', 'min:2', 'max:30'],
            'lines.*.account_id' => ['required', 'integer', Rule::exists('accounts', 'id')->where('active', true)],
            'lines.*.debit' => ['nullable', 'integer', 'min:0'],
            'lines.*.credit' => ['nullable', 'integer', 'min:0'],
        ]);

        $lines = array_map(fn ($l) => ['account_id' => (int) $l['account_id'], 'debit' => (int) ($l['debit'] ?? 0), 'credit' => (int) ($l['credit'] ?? 0)], $data['lines']);

        foreach ($lines as $line) {
            if (($line['debit'] > 0) === ($line['credit'] > 0)) {
                return back()->withErrors(['lines' => 'Each line needs either a debit or a credit amount, not both or neither.']);
            }
        }

        $debit = array_sum(array_column($lines, 'debit'));
        $credit = array_sum(array_column($lines, 'credit'));
        if ($debit !== $credit) {
            return back()->withErrors(['lines' => 'Total debit and total credit must be equal.']);
        }

        DB::transaction(function () use ($data, $lines, $request) {
            $year = date('Y', strtotime($data['date']));
            $seq = (int) DB::table('vouchers')->where('voucher_no', 'like', "V-{$year}-%")->count() + 1;
            do {
                $no = "V-{$year}-".str_pad((string) $seq++, 4, '0', STR_PAD_LEFT);
            } while (DB::table('vouchers')->where('voucher_no', $no)->exists());

            $id = DB::table('vouchers')->insertGetId([
                'voucher_no' => $no, 'date' => $data['date'], 'type' => $data['type'], 'narration' => $data['narration'] ?? null,
                'created_by' => $request->user()->id, 'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('voucher_lines')->insert(array_map(fn ($l) => $l + ['voucher_id' => $id], $lines));
        });

        return back()->with('status', 'Voucher saved.');
    }

    public function destroyVoucher(Request $request, int $id): RedirectResponse
    {
        $this->admin($request);
        DB::table('vouchers')->where('id', $id)->delete();

        return back()->with('status', 'Voucher deleted.');
    }

    public function register(Request $request): Response
    {
        $this->admin($request);
        [$from, $to] = $this->range($request, today()->startOfYear()->toDateString());
        $type = (string) $request->query('type', '');
        $search = trim((string) $request->query('search', ''));

        $vouchers = DB::table('vouchers')
            ->whereBetween('date', [$from, $to])
            ->when(in_array($type, self::TYPES, true), fn ($q) => $q->where('type', $type))
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q->where('voucher_no', 'like', "%{$search}%")->orWhere('narration', 'like', "%{$search}%")))
            ->orderByDesc('date')->orderByDesc('id')->limit(300)->get();

        $lines = DB::table('voucher_lines')->join('accounts', 'accounts.id', '=', 'voucher_lines.account_id')
            ->whereIn('voucher_id', $vouchers->pluck('id'))->get(['voucher_id', 'accounts.name', 'debit', 'credit'])->groupBy('voucher_id');

        $rows = $vouchers->map(fn ($v) => [
            'id' => $v->id,
            'voucher_no' => $v->voucher_no,
            'date' => $v->date,
            'type' => ucfirst($v->type),
            'narration' => $v->narration,
            'accounts' => collect($lines[$v->id] ?? [])->map(fn ($l) => $l->name.($l->debit > 0 ? ' Dr' : ' Cr'))->implode(', '),
            'amount' => (int) collect($lines[$v->id] ?? [])->sum('debit'),
            'delete_url' => "/app/accounting/vouchers/{$v->id}",
        ])->all();

        return $this->report($request, 'Voucher Register', 'Every voucher posted in the selected period.', [
            $this->dateFilters($from, $to),
            ['name' => 'type', 'label' => 'Type', 'type' => 'select', 'options' => [['value' => '', 'label' => 'All types'], ...array_map(fn ($t) => ['value' => $t, 'label' => ucfirst($t)], self::TYPES)]],
            ['name' => 'search', 'label' => 'Search', 'type' => 'text'],
        ], ['from' => $from, 'to' => $to, 'type' => $type, 'search' => $search], [[
            'title' => null,
            'columns' => [
                ['key' => 'voucher_no', 'label' => 'Voucher no.'], ['key' => 'date', 'label' => 'Date'], ['key' => 'type', 'label' => 'Type'],
                ['key' => 'narration', 'label' => 'Narration'], ['key' => 'accounts', 'label' => 'Accounts'], ['key' => 'amount', 'label' => 'Amount', 'money' => true],
            ],
            'rows' => $rows,
        ]]);
    }

    public function cashBook(Request $request): Response
    {
        $this->admin($request);
        [$from, $to] = $this->range($request, today()->startOfMonth()->toDateString());
        $cash = DB::table('accounts')->whereIn('type', ['asset'])->where('active', true)->orderBy('code')->get(['id', 'code', 'name']);
        $accountId = (int) $request->query('account', $cash->first()->id ?? 0);

        $opening = (int) DB::table('voucher_lines')->join('vouchers', 'vouchers.id', '=', 'voucher_lines.voucher_id')
            ->where('account_id', $accountId)->where('date', '<', $from)->sum(DB::raw('debit - credit'));

        $balance = $opening;
        $rows = [['date' => $from, 'voucher_no' => '', 'narration' => 'Opening balance', 'receipt' => null, 'payment' => null, 'balance' => $balance]];
        $receipts = $payments = 0;

        foreach (DB::table('voucher_lines')->join('vouchers', 'vouchers.id', '=', 'voucher_lines.voucher_id')
            ->where('account_id', $accountId)->whereBetween('date', [$from, $to])
            ->orderBy('date')->orderBy('vouchers.id')->get(['date', 'voucher_no', 'narration', 'debit', 'credit']) as $line) {
            $balance += $line->debit - $line->credit;
            $receipts += $line->debit;
            $payments += $line->credit;
            $rows[] = ['date' => $line->date, 'voucher_no' => $line->voucher_no, 'narration' => $line->narration, 'receipt' => $line->debit ?: null, 'payment' => $line->credit ?: null, 'balance' => $balance];
        }

        return $this->report($request, 'Cash Book', 'Money in and out of a cash or bank account, with a running balance.', [
            ['name' => 'account', 'label' => 'Account', 'type' => 'select', 'options' => $cash->map(fn ($a) => ['value' => (string) $a->id, 'label' => "{$a->code} · {$a->name}"])->all()],
            $this->dateFilters($from, $to),
        ], ['account' => (string) $accountId, 'from' => $from, 'to' => $to], [[
            'title' => null,
            'columns' => [
                ['key' => 'date', 'label' => 'Date'], ['key' => 'voucher_no', 'label' => 'Voucher no.'], ['key' => 'narration', 'label' => 'Narration'],
                ['key' => 'receipt', 'label' => 'Receipts', 'money' => true], ['key' => 'payment', 'label' => 'Payments', 'money' => true], ['key' => 'balance', 'label' => 'Balance', 'money' => true],
            ],
            'rows' => $rows,
            'footer' => ['narration' => 'Total', 'receipt' => $receipts, 'payment' => $payments, 'balance' => $balance],
        ]], [['label' => 'Opening balance', 'value' => $opening], ['label' => 'Receipts', 'value' => $receipts], ['label' => 'Payments', 'value' => $payments], ['label' => 'Closing balance', 'value' => $balance]]);
    }

    public function trialBalance(Request $request): Response
    {
        $this->admin($request);
        $asOf = $this->date($request->query('as_of'), today()->toDateString());

        $rows = DB::table('accounts')
            ->leftJoin('voucher_lines', 'voucher_lines.account_id', '=', 'accounts.id')
            ->leftJoin('vouchers', fn ($j) => $j->on('vouchers.id', '=', 'voucher_lines.voucher_id')->where('vouchers.date', '<=', $asOf))
            ->groupBy('accounts.id', 'accounts.code', 'accounts.name', 'accounts.type')
            ->orderBy('accounts.code')
            ->selectRaw('accounts.code, accounts.name, accounts.type, coalesce(sum(case when vouchers.id is not null then voucher_lines.debit else 0 end),0) as debit, coalesce(sum(case when vouchers.id is not null then voucher_lines.credit else 0 end),0) as credit')
            ->get()
            ->map(function ($r) {
                $net = (int) $r->debit - (int) $r->credit;

                return ['code' => $r->code, 'name' => $r->name, 'type' => ucfirst($r->type), 'debit' => $net > 0 ? $net : null, 'credit' => $net < 0 ? -$net : null];
            })->filter(fn ($r) => $r['debit'] || $r['credit'])->values()->all();

        $debit = array_sum(array_column($rows, 'debit'));
        $credit = array_sum(array_column($rows, 'credit'));

        return $this->report($request, 'Trial Balance', 'Account balances. Total debit must equal total credit.', [
            ['name' => 'as_of', 'label' => 'As of', 'type' => 'date'],
        ], ['as_of' => $asOf], [[
            'title' => null,
            'columns' => [['key' => 'code', 'label' => 'Code'], ['key' => 'name', 'label' => 'Account'], ['key' => 'type', 'label' => 'Type'], ['key' => 'debit', 'label' => 'Debit', 'money' => true], ['key' => 'credit', 'label' => 'Credit', 'money' => true]],
            'rows' => $rows,
            'footer' => ['name' => 'Total', 'debit' => $debit, 'credit' => $credit],
        ]], [['label' => 'Total debit', 'value' => $debit], ['label' => 'Total credit', 'value' => $credit], ['label' => 'Difference', 'value' => $debit - $credit]]);
    }

    public function incomeExpense(Request $request): Response
    {
        $this->admin($request);
        [$from, $to] = $this->range($request, today()->startOfYear()->toDateString());

        $totals = DB::table('voucher_lines')->join('vouchers', 'vouchers.id', '=', 'voucher_lines.voucher_id')->join('accounts', 'accounts.id', '=', 'voucher_lines.account_id')
            ->whereIn('accounts.type', ['income', 'expense'])->whereBetween('vouchers.date', [$from, $to])
            ->groupBy('accounts.id', 'accounts.code', 'accounts.name', 'accounts.type')->orderBy('accounts.code')
            ->selectRaw('accounts.code, accounts.name, accounts.type, sum(debit) as debit, sum(credit) as credit')->get();

        $income = $totals->where('type', 'income')->map(fn ($r) => ['code' => $r->code, 'name' => $r->name, 'amount' => (int) $r->credit - (int) $r->debit])->values()->all();
        $expense = $totals->where('type', 'expense')->map(fn ($r) => ['code' => $r->code, 'name' => $r->name, 'amount' => (int) $r->debit - (int) $r->credit])->values()->all();
        $in = array_sum(array_column($income, 'amount'));
        $out = array_sum(array_column($expense, 'amount'));
        $columns = [['key' => 'code', 'label' => 'Code'], ['key' => 'name', 'label' => 'Account'], ['key' => 'amount', 'label' => 'Amount', 'money' => true]];

        return $this->report($request, 'Income & Expenditure', 'What the school earned and spent in the period.', [$this->dateFilters($from, $to)], ['from' => $from, 'to' => $to], [
            ['title' => 'Income', 'columns' => $columns, 'rows' => $income, 'footer' => ['name' => 'Total income', 'amount' => $in]],
            ['title' => 'Expenditure', 'columns' => $columns, 'rows' => $expense, 'footer' => ['name' => 'Total expenditure', 'amount' => $out]],
        ], [['label' => 'Income', 'value' => $in], ['label' => 'Expenditure', 'value' => $out], ['label' => $in >= $out ? 'Surplus' : 'Deficit', 'value' => abs($in - $out)]]);
    }

    private function report(Request $request, string $title, string $subtitle, array $filters, array $values, array $sections, array $summary = []): Response
    {
        return Inertia::render('office/report', [
            ...$this->shared(),
            'title' => $title, 'subtitle' => $subtitle, 'filters' => $filters, 'values' => $values, 'sections' => $sections, 'summary' => $summary,
        ]);
    }

    private function dateFilters(string $from, string $to): array
    {
        return ['name' => 'from', 'label' => 'From', 'type' => 'date', 'with' => ['name' => 'to', 'label' => 'To', 'type' => 'date']];
    }

    /** @return array{0: string, 1: string} */
    private function range(Request $request, string $defaultFrom): array
    {
        $from = $this->date($request->query('from'), $defaultFrom);
        $to = $this->date($request->query('to'), today()->toDateString());

        return $from <= $to ? [$from, $to] : [$to, $from];
    }

    private function date(mixed $value, string $default): string
    {
        return is_string($value) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) && strtotime($value) ? $value : $default;
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
