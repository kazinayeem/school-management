<?php

namespace App\Http\Controllers;

use App\Support\Audit;
use App\Support\Permissions;
use App\Support\UserFiles;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** A family pays by bKash, Nagad or bank transfer, then tells the school here. An administrator checks it and approves or rejects it. */
class PaymentSubmissionController extends Controller
{
    private const METHODS = ['bKash', 'Nagad', 'Bank Transfer'];

    public function store(Request $request): RedirectResponse
    {
        $user = $request->user();
        abort_unless(in_array($user->role, ['student', 'parent'], true), 403);

        $data = $request->validate([
            'invoice_id' => ['required', 'integer'],
            'method' => ['required', Rule::in(self::METHODS)],
            'reference' => ['required', 'string', 'max:100', 'unique:online_transactions,reference'],
            'amount' => ['required', 'integer', 'min:1', 'max:10000000'],
            'paid_on' => ['required', 'date', 'before_or_equal:today', 'after:'.today()->subDays(120)->toDateString()],
            'sender_number' => ['nullable', 'string', 'max:40'],
            'note' => ['nullable', 'string', 'max:500'],
            'proof' => ['nullable', ...UserFiles::rules()],
        ], ['reference.unique' => 'That transaction number has already been submitted.']);

        $invoice = DB::table('fee_invoices')->find($data['invoice_id']);
        $allowed = $invoice !== null && ($user->role === 'student'
            ? (int) $invoice->user_id === $user->id
            : DB::table('guardian_student')->where('guardian_id', $user->id)->where('student_id', $invoice->user_id)->exists());
        abort_unless($allowed, 404);

        $balance = (int) $invoice->payable - (int) $invoice->paid;
        $pending = (int) DB::table('online_transactions')->where('invoice_id', $invoice->id)->where('status', 'pending')->sum('amount');
        if ($balance <= 0) {
            return back()->withErrors(['invoice_id' => 'This invoice is already fully paid.']);
        }
        if ($data['amount'] > $balance) {
            return back()->withErrors(['amount' => 'The amount is more than the invoice still owes ('.number_format($balance).').']);
        }
        if ($data['amount'] + $pending > $balance) {
            return back()->withErrors(['amount' => 'Other payments for this invoice are still waiting for approval. Only '.number_format($balance - $pending).' is left to submit.']);
        }

        $path = $request->hasFile('proof') ? $request->file('proof')->storeAs('payment-proofs/'.$invoice->user_id, Str::uuid().'.'.strtolower($request->file('proof')->getClientOriginalExtension() ?: $request->file('proof')->extension()), 'local') : null;

        DB::table('online_transactions')->insert([
            'user_id' => $invoice->user_id, 'invoice_id' => $invoice->id, 'gateway' => $data['method'], 'reference' => $data['reference'], 'amount' => $data['amount'], 'status' => 'pending',
            'proof_path' => $path, 'sender_number' => $data['sender_number'] ?? null, 'paid_on' => $data['paid_on'], 'note' => $data['note'] ?? null, 'submitted_by' => $user->id,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        Audit::log('created', 'Payment submitted', "{$invoice->invoice_no} · {$data['method']} · {$data['amount']}");

        return back()->with('status', 'Thank you. The school will check your payment and confirm it here.');
    }

    /** The slip or screenshot: the family it belongs to, and office staff who handle fees. */
    public function proof(Request $request, int $id)
    {
        $tx = DB::table('online_transactions')->find($id);
        abort_if($tx === null || ! $tx->proof_path, 404);
        $user = $request->user();
        $allowed = $user->role === 'admin' || $user->id === (int) $tx->user_id || (int) $tx->submitted_by === $user->id
            || ($user->role === 'parent' && DB::table('guardian_student')->where('guardian_id', $user->id)->where('student_id', $tx->user_id)->exists())
            || (Permissions::isEmployee($user->role) && Permissions::canRole($user->role, 'fees', 'view'));
        abort_unless($allowed, 404);

        return UserFiles::disk()->response($tx->proof_path, 'payment-proof-'.$id.'.'.pathinfo($tx->proof_path, PATHINFO_EXTENSION), ['X-Content-Type-Options' => 'nosniff', 'Cache-Control' => 'private, max-age=0'], 'inline');
    }
}
