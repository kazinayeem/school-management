<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PortalTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_student_portal_pages_load_from_sqlite_seed(): void
    {
        $student = User::where('username', 'student')->first();

        $this->actingAs($student)
            ->get('/student')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('portals/student')
                ->where('page', 'home')
                ->where('profile.name', 'Tanvir Ahmed')
                ->where('profile.class', 'Class 8'));

        foreach (['routine', 'calendar', 'attendance', 'homework', 'results', 'fees', 'library', 'transport', 'documents', 'leave', 'profile'] as $section) {
            $this->get("/student/{$section}")->assertOk();
        }

        $this->get('/dashboard')->assertRedirect('/student');
        $this->get('/parent')->assertForbidden();
    }

    public function test_parent_sees_linked_children(): void
    {
        $parent = User::where('username', 'parent')->first();

        $this->actingAs($parent)
            ->get('/parent')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('portals/parent')
                ->has('children', 1)
                ->where('account.name', 'Md. Kamal Uddin Ahmed')
                ->where('profile.class', 'Class 8'));

        $this->get('/student')->assertForbidden();
    }

    public function test_parent_login_opens_the_parent_portal(): void
    {
        $this->post('/login', [
            'login' => 'parent',
            'password' => 'Demo@1234',
            'role' => 'parent',
        ])->assertRedirect('/parent');
    }

    public function test_student_can_submit_homework_and_leave(): void
    {
        $student = User::where('username', 'student')->first();
        $homework = \Illuminate\Support\Facades\DB::table('homework_items')
            ->where('user_id', $student->id)
            ->where('status', 'todo')
            ->first();

        $this->actingAs($student)
            ->post("/student/homework/{$homework->id}", ['note' => 'Finished in class'])
            ->assertRedirect();

        $this->assertDatabaseHas('homework_items', [
            'id' => $homework->id,
            'status' => 'submitted',
        ]);

        $this->post('/student/leave', [
            'leave_type' => 'Casual Leave',
            'start_date' => '2026-11-02',
            'end_date' => '2026-11-03',
            'reason' => 'Family visit',
        ])->assertRedirect();

        $this->assertDatabaseHas('leave_requests', [
            'user_id' => $student->id,
            'reason' => 'Family visit',
            'status' => 'pending',
        ]);
    }
}
