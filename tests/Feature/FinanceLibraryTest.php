<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\StudentEnrollment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class FinanceLibraryTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->actingAs($admin = User::factory()->create(['role' => 'admin']));

        return $admin;
    }

    private function student(string $class = 'Class 6', string $section = 'A'): User
    {
        return StudentEnrollment::enroll(['name' => 'Kid '.random_int(1, 99999), 'class_name' => $class, 'section' => $section, 'gender' => 'Male']);
    }

    // ---- fees

    public function test_generate_invoices_applies_waivers_and_skips_duplicates(): void
    {
        $this->admin();
        $a = $this->student();
        $b = $this->student();
        $this->student('Class 7');
        $head = DB::table('fee_heads')->where('name', 'Monthly Tuition')->value('id');
        DB::table('fee_waivers')->insert(['user_id' => $a->id, 'kind' => 'percent', 'value' => 50, 'active' => true, 'created_at' => now(), 'updated_at' => now()]);

        $payload = ['fee_head_id' => $head, 'class_name' => 'Class 6', 'label' => 'October 2026 Tuition', 'due_on' => '2026-10-31'];
        $this->post('/app/fees/generate', $payload)->assertSessionHas('generated', ['created' => 2, 'skipped' => 0, 'waived' => 1]);

        $this->assertSame(750, (int) DB::table('fee_invoices')->where('user_id', $a->id)->value('payable'));
        $this->assertSame(1500, (int) DB::table('fee_invoices')->where('user_id', $b->id)->value('payable'));
        $this->assertSame(750, (int) $a->profile()->first()->dues_total);

        $this->post('/app/fees/generate', $payload)->assertSessionHas('generated', ['created' => 0, 'skipped' => 2, 'waived' => 0]);
        $this->assertSame(2, DB::table('fee_invoices')->count());
    }

    public function test_waiver_validation_and_lookup(): void
    {
        $this->admin();
        $s = $this->student();

        $this->post('/app/fees/waivers', ['student' => 'nobody', 'kind' => 'fixed', 'value' => 100])->assertSessionHasErrors('student');
        $this->post('/app/fees/waivers', ['student' => $s->profile()->first()->admission_no, 'kind' => 'percent', 'value' => 150])->assertSessionHasErrors('value');
        $this->post('/app/fees/waivers', ['student' => $s->profile()->first()->admission_no, 'kind' => 'fixed', 'value' => 200, 'reason' => 'Orphan'])->assertSessionHasNoErrors();

        $id = DB::table('fee_waivers')->value('id');
        $this->put("/app/fees/waivers/{$id}")->assertRedirect();
        $this->assertSame(0, (int) DB::table('fee_waivers')->value('active'));
        $this->delete("/app/fees/waivers/{$id}")->assertRedirect();
        $this->assertSame(0, DB::table('fee_waivers')->count());
    }

    public function test_online_transaction_confirmation_posts_payment_once(): void
    {
        $this->admin();
        $s = $this->student();
        $invoice = DB::table('fee_invoices')->insertGetId(['user_id' => $s->id, 'invoice_no' => 'INV-T-1', 'label' => 'Test', 'due_on' => '2026-10-31', 'payable' => 1000, 'paid' => 0, 'status' => 'unpaid']);

        $this->post('/app/fees/online', ['invoice_no' => 'INV-T-1', 'gateway' => 'bKash', 'reference' => 'TX1', 'amount' => 5000])->assertSessionHasErrors('amount');
        $this->post('/app/fees/online', ['invoice_no' => 'INV-T-1', 'gateway' => 'bKash', 'reference' => 'TX1', 'amount' => 600])->assertSessionHasNoErrors();
        $this->post('/app/fees/online', ['invoice_no' => 'INV-T-1', 'gateway' => 'bKash', 'reference' => 'TX1', 'amount' => 100])->assertSessionHasErrors('reference');

        $tx = DB::table('online_transactions')->value('id');
        $this->put("/app/fees/online/{$tx}", ['status' => 'success'])->assertSessionHasNoErrors();
        $this->assertSame(['partial', 600], [DB::table('fee_invoices')->find($invoice)->status, (int) DB::table('fee_invoices')->find($invoice)->paid]);
        $this->assertSame('bKash', DB::table('fee_payments')->value('method'));

        $this->put("/app/fees/online/{$tx}", ['status' => 'success'])->assertSessionHas('error');
        $this->assertSame(1, DB::table('fee_payments')->count());
    }

    public function test_fee_structure_and_gateway_settings(): void
    {
        $this->admin();

        $this->post('/app/fees/structure', ['name' => 'Lab Fee', 'class_name' => 'Class 9', 'amount' => 300, 'active' => true])->assertSessionHasNoErrors();
        $this->assertSame('Class 9', DB::table('fee_heads')->where('name', 'Lab Fee')->value('class_name'));
        $this->get('/app/fees/structure')->assertInertia(fn (Assert $p) => $p->where('definition.fields.1.type', 'select'));

        $this->post('/app/fees/gateway', ['gateway_enabled' => true, 'gateway_provider' => 'bKash', 'gateway_mode' => 'live', 'gateway_merchant_id' => 'M1'])->assertSessionHasNoErrors();
        $this->post('/app/fees/gateway', ['gateway_provider' => 'Paypal', 'gateway_mode' => 'live'])->assertSessionHasErrors('gateway_provider');
        $this->get('/app/fees/gateway')->assertInertia(fn (Assert $p) => $p->where('values.gateway_provider', 'bKash')->where('values.gateway_enabled', true));
    }

    // ---- accounting

    private function voucher(array $lines, string $date = '2026-10-10', string $type = 'receipt'): \Illuminate\Testing\TestResponse
    {
        return $this->post('/app/accounting/vouchers', ['date' => $date, 'type' => $type, 'narration' => 'Test', 'lines' => $lines]);
    }

    private function acc(string $code): int
    {
        return (int) DB::table('accounts')->where('code', $code)->value('id');
    }

    public function test_voucher_must_balance(): void
    {
        $this->admin();
        $cash = $this->acc('1001');
        $income = $this->acc('4001');

        $this->voucher([['account_id' => $cash, 'debit' => 100, 'credit' => 0], ['account_id' => $income, 'debit' => 0, 'credit' => 90]])->assertSessionHasErrors('lines');
        $this->voucher([['account_id' => $cash, 'debit' => 100, 'credit' => 100], ['account_id' => $income, 'debit' => 0, 'credit' => 0]])->assertSessionHasErrors('lines');
        $this->voucher([['account_id' => $cash, 'debit' => 100]])->assertSessionHasErrors('lines');
        $this->assertSame(0, DB::table('vouchers')->count());

        $this->voucher([['account_id' => $cash, 'debit' => 100, 'credit' => 0], ['account_id' => $income, 'debit' => 0, 'credit' => 100]])->assertSessionHasNoErrors();
        $this->assertSame('V-2026-0001', DB::table('vouchers')->value('voucher_no'));
        $this->assertSame(2, DB::table('voucher_lines')->count());
    }

    public function test_reports_reconcile(): void
    {
        $this->admin();
        [$cash, $income, $salary] = [$this->acc('1001'), $this->acc('4001'), $this->acc('5001')];

        $this->voucher([['account_id' => $cash, 'debit' => 5000, 'credit' => 0], ['account_id' => $income, 'debit' => 0, 'credit' => 5000]], '2026-09-20');
        $this->voucher([['account_id' => $cash, 'debit' => 2000, 'credit' => 0], ['account_id' => $income, 'debit' => 0, 'credit' => 2000]], '2026-10-05');
        $this->voucher([['account_id' => $salary, 'debit' => 1500, 'credit' => 0], ['account_id' => $cash, 'debit' => 0, 'credit' => 1500]], '2026-10-06', 'payment');

        $this->get('/app/accounting/cash-book?from=2026-10-01&to=2026-10-31')->assertInertia(fn (Assert $p) => $p
            ->where('summary.0.value', 5000)->where('summary.1.value', 2000)->where('summary.2.value', 1500)->where('summary.3.value', 5500)
            ->has('sections.0.rows', 3));

        $this->get('/app/accounting/trial-balance?as_of=2026-12-31')->assertInertia(fn (Assert $p) => $p
            ->where('summary.0.value', 7000)->where('summary.1.value', 7000)->where('summary.2.value', 0));
        $this->get('/app/accounting/trial-balance?as_of=2026-09-30')->assertInertia(fn (Assert $p) => $p->where('summary.0.value', 5000));

        $this->get('/app/accounting/income-expense?from=2026-01-01&to=2026-12-31')->assertInertia(fn (Assert $p) => $p
            ->where('summary.0.value', 7000)->where('summary.1.value', 1500)->where('summary.2.label', 'Surplus')->where('summary.2.value', 5500));

        $this->get('/app/accounting/register?type=payment&from=2026-01-01&to=2026-12-31')->assertInertia(fn (Assert $p) => $p->has('sections.0.rows', 1)->where('sections.0.rows.0.amount', 1500));
    }

    public function test_voucher_delete_and_account_guard(): void
    {
        $this->admin();
        $cash = $this->acc('1001');
        $this->voucher([['account_id' => $cash, 'debit' => 10, 'credit' => 0], ['account_id' => $this->acc('4001'), 'debit' => 0, 'credit' => 10]]);

        $this->delete("/app/accounting/chart/{$cash}")->assertSessionHas('error');
        $this->assertNotNull(DB::table('accounts')->find($cash));

        $this->delete('/app/accounting/vouchers/'.DB::table('vouchers')->value('id'))->assertRedirect();
        $this->assertSame(0, DB::table('voucher_lines')->count());
        $this->delete("/app/accounting/chart/{$cash}")->assertSessionMissing('error');
        $this->assertNull(DB::table('accounts')->find($cash));
    }

    // ---- library

    private function member(User $user): string
    {
        $this->post('/app/library/members', ['user_id' => $user->id, 'active' => true])->assertSessionHasNoErrors();

        return (string) DB::table('library_members')->where('user_id', $user->id)->value('card_no');
    }

    private function book(int $copies = 1): int
    {
        $this->post('/app/library/books', ['title' => 'Book '.random_int(1, 9999), 'author' => 'Au', 'copies' => $copies])->assertSessionHasNoErrors();

        return (int) DB::table('library_books')->max('id');
    }

    public function test_library_card_numbering_and_lending_limits(): void
    {
        $this->admin();
        $card = $this->member($this->student());
        $this->assertSame('LIB-00001', $card);
        $single = $this->book(1);

        $this->post('/app/library/loans', ['member' => 'NOPE', 'book_id' => $single])->assertSessionHasErrors('member');
        $this->post('/app/library/loans', ['member' => $card, 'book_id' => $single])->assertSessionHasNoErrors();
        $this->assertSame(0, (int) DB::table('library_books')->where('id', $single)->selectRaw("copies - (select count(*) from library_loans l where l.book_id = library_books.id and l.status != 'returned') as a")->value('a'));

        $other = $this->member($this->student());
        $this->post('/app/library/loans', ['member' => $other, 'book_id' => $single])->assertSessionHasErrors('book_id');

        DB::table('site_settings')->insert(['key' => 'library_max_books', 'value' => '1']);
        $second = $this->book(2);
        $this->post('/app/library/loans', ['member' => $card, 'book_id' => $second])->assertSessionHasErrors('member');
    }

    public function test_returning_late_charges_fine_and_it_can_be_paid(): void
    {
        $this->admin();
        $card = $this->member($this->student());
        $book = $this->book();
        $this->post('/app/library/loans', ['member' => $card, 'book_id' => $book])->assertSessionHasNoErrors();

        $loan = DB::table('library_loans')->value('id');
        DB::table('library_loans')->where('id', $loan)->update(['due_on' => today()->subDays(4)->toDateString()]);

        $this->get('/app/library/fines')->assertInertia(fn (Assert $p) => $p->where('rows.0.accrued', 20));
        $this->put("/app/library/loans/{$loan}/return")->assertSessionHas('status');
        $row = DB::table('library_loans')->find($loan);
        $this->assertSame(['returned', 20, 0], [$row->status, (int) $row->fine, (int) $row->fine_paid]);

        $this->put("/app/library/loans/{$loan}/return")->assertSessionHas('error');
        $this->get('/app/library/fines')->assertInertia(fn (Assert $p) => $p->has('unpaid', 1));
        $this->put("/app/library/loans/{$loan}/fine")->assertSessionHasNoErrors();
        $this->get('/app/library/fines')->assertInertia(fn (Assert $p) => $p->has('unpaid', 0));
    }

    public function test_reservations_flow_and_fulfilment_on_lending(): void
    {
        $this->admin();
        $card = $this->member($this->student());
        $book = $this->book();

        $this->post('/app/library/reservations', ['member' => $card, 'book_id' => $book])->assertSessionHasNoErrors();
        $this->post('/app/library/reservations', ['member' => $card, 'book_id' => $book])->assertSessionHasErrors('book_id');
        $id = DB::table('library_reservations')->value('id');
        $this->put("/app/library/reservations/{$id}", ['status' => 'ready'])->assertSessionHasNoErrors();

        $this->post('/app/library/loans', ['member' => $card, 'book_id' => $book])->assertSessionHasNoErrors();
        $this->assertSame('fulfilled', DB::table('library_reservations')->value('status'));
        $this->put("/app/library/reservations/{$id}", ['status' => 'cancelled'])->assertNotFound();
    }

    public function test_library_settings_and_staff_access(): void
    {
        $this->admin();
        $this->post('/app/library/settings', ['library_loan_days' => 10, 'library_fine_per_day' => 7, 'library_max_books' => 2])->assertSessionHasNoErrors();
        $this->post('/app/library/settings', ['library_loan_days' => 0, 'library_fine_per_day' => 7, 'library_max_books' => 2])->assertSessionHasErrors('library_loan_days');
        $this->get('/app/library')->assertInertia(fn (Assert $p) => $p->where('loanDays', 10));

        $this->actingAs(User::factory()->create(['role' => 'teacher']));
        foreach (['/app/library', '/app/library/desk', '/app/library/fines'] as $path) {
            $this->get($path)->assertOk();
        }
        foreach (['/app/library/reservations', '/app/library/books'] as $path) {
            $this->get($path)->assertOk();
        }
        foreach (['/app/library/settings', '/app/accounting', '/app/fees/generate', '/app/fees/gateway'] as $path) {
            $this->get($path)->assertForbidden();
        }
    }

    // ---- academic structure

    public function test_academic_pages_and_single_current_session(): void
    {
        $this->admin();

        foreach (['sessions' => 1, 'classes' => 10, 'sections' => 20, 'subjects' => 6, 'assignments' => 0] as $resource => $count) {
            $this->get("/app/academic/{$resource}")->assertOk()->assertInertia(fn (Assert $p) => $p->has('rows', $count));
        }

        $this->post('/app/academic/sessions', ['name' => '2027', 'starts_on' => '2027-01-01', 'ends_on' => '2027-12-31', 'is_current' => true])->assertSessionHasNoErrors();
        $this->assertSame(1, DB::table('academic_sessions')->where('is_current', true)->count());
        $this->assertSame('2027', DB::table('academic_sessions')->where('is_current', true)->value('name'));
        $this->post('/app/academic/sessions', ['name' => '2028', 'starts_on' => '2028-06-01', 'ends_on' => '2028-01-01'])->assertSessionHasErrors('ends_on');
    }

    public function test_academic_assignments_sections_and_guards(): void
    {
        $this->admin();
        $teacher = User::factory()->create(['role' => 'teacher']);
        $subject = DB::table('academic_subjects')->where('code', 'MAT')->value('id');

        $this->post('/app/academic/assignments', ['user_id' => $teacher->id, 'class_name' => 'Class 6', 'section' => 'A', 'subject_id' => $subject])->assertSessionHasNoErrors();
        $this->get('/app/academic/assignments')->assertInertia(fn (Assert $p) => $p->where('rows.0.teacher_name', $teacher->name)->where('rows.0.subject_name', 'Mathematics'));

        $this->delete("/app/academic/subjects/{$subject}")->assertSessionHas('error');
        $this->post('/app/academic/sections', ['class_name' => 'Class 99', 'name' => 'C', 'capacity' => 30])->assertSessionHasErrors('class_name');
        $this->post('/app/academic/subjects', ['name' => 'Art', 'code' => 'ART', 'kind' => 'theory', 'full_marks' => 50, 'pass_marks' => 80])->assertSessionHasErrors('pass_marks');

        $class = DB::table('academic_classes')->where('name', 'Class 1')->value('id');
        $this->delete("/app/academic/classes/{$class}")->assertSessionHas('error');
    }

    public function test_staff_can_only_read_classes_sections_subjects(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'teacher']));

        foreach (['classes', 'sections', 'subjects'] as $resource) {
            $this->get("/app/academic/{$resource}")->assertOk()->assertInertia(fn (Assert $p) => $p->where('readOnly', true));
        }
        $this->get('/app/academic/sessions')->assertForbidden();
        $this->post('/app/academic/classes', ['name' => 'Class 11', 'sort_order' => 11])->assertForbidden();
    }
}
