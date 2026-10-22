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

    public function test_teacher_views_any_routine_finds_free_rooms_and_sees_own_hr(): void
    {
        $this->actingAs($this->user('jsmith'));
        $other = $this->studentIn('Class 3', 'B');

        $this->get('/app/timetable?class=Class%203&section=B')->assertInertia(fn (Assert $p) => $p->where('filters.class', 'Class 3')->where('filters.section', 'B')->where('readOnly', true));
        $this->get('/app/timetable?student='.$other->id)->assertInertia(fn (Assert $p) => $p->where('student.name', $other->name)->where('filters.class', 'Class 3'));
        $this->getJson('/app/lookup/students?scope=routine&q='.urlencode(explode(' ', $other->name)[0]))->assertOk()->assertJsonFragment(['id' => $other->id]);
        $this->get('/app/timetable/teacher?teacher='.urlencode((string) DB::table('users')->where('username', 'teacher01')->value('name')))->assertInertia(fn (Assert $p) => $p->where('teacherMode', true)->has('teachers', 24));
        $this->post('/app/timetable', ['class_name' => 'Class 3', 'section' => 'B', 'cells' => []])->assertForbidden();

        $this->get('/app/timetable/free-rooms?day=0&period=1')->assertInertia(fn (Assert $p) => $p->component('office/free-rooms')->has('rooms', 23)->where('rooms', fn ($r) => collect($r)->firstWhere('name', 'Room 101')['free'] === false));
        DB::table('class_periods')->where('day', 0)->where('sort', 1)->update(['room' => null]);
        $this->get('/app/timetable/free-rooms?day=0&period=1&kind=lab')->assertInertia(fn (Assert $p) => $p->has('rooms', 2)->where('rooms.0.free', true));

        $this->get('/app/me')->assertInertia(fn (Assert $p) => $p->component('office/my-hr')->where('me.name', 'Md. Jahangir Alam')->has('pay.payslips', 2)->has('attendance.rows')->has('leave.types'));
        $slip = DB::table('payslips')->where('user_id', $this->user('jsmith')->id)->value('id');
        $this->get("/app/me/payslips/{$slip}")->assertOk();
        $this->actingAs($this->user('accountant'));
        $this->get("/app/me/payslips/{$slip}")->assertNotFound();
        $this->post('/app/me/leave', ['leave_type' => DB::table('leave_types')->value('name'), 'start_date' => today()->addDay()->toDateString(), 'end_date' => today()->addDays(2)->toDateString(), 'reason' => 'Family'])->assertSessionHasNoErrors();
        $this->assertSame(2, (int) DB::table('leave_requests')->where('user_id', $this->user('accountant')->id)->value('days'));
        $this->actingAs($this->user('student'))->get('/app/me')->assertForbidden();
    }

    public function test_teachers_give_check_and_comment_on_homework_for_their_own_sections_only(): void
    {
        $teacher = $this->user('jsmith');
        $maths = (int) DB::table('academic_subjects')->where('code', 'MAT')->value('id');
        $bangla = (int) DB::table('academic_subjects')->where('code', 'BAN')->value('id');
        $base = ['title' => 'Chapter 5 sums', 'due_on' => today()->addDays(3)->toDateString(), 'instructions' => 'Do all'];

        $this->actingAs($teacher);
        $this->post('/app/homework', $base + ['class_name' => 'Class 8', 'section' => 'A', 'subject_id' => $bangla])->assertForbidden();
        $this->post('/app/homework', $base + ['class_name' => 'Class 3', 'section' => 'A', 'subject_id' => $maths])->assertForbidden();
        $this->post('/app/homework', $base + ['class_name' => 'Class 8', 'section' => 'A', 'subject_id' => $maths])->assertRedirect();

        $batch = DB::table('homework_items')->where('title', 'Chapter 5 sums')->value('batch');
        $this->assertSame(3, DB::table('homework_items')->where('batch', $batch)->count());
        $student = $this->user('student');
        $item = DB::table('homework_items')->where('batch', $batch)->where('user_id', $student->id)->first();

        $this->actingAs($student)->post("/student/homework/{$item->id}", ['note' => 'Done'])->assertRedirect();

        $this->actingAs($teacher)->get("/app/homework/{$batch}")->assertInertia(fn (Assert $p) => $p->component('office/homework-check')->has('items', 3));
        $this->post("/app/homework/items/{$item->id}", ['grade' => 'A', 'remarks' => 'Well done'])->assertRedirect();
        $this->assertSame('marked', DB::table('homework_items')->where('id', $item->id)->value('status'));

        $this->actingAs($student)->get('/student/homework')->assertInertia(fn (Assert $p) => $p->where('data', fn ($d) => collect($d)->firstWhere('title', 'Chapter 5 sums')['grade'] === 'A' && collect($d)->firstWhere('title', 'Chapter 5 sums')['remarks'] === 'Well done'));
        $this->actingAs($this->user('parent'))->get('/parent/homework?child='.$student->id)->assertInertia(fn (Assert $p) => $p->where('data', fn ($d) => collect($d)->firstWhere('title', 'Chapter 5 sums')['remarks'] === 'Well done'));

        $this->actingAs($this->user('teacher05'))->get("/app/homework/{$batch}")->assertNotFound();
        $this->post("/app/homework/items/{$item->id}", ['grade' => 'F'])->assertNotFound();
        $this->actingAs($teacher)->delete("/app/homework/{$batch}")->assertRedirect();
        $this->assertSame(0, DB::table('homework_items')->where('batch', $batch)->count());
    }

    public function test_every_role_edits_its_own_profile_and_password_and_downloads_its_own_card(): void
    {
        foreach (['superadmin', 'jsmith', 'accountant', 'student', 'parent'] as $username) {
            $user = $this->user($username);
            $this->actingAs($user);

            $this->post('/profile', ['name' => $user->name, 'email' => $user->email, 'phone' => '01712345678', 'alt_phone' => '01812345678', 'address' => 'House 5, Dhaka', 'emergency_name' => 'Kin', 'emergency_phone' => '01912345678', 'blood_group' => 'O+'])->assertSessionHasNoErrors();
            $fresh = $user->fresh();
            $this->assertSame(['01812345678', 'House 5, Dhaka', 'O+'], [$fresh->alt_phone, $fresh->address, $fresh->blood_group], $username);
            $this->post('/profile', ['name' => $user->name, 'email' => $user->email, 'blood_group' => 'Z+'])->assertSessionHasErrors('blood_group');

            $this->post('/profile/password', ['current_password' => 'wrong', 'password' => 'NewPass123', 'password_confirmation' => 'NewPass123'])->assertSessionHasErrors('current_password');
            $this->post('/profile/password', ['current_password' => 'Demo@1234', 'password' => 'NewPass123', 'password_confirmation' => 'NewPass123'])->assertSessionHasNoErrors();
            $this->assertTrue(\Hash::check('NewPass123', $user->fresh()->password));
        }

        $this->actingAs($this->user('jsmith'))->get('/app/profile')->assertInertia(fn (Assert $p) => $p->component('office/profile')->where('profile.address', 'House 5, Dhaka'));
        $this->get('/app/me/card')->assertInertia(fn (Assert $p) => $p->where('card.kind', 'employee'));
        $this->actingAs($this->user('student'))->get('/student/id-card')->assertInertia(fn (Assert $p) => $p->component('portals/card')->where('card.kind', 'student')->where('card.number', fn ($n) => str_starts_with($n, '2026-')));
        $this->get('/app/me/card')->assertForbidden();
        $this->actingAs($this->user('parent'))->get('/parent/pass')->assertInertia(fn (Assert $p) => $p->where('card.kind', 'parent')->where('card.number', fn ($n) => str_starts_with($n, 'VP-'))->has('card.lines', 1));
        $this->get('/student/id-card')->assertForbidden();
        $this->actingAs($this->user('superadmin'))->get('/app/me/card')->assertOk();
    }

    public function test_admin_uploads_documents_that_the_owner_and_parent_can_open_but_not_change(): void
    {
        Storage::fake('local');
        $student = $this->user('student');
        $admin = $this->user('superadmin');
        $pdf = fn (string $name) => UploadedFile::fake()->create($name, 100, 'application/pdf');

        $this->actingAs($admin)->post("/files/{$student->id}", ['kind' => 'birth_certificate', 'file' => $pdf('birth.pdf')])->assertSessionHasNoErrors();
        $this->post("/files/{$student->id}", ['kind' => 'nid', 'file' => UploadedFile::fake()->create('x.exe', 10, 'application/x-msdownload')])->assertSessionHasErrors('file');
        $this->post("/files/{$student->id}", ['kind' => 'bogus', 'file' => $pdf('a.pdf')])->assertSessionHasErrors('kind');
        $id = (int) DB::table('user_files')->where('user_id', $student->id)->value('id');
        $this->get("/files/{$id}")->assertOk();
        $this->get("/app/students/{$student->id}")->assertInertia(fn (Assert $p) => $p->has('files', 1)->where('can.files', true));

        // Owner and linked parent can open, but not add or delete.
        foreach (['student', 'parent'] as $who) {
            $this->actingAs($this->user($who))->get("/files/{$id}")->assertOk();
            $this->post("/files/{$student->id}", ['kind' => 'nid', 'file' => $pdf('n.pdf')])->assertForbidden();
            $this->delete("/files/{$id}")->assertForbidden();
        }
        $this->actingAs($this->user('student'))->get('/student/documents')->assertInertia(fn (Assert $p) => $p->has('files', 1));
        $this->actingAs($this->user('parent'))->get('/parent/documents?child='.$student->id)->assertInertia(fn (Assert $p) => $p->has('files', 1));

        // Someone else's family, and teachers outside the section, cannot.
        $this->actingAs($this->user('nusrat'))->get("/files/{$id}")->assertNotFound();
        $this->actingAs($this->user('jsmith'))->get("/files/{$id}")->assertOk();
        $other = $this->studentIn('Class 3', 'A');
        $this->actingAs($admin)->post("/files/{$other->id}", ['kind' => 'nid', 'file' => $pdf('o.pdf')]);
        $otherId = (int) DB::table('user_files')->where('user_id', $other->id)->value('id');
        $this->actingAs($this->user('jsmith'))->get("/files/{$otherId}")->assertNotFound();

        // Teachers' own papers are visible to the teacher and admin only.
        $teacher = $this->user('jsmith');
        $this->actingAs($admin)->post("/files/{$teacher->id}", ['kind' => 'nid', 'file' => $pdf('t.pdf')]);
        $teacherFile = (int) DB::table('user_files')->where('user_id', $teacher->id)->value('id');
        $this->actingAs($teacher)->get("/files/{$teacherFile}")->assertOk();
        $this->actingAs($this->user('accountant'))->get("/files/{$teacherFile}")->assertNotFound();

        $this->actingAs($admin)->delete("/files/{$id}")->assertRedirect();
        $this->assertSame(0, DB::table('user_files')->where('id', $id)->count());
    }

    public function test_documents_can_be_attached_while_adding_a_student_or_teacher(): void
    {
        Storage::fake('local');
        $this->actingAs($this->user('superadmin'));
        $pdf = UploadedFile::fake()->create('birth.pdf', 50, 'application/pdf');

        $this->post('/app/students', ['name' => 'New Kid', 'class_name' => 'Class 4', 'section' => 'A', 'gender' => 'Male', 'doc_birth_certificate' => $pdf])->assertRedirect();
        $kid = User::where('name', 'New Kid')->firstOrFail();
        $this->assertSame(['birth_certificate'], DB::table('user_files')->where('user_id', $kid->id)->pluck('kind')->all());

        $this->post('/app/staff', ['name' => 'New Teacher', 'role' => 'teacher', 'password' => 'Secret123', 'doc_nid' => UploadedFile::fake()->create('nid.jpg', 20, 'image/jpeg')])->assertRedirect('/app/staff');
        $this->assertSame(['nid'], DB::table('user_files')->where('user_id', User::where('name', 'New Teacher')->value('id'))->pluck('kind')->all());
    }

    public function test_class_wise_fees_extras_and_discounts_flow_into_invoices_and_every_portal(): void
    {
        $student = $this->user('student');
        $admin = $this->actingAs($this->user('superadmin'));

        // Class 8 pays more tuition than Class 1, and the demo student has a 25 percent merit discount on tuition only.
        $tuition = fn (string $class) => (int) DB::table('fee_heads')->where('name', 'Monthly Tuition')->where('class_name', $class)->value('amount');
        $this->assertSame([1000, 1800], [$tuition('Class 1'), $tuition('Class 8')]);

        $this->get('/app/fees/student?student='.$student->id)->assertInertia(fn (Assert $p) => $p->component('office/student-fees')
            ->where('account.plan', fn ($plan) => collect($plan)->firstWhere('label', 'Monthly Tuition')['net'] === 1350 && collect($plan)->firstWhere('label', 'Monthly Tuition')['discount'] === 450)
            ->where('account.optional', fn ($o) => count($o) === 2));

        // Switching an extra off removes it from the plan; the lab is only billed to students who joined.
        $lab = (int) DB::table('fee_heads')->where('name', 'Science Lab')->where('class_name', 'Class 8')->value('id');
        $this->post("/app/fees/student/{$student->id}/assign", ['fee_head_id' => $lab, 'on' => false])->assertSessionHasNoErrors();
        $this->post("/app/fees/student/{$student->id}/assign", ['fee_head_id' => (int) DB::table('fee_heads')->where('name', 'Monthly Tuition')->value('id'), 'on' => true])->assertSessionHasErrors('fee_head_id');

        $heads = DB::table('fee_heads')->where('class_name', 'Class 8')->whereIn('name', ['Monthly Tuition', 'Science Lab'])->pluck('id')->all();
        $this->post('/app/fees/generate', ['head_ids' => $heads, 'class_name' => 'Class 8', 'section' => 'A', 'label' => 'November 2026 Fees', 'due_on' => '2026-11-10'])->assertSessionHasNoErrors();
        $invoice = DB::table('fee_invoices')->where('user_id', $student->id)->where('label', 'November 2026 Fees')->first();
        $this->assertSame(1350, (int) $invoice->payable, 'tuition after discount, no lab because the student left it');
        $items = DB::table('fee_invoice_items')->where('invoice_id', $invoice->id)->get();
        $this->assertSame([1800, 450, 1350], [(int) $items[0]->amount, (int) $items[0]->discount, (int) $items[0]->net]);
        $this->assertSame(3, DB::table('fee_invoices')->where('label', 'November 2026 Fees')->count());

        // The student and the parent both see the same breakdown, read only.
        $this->actingAs($student)->get('/student/fees')->assertInertia(fn (Assert $p) => $p->where('data.plan', fn ($plan) => collect($plan)->firstWhere('label', 'Monthly Tuition')['discount'] === 450)
            ->where('data.invoices', fn ($inv) => count(collect($inv)->firstWhere('label', 'November 2026 Fees')['items']) === 1));
        $this->actingAs($this->user('parent'))->get('/parent/fees?child='.$student->id)->assertInertia(fn (Assert $p) => $p->where('page', 'fees')->where('data.plan', fn ($plan) => count($plan) >= 2));
        $this->post("/app/fees/student/{$student->id}/assign", ['fee_head_id' => $lab, 'on' => true])->assertForbidden();
        $this->post('/app/fees/structure', ['rows' => []])->assertForbidden();

        // A student can hold only one active waiver at a time.
        $admission = $student->profile->admission_no;
        $second = ['student' => $admission, 'kind' => 'fixed', 'value' => 100, 'applies_to' => 'lab', 'type' => 'need', 'reason' => 'Hardship'];
        $this->actingAs($this->user('superadmin'))->post('/app/fees/waivers', $second)->assertSessionHasErrors('student');
        $this->assertSame(1, DB::table('fee_waivers')->where('user_id', $student->id)->count());

        // Pausing the waiver puts the full amount back on the open invoice, and the portals show it at once.
        $existing = (int) DB::table('fee_waivers')->where('user_id', $student->id)->value('id');
        $this->put("/app/fees/waivers/{$existing}")->assertSessionHasNoErrors();
        $this->assertSame(1800, (int) DB::table('fee_invoices')->where('id', $invoice->id)->value('payable'));
        $this->assertSame(0, (int) DB::table('fee_invoice_items')->where('invoice_id', $invoice->id)->value('discount'));
        $this->actingAs($student)->get('/student/fees')->assertInertia(fn (Assert $p) => $p->where('data.waivers', [])->where('data.invoices', fn ($inv) => collect($inv)->firstWhere('label', 'November 2026 Fees')['payable'] === 1800));

        // A different waiver can now be given, discounts only its own kind of fee, and re-prices the open invoice.
        $this->actingAs($this->user('superadmin'))->post('/app/fees/waivers', ['student' => $admission, 'kind' => 'percent', 'value' => 50, 'applies_to' => 'tuition', 'type' => 'scholarship', 'reason' => 'Top of class'])->assertSessionHasNoErrors();
        $this->assertSame(900, (int) DB::table('fee_invoices')->where('id', $invoice->id)->value('payable'));
        $this->assertSame(1, DB::table('fee_waivers')->where('user_id', $student->id)->where('active', 1)->count());
        $this->actingAs($this->user('parent'))->get('/parent/fees?child='.$student->id)->assertInertia(fn (Assert $p) => $p->where('data.waivers.0.value', 50)->where('data.waivers.0.type_label', 'Scholarship / merit')
            ->where('data.invoices', fn ($inv) => collect($inv)->firstWhere('label', 'November 2026 Fees')['payable'] === 900));

        // The paused one cannot be switched back on while another is active.
        $this->actingAs($this->user('superadmin'))->put("/app/fees/waivers/{$existing}")->assertSessionHas('error');
        $this->assertSame(0, (int) DB::table('fee_waivers')->where('id', $existing)->value('active'));

        // A paid invoice is history: it keeps the amount it was billed at.
        $paid = DB::table('fee_invoices')->where('user_id', $student->id)->where('status', 'paid')->first();
        $this->assertSame((int) $paid->payable, (int) DB::table('fee_invoices')->where('id', $paid->id)->value('payable'));
    }

    public function test_student_file_uses_the_user_id_even_when_the_profile_id_is_different(): void
    {
        // The profile row has its own id. The page must act on the student's user id, never the profile id.
        $user = User::factory()->create(['role' => 'student', 'name' => 'Offset Kid']);
        DB::table('student_profiles')->insert(['id' => 9000, 'user_id' => $user->id, 'class_name' => 'Class 4', 'section' => 'A', 'roll_no' => '9', 'session' => '2026', 'attendance_present' => 0, 'attendance_working' => 0, 'dues_total' => 0, 'dues_count' => 0, 'status' => 'active', 'admission_no' => '2026-09000']);

        $this->actingAs($this->user('superadmin'))->get("/app/students/{$user->id}?tab=documents")->assertInertia(fn (Assert $p) => $p->where('student.id', $user->id));
        \Illuminate\Support\Facades\Storage::fake('local');
        $this->post("/files/{$user->id}", ['kind' => 'nid', 'file' => UploadedFile::fake()->create('n.pdf', 20, 'application/pdf')])->assertSessionHasNoErrors();
        $this->get("/app/students/{$user->id}?tab=documents")->assertInertia(fn (Assert $p) => $p->has('files', 1)->where('files.0.kind', 'nid'));
    }

    public function test_families_submit_bkash_nagad_or_bank_payments_and_admin_approves_or_rejects(): void
    {
        Storage::fake('local');
        $student = $this->user('student');
        $invoice = DB::table('fee_invoices')->where('user_id', $student->id)->first();
        DB::table('fee_invoices')->where('id', $invoice->id)->update(['paid' => 0, 'status' => 'unpaid', 'payable' => 1500]);
        $slip = fn () => UploadedFile::fake()->image('slip.png', 300, 300);
        $base = ['invoice_id' => $invoice->id, 'method' => 'bKash', 'reference' => 'TRX1001', 'amount' => 1000, 'paid_on' => today()->toDateString(), 'sender_number' => '01712345678'];

        // The fee page tells the family where to send money, using only methods the school has set up.
        DB::table('site_settings')->updateOrInsert(['key' => 'pay_bkash_number'], ['value' => '01711000000']);
        DB::table('site_settings')->updateOrInsert(['key' => 'pay_bank_account_number'], ['value' => '12345']);
        DB::table('site_settings')->where('key', 'pay_nagad_number')->delete();
        $this->actingAs($this->user('parent'))->get('/parent/fees?child='.$student->id)->assertInertia(fn (Assert $p) => $p
            ->where('data.pay.methods', fn ($m) => collect($m)->pluck('method')->all() === ['bKash', 'Bank Transfer']));

        // A parent submits with a slip; it waits for approval and the invoice is untouched until then.
        $this->post('/payments/submit', $base + ['proof' => $slip()])->assertSessionHasNoErrors();
        $tx = DB::table('online_transactions')->where('reference', 'TRX1001')->first();
        $this->assertSame(['pending', $student->id, $this->user('parent')->id], [$tx->status, (int) $tx->user_id, (int) $tx->submitted_by]);
        Storage::disk('local')->assertExists($tx->proof_path);
        $this->assertSame(0, (int) DB::table('fee_invoices')->where('id', $invoice->id)->value('paid'));

        // Rules: no duplicate transaction id, no overpaying, no unsupported method, no bad file, nobody else's invoice.
        $this->post('/payments/submit', $base)->assertSessionHasErrors('reference');
        $this->post('/payments/submit', array_merge($base, ['reference' => 'TRX1002', 'amount' => 600]))->assertSessionHasErrors('amount');
        $this->post('/payments/submit', array_merge($base, ['reference' => 'TRX1003', 'method' => 'PayPal']))->assertSessionHasErrors('method');
        $this->post('/payments/submit', array_merge($base, ['reference' => 'TRX1004', 'amount' => 100, 'proof' => UploadedFile::fake()->create('x.exe', 10, 'application/x-msdownload')]))->assertSessionHasErrors('proof');
        $this->post('/payments/submit', array_merge($base, ['reference' => 'TRX1005', 'amount' => 100, 'paid_on' => today()->addDay()->toDateString()]))->assertSessionHasErrors('paid_on');
        $other = DB::table('fee_invoices')->where('user_id', '!=', $student->id)->value('id');
        $this->post('/payments/submit', array_merge($base, ['reference' => 'TRX1006', 'invoice_id' => $other, 'amount' => 100]))->assertNotFound();
        $this->actingAs($this->user('nusrat'))->post('/payments/submit', array_merge($base, ['reference' => 'TRX1007', 'amount' => 100]))->assertNotFound();

        // The student sees it as waiting, and the slip opens only for the family and the school.
        $this->actingAs($student)->get('/student/fees')->assertInertia(fn (Assert $p) => $p->where('data.submissions.0.status', 'pending')->where('data.submissions.0.reference', 'TRX1001'));
        $this->get("/payment-proofs/{$tx->id}")->assertOk();
        $this->actingAs($this->user('nusrat'))->get("/payment-proofs/{$tx->id}")->assertNotFound();
        $this->actingAs($this->user('teacher01'))->get("/payment-proofs/{$tx->id}")->assertNotFound();

        // Admin approves: the invoice is paid down, a receipt exists, the dues fall, and the family sees Approved.
        $before = (int) DB::table('fee_payments')->where('user_id', $student->id)->count();
        $this->actingAs($this->user('superadmin'))->get('/app/fees/online')->assertInertia(fn (Assert $p) => $p->where('rows.0.proof_url', "/payment-proofs/{$tx->id}")->where('rows.0.submitted_by_name', $this->user('parent')->name));
        $this->put("/app/fees/online/{$tx->id}", ['status' => 'success'])->assertSessionHasNoErrors();
        $this->assertSame(1000, (int) DB::table('fee_invoices')->where('id', $invoice->id)->value('paid'));
        $this->assertSame('partial', DB::table('fee_invoices')->where('id', $invoice->id)->value('status'));
        $this->assertSame($before + 1, DB::table('fee_payments')->where('user_id', $student->id)->count());
        $this->put("/app/fees/online/{$tx->id}", ['status' => 'success'])->assertSessionHas('error');

        // A second submission is rejected with a reason the family can read, and nothing is posted.
        $this->actingAs($this->user('parent'))->post('/payments/submit', array_merge($base, ['reference' => 'TRX2001', 'method' => 'Bank Transfer', 'amount' => 500]))->assertSessionHasNoErrors();
        $second = (int) DB::table('online_transactions')->where('reference', 'TRX2001')->value('id');
        $this->actingAs($this->user('superadmin'))->put("/app/fees/online/{$second}", ['status' => 'failed', 'review_note' => 'Amount does not match the bank slip.'])->assertSessionHasNoErrors();
        $this->assertSame(1000, (int) DB::table('fee_invoices')->where('id', $invoice->id)->value('paid'));
        $this->actingAs($student)->get('/student/fees')->assertInertia(fn (Assert $p) => $p->where('data.submissions', fn ($s) => collect($s)->firstWhere('reference', 'TRX2001')['review_note'] === 'Amount does not match the bank slip.' && collect($s)->firstWhere('reference', 'TRX1001')['status'] === 'success'));

        // Staff without the fees permission cannot review.
        $this->actingAs($this->user('jsmith'))->put("/app/fees/online/{$second}", ['status' => 'success'])->assertForbidden();
    }

    public function test_admin_changes_school_name_logo_and_pdf_banner_everywhere(): void
    {
        Storage::fake('public');
        $this->actingAs($this->user('superadmin'));

        $this->post('/app/settings/branding', ['school_name' => 'Shapla Model School', 'tagline' => 'Learn. Lead.', 'brand_logo' => UploadedFile::fake()->image('logo.png', 400, 200), 'pdf_banner' => UploadedFile::fake()->image('banner.jpg', 1800, 300)])->assertSessionHasNoErrors();
        $logo = \App\Support\SiteSettings::get('brand_logo');
        $banner = \App\Support\SiteSettings::get('pdf_banner');
        Storage::disk('public')->assertExists([$logo, $banner]);

        // The shared name and logo change on the public site, login page and portals.
        $this->post('/logout');
        $this->get('/login')->assertInertia(fn (Assert $p) => $p->where('name', 'Shapla Model School')->where('branding.name', 'Shapla Model School')->where('branding.logo', fn ($u) => str_contains($u, basename($logo))));
        $this->get('/')->assertInertia(fn (Assert $p) => $p->where('name', 'Shapla Model School'));

        // Every PDF carries the new letterhead and name.
        $this->actingAs($this->user('superadmin'));
        $html = view('pdf.table', ['title' => 'T', 'sections' => [], 'school' => \App\Support\Pdf::school()])->render();
        $this->assertStringContainsString('data:image/png;base64', $html, 'the banner is embedded in the PDF');
        $pdf = $this->get('/card.pdf')->assertOk();
        $this->assertStringStartsWith('%PDF', $pdf->getContent());
        $this->assertSame('Shapla Model School', \App\Support\Pdf::school()['name']);

        // Replacing deletes the old file; removing falls back to the built-in logo.
        $this->post('/app/settings/branding', ['school_name' => 'Shapla Model School', 'brand_logo' => UploadedFile::fake()->image('new.png', 300, 300)])->assertSessionHasNoErrors();
        Storage::disk('public')->assertMissing($logo);
        $this->post('/app/settings/branding', ['school_name' => 'Shapla Model School', 'remove' => ['brand_logo', 'pdf_banner']])->assertSessionHasNoErrors();
        $this->assertNull(\App\Support\Branding::image('pdf_banner'));
        $this->assertStringContainsString('images/logo.png', \App\Support\Branding::data()['logo']);

        // Only an administrator, and only real pictures.
        $this->post('/app/settings/branding', ['school_name' => 'X', 'brand_logo' => UploadedFile::fake()->create('logo.svg', 5, 'image/svg+xml')])->assertSessionHasErrors('brand_logo');
        $this->post('/app/settings/branding', ['school_name' => ''])->assertSessionHasErrors('school_name');
        $this->actingAs($this->user('jsmith'))->post('/app/settings/branding', ['school_name' => 'Hacked'])->assertForbidden();
        $this->assertSame('Shapla Model School', \App\Support\Branding::name());
    }

    public function test_uploaded_photos_survive_a_reload_for_every_role(): void
    {
        Storage::fake('public');
        foreach (['superadmin', 'jsmith', 'accountant', 'student', 'parent'] as $username) {
            $user = $this->user($username);
            $this->actingAs($user)->post("/photos/{$user->id}", ['photo' => UploadedFile::fake()->image($username.'.jpg', 300, 300)])->assertSessionHasNoErrors();

            $path = $user->fresh()->photo;
            $this->assertNotNull($path, $username);
            Storage::disk('public')->assertExists($path);
            // A brand-new request, as after a browser reload, still sees it on the profile.
            $url = $username === 'student' ? '/student/profile' : ($username === 'parent' ? '/parent/profile' : '/app/profile');
            $this->actingAs($user->fresh())->get($url)->assertInertia(fn (Assert $p) => $p->where('auth.user.photo_url', fn ($u) => str_ends_with((string) $u, basename($path))));
        }

        // An administrator changing a student's picture from the student file is also still there afterwards.
        $kid = $this->studentIn('Class 3', 'A');
        $this->actingAs($this->user('superadmin'))->post("/photos/{$kid->id}", ['photo' => UploadedFile::fake()->image('kid.png', 300, 300)])->assertSessionHasNoErrors();
        $this->get("/app/students/{$kid->id}")->assertInertia(fn (Assert $p) => $p->where('student.photo_url', fn ($u) => str_ends_with((string) $u, basename($kid->fresh()->photo))));
    }

    public function test_admin_picks_primary_and_secondary_colours_that_recolour_the_whole_site(): void
    {
        $this->actingAs($this->user('superadmin'));
        $this->assertSame(['primary' => '#b31299', 'secondary' => '#7c3aed'], \App\Support\Branding::colors());

        $this->post('/app/settings/branding', ['school_name' => 'Borno School', 'brand_primary' => '#0369A1', 'brand_secondary' => '#0d9488'])->assertSessionHasNoErrors();
        $this->assertSame(['primary' => '#0369a1', 'secondary' => '#0d9488'], \App\Support\Branding::colors());

        // The page carries the new colour scale from the very first byte, so there is no flash of the old colours.
        $this->post('/logout');
        $html = $this->get('/login')->getContent();
        $this->assertStringContainsString('--brand-primary:#0369a1', $html);
        $this->assertStringContainsString('--color-fuchsia-700:#0369a1', $html);
        $this->assertStringContainsString('--brand-secondary:#0d9488', $html);
        $this->assertStringContainsString('--color-violet-700:#0d9488', $html);
        $this->assertStringContainsString('content="#0369a1"', $html);
        $this->get('/login')->assertInertia(fn (Assert $p) => $p->where('branding.theme.primary', '#0369a1')->where('branding.theme.secondary', '#0d9488'));

        // PDFs use it too.
        $this->actingAs($this->user('superadmin'));
        $this->assertStringContainsString('#0369a1', view('pdf.table', ['title' => 'T', 'sections' => [], 'school' => \App\Support\Pdf::school()])->render());

        // Only real hex colours are accepted.
        foreach (['red', '#12345', '#gggggg', '0369a1'] as $bad) {
            $this->post('/app/settings/branding', ['school_name' => 'Borno School', 'brand_primary' => $bad])->assertSessionHasErrors('brand_primary');
        }
        $this->assertSame('#0369a1', \App\Support\Branding::colors()['primary']);

        // Lighter steps are lighter, darker steps are darker, and the colour itself is step 700.
        $scale = \App\Support\Branding::scale('#0369a1');
        $this->assertSame('#0369a1', $scale[700]);
        $lum = fn (string $c) => array_sum(sscanf($c, '#%02x%02x%02x'));
        $this->assertGreaterThan($lum($scale[700]), $lum($scale[50]));
        $this->assertLessThan($lum($scale[700]), $lum($scale[950]));
    }
}
