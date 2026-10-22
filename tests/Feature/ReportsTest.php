<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\StudentEnrollment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ReportsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->actingAs($admin = User::factory()->create(['role' => 'admin']));

        return $admin;
    }

    private function staff(string $name): User
    {
        return User::factory()->create(['role' => 'teacher', 'name' => $name]);
    }

    private function student(string $name, string $class = 'Class 6', string $section = 'A', string $mobile = '01711111111'): User
    {
        return StudentEnrollment::enroll(['name' => $name, 'class_name' => $class, 'section' => $section, 'gender' => 'Male', 'guardian_name' => $name.' Guardian', 'guardian_mobile' => $mobile]);
    }

    private function set(array $v): void
    {
        foreach ($v as $k => $x) {
            DB::table('site_settings')->updateOrInsert(['key' => $k], ['value' => (string) $x]);
        }
    }

    // ---- staff attendance

    public function test_staff_attendance_defaults_prefills_leave_and_lists_only_active_staff(): void
    {
        $this->admin();
        $a = $this->staff('Anna');
        $b = $this->staff('Bela');
        $this->staff('Gone')->update(['is_active' => false]);
        DB::table('leave_requests')->insert(['user_id' => $b->id, 'leave_type' => 'Sick Leave', 'start_date' => '2026-10-19', 'end_date' => '2026-10-21', 'days' => 3, 'reason' => 'Flu', 'status' => 'approved', 'created_at' => now(), 'updated_at' => now()]);
        DB::table('leave_requests')->insert(['user_id' => $a->id, 'leave_type' => 'Casual Leave', 'start_date' => '2026-10-20', 'end_date' => '2026-10-20', 'days' => 1, 'reason' => 'x', 'status' => 'pending', 'created_at' => now(), 'updated_at' => now()]);

        $this->get('/app/attendance/staff?date=2026-10-20')->assertInertia(fn (Assert $p) => $p->has('rows', 3 - 1 + 1 - 1 + 0)
            ->where('rows.0.name', 'Anna')->where('rows.0.status', 'present')->where('rows.0.on_leave', false)->where('rows.0.marked', false)
            ->where('rows.1.name', 'Bela')->where('rows.1.status', 'leave')->where('rows.1.on_leave', true));
        $this->get('/app/attendance/staff?date=2026-10-25')->assertInertia(fn (Assert $p) => $p->where('rows.1.status', 'present')->where('rows.1.on_leave', false));
    }

    public function test_staff_attendance_is_saved_updated_and_validated(): void
    {
        $this->admin();
        $a = $this->staff('Anna');
        $b = $this->staff('Bela');
        $student = $this->student('Kid');

        $mark = fn (array $m, string $d = '2026-10-20') => $this->post('/app/attendance/staff', ['date' => $d, 'marks' => $m]);

        $mark([['user_id' => $a->id, 'status' => 'late', 'note' => 'Bus broke down'], ['user_id' => $b->id, 'status' => 'present']])->assertSessionHasNoErrors();
        $this->assertSame(['late', 'Bus broke down'], [DB::table('staff_attendance')->where('user_id', $a->id)->value('status'), DB::table('staff_attendance')->where('user_id', $a->id)->value('note')]);

        $mark([['user_id' => $a->id, 'status' => 'absent']])->assertSessionHasNoErrors();
        $this->assertSame(2, DB::table('staff_attendance')->count(), 'saving again updates the same row');
        $this->assertSame('absent', DB::table('staff_attendance')->where('user_id', $a->id)->value('status'));

        $mark([['user_id' => $a->id, 'status' => 'holiday']])->assertSessionHasErrors('marks.0.status');
        $mark([['user_id' => $student->id, 'status' => 'present']])->assertSessionHasErrors('marks.0.user_id');
        $mark([])->assertSessionHasErrors('marks');

        $this->get('/app/attendance/staff?date=2026-10-20')->assertInertia(fn (Assert $p) => $p->where('rows.0.marked', true)->where('rows.0.status', 'absent')->where('rows.0.note', ''));
    }

    public function test_staff_month_summary_counts_and_percentages(): void
    {
        $this->admin();
        $a = $this->staff('Anna');
        foreach ([['2026-10-01', 'present'], ['2026-10-02', 'present'], ['2026-10-05', 'late'], ['2026-10-06', 'absent'], ['2026-10-07', 'leave'], ['2026-09-30', 'absent']] as [$d, $s]) {
            DB::table('staff_attendance')->insert(['user_id' => $a->id, 'date' => $d, 'status' => $s, 'created_at' => now(), 'updated_at' => now()]);
        }

        $this->get('/app/attendance/staff?date=2026-10-15')->assertInertia(fn (Assert $p) => $p
            ->where('monthLabel', 'October 2026')->where('month.0.present', 2)->where('month.0.late', 1)->where('month.0.absent', 1)->where('month.0.leave', 1)->where('month.0.percent', 75));
    }

    public function test_staff_punches_mark_present_or_late_and_leave_saved_marks_alone(): void
    {
        $this->admin();
        $this->set(['attendance_late_after' => '08:15']);
        [$early, $late, $saved] = [$this->staff('Early'), $this->staff('Late'), $this->staff('Saved')];
        $kid = $this->student('Student');
        $punch = fn (User $u, string $t) => DB::table('punches')->insert(['user_id' => $u->id, 'punched_at' => "2026-10-20 {$t}:00", 'direction' => 'in', 'created_at' => now(), 'updated_at' => now()]);
        $punch($early, '08:00');
        $punch($late, '08:40');
        $punch($saved, '09:00');
        $punch($kid, '09:00');
        DB::table('staff_attendance')->insert(['user_id' => $saved->id, 'date' => '2026-10-20', 'status' => 'present', 'created_at' => now(), 'updated_at' => now()]);

        $this->post('/app/attendance/staff/punches', ['date' => '2026-10-20'])->assertSessionHas('status', 'Attendance marked from the punch log.');
        $status = fn (User $u) => DB::table('staff_attendance')->where('user_id', $u->id)->value('status');
        $this->assertSame(['present', 'late', 'present'], [$status($early), $status($late), $status($saved)]);
        $this->assertSame(3, DB::table('staff_attendance')->count());
        $this->assertSame(0, DB::table('attendance_days')->count(), 'students are not touched');
    }

    // ---- due list

    private function invoice(User $s, int $payable, int $paid, int $daysOverdue, string $label = 'Tuition'): int
    {
        return DB::table('fee_invoices')->insertGetId([
            'user_id' => $s->id, 'invoice_no' => 'INV-'.random_int(10000, 99999), 'label' => $label, 'due_on' => today()->subDays($daysOverdue)->toDateString(),
            'payable' => $payable, 'paid' => $paid, 'status' => $paid >= $payable ? 'paid' : ($paid > 0 ? 'partial' : 'unpaid'),
        ]);
    }

    public function test_due_list_shows_balances_overdue_days_and_late_fees(): void
    {
        $this->admin();
        $this->set(['late_fee_enabled' => 1, 'late_fee_kind' => 'fixed', 'late_fee_value' => 50, 'late_fee_grace_days' => 0]);
        $a = $this->student('Alice');
        $b = $this->student('Bob', 'Class 7', 'B');
        $gone = $this->student('Gone');
        $gone->profile()->first()->update(['status' => 'alumni']);
        $this->invoice($a, 1000, 400, 10);
        $this->invoice($a, 500, 0, -5, 'Exam');
        $this->invoice($b, 800, 0, 2);
        $this->invoice($a, 300, 300, 20, 'Settled');
        $this->invoice($gone, 999, 0, 9);

        $this->get('/app/fees/dues')->assertInertia(fn (Assert $p) => $p
            ->has('sections.0.rows', 3)
            ->where('sections.0.rows.0.student', 'Alice')->where('sections.0.rows.0.days', 10)->where('sections.0.rows.0.balance', 600)->where('sections.0.rows.0.late', 50)->where('sections.0.rows.0.total', 650)
            ->where('sections.0.rows.1.student', 'Bob')->where('sections.0.rows.1.days', 2)
            ->where('sections.0.rows.2.label', 'Exam')->where('sections.0.rows.2.days', 0)->where('sections.0.rows.2.late', 0)
            ->where('sections.0.footer.balance', 600 + 800 + 500)->where('sections.0.footer.late', 100)
            ->where('summary.0.value', 2)->where('summary.1.value', 1900)->where('summary.2.value', 1400)->where('summary.3.value', 100));
    }

    public function test_due_list_filters(): void
    {
        $this->admin();
        $a = $this->student('Alice');
        $b = $this->student('Bob', 'Class 7', 'B');
        $this->invoice($a, 1000, 0, 10);
        $this->invoice($a, 500, 0, -5, 'Future');
        $this->invoice($b, 800, 0, 2);

        $rows = fn (string $qs) => $this->get('/app/fees/dues?'.$qs)->viewData('page')['props']['sections'][0]['rows'];
        $this->assertCount(1, $rows('class=Class%207'));
        $this->assertCount(2, $rows('class=Class%206'));
        $this->assertCount(1, $rows('section=B'));
        $this->assertCount(2, $rows('overdue=1'));
        $this->assertCount(1, $rows('search=bob'));
        $this->assertCount(1, $rows('search='.DB::table('fee_invoices')->where('label', 'Future')->value('invoice_no')));
        $this->assertCount(3, $rows('class=Class%2099'), 'an unknown class is ignored');
    }

    public function test_reminders_are_queued_once_per_student_with_the_total_due(): void
    {
        $this->admin();
        $a = $this->student('Alice', 'Class 6', 'A', '01711000001');
        $b = $this->student('Bob', 'Class 6', 'A', '');
        $c = $this->student('Cara', 'Class 7', 'A', '01711000003');
        $this->invoice($a, 1000, 0, 5);
        $this->invoice($a, 500, 0, 3, 'Exam');
        $this->invoice($b, 700, 0, 5);
        $this->invoice($c, 200, 0, 5);

        $this->post('/app/fees/dues/remind', ['class' => 'Class 6'])->assertSessionHas('status', 'Reminders queued.');
        $this->assertSame(1, DB::table('sms_logs')->count(), 'Alice once; Bob has no number; Cara is another class');
        $log = DB::table('sms_logs')->first();
        $this->assertSame(['01711000001', 'queued'], [$log->phone, $log->status]);
        $this->assertStringContainsString('Alice Guardian', $log->message);
        $this->assertStringContainsString('Amount due: 1,500.', $log->message);

        $this->post('/app/fees/dues/remind', ['class' => 'Class 6']);
        $this->assertSame(1, DB::table('sms_logs')->count(), 'the same reminder is not sent twice in a day');

        $this->post('/app/fees/dues/remind', []);
        $this->assertSame(2, DB::table('sms_logs')->count(), 'now Cara is texted too');
    }

    public function test_reminders_go_through_the_gateway_when_it_is_on(): void
    {
        $this->admin();
        $this->set(['sms_enabled' => 1, 'sms_provider' => 'bulksmsbd']);
        config(['services.bulksmsbd.key' => 'k']);
        Http::fake(['bulksmsbd.net/*' => Http::response(['response_code' => 202])]);
        $this->invoice($this->student('Alice', 'Class 6', 'A', '01711000001'), 1000, 0, 5);

        $this->post('/app/fees/dues/remind', []);
        $this->assertSame('sent', DB::table('sms_logs')->value('status'));
    }

    // ---- collection report

    private function payment(User $s, string $date, int $amount, string $method = 'Cash', int $late = 0): void
    {
        DB::table('fee_payments')->insert(['user_id' => $s->id, 'receipt_no' => 'RCP-'.random_int(1000, 9999).$amount, 'paid_on' => $date, 'method' => $method, 'amount' => $amount, 'late_fee' => $late, 'status' => 'posted']);
    }

    public function test_collection_report_totals_filters_and_method_breakdown(): void
    {
        $this->admin();
        $a = $this->student('Alice');
        $b = $this->student('Bob', 'Class 7', 'B');
        $this->payment($a, '2026-10-05', 1000, 'Cash');
        $this->payment($a, '2026-10-10', 550, 'bKash', 50);
        $this->payment($b, '2026-10-12', 700, 'Cash');
        $this->payment($b, '2026-09-30', 9999, 'Cash');

        $this->get('/app/fees/payments?from=2026-10-01&to=2026-10-31')->assertInertia(fn (Assert $p) => $p
            ->has('sections.0.rows', 3)->where('sections.0.rows.0.paid_on', '2026-10-12')->where('sections.0.footer.amount', 2250)->where('sections.0.footer.late_fee', 50)
            ->where('sections.1.rows.0.method', 'Cash')->where('sections.1.rows.0.receipts', 2)->where('sections.1.rows.0.amount', 1700)->where('sections.1.rows.1.method', 'bKash')
            ->where('summary.0.value', 2250)->where('summary.1.value', 3)->where('summary.2.value', 50)->where('summary.3.value', 750));

        $rows = fn (string $qs) => $this->get('/app/fees/payments?from=2026-10-01&to=2026-10-31&'.$qs)->viewData('page')['props']['sections'][0]['rows'];
        $this->assertCount(2, $rows('method=Cash'));
        $this->assertCount(1, $rows('class=Class%207'));
        $this->assertCount(0, $rows('method=Cheque'));

        $this->get('/app/fees/payments?from=2026-10-31&to=2026-10-01')->assertInertia(fn (Assert $p) => $p->where('values.from', '2026-10-01')->where('values.to', '2026-10-31'));
        $this->get('/app/fees/payments?from=2026-10-05&to=2026-10-05')->assertInertia(fn (Assert $p) => $p->has('sections.0.rows', 1));
    }

    public function test_collection_report_can_be_exported_as_csv(): void
    {
        $this->admin();
        $this->payment($this->student('Alice'), '2026-10-05', 1000, 'Cash');
        $this->payment($this->student('Bob'), '2026-10-06', 400, 'bKash', 25);

        $response = $this->get('/app/fees/payments/export?from=2026-10-01&to=2026-10-31&method=bKash');
        $response->assertOk()->assertHeader('content-disposition', 'attachment; filename=collection-report.csv');
        $csv = $response->streamedContent();
        $this->assertStringContainsString('Date,Receipt,Student,Class,Method,"Late fee",Amount', $csv);
        $this->assertStringContainsString('Bob', $csv);
        $this->assertStringNotContainsString('Alice', $csv);
        $this->get('/app/fees/payments')->assertInertia(fn (Assert $p) => $p->where('actions.0.href', fn ($h) => str_starts_with($h, '/app/fees/payments/export')));
    }

    public function test_these_reports_are_admin_only(): void
    {
        $this->actingAs($this->staff('Teacher'));

        foreach (['/app/attendance/staff', '/app/fees/dues', '/app/fees/payments', '/app/fees/payments/export'] as $path) {
            $this->assertContains($this->get($path)->getStatusCode(), [403, 404], $path);
        }
        $this->post('/app/fees/dues/remind', [])->assertForbidden();
        $this->post('/app/attendance/staff', ['date' => '2026-10-20', 'marks' => []])->assertForbidden();
        $this->post('/app/attendance/staff/punches', ['date' => '2026-10-20'])->assertForbidden();
    }
}
