<?php

namespace App\Http\Controllers;

use App\Support\Audit;
use App\Support\OfficeMenu;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PayrollController extends Controller
{
    public function structures(Request $request): Response
    {
        $this->admin($request);
        $components = DB::table('payroll_components')->where('active', true)->orderBy('kind')->orderBy('name')->get(['id', 'name', 'kind', 'calc', 'value']);
        $staff = DB::table('users')->whereIn('role', ['teacher', 'staff'])->where('is_active', true)->orderBy('name')->get(['id', 'name']);
        $structures = DB::table('salary_structures')->get()->keyBy('user_id');
        $items = DB::table('salary_structure_items')->get()->groupBy('structure_id');

        $rows = $staff->map(function ($u) use ($structures, $items, $components) {
            $s = $structures[$u->id] ?? null;
            $values = $s ? $items[$s->id]->pluck('value', 'component_id') ?? collect() : collect();
            $calc = $s ? $this->compute((int) $s->basic, $components, $values) : null;

            return ['id' => $u->id, 'name' => $u->name, 'basic' => $s?->basic, 'values' => (object) ($s ? ($items[$s->id] ?? collect())->pluck('value', 'component_id')->all() : []), 'earnings' => $calc['earnings'] ?? null, 'deductions' => $calc['deductions'] ?? null, 'net' => $calc['net'] ?? null];
        });

        return Inertia::render('office/salary-structures', [...$this->shared(), 'rows' => $rows, 'components' => $components]);
    }

    public function saveStructure(Request $request): RedirectResponse
    {
        $this->admin($request);
        $data = $request->validate([
            'user_id' => ['required', 'integer', 'exists:users,id'],
            'basic' => ['required', 'integer', 'min:1', 'max:100000000'],
            'items' => ['nullable', 'array'],
            'items.*' => ['nullable', 'integer', 'min:0'],
        ]);
        abort_unless(DB::table('users')->where('id', $data['user_id'])->whereIn('role', ['teacher', 'staff'])->exists(), 422);

        $valid = DB::table('payroll_components')->pluck('id')->all();
        DB::transaction(function () use ($data, $valid) {
            DB::table('salary_structures')->updateOrInsert(['user_id' => $data['user_id']], ['basic' => $data['basic'], 'updated_at' => now(), 'created_at' => now()]);
            $id = DB::table('salary_structures')->where('user_id', $data['user_id'])->value('id');
            DB::table('salary_structure_items')->where('structure_id', $id)->delete();
            foreach ($data['items'] ?? [] as $componentId => $value) {
                if ($value !== null && in_array((int) $componentId, $valid, true)) {
                    DB::table('salary_structure_items')->insert(['structure_id' => $id, 'component_id' => (int) $componentId, 'value' => (int) $value]);
                }
            }
        });
        Audit::log('updated', 'Salary Structure', 'User #'.$data['user_id']);

        return back()->with('status', 'Salary structure saved.');
    }

    public function runs(Request $request): Response
    {
        $this->admin($request);
        $runs = DB::table('payroll_runs')->leftJoin('payslips', 'payslips.run_id', '=', 'payroll_runs.id')->groupBy('payroll_runs.id')->orderByDesc('payroll_runs.month')
            ->selectRaw('payroll_runs.*, count(payslips.id) as staff_count, coalesce(sum(payslips.net),0) as total_net')->get();
        $selected = (int) $request->query('run', $runs->first()->id ?? 0);

        return Inertia::render('office/payroll-runs', [
            ...$this->shared(),
            'runs' => $runs,
            'selected' => $selected,
            'payslips' => DB::table('payslips')->join('users', 'users.id', '=', 'payslips.user_id')->where('run_id', $selected)->orderBy('users.name')->get(['payslips.*', 'users.name']),
            'defaultMonth' => today()->format('Y-m'),
        ]);
    }

    public function generate(Request $request): RedirectResponse
    {
        $this->admin($request);
        $data = $request->validate(['month' => ['required', 'regex:/^\d{4}-(0[1-9]|1[0-2])$/', 'unique:payroll_runs,month']], ['month.unique' => 'A payroll run already exists for that month.']);

        $components = DB::table('payroll_components')->where('active', true)->get(['id', 'kind', 'calc', 'value']);
        $structures = DB::table('salary_structures')->get();
        if ($structures->isEmpty()) {
            return back()->withErrors(['month' => 'Set up at least one salary structure first.']);
        }
        $items = DB::table('salary_structure_items')->get()->groupBy('structure_id');

        $id = DB::transaction(function () use ($request, $data, $structures, $items, $components) {
            $runId = DB::table('payroll_runs')->insertGetId(['month' => $data['month'], 'status' => 'draft', 'generated_by' => $request->user()->id, 'created_at' => now(), 'updated_at' => now()]);
            foreach ($structures as $s) {
                $calc = $this->compute((int) $s->basic, $components, ($items[$s->id] ?? collect())->pluck('value', 'component_id'));
                DB::table('payslips')->insert(['run_id' => $runId, 'user_id' => $s->user_id, 'basic' => $s->basic, 'earnings' => $calc['earnings'], 'deductions' => $calc['deductions'], 'net' => $calc['net']]);
            }

            return $runId;
        });
        Audit::log('created', 'Payroll Run', $data['month']);

        return redirect("/app/payroll?run={$id}")->with('status', 'Payroll generated.');
    }

    public function pay(Request $request, int $id): RedirectResponse
    {
        $this->admin($request);
        $run = DB::table('payroll_runs')->find($id);
        abort_if($run === null, 404);
        if ($run->status === 'paid') {
            return back()->with('error', 'This payroll was already paid.');
        }

        $total = (int) DB::table('payslips')->where('run_id', $id)->sum('net');
        DB::transaction(function () use ($request, $run, $id, $total) {
            DB::table('payroll_runs')->where('id', $id)->update(['status' => 'paid', 'paid_on' => today()->toDateString(), 'updated_at' => now()]);

            $salary = DB::table('accounts')->where('code', '5001')->value('id');
            $cash = DB::table('accounts')->where('code', '1001')->value('id');
            if ($salary && $cash && $total > 0) {
                $no = 'V-'.date('Y').'-'.str_pad((string) (DB::table('vouchers')->count() + 1), 4, '0', STR_PAD_LEFT);
                while (DB::table('vouchers')->where('voucher_no', $no)->exists()) {
                    $no .= 'x';
                }
                $voucher = DB::table('vouchers')->insertGetId(['voucher_no' => $no, 'date' => today()->toDateString(), 'type' => 'payment', 'narration' => "Salary for {$run->month}", 'created_by' => $request->user()->id, 'created_at' => now(), 'updated_at' => now()]);
                DB::table('voucher_lines')->insert([['voucher_id' => $voucher, 'account_id' => $salary, 'debit' => $total, 'credit' => 0], ['voucher_id' => $voucher, 'account_id' => $cash, 'debit' => 0, 'credit' => $total]]);
            }
        });
        Audit::log('other', 'Payroll Run', "Paid {$run->month}");

        return back()->with('status', 'Payroll marked as paid.');
    }

    public function destroy(Request $request, int $id): RedirectResponse
    {
        $this->admin($request);
        $run = DB::table('payroll_runs')->find($id);
        abort_if($run === null, 404);
        if ($run->status === 'paid') {
            return back()->with('error', 'A paid payroll cannot be deleted.');
        }
        DB::table('payroll_runs')->where('id', $id)->delete();
        Audit::log('deleted', 'Payroll Run', $run->month);

        return redirect('/app/payroll')->with('status', 'Entry deleted.');
    }

    /** @return array{earnings: int, deductions: int, net: int} */
    private function compute(int $basic, $components, $values): array
    {
        $earnings = $deductions = 0;
        foreach ($components as $c) {
            if (! isset($values[$c->id])) {
                continue;
            }
            $amount = $c->calc === 'percent' ? (int) round($basic * $values[$c->id] / 100) : (int) $values[$c->id];
            $c->kind === 'earning' ? $earnings += $amount : $deductions += $amount;
        }

        return ['earnings' => $earnings, 'deductions' => $deductions, 'net' => $basic + $earnings - $deductions];
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
