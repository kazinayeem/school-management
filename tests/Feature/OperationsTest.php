<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\BackupService;
use App\Support\StudentEnrollment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OperationsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->actingAs($admin = User::factory()->create(['role' => 'admin']));

        return $admin;
    }

    private function staff(string $name = 'Teacher One'): User
    {
        return User::factory()->create(['role' => 'staff', 'name' => $name, 'phone' => '01712345678']);
    }

    private function student(): User
    {
        return StudentEnrollment::enroll(['name' => 'Kid '.random_int(1, 99999), 'class_name' => 'Class 6', 'section' => 'A', 'gender' => 'Male', 'guardian_name' => 'Parent', 'guardian_mobile' => '01811111111']);
    }

    // ---- generic CRUD pages

    public function test_generic_pages_render_with_seed_data(): void
    {
        $this->admin();

        foreach (['/app/devices' => 0, '/app/devices/people' => 0, '/app/devices/punches' => 0, '/app/certificates/templates' => 3, '/app/leave/types' => 3, '/app/timetable/periods' => 7, '/app/payroll/components' => 4, '/app/sms/templates' => 3, '/app/settings/grading' => 7] as $path => $rows) {
            $this->get($path)->assertOk()->assertInertia(fn (Assert $p) => $p->component('office/website')->has('rows', $rows));
        }
    }

    public function test_device_credentials_punches_and_delete_guard(): void
    {
        $this->admin();
        $teacher = $this->staff();

        $this->post('/app/devices', ['name' => 'Gate 1', 'kind' => 'card', 'serial' => 'SN1', 'active' => true])->assertSessionHasNoErrors();
        $device = DB::table('devices')->value('id');
        $this->post('/app/devices', ['name' => 'Gate 2', 'kind' => 'card', 'serial' => 'SN1'])->assertSessionHasErrors('serial');
        $this->put("/app/devices/{$device}", ['name' => 'Gate 1', 'kind' => 'card', 'serial' => 'SN1', 'active' => false])->assertSessionHasNoErrors();

        $this->post('/app/devices/people', ['user_id' => $teacher->id, 'kind' => 'card', 'code' => '12345', 'active' => true])->assertSessionHasNoErrors();
        $this->post('/app/devices/punches', ['user_id' => $teacher->id, 'device_id' => $device, 'punched_at' => '2026-10-20T08:05', 'direction' => 'in'])->assertSessionHasNoErrors();
        $this->assertSame('2026-10-20 08:05', DB::table('punches')->value('punched_at') ? substr(DB::table('punches')->value('punched_at'), 0, 16) : null);

        $this->get('/app/devices/punches')->assertInertia(fn (Assert $p) => $p->where('rows.0.person_name', 'Teacher One')->where('rows.0.device_name', 'Gate 1'));
        $this->delete("/app/devices/{$device}")->assertSessionHas('error');
    }

    public function test_time_fields_survive_an_edit_round_trip(): void
    {
        $this->admin();
        $id = DB::table('bell_periods')->where('label', '1st Period')->value('id');

        $this->get('/app/timetable/periods')->assertInertia(fn (Assert $p) => $p->where('rows.0.starts_at', fn ($v) => str_starts_with($v, '08:00')));
        $this->put("/app/timetable/periods/{$id}", ['label' => '1st Period', 'starts_at' => '08:00', 'ends_at' => '08:40', 'is_break' => false, 'sort_order' => 1])->assertSessionHasNoErrors();
        $this->put("/app/timetable/periods/{$id}", ['label' => '1st Period', 'starts_at' => '09:00', 'ends_at' => '08:40', 'sort_order' => 1])->assertSessionHasErrors('ends_at');
    }

    public function test_grade_scale_validation_and_leave_type_crud(): void
    {
        $this->admin();

        $this->post('/app/settings/grading', ['grade' => 'X', 'min_marks' => 50, 'max_marks' => 40, 'point' => 2])->assertSessionHasErrors('max_marks');
        $this->post('/app/settings/grading', ['grade' => 'X', 'min_marks' => 10, 'max_marks' => 20, 'point' => '1.50'])->assertSessionHasNoErrors();
        $this->post('/app/leave/types', ['name' => 'Study Leave', 'days_per_year' => 5, 'paid' => false, 'active' => true])->assertSessionHasNoErrors();
        $this->assertSame(0, (int) DB::table('leave_types')->where('name', 'Study Leave')->value('paid'));
    }

    // ---- timetable

    public function test_routine_save_blocks_teacher_clashes(): void
    {
        $this->admin();
        $t = $this->staff('Rahim Sir');

        $cell = fn (int $day, int $sort) => ['day' => $day, 'sort' => $sort, 'subject' => 'Mathematics', 'teacher' => $t->name, 'room' => 'R1'];

        $this->post('/app/timetable', ['class_name' => 'Class 6', 'section' => 'A', 'cells' => [$cell(0, 1), $cell(1, 2)]])->assertSessionHasNoErrors();
        $this->assertSame(35, DB::table('class_periods')->where('class_name', 'Class 6')->where('section', 'A')->count());
        $this->assertSame(0, (int) DB::table('class_periods')->where('class_name', 'Class 6')->where('day', 0)->where('sort', 4)->value('is_break') ? 0 : 1);

        $this->post('/app/timetable', ['class_name' => 'Class 7', 'section' => 'B', 'cells' => [$cell(0, 1)]])->assertSessionHasErrors('cells');
        $this->post('/app/timetable', ['class_name' => 'Class 7', 'section' => 'B', 'cells' => [$cell(0, 2)]])->assertSessionHasNoErrors();
        $this->post('/app/timetable', ['class_name' => 'Class 6', 'section' => 'A', 'cells' => [['day' => 0, 'sort' => 1, 'subject' => 'Nope', 'teacher' => null]]])->assertSessionHasErrors('cells.0.subject');

        $this->get('/app/timetable/teacher?teacher='.urlencode($t->name))->assertInertia(fn (Assert $p) => $p->where('teacherMode', true)->where('cells.0-1.teacher', 'Class 6 A')->where('cells.0-2.teacher', 'Class 7 B'));
    }

    public function test_substitutions(): void
    {
        $this->admin();
        $away = $this->staff('Away Sir');
        $cover = $this->staff('Cover Sir');
        $this->post('/app/timetable', ['class_name' => 'Class 6', 'section' => 'A', 'cells' => [['day' => 0, 'sort' => 1, 'subject' => 'Bangla', 'teacher' => $away->name]]]);

        $sunday = now()->next('Sunday')->toDateString();
        $payload = ['date' => $sunday, 'class_name' => 'Class 6', 'section' => 'A', 'period_label' => '1st Period', 'substitute_user_id' => $cover->id];

        $this->post('/app/timetable/substitutions', ['date' => now()->next('Friday')->toDateString()] + $payload)->assertSessionHasErrors('date');
        $this->post('/app/timetable/substitutions', ['period_label' => '2nd Period'] + $payload)->assertSessionHasErrors('period_label');
        $this->post('/app/timetable/substitutions', ['substitute_user_id' => $away->id] + $payload)->assertSessionHasErrors('substitute_user_id');
        $this->post('/app/timetable/substitutions', $payload)->assertSessionHasNoErrors();
        $this->assertSame('Away Sir', DB::table('substitutions')->value('original_teacher'));
        $this->post('/app/timetable/substitutions', ['section' => 'A'] + $payload)->assertSessionHasErrors('substitute_user_id');
    }

    public function test_staff_can_view_but_not_edit_the_routine(): void
    {
        $this->actingAs($this->staff());

        $this->get('/app/timetable')->assertOk()->assertInertia(fn (Assert $p) => $p->where('readOnly', true));
        $this->get('/app/timetable/teacher')->assertOk();
        $this->post('/app/timetable', ['class_name' => 'Class 6', 'section' => 'A', 'cells' => []])->assertForbidden();
        $this->get('/app/timetable/substitutions')->assertForbidden();
    }

    // ---- certificates

    public function test_certificates_are_issued_with_placeholders_filled(): void
    {
        $this->admin();
        $s = $this->student();
        $template = DB::table('certificate_templates')->where('kind', 'character')->value('id');
        $adm = $s->profile()->first()->admission_no;

        $this->post('/app/certificates', ['template_id' => $template, 'student' => 'ghost', 'date' => '2026-10-10'])->assertSessionHasErrors('student');
        $response = $this->post('/app/certificates', ['template_id' => $template, 'student' => $adm, 'date' => '2026-10-10']);
        $id = DB::table('certificates')->value('id');
        $response->assertRedirect("/app/certificates/{$id}/print");

        $row = DB::table('certificates')->find($id);
        $this->assertSame('CERT-2026-0001', $row->number);
        $this->assertStringContainsString($s->name, $row->body);
        $this->assertStringContainsString($adm, $row->body);
        $this->assertStringContainsString('10 October 2026', $row->body);
        $this->assertStringNotContainsString('{', $row->body);

        $this->get("/app/certificates/{$id}/print")->assertOk()->assertInertia(fn (Assert $p) => $p->component('office/certificate-print')->where('certificate.number', 'CERT-2026-0001'));
        $this->get('/app/certificates/history?search='.urlencode($s->name))->assertInertia(fn (Assert $p) => $p->has('rows', 1));

        $this->post('/app/certificates', ['template_id' => $template, 'student' => $adm, 'date' => '2026-10-11']);
        $this->assertSame('CERT-2026-0002', DB::table('certificates')->max('number'));
    }

    // ---- payroll

    public function test_payroll_run_computes_pays_and_posts_a_voucher(): void
    {
        $this->admin();
        $a = $this->staff('Staff A');
        $house = DB::table('payroll_components')->where('name', 'House Rent')->value('id');
        $medical = DB::table('payroll_components')->where('name', 'Medical Allowance')->value('id');
        $pf = DB::table('payroll_components')->where('name', 'Provident Fund')->value('id');

        $this->post('/app/payroll', ['month' => '2026-10'])->assertSessionHasErrors('month');
        $this->post('/app/payroll/structures', ['user_id' => $a->id, 'basic' => 20000, 'items' => [$house => 40, $medical => 500, $pf => 5]])->assertSessionHasNoErrors();
        $this->get('/app/payroll/structures')->assertInertia(fn (Assert $p) => $p->where('rows.0.net', 20000 + 8000 + 500 - 1000));

        $this->post('/app/payroll', ['month' => '2026-10'])->assertSessionHasNoErrors();
        $slip = DB::table('payslips')->first();
        $this->assertSame([20000, 8500, 1000, 27500], [(int) $slip->basic, (int) $slip->earnings, (int) $slip->deductions, (int) $slip->net]);
        $this->post('/app/payroll', ['month' => '2026-10'])->assertSessionHasErrors('month');
        $this->post('/app/payroll', ['month' => '2026-13'])->assertSessionHasErrors('month');

        $run = DB::table('payroll_runs')->value('id');
        $this->put("/app/payroll/{$run}/pay")->assertSessionHasNoErrors();
        $this->put("/app/payroll/{$run}/pay")->assertSessionHas('error');
        $this->delete("/app/payroll/{$run}")->assertSessionHas('error');

        $line = DB::table('voucher_lines')->where('debit', 27500)->first();
        $this->assertNotNull($line);
        $this->assertSame(27500, (int) DB::table('voucher_lines')->where('credit', 27500)->value('credit'));
        $this->get('/app/accounting/trial-balance')->assertInertia(fn (Assert $p) => $p->where('summary.2.value', 0));
    }

    public function test_draft_payroll_can_be_deleted(): void
    {
        $this->admin();
        $a = $this->staff();
        $this->post('/app/payroll/structures', ['user_id' => $a->id, 'basic' => 1000]);
        $this->post('/app/payroll', ['month' => '2026-09']);

        $this->delete('/app/payroll/'.DB::table('payroll_runs')->value('id'))->assertRedirect('/app/payroll');
        $this->assertSame(0, DB::table('payslips')->count());
    }

    // ---- sms

    public function test_sms_queues_personalised_messages(): void
    {
        $this->admin();
        $s = $this->student();
        $this->staff();

        $this->post('/app/sms', ['audience' => 'guardians', 'class_name' => 'Class 6', 'message' => 'Hi {guardian}, {student} in {class}'])->assertSessionHas('sms', ['queued' => 1, 'skipped' => 0, 'sent' => 0, 'failed' => 0]);
        $this->assertSame("Hi Parent, {$s->name} in Class 6 A", DB::table('sms_logs')->value('message'));

        $this->post('/app/sms', ['audience' => 'staff', 'message' => 'Meeting at 3'])->assertSessionHas('sms');
        $this->post('/app/sms', ['audience' => 'number', 'number' => 'abc', 'message' => 'x'])->assertSessionHasErrors('number');
        $this->post('/app/sms', ['audience' => 'number', 'number' => '+8801700000000', 'message' => 'x'])->assertSessionHasNoErrors();
        $this->post('/app/sms', ['audience' => 'guardians', 'class_name' => 'Class 9', 'message' => 'x'])->assertSessionHasErrors('audience');
        $this->assertSame(3, DB::table('sms_logs')->count());

        $id = DB::table('sms_logs')->value('id');
        $this->put("/app/sms/log/{$id}", ['status' => 'sent'])->assertSessionHasNoErrors();
        $this->put("/app/sms/log/{$id}", ['status' => 'failed'])->assertNotFound();
        $this->get('/app/sms/log?status=queued')->assertInertia(fn (Assert $p) => $p->has('rows', 2)->where('counts.sent', 1));
    }

    // ---- system

    public function test_audit_log_records_changes_and_filters(): void
    {
        $admin = $this->admin();

        $this->post('/app/leave/types', ['name' => 'Study', 'days_per_year' => 3, 'paid' => true, 'active' => true]);
        $id = DB::table('leave_types')->where('name', 'Study')->value('id');
        $this->put("/app/leave/types/{$id}", ['name' => 'Study', 'days_per_year' => 4, 'paid' => true, 'active' => true]);
        $this->delete("/app/leave/types/{$id}");

        $this->assertSame(['created', 'updated', 'deleted'], DB::table('audit_logs')->orderBy('id')->pluck('action')->all());
        $this->assertSame($admin->id, (int) DB::table('audit_logs')->value('user_id'));
        $this->get('/app/system/audit?action=deleted')->assertInertia(fn (Assert $p) => $p->has('sections.0.rows', 1)->where('sections.0.rows.0.who', $admin->name));
        $this->get('/app/system/audit?search=zzz')->assertInertia(fn (Assert $p) => $p->has('sections.0.rows', 0));
    }

    public function test_role_permissions_control_the_staff_menu(): void
    {
        $this->admin();
        $this->post('/app/system/roles', ['permissions' => ['students', 'notices']])->assertSessionHasNoErrors();
        $this->post('/app/system/roles', ['permissions' => ['nonsense']])->assertSessionHasErrors('permissions.0');

        $this->actingAs($this->staff());
        $this->get('/app/students')->assertOk();
        $this->get('/app/library')->assertForbidden();
        $this->get('/app/timetable')->assertForbidden();
        $this->get('/app')->assertInertia(fn (Assert $p) => $p->has('navigation', 3));

        $this->actingAs(User::where('role', 'admin')->first());
        $this->post('/app/system/roles', ['permissions' => []])->assertSessionHasNoErrors();
        $this->assertSame(0, DB::table('role_permissions')->where('role', 'staff')->count());
    }

    public function test_settings_forms_validate_and_languages_are_enforced(): void
    {
        $this->admin();

        $this->post('/app/settings/general', ['school_name' => 'Borno High', 'currency' => 'Tk', 'timezone' => 'Asia/Dhaka'])->assertSessionHasNoErrors();
        $this->post('/app/settings/general', ['school_name' => '', 'currency' => 'Tk', 'timezone' => 'Mars/Base'])->assertSessionHasErrors(['school_name', 'timezone']);
        $this->post('/app/settings/fees', ['late_fee_enabled' => true, 'late_fee_kind' => 'percent', 'late_fee_value' => 5, 'late_fee_grace_days' => 3])->assertSessionHasNoErrors();
        $this->get('/app/settings/fees')->assertInertia(fn (Assert $p) => $p->where('values.late_fee_enabled', true)->where('values.late_fee_value', '5'));
        $this->post('/app/settings/attendance', ['attendance_late_after' => '8am', 'attendance_min_percent' => 75])->assertSessionHasErrors('attendance_late_after');
        $this->post('/app/settings/mail', ['mail_encryption' => 'tls', 'mail_port' => 587])->assertSessionHasNoErrors();
        $this->post('/app/sms/settings', ['sms_provider' => 'twilio', 'sms_enabled' => true])->assertSessionHasNoErrors();
        $this->post('/app/settings/appearance', ['default_theme' => 'dark'])->assertSessionHasNoErrors();

        $this->post('/app/settings/languages', ['lang_en' => false, 'lang_bn' => false, 'lang_ar' => false, 'default_language' => 'en'])->assertSessionHasErrors('lang_en');
        $this->post('/app/settings/languages', ['lang_en' => true, 'lang_bn' => false, 'lang_ar' => false, 'default_language' => 'bn'])->assertSessionHasErrors('default_language');
        $this->post('/app/settings/languages', ['lang_en' => true, 'lang_bn' => true, 'lang_ar' => false, 'default_language' => 'bn'])->assertSessionHasNoErrors();
        $this->get('/app')->assertInertia(fn (Assert $p) => $p->where('enabledLanguages', ['en', 'bn']));
        $this->post('/logout');
        $this->get('/login')->assertSee('theme:"dark"', false)->assertSee('locale:"bn"', false);
    }

    public function test_backup_service_creates_downloads_restores_and_rejects_bad_names(): void
    {
        $dir = sys_get_temp_dir().'/borno-backup-test-'.uniqid();
        $db = $dir.'.sqlite';
        file_put_contents($db, 'original-data');
        config(['database.default' => 'sqlite']);
        $service = new BackupService($db, $dir);

        $this->assertTrue($service->supported());
        $name = $service->create();
        $this->assertSame([$name], array_column($service->all(), 'name'));

        file_put_contents($db, 'changed-data');
        $safety = $service->restore($name);
        $this->assertSame('original-data', file_get_contents($db));
        $this->assertSame('changed-data', file_get_contents($dir.'/'.$safety));

        foreach (['../etc/passwd', 'backup-x.sqlite', 'backup-20260101-000000-001.sqlite'] as $bad) {
            try {
                $service->path($bad);
                $this->fail("Accepted {$bad}");
            } catch (\RuntimeException $e) {
                $this->assertSame('That backup does not exist.', $e->getMessage());
            }
        }

        $service->delete($name);
        $this->assertCount(1, $service->all());

        array_map('unlink', glob($dir.'/*'));
        rmdir($dir);
        unlink($db);
    }

    public function test_backup_page_reports_unsupported_in_memory_database(): void
    {
        $this->admin();

        $this->get('/app/settings/backup')->assertInertia(fn (Assert $p) => $p->where('supported', false));
        $this->post('/app/settings/backup')->assertSessionHas('error');
        $this->post('/app/settings/backup/backup-20260101-000000-001.sqlite/restore', ['confirm' => 'nope'])->assertSessionHasErrors('confirm');
        $this->post('/app/settings/backup/backup-20260101-000000-001.sqlite/restore', ['confirm' => 'RESTORE'])->assertSessionHas('error');
    }

    public function test_all_of_these_pages_are_admin_only(): void
    {
        $this->actingAs($this->staff());

        foreach (['/app/devices', '/app/devices/people', '/app/certificates', '/app/certificates/history', '/app/payroll', '/app/payroll/structures', '/app/sms', '/app/sms/log', '/app/system/audit', '/app/system/roles', '/app/settings/backup', '/app/settings/general', '/app/sms/settings', '/app/leave/types', '/app/settings/grading'] as $path) {
            $this->assertContains($this->get($path)->getStatusCode(), [403, 404], $path);
        }
        $this->post('/app/system/roles', ['permissions' => []])->assertForbidden();
        $this->post('/app/sms', ['audience' => 'staff', 'message' => 'x'])->assertForbidden();
    }
}
