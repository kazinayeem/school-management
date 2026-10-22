<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\MailSettings;
use App\Support\StudentEnrollment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SettingsTakeEffectTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->actingAs($admin = User::factory()->create(['role' => 'admin']));

        return $admin;
    }

    private function set(array $values): void
    {
        foreach ($values as $key => $value) {
            DB::table('site_settings')->updateOrInsert(['key' => $key], ['value' => (string) $value]);
        }
    }

    private function student(string $name = 'Kid', string $mobile = '01711111111'): User
    {
        return StudentEnrollment::enroll(['name' => $name, 'class_name' => 'Class 6', 'section' => 'A', 'gender' => 'Male', 'guardian_name' => 'Guardian', 'guardian_mobile' => $mobile]);
    }

    private function invoice(User $s, int $daysOverdue, int $payable = 1000, int $paid = 0): int
    {
        $id = DB::table('fee_invoices')->insertGetId([
            'user_id' => $s->id, 'invoice_no' => 'INV-'.random_int(1000, 9999), 'label' => 'Test', 'due_on' => today()->subDays($daysOverdue)->toDateString(),
            'payable' => $payable, 'paid' => $paid, 'status' => $paid > 0 ? 'partial' : 'unpaid',
        ]);
        $s->profile()->first()->update(['dues_total' => $payable - $paid, 'dues_count' => 1]);

        return $id;
    }

    // ---- late fee

    public function test_late_fee_is_added_once_after_the_grace_period(): void
    {
        $this->admin();
        $this->set(['late_fee_enabled' => 1, 'late_fee_kind' => 'fixed', 'late_fee_value' => 100, 'late_fee_grace_days' => 3]);
        $s = $this->student();
        $id = $this->invoice($s, 10);

        $this->get('/app/fees')->assertInertia(fn (Assert $p) => $p->where('module.rows.0.lateFee', 100));

        $this->post("/app/fees/{$id}", ['amount' => 500])->assertRedirect();
        $row = DB::table('fee_invoices')->find($id);
        $this->assertSame([1100, 100, 500, 'partial'], [(int) $row->payable, (int) $row->late_fee, (int) $row->paid, $row->status]);
        $this->assertSame(100, (int) DB::table('fee_payments')->value('late_fee'));
        $this->assertSame(600, (int) $s->profile()->first()->dues_total, 'dues went 1000 + 100 late - 500 paid');

        $this->get('/app/fees')->assertInertia(fn (Assert $p) => $p->where('module.rows.0.lateFee', 0));
        $this->post("/app/fees/{$id}", ['amount' => 600]);
        $row = DB::table('fee_invoices')->find($id);
        $this->assertSame([1100, 100, 1100, 'paid'], [(int) $row->payable, (int) $row->late_fee, (int) $row->paid, $row->status]);
        $this->assertSame([100, 0], DB::table('fee_payments')->orderBy('id')->pluck('late_fee')->map(fn ($v) => (int) $v)->all());
        $this->assertSame(0, (int) $s->profile()->first()->dues_total);
    }

    public function test_percent_late_fee_is_taken_from_the_unpaid_balance(): void
    {
        $this->admin();
        $this->set(['late_fee_enabled' => 1, 'late_fee_kind' => 'percent', 'late_fee_value' => 5, 'late_fee_grace_days' => 0]);
        $id = $this->invoice($this->student(), 2, 2000, 1000);

        $this->get('/app/fees')->assertInertia(fn (Assert $p) => $p->where('module.rows.0.lateFee', 50));
        $this->post("/app/fees/{$id}", ['amount' => 1050]);
        $this->assertSame(['paid', 2050, 50], (function () use ($id) {
            $r = DB::table('fee_invoices')->find($id);

            return [$r->status, (int) $r->payable, (int) $r->late_fee];
        })());
    }

    public function test_no_late_fee_inside_the_grace_period_when_off_or_not_yet_due(): void
    {
        $this->admin();
        $s = $this->student();
        $this->set(['late_fee_enabled' => 1, 'late_fee_kind' => 'fixed', 'late_fee_value' => 100, 'late_fee_grace_days' => 5]);
        $inGrace = $this->invoice($s, 5);
        $this->post("/app/fees/{$inGrace}", ['amount' => 1000]);
        $this->assertSame([1000, 0], [(int) DB::table('fee_invoices')->find($inGrace)->payable, (int) DB::table('fee_invoices')->find($inGrace)->late_fee]);

        $future = $this->invoice($s, -3);
        $this->post("/app/fees/{$future}", ['amount' => 1000]);
        $this->assertSame(0, (int) DB::table('fee_invoices')->find($future)->late_fee);

        $this->set(['late_fee_enabled' => 0]);
        $off = $this->invoice($s, 30);
        $this->post("/app/fees/{$off}", ['amount' => 1000]);
        $this->assertSame(1000, (int) DB::table('fee_invoices')->find($off)->payable);
    }

    public function test_online_payments_also_get_the_late_fee(): void
    {
        $this->admin();
        $this->set(['late_fee_enabled' => 1, 'late_fee_kind' => 'fixed', 'late_fee_value' => 75, 'late_fee_grace_days' => 0]);
        $id = $this->invoice($this->student(), 4);
        $no = DB::table('fee_invoices')->where('id', $id)->value('invoice_no');

        $this->post('/app/fees/online', ['invoice_no' => $no, 'gateway' => 'bKash', 'reference' => 'TX9', 'amount' => 1000])->assertSessionHasNoErrors();
        $this->put('/app/fees/online/'.DB::table('online_transactions')->value('id'), ['status' => 'success'])->assertSessionHasNoErrors();

        $row = DB::table('fee_invoices')->find($id);
        $this->assertSame([1075, 75, 1000, 'partial'], [(int) $row->payable, (int) $row->late_fee, (int) $row->paid, $row->status]);
    }

    // ---- attendance rules

    private function mark(array $marks, string $date = '2026-10-20'): void
    {
        $this->post('/app/attendance', ['section' => 'Class 6-A', 'date' => $date, 'marks' => $marks])->assertRedirect();
    }

    public function test_guardians_are_texted_once_when_a_student_is_marked_absent(): void
    {
        $this->admin();
        $this->set(['attendance_notify_absent' => 1]);
        $a = $this->student('Absent Kid', '01722222222');
        $b = $this->student('Present Kid', '01733333333');

        $this->mark([['user_id' => $a->id, 'status' => 'absent'], ['user_id' => $b->id, 'status' => 'present']]);
        $this->assertSame(1, DB::table('sms_logs')->count());
        $log = DB::table('sms_logs')->first();
        $this->assertSame(['01722222222', 'queued'], [$log->phone, $log->status]);
        $this->assertStringContainsString('Absent Kid', $log->message);
        $this->assertStringContainsString('2026-10-20', $log->message);

        $this->mark([['user_id' => $a->id, 'status' => 'absent']]);
        $this->assertSame(1, DB::table('sms_logs')->count(), 'saving the same absence again does not text twice');

        $this->mark([['user_id' => $a->id, 'status' => 'present']]);
        $this->mark([['user_id' => $a->id, 'status' => 'absent']]);
        $this->assertSame(2, DB::table('sms_logs')->count(), 'a new absence after a correction is a new alert');
    }

    public function test_no_absence_text_when_the_rule_is_off_or_there_is_no_number(): void
    {
        $this->admin();
        $a = $this->student('Kid A', '01744444444');
        $this->mark([['user_id' => $a->id, 'status' => 'absent']]);
        $this->assertSame(0, DB::table('sms_logs')->count());

        $this->set(['attendance_notify_absent' => 1]);
        $b = $this->student('Kid B', '');
        $this->mark([['user_id' => $b->id, 'status' => 'absent']], '2026-10-21');
        $this->assertSame(0, DB::table('sms_logs')->count());
    }

    public function test_register_flags_students_below_the_minimum(): void
    {
        $this->admin();
        $this->set(['attendance_min_percent' => 75]);
        $good = $this->student('Good Kid');
        $poor = $this->student('Poor Kid');
        $new = $this->student('Unmarked Kid');
        foreach (['2026-10-18', '2026-10-19', '2026-10-20', '2026-10-21'] as $i => $date) {
            DB::table('attendance_days')->insert(['user_id' => $good->id, 'date' => $date, 'status' => $i === 3 ? 'late' : 'present']);
            DB::table('attendance_days')->insert(['user_id' => $poor->id, 'date' => $date, 'status' => $i < 2 ? 'absent' : 'present']);
        }
        DB::table('attendance_days')->insert(['user_id' => $good->id, 'date' => '2026-10-22', 'status' => 'leave']);

        $this->get('/app/attendance/register?class=Class%206&from=2026-10-01&to=2026-10-31')->assertInertia(fn (Assert $p) => $p
            ->where('sections.0.rows.0.name', 'Good Kid')->where('sections.0.rows.0.percent', '100%')->where('sections.0.rows.0.leave', 1)->where('sections.0.rows.0.flag', '')
            ->where('sections.0.rows.1.name', 'Poor Kid')->where('sections.0.rows.1.percent', '50%')->where('sections.0.rows.1.flag', 'Below the minimum')
            ->where('sections.0.rows.2.percent', '—')->where('sections.0.rows.2.flag', '')
            ->where('summary.1.value', 1)->where('summary.2.value', 75)->where('summary.3.value', 75));

        $this->set(['attendance_min_percent' => 40]);
        $this->get('/app/attendance/register?class=Class%206&from=2026-10-01&to=2026-10-31')->assertInertia(fn (Assert $p) => $p->where('summary.1.value', 0));
        $this->get('/app/attendance/register?class=Class%206&from=2026-10-20&to=2026-10-31')->assertInertia(fn (Assert $p) => $p->where('sections.0.rows.1.percent', '100%'));
    }

    public function test_punches_mark_present_or_late_by_the_late_after_time(): void
    {
        $this->admin();
        $this->set(['attendance_late_after' => '08:15']);
        $early = $this->student('Early');
        $late = $this->student('Late');
        $manual = $this->student('Manual');
        $teacher = User::factory()->create(['role' => 'teacher']);
        $punch = fn (User $u, string $time, string $dir = 'in') => DB::table('punches')->insert(['user_id' => $u->id, 'punched_at' => "2026-10-20 {$time}:00", 'direction' => $dir, 'created_at' => now(), 'updated_at' => now()]);

        $punch($early, '08:15');
        $punch($early, '07:50');
        $punch($late, '08:16');
        $punch($late, '08:05', 'out');
        $punch($manual, '09:30');
        $punch($teacher, '09:30');
        DB::table('attendance_days')->insert(['user_id' => $manual->id, 'date' => '2026-10-20', 'status' => 'absent']);

        $this->post('/app/attendance/punches', ['date' => '2026-10-20'])->assertSessionHas('status');
        $status = fn (User $u) => DB::table('attendance_days')->where('user_id', $u->id)->where('date', '2026-10-20')->value('status');
        $this->assertSame(['present', 'late', 'absent'], [$status($early), $status($late), $status($manual)]);
        $this->assertNull($status($teacher), 'staff punches are not student attendance');

        $this->post('/app/attendance/punches', ['date' => '2026-10-20'])->assertSessionHas('status');
        $this->assertSame(3, DB::table('attendance_days')->count(), 'running it again changes nothing');

        $this->set(['attendance_late_after' => '07:00']);
        $other = $this->student('Other');
        $punch($other, '07:30');
        $this->post('/app/attendance/punches', ['date' => '2026-10-20']);
        $this->assertSame('late', $status($other));
    }

    public function test_punch_marking_is_admin_only(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'teacher']));
        $this->post('/app/attendance/punches', ['date' => '2026-10-20'])->assertForbidden();
        $this->get('/app/attendance/register')->assertOk();
    }

    // ---- SMTP

    public function test_saved_smtp_settings_configure_the_mailer(): void
    {
        MailSettings::apply();
        $this->assertNotSame('smtp.school.test', config('mail.mailers.smtp.host'), 'nothing changes without a saved host');

        $this->set(['mail_host' => 'smtp.school.test', 'mail_port' => 2525, 'mail_username' => 'mailer', 'mail_encryption' => 'ssl', 'mail_from_address' => 'office@school.test', 'mail_from_name' => 'Borno Office']);
        MailSettings::apply();

        $this->assertSame(['smtp', 'smtp.school.test', 2525, 'smtps', 'mailer', 'office@school.test', 'Borno Office'], [
            config('mail.default'), config('mail.mailers.smtp.host'), config('mail.mailers.smtp.port'), config('mail.mailers.smtp.scheme'),
            config('mail.mailers.smtp.username'), config('mail.from.address'), config('mail.from.name'),
        ]);

        $this->set(['mail_encryption' => 'tls', 'mail_port' => '']);
        MailSettings::apply();
        $this->assertSame(['smtp', 587], [config('mail.mailers.smtp.scheme'), config('mail.mailers.smtp.port')]);
    }

    public function test_the_test_email_needs_a_saved_host_and_reports_failures(): void
    {
        $this->admin();

        $this->post('/app/settings/mail/test', ['email' => 'me@example.com'])->assertSessionHas('error');
        $this->post('/app/settings/mail/test', ['email' => 'nope'])->assertSessionHasErrors('email');

        $this->set(['mail_host' => 'smtp.school.test']);
        \Illuminate\Support\Facades\Mail::fake();
        $this->post('/app/settings/mail/test', ['email' => 'me@example.com'])->assertSessionHas('status', 'Test email sent.');

        \Illuminate\Support\Facades\Mail::shouldReceive('raw')->andThrow(new \RuntimeException('Connection refused'));
        $this->post('/app/settings/mail/test', ['email' => 'me@example.com'])->assertSessionHas('error', fn ($m) => str_contains($m, 'Connection refused'));

        $this->get('/app/settings/mail')->assertInertia(fn (Assert $p) => $p->where('definition.test.url', '/app/settings/mail/test'));
    }

    // ---- SMS gateway

    private function enableSms(string $provider, array $extra = []): void
    {
        $this->set(['sms_enabled' => 1, 'sms_provider' => $provider] + $extra);
    }

    public function test_bulksmsbd_messages_are_delivered_and_failures_are_recorded(): void
    {
        $this->admin();
        $this->enableSms('bulksmsbd', ['sms_sender_id' => 'BORNO']);
        config(['services.bulksmsbd.key' => 'secret-key']);
        Http::fake(['bulksmsbd.net/*' => Http::sequence()->push(['response_code' => 202, 'success_message' => 'ok'])->push(['response_code' => 1007, 'error_message' => 'Balance insufficient'])]);

        $this->post('/app/sms', ['audience' => 'number', 'number' => '01712345678', 'message' => 'Hello'])->assertSessionHas('sms', fn ($r) => $r['sent'] === 1 && $r['failed'] === 0 && $r['queued'] === 0);
        $this->assertSame('sent', DB::table('sms_logs')->value('status'));
        Http::assertSent(fn (HttpRequest $r) => $r['api_key'] === 'secret-key' && $r['number'] === '8801712345678' && $r['senderid'] === 'BORNO' && $r['message'] === 'Hello');

        $this->post('/app/sms', ['audience' => 'number', 'number' => '01812345678', 'message' => 'Again']);
        $second = DB::table('sms_logs')->orderByDesc('id')->first();
        $this->assertSame(['failed', 'Balance insufficient'], [$second->status, $second->detail]);
    }

    public function test_twilio_messages_use_basic_auth_and_an_international_number(): void
    {
        $this->admin();
        $this->enableSms('twilio');
        config(['services.twilio.sid' => 'AC123', 'services.twilio.token' => 'tok', 'services.twilio.from' => '+15005550006']);
        Http::fake(['api.twilio.com/*' => Http::response(['sid' => 'SM1'], 201)]);

        $this->post('/app/sms', ['audience' => 'number', 'number' => '+8801712345678', 'message' => 'Hi there']);
        $this->assertSame('sent', DB::table('sms_logs')->value('status'));
        Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), '/Accounts/AC123/Messages.json') && $r['To'] === '+8801712345678' && $r['From'] === '+15005550006' && $r['Body'] === 'Hi there'
            && $r->hasHeader('Authorization', 'Basic '.base64_encode('AC123:tok')));
    }

    public function test_missing_credentials_or_a_bad_number_fail_without_calling_the_provider(): void
    {
        $this->admin();
        Http::fake();
        $this->enableSms('bulksmsbd');
        config(['services.bulksmsbd.key' => null]);
        $this->post('/app/sms', ['audience' => 'number', 'number' => '01712345678', 'message' => 'x']);
        $this->assertSame('failed', DB::table('sms_logs')->value('status'));
        $this->assertStringContainsString('BULKSMSBD_API_KEY', (string) DB::table('sms_logs')->value('detail'));

        config(['services.bulksmsbd.key' => 'k']);
        DB::table('sms_logs')->insert(['phone' => '12', 'message' => 'x', 'status' => 'queued', 'created_at' => now(), 'updated_at' => now()]);
        $this->post('/app/sms/log/deliver');
        $this->assertStringContainsString('not valid', (string) DB::table('sms_logs')->orderByDesc('id')->value('detail'));
        Http::assertNothingSent();
    }

    public function test_with_no_provider_messages_stay_queued_and_nothing_is_sent(): void
    {
        $this->admin();
        Http::fake();

        $this->post('/app/sms', ['audience' => 'number', 'number' => '01712345678', 'message' => 'x'])->assertSessionHas('sms', fn ($r) => $r['queued'] === 1 && $r['sent'] === 0);
        $this->enableSms('manual');
        $this->post('/app/sms', ['audience' => 'number', 'number' => '01712345678', 'message' => 'y']);
        $this->set(['sms_enabled' => 0, 'sms_provider' => 'twilio']);
        $this->post('/app/sms', ['audience' => 'number', 'number' => '01712345678', 'message' => 'z']);

        $this->assertSame(['queued', 'queued', 'queued'], DB::table('sms_logs')->pluck('status')->all());
        $this->post('/app/sms/log/deliver')->assertSessionHas('error');
        Http::assertNothingSent();
    }

    public function test_queued_messages_are_delivered_in_batches(): void
    {
        $this->admin();
        $this->enableSms('bulksmsbd');
        config(['services.bulksmsbd.key' => 'k']);
        Http::fake(['bulksmsbd.net/*' => Http::response(['response_code' => 202])]);
        DB::table('sms_logs')->insert(array_map(fn ($i) => ['phone' => '017000000'.str_pad((string) $i, 2, '0', STR_PAD_LEFT), 'message' => "m{$i}", 'status' => 'queued', 'created_at' => now(), 'updated_at' => now()], range(1, 60)));

        $this->get('/app/sms/log')->assertInertia(fn (Assert $p) => $p->where('gateway.enabled', true)->where('gateway.provider', 'bulksmsbd')->where('counts.queued', 60));
        $this->post('/app/sms/log/deliver')->assertSessionHas('status', 'Delivery finished.');
        $this->assertSame([50, 10], [DB::table('sms_logs')->where('status', 'sent')->count(), DB::table('sms_logs')->where('status', 'queued')->count()]);
        $this->post('/app/sms/log/deliver');
        $this->assertSame(60, DB::table('sms_logs')->where('status', 'sent')->count());
    }

    public function test_absence_alerts_go_out_through_the_gateway(): void
    {
        $this->admin();
        $this->enableSms('bulksmsbd');
        $this->set(['attendance_notify_absent' => 1]);
        config(['services.bulksmsbd.key' => 'k']);
        Http::fake(['bulksmsbd.net/*' => Http::response(['response_code' => 202])]);
        $s = $this->student('Sick Kid', '01755555555');

        $this->mark([['user_id' => $s->id, 'status' => 'absent']]);
        $this->assertSame('sent', DB::table('sms_logs')->value('status'));
        Http::assertSent(fn (HttpRequest $r) => $r['number'] === '8801755555555' && str_contains($r['message'], 'Sick Kid'));
    }

    public function test_gateway_helpers_are_admin_only(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'teacher']));
        $this->post('/app/sms/log/deliver')->assertForbidden();
        $this->post('/app/settings/mail/test', ['email' => 'a@b.co'])->assertForbidden();
    }
}
