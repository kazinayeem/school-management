<?php

namespace App\Http\Controllers;

use App\Support\FeePayments;
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
