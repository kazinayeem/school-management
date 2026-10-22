<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/** Pages and policies the school edits itself. Anything not edited falls back to the built-in text. */
class SitePages
{
    public const POLICIES = [
        'fee-payment-policy' => ['Fee payment policy', '<h2>When fees are due</h2><p>Tuition is due by the <strong>10th of every month</strong>. Admission and one-time fees are collected once, when the place is confirmed.</p><h2>How to pay</h2><ul><li>Pay at the school accounts office in cash and collect a receipt.</li><li>Pay by <strong>bKash</strong>, <strong>Nagad</strong> or bank deposit, then submit the transaction ID and proof from the parent or student portal.</li><li>A payment is counted once the office approves it, normally within one working day.</li></ul>'],
        'late-fee-policy' => ['Late payment policy', '<p>A reminder is sent through the portal and by SMS when a monthly fee is overdue. If tuition remains unpaid after the <strong>25th</strong>, the office may add the late fee shown on the fee schedule.</p><p>Please contact the office <em>before</em> the due date if you need more time. Hardship cases are reviewed individually.</p>'],
        'refund-policy' => ['Refund policy', '<ul><li>Admission fees are <strong>not refundable</strong> once the place is confirmed.</li><li>Tuition paid in advance is refunded or carried forward when a student leaves, after the office clears dues.</li><li>Payments made twice by mistake are returned in full after verification.</li></ul>'],
        'waiver-and-discount-policy' => ['Waiver & discount policy', '<h2>Who can apply</h2><p>Families may ask for a waiver for reasons such as <strong>sibling discount, merit, financial hardship or staff children</strong>.</p><h2>How it works</h2><ul><li>Each student can have <strong>one active waiver</strong> at a time.</li><li>The office decides the percentage or fixed amount and the period it covers.</li><li>The discount appears on every new invoice and in the parent and student portals.</li></ul>'],
        'attendance-policy' => ['Attendance policy', '<p>Students are expected to attend at least <strong>80%</strong> of school days. An SMS goes to the guardian the same day a child is marked absent. Leave should be requested from the parent portal in advance wherever possible.</p>'],
    ];

    public const ARTICLES = [
        'privacy-policy' => ['Privacy policy', '<p>Application details, guardian numbers and result lookups are used only to run the school. We do not sell this information.</p><ul><li>Admission forms stay with the office</li><li>Result checks use roll number only</li><li>Contact messages are read by the office</li></ul>'],
        'terms-and-conditions' => ['Terms & conditions', '<p>Online admission gives you a tracking number. A place is confirmed only after the office reviews the application.</p><ul><li>A tracking number is not a confirmed seat</li><li>Published results can be checked by roll number</li><li>Fee receipts are issued after a payment is recorded</li></ul>'],
    ];

    public static function seed(): void
    {
        $now = now();
        $i = 0;
        foreach (self::POLICIES as $slug => [$title, $body]) {
            DB::table('website_pages')->updateOrInsert(['slug' => $slug], ['title' => $title, 'body' => $body, 'kind' => 'policy', 'published' => 1, 'sort_order' => ++$i, 'created_at' => $now, 'updated_at' => $now]);
        }
        foreach (self::ARTICLES as $slug => [$title, $body]) {
            DB::table('website_pages')->updateOrInsert(['slug' => $slug], ['title' => $title, 'body' => $body, 'kind' => 'policy', 'published' => 1, 'sort_order' => ++$i, 'created_at' => $now, 'updated_at' => $now]);
        }
    }

    /** @return list<array{slug: string, title: string}> */
    public static function policies(): array
    {
        return DB::table('website_pages')->where('kind', 'policy')->where('published', 1)->orderBy('sort_order')->orderBy('title')->get(['slug', 'title'])
            ->map(fn ($r) => ['slug' => $r->slug, 'title' => $r->title])->all();
    }

    public static function find(string $slug): ?object
    {
        return DB::table('website_pages')->where('slug', $slug)->where('published', 1)->first();
    }

    /** Body as HTML. Plain text saved before the editor existed is turned into paragraphs. */
    public static function html(string $body): string
    {
        return $body === strip_tags($body) ? '<p>'.nl2br(e($body)).'</p>' : Html::clean($body);
    }
}
