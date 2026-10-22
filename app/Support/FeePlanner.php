<?php

namespace App\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Works out what one student is charged for a set of fee heads, and how much each waiver takes off. */
class FeePlanner
{
    public const CATEGORIES = [
        'tuition' => 'Tuition',
        'admission' => 'Admission',
        'lab' => 'Lab',
        'exam' => 'Exam',
        'library' => 'Library',
        'sports' => 'Sports & activities',
        'transport' => 'Transport',
        'other' => 'Other',
    ];

    public const FREQUENCIES = [
        'monthly' => 'Every month',
        'term' => 'Every term',
        'yearly' => 'Once a year',
        'one_time' => 'One time (at admission)',
        'per_exam' => 'Every exam',
    ];

    public const WAIVER_TYPES = ['scholarship' => 'Scholarship / merit', 'sibling' => 'Sibling discount', 'need' => 'Financial need', 'staff_child' => 'Staff child', 'other' => 'Other'];

    /**
     * @param  Collection<int, object>  $heads  fee heads to charge
     * @param  Collection<int, object>  $waivers  the student's active waivers
     * @param  array<int, bool>  $assigned  head ids the student has been given (for optional heads)
     * @return list<array{fee_head_id: int, category: string, label: string, amount: int, discount: int, net: int, discount_note: ?string}>
     */
    public static function items(string $class, Collection $heads, Collection $waivers, array $assigned = []): array
    {
        $items = [];
        foreach ($heads as $head) {
            if ($head->class_name && $head->class_name !== $class) {
                continue;
            }
            if ($head->optional && ! isset($assigned[$head->id])) {
                continue;
            }

            $amount = (int) $head->amount;
            [$discount, $notes] = self::discount($amount, $head->category, $waivers);

            $items[] = [
                'fee_head_id' => (int) $head->id, 'category' => $head->category, 'label' => $head->name, 'amount' => $amount, 'discount' => $discount,
                'net' => $amount - $discount, 'discount_note' => $notes ?: null,
            ];
        }

        return $items;
    }

    /**
     * What the active waivers take off one charge.
     *
     * @return array{0: int, 1: string} the amount off, and a short reason such as "Scholarship / merit 25%"
     */
    public static function discount(int $amount, string $category, Collection $waivers): array
    {
        $discount = 0;
        $notes = [];
        foreach ($waivers as $w) {
            if ($w->applies_to !== 'all' && $w->applies_to !== $category) {
                continue;
            }
            $cut = $w->kind === 'percent' ? (int) round($amount * $w->value / 100) : (int) $w->value;
            $cut = min($cut, $amount - $discount);
            if ($cut > 0) {
                $discount += $cut;
                $notes[] = (self::WAIVER_TYPES[$w->type] ?? 'Discount').($w->kind === 'percent' ? " {$w->value}%" : '');
            }
        }

        return [$discount, implode(', ', $notes)];
    }

    /**
     * After a waiver is added, paused or removed, bring the student's open invoices in line with it so the
     * student, the parent and the dues all show the new amount. Paid invoices are history and are left alone.
     */
    public static function reapply(int $userId): void
    {
        $waivers = self::waiversFor($userId);
        DB::transaction(function () use ($userId, $waivers) {
            foreach (DB::table('fee_invoices')->where('user_id', $userId)->whereIn('status', ['unpaid', 'partial'])->get() as $invoice) {
                $items = DB::table('fee_invoice_items')->where('invoice_id', $invoice->id)->get();
                if ($items->isEmpty()) {
                    continue;
                }
                $payable = 0;
                foreach ($items as $item) {
                    [$discount, $note] = self::discount((int) $item->amount, $item->category, $waivers);
                    DB::table('fee_invoice_items')->where('id', $item->id)->update(['discount' => $discount, 'net' => (int) $item->amount - $discount, 'discount_note' => $note ?: null]);
                    $payable += (int) $item->amount - $discount;
                }
                $payable += (int) $invoice->late_fee;
                $status = (int) $invoice->paid >= $payable ? 'paid' : ((int) $invoice->paid > 0 ? 'partial' : 'unpaid');
                DB::table('fee_invoices')->where('id', $invoice->id)->update(['payable' => $payable, 'status' => $status]);
            }

            $open = DB::table('fee_invoices')->where('user_id', $userId)->whereColumn('payable', '>', 'paid')->get(['payable', 'paid', 'due_on']);
            DB::table('student_profiles')->where('user_id', $userId)->update([
                'dues_total' => (int) $open->sum(fn ($i) => $i->payable - $i->paid), 'dues_count' => $open->count(), 'dues_due_date' => $open->min('due_on'),
            ]);
        });
    }

    /** @return Collection<int, object> */
    public static function waiversFor(int $userId): Collection
    {
        return DB::table('fee_waivers')->where('user_id', $userId)->where('active', true)->get();
    }

    /** Head ids a student has been individually given. @return array<int, bool> */
    public static function assignedFor(int $userId): array
    {
        return DB::table('fee_assignments')->where('user_id', $userId)->pluck('fee_head_id')->flip()->map(fn () => true)->all();
    }
}
