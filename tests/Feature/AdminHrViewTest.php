<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AdminHrViewTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_opens_any_teachers_hr_record_and_payslips_read_only(): void
    {
        $this->seed(DatabaseSeeder::class);
        $teacher = User::where('username', 'jsmith')->first();
        $slip = DB::table('payslips')->where('user_id', $teacher->id)->first();
        $this->assertNotNull($slip, 'seeded payslip');

        $this->actingAs(User::where('username', 'superadmin')->first());
        $this->get("/app/staff/{$teacher->id}/hr")->assertOk()->assertInertia(fn (Assert $p) => $p->component('office/my-hr')->where('readOnly', true)->where('me.name', $teacher->name)->has('pay.payslips'));
        $paid = DB::table('payslips')->join('payroll_runs', 'payroll_runs.id', '=', 'payslips.run_id')->where('payslips.user_id', $teacher->id)->where('payroll_runs.status', 'paid')->value('payslips.id');
        if ($paid) {
            $this->get("/app/staff/{$teacher->id}/hr/payslips/{$paid}")->assertOk();
            $this->get("/app/staff/{$teacher->id}/hr/payslips/{$paid}/pdf")->assertOk();
        }
        $student = User::where('role', 'student')->first();
        $this->get("/app/staff/{$student->id}/hr")->assertNotFound();

        $this->actingAs($teacher)->get('/app/staff/'.$teacher->id.'/hr')->assertForbidden();
        $this->actingAs(User::where('role', 'teacher')->where('id', '!=', $teacher->id)->first())->get("/app/staff/{$teacher->id}/hr/payslips/1")->assertForbidden();
    }
}
