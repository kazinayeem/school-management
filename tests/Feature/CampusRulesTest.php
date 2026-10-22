<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class CampusRulesTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function user(string $username): User
    {
        return User::where('username', $username)->firstOrFail();
    }

    private function studentIn(string $class, string $section): User
    {
        return User::findOrFail(DB::table('student_profiles')->where('class_name', $class)->where('section', $section)->orderBy('roll_no')->value('user_id'));
    }

    public function test_seed_is_clash_free_and_realistic(): void
    {
        $this->assertSame(0, DB::table('class_periods')->whereNotNull('teacher')->groupBy('teacher', 'day', 'sort')->havingRaw('count(*) > 1')->get()->count());
        $this->assertSame(0, DB::table('class_periods')->whereNotNull('room')->groupBy('room', 'day', 'sort')->havingRaw('count(*) > 1')->get()->count());
        $this->assertSame(60, DB::table('student_profiles')->count());
        $this->assertSame('Class 8', $this->user('student')->profile->class_name);
        $this->assertSame(1, DB::table('guardian_student')->where('guardian_id', $this->user('parent')->id)->count());
    }

    public function test_teachers_cannot_register_students_and_only_see_their_own(): void
    {
        $teacher = $this->user('jsmith');
        $this->actingAs($teacher);

        $this->get('/app/students/new')->assertForbidden();
        $this->post('/app/students', ['name' => 'X', 'class_name' => 'Class 8', 'section' => 'A', 'gender' => 'Male'])->assertForbidden();

        $this->get('/app/students')->assertInertia(fn (Assert $p) => $p
            ->has('module.rows', 18)
            ->where('module.rows.0.class', fn ($c) => (bool) preg_match('/^[789]-/', (string) $c)));

        $this->get('/app/students/'.$this->studentIn('Class 8', 'A')->id)->assertOk();
        $this->get('/app/students/'.$this->studentIn('Class 3', 'B')->id)->assertForbidden();
        $this->getJson('/app/lookup/students?q=a')->assertOk()->assertJsonMissing(['class' => 'Class 3']);
    }

    public function test_module_permissions_decide_view_and_edit(): void
    {
        $this->actingAs($this->user('accountant'));
        $this->get('/app/fees')->assertOk();
        $this->get('/app/attendance')->assertForbidden();

        $notice = ['title' => 'Holiday', 'body' => 'School closed.', 'published_on' => today()->toDateString()];
        $this->post('/app/notices', $notice)->assertRedirect();

        DB::table('role_permissions')->where('role', 'staff')->where('permission', 'notices.edit')->delete();
        \App\Support\Permissions::flush();
        $this->post('/app/notices', $notice)->assertForbidden();
        $this->get('/app/notices')->assertOk();
    }

    public function test_teacher_takes_attendance_only_for_assigned_subject_and_recent_dates(): void
    {
        $teacher = $this->user('jsmith');
        $maths = (int) DB::table('academic_subjects')->where('code', 'MAT')->value('id');
        $bangla = (int) DB::table('academic_subjects')->where('code', 'BAN')->value('id');
        $ids = DB::table('student_profiles')->where('class_name', 'Class 8')->where('section', 'A')->pluck('user_id')->all();
        $marks = fn (string $status) => array_map(fn ($id) => ['user_id' => $id, 'status' => $status], $ids);
        $date = today()->subDay()->toDateString();

        $this->actingAs($teacher);
        $this->get('/app/attendance')->assertInertia(fn (Assert $p) => $p
            ->where('classes', ['Class 7', 'Class 8', 'Class 9'])->where('sections', ['A', 'B'])->has('subjects', 1)->where('subjects.0.name', 'Mathematics'));

        $base = ['class_name' => 'Class 8', 'section' => 'A'];
        $this->post('/app/attendance/take', $base + ['date' => $date, 'subject' => (string) $bangla, 'marks' => $marks('present')])->assertForbidden();
        $this->post('/app/attendance/take', $base + ['date' => $date, 'subject' => 'whole', 'marks' => $marks('present')])->assertForbidden();
        $this->post('/app/attendance/take', $base + ['date' => today()->subDays(20)->toDateString(), 'subject' => (string) $maths, 'marks' => $marks('present')])->assertSessionHasErrors('date');
        $this->post('/app/attendance/take', $base + ['date' => today()->addDay()->toDateString(), 'subject' => (string) $maths, 'marks' => $marks('present')])->assertSessionHasErrors('date');
        $this->post('/app/attendance/take', ['class_name' => 'Class 3', 'section' => 'A', 'date' => $date, 'subject' => (string) $maths, 'marks' => $marks('present')])->assertForbidden();

        $this->post('/app/attendance/take', $base + ['date' => $date, 'subject' => (string) $maths, 'marks' => $marks('absent')])->assertSessionHasNoErrors();
        $this->assertSame(3, DB::table('period_attendance')->where('date', $date)->where('subject_id', $maths)->where('status', 'absent')->count());
        $this->assertSame(3, DB::table('attendance_days')->whereIn('user_id', $ids)->where('date', $date)->where('status', 'absent')->count());

        $this->post('/app/attendance/take', $base + ['date' => $date, 'subject' => (string) $maths, 'marks' => [['user_id' => $this->studentIn('Class 3', 'A')->id, 'status' => 'present']]])->assertSessionHasErrors('marks');
    }

    public function test_admin_can_take_whole_day_attendance_for_any_section(): void
    {
        $this->actingAs($this->user('superadmin'));
        $student = $this->studentIn('Class 3', 'B');
        $date = today()->subDays(15)->toDateString();

        $this->post('/app/attendance/take', ['class_name' => 'Class 3', 'section' => 'B', 'date' => $date, 'subject' => 'whole', 'marks' => [['user_id' => $student->id, 'status' => 'leave']]])->assertSessionHasNoErrors();
        $this->assertSame('leave', DB::table('attendance_days')->where('user_id', $student->id)->where('date', $date)->value('status'));
    }

    public function test_a_room_cannot_be_used_twice_in_the_same_period(): void
    {
        $this->actingAs($this->user('superadmin'));
        DB::table('rooms')->insert(['name' => 'Lab X', 'kind' => 'lab', 'capacity' => 20, 'active' => 1]);
        $cell = fn (string $teacher, string $room) => ['day' => 0, 'sort' => 1, 'subject' => 'Mathematics', 'teacher' => $teacher, 'room' => $room];
        $one = DB::table('users')->where('role', 'teacher')->where('username', '!=', 'jsmith')->value('name');
        $two = DB::table('users')->where('role', 'teacher')->where('username', '!=', 'jsmith')->orderByDesc('id')->value('name');

        DB::table('class_periods')->where('day', 0)->where('sort', 1)->update(['room' => null, 'teacher' => null]);
        $this->post('/app/timetable', ['class_name' => 'Class 6', 'section' => 'A', 'cells' => [$cell($one, 'Lab X')]])->assertSessionHasNoErrors();
        $this->post('/app/timetable', ['class_name' => 'Class 7', 'section' => 'B', 'cells' => [$cell($two, 'Lab X')]])->assertSessionHasErrors('cells');
        $this->post('/app/timetable', ['class_name' => 'Class 7', 'section' => 'B', 'cells' => [$cell($two, 'No Such Room')]])->assertSessionHasErrors();
    }

    public function test_student_file_shows_everything_and_notes_can_be_added(): void
    {
        $admin = $this->actingAs($this->user('superadmin'));
        $student = $this->user('student');

        $this->get("/app/students/{$student->id}")->assertInertia(fn (Assert $p) => $p
            ->component('office/student-file')->where('student.name', 'Tanvir Ahmed')->has('attendance')->has('fees')->has('results'));

        $this->post("/app/students/{$student->id}/notes", ['kind' => 'violation', 'severity' => 'high', 'title' => 'Fight in class', 'occurred_on' => today()->toDateString(), 'visible_to_parent' => true])->assertSessionHasNoErrors();
        $this->post("/app/students/{$student->id}/notes", ['kind' => 'nonsense', 'title' => 'x', 'occurred_on' => today()->toDateString()])->assertSessionHasErrors('kind');
        $this->post("/app/students/{$student->id}/notes", ['kind' => 'other', 'title' => 'x', 'occurred_on' => today()->addDay()->toDateString()])->assertSessionHasErrors('occurred_on');
        $this->assertSame(1, DB::table('student_notes')->where('user_id', $student->id)->where('title', 'Fight in class')->count());

        $this->get("/app/students/{$student->id}/transcript")->assertOk();
        $this->get("/app/students/{$student->id}/print")->assertOk();
        $this->get("/app/students/{$student->id}/export")->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $id = DB::table('student_notes')->where('user_id', $student->id)->value('id');
        $this->delete("/app/students/{$student->id}/notes/{$id}")->assertRedirect();
        $this->assertSame(0, DB::table('student_notes')->where('id', $id)->count());
    }

    public function test_photos_are_validated_authorised_and_replaced(): void
    {
        Storage::fake('public');
        $student = $this->user('student');
        $other = $this->user('nusrat');

        $this->actingAs($student);
        $this->post("/photos/{$student->id}", ['photo' => UploadedFile::fake()->image('me.jpg', 300, 300)])->assertSessionHasNoErrors();
        $first = $student->fresh()->photo;
        Storage::disk('public')->assertExists($first);

        $this->post("/photos/{$student->id}", ['photo' => UploadedFile::fake()->image('me2.png', 400, 400)])->assertSessionHasNoErrors();
        Storage::disk('public')->assertMissing($first);

        $this->post("/photos/{$student->id}", ['photo' => UploadedFile::fake()->create('virus.pdf', 10, 'application/pdf')])->assertSessionHasErrors('photo');
        $this->post("/photos/{$student->id}", ['photo' => UploadedFile::fake()->image('tiny.jpg', 20, 20)])->assertSessionHasErrors('photo');
        $this->post("/photos/{$other->id}", ['photo' => UploadedFile::fake()->image('x.jpg', 300, 300)])->assertForbidden();

        $this->delete("/photos/{$student->id}")->assertSessionHasNoErrors();
        $this->assertNull($student->fresh()->photo);

        $this->actingAs($this->user('parent'));
        $this->post("/photos/{$student->id}", ['photo' => UploadedFile::fake()->image('p.jpg', 300, 300)])->assertSessionHasNoErrors();
        $this->post("/photos/{$other->id}", ['photo' => UploadedFile::fake()->image('p.jpg', 300, 300)])->assertForbidden();
    }

    public function test_login_page_offers_the_teacher_role_and_roles_matrix_loads(): void
    {
        $this->post('/login', ['login' => 'jsmith', 'password' => 'Demo@1234', 'role' => 'teacher'])->assertRedirect('/app');
        $this->post('/logout');
        $this->post('/login', ['login' => 'jsmith', 'password' => 'Demo@1234', 'role' => 'staff'])->assertSessionHasErrors();

        $this->actingAs($this->user('superadmin'));
        $this->get('/app/system/roles')->assertInertia(fn (Assert $p) => $p->has('modules')->has('granted.teacher')->has('granted.staff'));
    }

    public function test_parent_sees_visible_conduct_notes_public_teachers_have_photos_and_admit_cards_carry_them(): void
    {
        $student = $this->user('student');
        DB::table('student_notes')->insert([
            ['user_id' => $student->id, 'kind' => 'commendation', 'title' => 'Visible note', 'occurred_on' => today()->toDateString(), 'visible_to_parent' => 1],
            ['user_id' => $student->id, 'kind' => 'violation', 'title' => 'Private note', 'occurred_on' => today()->toDateString(), 'visible_to_parent' => 0],
        ]);

        $this->actingAs($this->user('parent'))->get('/parent/conduct?child='.$student->id)->assertInertia(fn (Assert $p) => $p
            ->where('page', 'conduct')->has('data', fn (Assert $d) => $d->where('0.title', 'Visible note')->etc()));
        $this->assertSame(1, collect(DB::table('student_notes')->where('user_id', $student->id)->where('visible_to_parent', 1)->get())->where('title', 'Visible note')->count());

        $this->post('/logout');
        $this->get('/teachers')->assertInertia(fn (Assert $p) => $p->has('page.teachers', 24)->where('page.teachers.0.name', fn ($n) => is_string($n)));

        DB::table('users')->where('id', $student->id)->update(['photo' => 'photos/seed/student.svg']);
        $exam = DB::table('exams')->value('id');
        $this->actingAs($this->user('superadmin'))->get("/app/exams/admit-cards?exam={$exam}&class=Class%208&section=A")
            ->assertInertia(fn (Assert $p) => $p->where('students.0.photo', '/storage/photos/seed/student.svg'));
    }
}
