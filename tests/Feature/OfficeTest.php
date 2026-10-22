<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OfficeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_admin_login_opens_the_office_dashboard(): void
    {
        $this->post('/login', [
            'login' => 'superadmin',
            'password' => 'Demo@1234',
            'role' => 'admin',
        ])->assertRedirect('/app');

        $this->get('/app')->assertInertia(fn (Assert $page) => $page
            ->component('office/home')
            ->where('dashboard.firstName', 'Super')
            ->where('dashboard.metrics.0.value', '190')
            ->where('navigation.3.caption', 'Finance'));
    }

    public function test_staff_login_opens_the_same_dashboard_with_a_smaller_menu(): void
    {
        $this->post('/login', [
            'login' => 'jsmith',
            'password' => 'Demo@1234',
            'role' => 'staff',
        ])->assertRedirect('/app');

        $this->get('/app')->assertInertia(fn (Assert $page) => $page
            ->component('office/home')
            ->where('dashboard.firstName', 'John')
            ->where('roleLabel', 'Teacher')
            ->has('navigation', 5)
            ->where('navigation.1.caption', 'Academic'));

        $this->get('/app/students')->assertOk();
        $this->get('/app/payroll')->assertNotFound();
    }

    public function test_staff_can_mark_attendance_publish_a_notice_and_open_a_student(): void
    {
        $staff = User::where('username', 'jsmith')->first();
        $ethan = User::where('username', 'student')->first();

        $this->actingAs($staff)
            ->get('/app/students?q=Ethan')
            ->assertInertia(fn (Assert $page) => $page
                ->component('office/module')
                ->where('module.kind', 'students')
                ->where('module.rows.0.name', 'Ethan Parker'));

        $this->get('/app/students/'.$ethan->id)
            ->assertInertia(fn (Assert $page) => $page
                ->where('module.kind', 'profile')
                ->where('module.student.username', 'student'));

        $this->post('/app/attendance', [
            'section' => 'Class 8-A',
            'date' => '2026-10-22',
            'marks' => [
                ['user_id' => $ethan->id, 'status' => 'absent'],
            ],
        ])->assertRedirect();

        $this->assertDatabaseHas('attendance_days', [
            'user_id' => $ethan->id,
            'date' => '2026-10-22',
            'status' => 'absent',
        ]);

        $this->post('/app/notices', [
            'title' => 'Sports day practice',
            'body' => 'Practice starts at 3 PM on the main field.',
            'published_on' => '2026-10-22',
            'expires_on' => '2026-10-30',
        ])->assertRedirect('/app/notices');

        $this->assertDatabaseHas('school_notices', ['title' => 'Sports day practice']);

        $this->post('/app/students', [
            'name' => 'New Child',
            'class_name' => 'Class 1',
            'section' => 'A',
            'gender' => 'Female',
        ])->assertForbidden();
    }

    public function test_admin_can_admit_a_student_and_collect_a_fee(): void
    {
        $admin = User::where('username', 'superadmin')->first();

        $this->actingAs($admin)->post('/app/students', [
            'name' => 'Mina Chowdhury',
            'class_name' => 'Class 4',
            'section' => 'B',
            'gender' => 'Female',
            'guardian_name' => 'Karim Chowdhury',
            'guardian_mobile' => '01819000111',
        ])->assertRedirect();

        $student = User::where('name', 'Mina Chowdhury')->first();
        $this->assertNotNull($student);
        $this->assertDatabaseHas('student_profiles', [
            'user_id' => $student->id,
            'class_name' => 'Class 4',
            'section' => 'B',
        ]);

        $invoice = \Illuminate\Support\Facades\DB::table('fee_invoices')->where('status', 'unpaid')->first();
        $this->post('/app/fees/'.$invoice->id, ['amount' => (int) $invoice->payable])
            ->assertRedirect('/app/fees');

        $this->assertDatabaseHas('fee_invoices', [
            'id' => $invoice->id,
            'status' => 'paid',
        ]);
    }

    public function test_students_cannot_open_the_office(): void
    {
        $this->actingAs(User::where('username', 'student')->first())
            ->get('/app')
            ->assertForbidden();
    }
}
