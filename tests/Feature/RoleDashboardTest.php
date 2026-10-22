<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RoleDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public static function roles(): array
    {
        return [
            'admin' => ['superadmin', 'admin'],
            'teacher' => ['jsmith', 'teacher'],
        ];
    }

    #[DataProvider('roles')]
    public function test_each_role_gets_its_own_dashboard(string $username, string $role): void
    {
        $this->actingAs(User::where('username', $username)->first())
            ->get('/dashboard')
            ->assertRedirect('/app');

        $this->get('/app')->assertInertia(fn (Assert $page) => $page
            ->component('office/home')
            ->where('roleLabel', $role === 'admin' ? 'Super Admin' : 'Teacher')
            ->has('dashboard.metrics', 4)
            ->has('navigation', $role === 'admin' ? 7 : 5));
    }

    public function test_login_with_wrong_role_fails(): void
    {
        $this->post('/login', ['login' => 'student', 'password' => 'Demo@1234', 'role' => 'admin'])
            ->assertSessionHasErrors('login');
        $this->assertGuest();
    }

    public function test_login_with_correct_role_succeeds(): void
    {
        $this->post('/login', ['login' => 'student', 'password' => 'Demo@1234', 'role' => 'student'])
            ->assertRedirect('/student');
        $this->assertAuthenticated();
    }

    public function test_menu_depends_on_role(): void
    {
        $this->actingAs(User::where('username', 'student')->first())
            ->get('/student')
            ->assertInertia(fn (Assert $page) => $page->where('menu.0.url', '/student'));

        $this->get('/student/routine')->assertOk();
        $this->get('/m/students')->assertNotFound();

        $this->actingAs(User::where('username', 'superadmin')->first())
            ->get('/app/students')
            ->assertInertia(fn (Assert $page) => $page->component('office/module')->where('module.title', 'Students'));

        $this->actingAs(User::where('username', 'jsmith')->first())
            ->get('/app/fees')
            ->assertNotFound();
    }
}
