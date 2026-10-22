<?php

namespace App\Support;

use App\Models\StudentProfile;
use Illuminate\Support\Facades\DB;

class FeePayments
{
    /**
     * The late fee an invoice would be charged if it were paid today, as set under
     * Settings → Fees & Late Fee. It is charged once, on the unpaid balance, after
     * the due date plus the grace days.
     */
    public static function lateFee(object $invoice): int
    {
        if (SiteSettings::get('late_fee_enabled', '0') !== '1' || (int) ($invoice->late_fee ?? 0) > 0 || blank($invoice->due_on ?? null)) {
            return 0;
        }

        $remaining = (int) $invoice->payable - (int) $invoice->paid;
        $grace = SiteSettings::int('late_fee_grace_days', 0);
        if ($remaining <= 0 || today()->lte(\Illuminate\Support\Carbon::parse($invoice->due_on)->addDays($grace))) {
            return 0;
        }

        $value = SiteSettings::int('late_fee_value', 0);

        return SiteSettings::get('late_fee_kind', 'fixed') === 'percent' ? (int) round($remaining * $value / 100) : $value;
    }

    /**
     * Record a payment against an invoice, updating the invoice, the payment
     * register and the student's dues. A late fee is added to the invoice first
     * when one applies. Returns the receipt number, or null if nothing is left to pay.
     */
    public static function collect(object $invoice, int $amount, string $method): ?string
    {
        $late = self::lateFee($invoice);
        $payable = (int) $invoice->payable + $late;
        $amount = min($amount, max(0, $payable - (int) $invoice->paid));
        if ($amount < 1) {
            return null;
        }

        $paid = (int) $invoice->paid + $amount;
        $settled = $paid >= $payable;
        $receipt = 'RCP-'.now()->format('ymdHis').'-'.random_int(100, 999);

        DB::transaction(function () use ($invoice, $amount, $method, $paid, $payable, $late, $settled, $receipt) {
            DB::table('fee_invoices')->where('id', $invoice->id)->update(['payable' => $payable, 'late_fee' => (int) ($invoice->late_fee ?? 0) + $late, 'paid' => $paid, 'status' => $settled ? 'paid' : 'partial']);
            DB::table('fee_payments')->insert([
                'user_id' => $invoice->user_id,
                'receipt_no' => $receipt,
                'paid_on' => now()->toDateString(),
                'method' => $method,
                'amount' => $amount,
                'late_fee' => $late,
                'status' => 'posted',
            ]);

            $profile = StudentProfile::query()->where('user_id', $invoice->user_id)->first();
            $profile?->update([
                'dues_total' => max(0, (int) $profile->dues_total + $late - $amount),
                'dues_count' => $settled ? max(0, (int) $profile->dues_count - 1) : $profile->dues_count,
            ]);
        });

        return $receipt;
    }
}
