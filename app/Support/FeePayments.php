<?php

namespace App\Support;

use App\Models\StudentProfile;
use Illuminate\Support\Facades\DB;

class FeePayments
{
    /**
     * Record a payment against an invoice, updating the invoice, the payment
     * register and the student's dues. Returns the receipt number, or null if
     * nothing is left to pay.
     */
    public static function collect(object $invoice, int $amount, string $method): ?string
    {
        $amount = min($amount, max(0, (int) $invoice->payable - (int) $invoice->paid));
        if ($amount < 1) {
            return null;
        }

        $paid = (int) $invoice->paid + $amount;
        $settled = $paid >= (int) $invoice->payable;
        $receipt = 'RCP-'.now()->format('ymdHis').'-'.random_int(100, 999);

        DB::transaction(function () use ($invoice, $amount, $method, $paid, $settled, $receipt) {
            DB::table('fee_invoices')->where('id', $invoice->id)->update(['paid' => $paid, 'status' => $settled ? 'paid' : 'partial']);
            DB::table('fee_payments')->insert([
                'user_id' => $invoice->user_id,
                'receipt_no' => $receipt,
                'paid_on' => now()->toDateString(),
                'method' => $method,
                'amount' => $amount,
                'late_fee' => 0,
                'status' => 'posted',
            ]);

            $profile = StudentProfile::query()->where('user_id', $invoice->user_id)->first();
            $profile?->update([
                'dues_total' => max(0, (int) $profile->dues_total - $amount),
                'dues_count' => $settled ? max(0, (int) $profile->dues_count - 1) : $profile->dues_count,
            ]);
        });

        return $receipt;
    }
}
