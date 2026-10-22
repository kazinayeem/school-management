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
            'login' => 'staff',
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

    public function test_students_cannot_open_the_office(): void
    {
        $this->actingAs(User::where('username', 'student')->first())
            ->get('/app')
            ->assertForbidden();
    }
}
