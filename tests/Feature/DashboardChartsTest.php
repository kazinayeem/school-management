<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardChartsTest extends TestCase
{
    use RefreshDatabase;

    private function user(string $u): User
    {
        return User::where('username', $u)->firstOrFail();
    }

    public function test_admin_charts_come_from_the_database_and_follow_the_filters(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->actingAs($this->user('superadmin'));
        $students = DB::table('student_profiles')->where('status', 'active')->count();

        $this->get('/app')->assertInertia(fn (Assert $p) => $p->component('office/home')
            ->where('charts.people.students', $students)->where('charts.people.teachers', 24)->where('charts.people.staff', 6)
            ->where('charts.students_by_class', fn ($rows) => collect($rows)->sum(fn ($r) => ($r['A'] ?? 0) + ($r['B'] ?? 0)) === $students)
            ->has('charts.attendance.trend')->where('charts.attendance.split', fn ($s) => collect($s)->pluck('name')->all() === ['present', 'late', 'absent', 'leave'])
            ->has('charts.exam.grades')->has('charts.fees.months')->has('charts.fees.collections')->has('charts.admissions.status')->has('charts.messages')->has('charts.library.status')->has('charts.transport')
            ->where('charts.filters.range', 30)->where('charts.filters.class', ''));

        // The period and class filters narrow the numbers; unknown values fall back safely.
        $this->get('/app?range=7')->assertInertia(fn (Assert $p) => $p->where('charts.filters.range', 7)->where('charts.attendance.trend', fn ($t) => count($t) <= 5));
        $this->get('/app?range=9999&class=Nonsense')->assertInertia(fn (Assert $p) => $p->where('charts.filters.range', 30)->where('charts.filters.class', ''));
        $this->get('/app?class=Class%208')->assertInertia(fn (Assert $p) => $p->where('charts.students_by_class', fn ($rows) => count($rows) === 1 && $rows[0]['name'] === 'Class 8'));

        // The attendance split adds up to the rows in the database for that period.
        $from = today()->subDays(29)->toDateString();
        $count = DB::table('attendance_days')->join('student_profiles', 'student_profiles.user_id', '=', 'attendance_days.user_id')->where('student_profiles.status', 'active')->where('date', '>=', $from)->count();
        $this->get('/app')->assertInertia(fn (Assert $p) => $p->where('charts.attendance.split', fn ($s) => collect($s)->sum('value') === $count));
    }

    public function test_a_teacher_sees_only_their_own_classes_subjects_and_nothing_financial(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->actingAs($this->user('jsmith'));

        $this->get('/app')->assertInertia(fn (Assert $p) => $p
            ->where('charts.students_by_class', fn ($rows) => collect($rows)->pluck('name')->sort()->values()->all() === ['Class 7', 'Class 8', 'Class 9'])
            ->where('charts.exam.subjects', fn ($rows) => collect($rows)->pluck('subject')->all() === ['Mathematics'])
            ->has('charts.today_classes')->has('charts.homework')->has('charts.attendance')
            ->missing('charts.people')->missing('charts.fees')->missing('charts.admissions')->missing('charts.library')->missing('charts.transport')->missing('charts.messages')
            ->where('charts.filters.classes', ['Class 7', 'Class 8', 'Class 9']));

        // A class they do not teach is ignored, and the numbers stay theirs.
        $this->get('/app?class=Class%203')->assertInertia(fn (Assert $p) => $p->where('charts.filters.class', '')->where('charts.students_by_class', fn ($rows) => collect($rows)->pluck('name')->diff(['Class 7', 'Class 8', 'Class 9'])->isEmpty()));
        $mine = DB::table('student_profiles')->whereIn('class_name', ['Class 7', 'Class 8', 'Class 9'])->count();
        $this->get('/app')->assertInertia(fn (Assert $p) => $p->where('charts.students_by_class', fn ($rows) => collect($rows)->sum(fn ($r) => ($r['A'] ?? 0) + ($r['B'] ?? 0)) === $mine));
    }

    public function test_office_staff_get_charts_only_for_the_modules_they_may_view(): void
    {
        $this->seed(DatabaseSeeder::class);
        $this->actingAs($this->user('accountant'));

        $this->get('/app')->assertInertia(fn (Assert $p) => $p->has('charts.fees.months')->has('charts.admissions')->has('charts.library')->has('charts.transport')
            ->missing('charts.attendance')->missing('charts.people')->missing('charts.exam')->missing('charts.homework'));

        DB::table('role_permissions')->where('role', 'staff')->where('permission', 'like', 'fees.%')->delete();
        \App\Support\Permissions::flush();
        $this->get('/app')->assertInertia(fn (Assert $p) => $p->missing('charts.fees'));
    }

    public function test_a_student_sees_only_their_own_progress(): void
    {
        $this->seed(DatabaseSeeder::class);
        $student = $this->user('student');
        $due = (int) DB::table('fee_invoices')->where('user_id', $student->id)->whereColumn('payable', '>', 'paid')->selectRaw('sum(payable - paid) as d')->value('d');
        $days = DB::table('attendance_days')->where('user_id', $student->id)->where('date', '>=', today()->subWeeks(11)->startOfWeek()->toDateString())->count();

        $this->actingAs($student)->get('/student')->assertInertia(fn (Assert $p) => $p->component('portals/student')
            ->where('charts.fees.due', $due)->where('charts.attendance.split', fn ($s) => collect($s)->sum('value') === $days)
            ->where('charts.progress.0.exam', 'Half Yearly Examination 2026')->where('charts.progress.0.gpa', fn ($g) => $g > 0)
            ->where('charts.subjects.rows', fn ($rows) => count($rows) === 6)->has('charts.homework')->has('charts.upcoming_exams'));
        // Other pages do not carry the dashboard numbers.
        $this->get('/student/fees')->assertInertia(fn (Assert $p) => $p->where('charts', null));
    }

    public function test_a_parent_with_two_children_sees_the_chosen_child_and_never_a_stranger(): void
    {
        $this->seed(DatabaseSeeder::class);
        $parent = $this->user('parent');
        $first = $this->user('student');
        $second = $this->user('nusrat');
        DB::table('guardian_student')->insert(['guardian_id' => $parent->id, 'student_id' => $second->id]);
        $stranger = DB::table('student_profiles')->where('class_name', 'Class 2')->value('user_id');

        $countFor = fn (int $id) => DB::table('attendance_days')->where('user_id', $id)->where('date', '>=', today()->subWeeks(11)->startOfWeek()->toDateString())->count();
        $this->actingAs($parent);
        $this->get('/parent?child='.$first->id)->assertInertia(fn (Assert $p) => $p->where('child', $first->id)->where('charts.attendance.split', fn ($s) => collect($s)->sum('value') === $countFor($first->id)));
        $this->get('/parent?child='.$second->id)->assertInertia(fn (Assert $p) => $p->where('child', $second->id)->where('charts.attendance.split', fn ($s) => collect($s)->sum('value') === $countFor($second->id))
            ->where('charts.fees.due', (int) DB::table('fee_invoices')->where('user_id', $second->id)->whereColumn('payable', '>', 'paid')->selectRaw('sum(payable - paid) as d')->value('d')));
        // Asking for someone else's child shows the parent's first child instead.
        $this->get('/parent?child='.$stranger)->assertInertia(fn (Assert $p) => $p->where('child', fn ($c) => in_array($c, [$first->id, $second->id], true)));
    }

    public function test_dashboards_work_on_an_empty_school_and_for_a_student_with_no_data(): void
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin)->get('/app')->assertOk()->assertInertia(fn (Assert $p) => $p->where('charts.people.students', 0)->where('charts.attendance.trend', [])->where('charts.students_by_class', [])->where('charts.fees.months', []));

        $kid = User::factory()->create(['role' => 'student']);
        DB::table('student_profiles')->insert(['user_id' => $kid->id, 'class_name' => 'Class 1', 'section' => 'A', 'roll_no' => '1', 'session' => '2026', 'status' => 'active', 'attendance_present' => 0, 'attendance_working' => 0, 'dues_total' => 0, 'dues_count' => 0]);
        $this->actingAs($kid)->get('/student')->assertOk()->assertInertia(fn (Assert $p) => $p->where('charts.attendance.trend', [])->where('charts.progress', [])->where('charts.fees.due', 0));
    }
}
