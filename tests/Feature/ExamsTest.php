<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\Grading;
use App\Support\StudentEnrollment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ExamsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Grading::flush();
        DB::table('rooms')->insert([['name' => '101', 'kind' => 'classroom', 'capacity' => 40, 'active' => 1], ['name' => '102', 'kind' => 'classroom', 'capacity' => 40, 'active' => 1]]);
    }

    private function admin(): User
    {
        $this->actingAs($admin = User::factory()->create(['role' => 'admin']));

        return $admin;
    }

    private function student(string $name, string $section = 'A'): User
    {
        return StudentEnrollment::enroll(['name' => $name, 'class_name' => 'Class 6', 'section' => $section, 'gender' => 'Male']);
    }

    private function subject(string $code): int
    {
        return (int) DB::table('academic_subjects')->where('code', $code)->value('id');
    }

    /** Create an exam with Bangla (100/33) and Math (50/17) scheduled for Class 6. */
    private function exam(): int
    {
        $this->post('/app/exams', ['name' => 'Mid Term 2026', 'starts_on' => '2026-11-01', 'ends_on' => '2026-11-10'])->assertSessionHasNoErrors();
        $id = (int) DB::table('exams')->value('id');
        $this->post('/app/exams/schedule', ['exam_id' => $id, 'class_name' => 'Class 6', 'rows' => [
            ['subject_id' => $this->subject('BAN'), 'include' => true, 'exam_date' => '2026-11-02', 'starts_at' => '10:00', 'full_marks' => 100, 'pass_marks' => 33, 'room' => '101'],
            ['subject_id' => $this->subject('MAT'), 'include' => true, 'exam_date' => '2026-11-03', 'starts_at' => '10:00', 'full_marks' => 50, 'pass_marks' => 17, 'room' => '101'],
            ['subject_id' => $this->subject('ENG'), 'include' => false, 'exam_date' => '', 'full_marks' => 100, 'pass_marks' => 33],
        ]])->assertSessionHasNoErrors();

        return $id;
    }

    private function enter(int $exam, string $code, array $marks, ?string $section = null): void
    {
        $this->post('/app/exams/marks', ['exam_id' => $exam, 'class_name' => 'Class 6', 'section' => $section, 'subject_id' => $this->subject($code), 'entries' => $marks])->assertSessionHasNoErrors();
    }

    public function test_grading_helper_uses_the_scale_and_fails_correctly(): void
    {
        $this->seed();
        Grading::flush();

        $this->assertSame(['grade' => 'A+', 'point' => 5.0, 'failed' => false], Grading::subject(85, 100, 33, false));
        $this->assertSame('A-', Grading::subject(65, 100, 33, false)['grade']);
        $this->assertSame('D', Grading::subject(35, 100, 33, false)['grade']);
        $this->assertSame('A+', Grading::subject(40, 50, 17, false)['grade'], '40/50 is 80 percent');
        $this->assertSame(['grade' => 'F', 'point' => 0.0, 'failed' => true], Grading::subject(32, 100, 33, false));
        $this->assertTrue(Grading::subject(90, 100, 33, true)['failed'], 'absent always fails');

        $this->assertSame(['gpa' => 4.5, 'grade' => 'A', 'failed' => false], Grading::overall([['point' => 5.0, 'failed' => false], ['point' => 4.0, 'failed' => false]]));
        $this->assertSame(['gpa' => 0.0, 'grade' => 'F', 'failed' => true], Grading::overall([['point' => 5.0, 'failed' => false], ['point' => 0.0, 'failed' => true]]));
    }

    public function test_changing_the_grading_scale_changes_results(): void
    {
        $this->admin();
        Grading::flush();
        $this->assertSame('A+', Grading::subject(80, 100, 33, false)['grade']);

        DB::table('grade_scales')->where('grade', 'A+')->update(['min_marks' => 90]);
        DB::table('grade_scales')->where('grade', 'A')->update(['max_marks' => 89, 'min_marks' => 70]);
        Grading::flush();
        $this->assertSame('A', Grading::subject(80, 100, 33, false)['grade']);
    }

    public function test_exam_setup_validates_and_guards(): void
    {
        $this->admin();

        $this->post('/app/exams', ['name' => 'Bad', 'starts_on' => '2026-11-10', 'ends_on' => '2026-11-01'])->assertSessionHasErrors('ends_on');
        $id = $this->exam();
        $this->post('/app/exams', ['name' => 'Mid Term 2026', 'starts_on' => '2026-12-01', 'ends_on' => '2026-12-05'])->assertSessionHasErrors('name');

        $row = fn ($date, $pass = 33) => [['subject_id' => $this->subject('BAN'), 'include' => true, 'exam_date' => $date, 'full_marks' => 100, 'pass_marks' => $pass]];
        $this->post('/app/exams/schedule', ['exam_id' => $id, 'class_name' => 'Class 6', 'rows' => $row('2026-12-31')])->assertSessionHasErrors('rows.0.exam_date');
        $this->post('/app/exams/schedule', ['exam_id' => $id, 'class_name' => 'Class 6', 'rows' => $row('2026-11-02', 150)])->assertSessionHasErrors('rows.0.pass_marks');
        $this->post('/app/exams/schedule', ['exam_id' => $id, 'class_name' => 'Class 6', 'rows' => $row('')])->assertSessionHasErrors('rows.0.exam_date');

        $this->assertSame(2, DB::table('exam_subjects')->where('exam_id', $id)->count());
        $this->get("/app/exams?exam={$id}&class=Class%206")->assertInertia(fn (Assert $p) => $p
            ->where('schedule.0.include', true)->where('schedule.0.name', 'Bangla')->where('schedule.1.include', false)->where('schedule.2.name', 'Mathematics')->where('schedule.2.include', true)->where('exams.0.classes', 1));
    }

    public function test_marks_are_graded_validated_and_removable(): void
    {
        $this->admin();
        $id = $this->exam();
        $a = $this->student('Alice');
        $b = $this->student('Bob');

        $this->post('/app/exams/marks', ['exam_id' => $id, 'class_name' => 'Class 6', 'subject_id' => $this->subject('BAN'), 'entries' => [['user_id' => $a->id, 'marks' => 101]]])->assertSessionHasErrors('entries.0.marks');
        $this->post('/app/exams/marks', ['exam_id' => $id, 'class_name' => 'Class 6', 'subject_id' => $this->subject('ENG'), 'entries' => []])->assertForbidden();

        $this->enter($id, 'BAN', [['user_id' => $a->id, 'marks' => 85], ['user_id' => $b->id, 'marks' => 20]]);
        $this->assertSame(['A+', 0], [DB::table('exam_results')->where('user_id', $a->id)->value('letter_grade'), (int) DB::table('exam_results')->where('user_id', $a->id)->value('failed')]);
        $this->assertSame(['F', 1], [DB::table('exam_results')->where('user_id', $b->id)->value('letter_grade'), (int) DB::table('exam_results')->where('user_id', $b->id)->value('failed')]);
        $this->assertSame('Mid Term 2026', DB::table('exam_results')->where('user_id', $a->id)->value('exam'));

        $this->enter($id, 'BAN', [['user_id' => $a->id, 'marks' => 70, 'absent' => false], ['user_id' => $b->id, 'marks' => null, 'absent' => true]]);
        $this->assertSame('A', DB::table('exam_results')->where('user_id', $a->id)->value('letter_grade'));
        $this->assertSame([1, 0], [(int) DB::table('exam_results')->where('user_id', $b->id)->value('absent'), (int) DB::table('exam_results')->where('user_id', $b->id)->value('marks')]);
        $this->assertSame(2, DB::table('exam_results')->count(), 'saving again updates instead of duplicating');

        $this->enter($id, 'BAN', [['user_id' => $b->id, 'marks' => null, 'absent' => false]]);
        $this->assertSame(1, DB::table('exam_results')->count(), 'a cleared mark removes the row');

        $this->get("/app/exams/marks?exam={$id}&class=Class%206&subject=".$this->subject('BAN'))->assertInertia(fn (Assert $p) => $p
            ->where('current.full_marks', 100)->has('students', 2)->where('students.0.marks', 70)->has('scale', 7));
    }

    public function test_results_rank_by_gpa_and_publishing_updates_profiles(): void
    {
        $this->admin();
        $id = $this->exam();
        $top = $this->student('Top');
        $mid = $this->student('Mid', 'B');
        $low = $this->student('Fail');

        $this->enter($id, 'BAN', [['user_id' => $top->id, 'marks' => 90], ['user_id' => $mid->id, 'marks' => 70], ['user_id' => $low->id, 'marks' => 20]]);
        $this->post('/app/exams/publish', ['exam_id' => $id, 'class_name' => 'Class 6'])->assertSessionHas('error');
        $this->assertSame(0, DB::table('exam_publications')->count());

        $this->enter($id, 'MAT', [['user_id' => $top->id, 'marks' => 45], ['user_id' => $mid->id, 'marks' => 20], ['user_id' => $low->id, 'marks' => 40]]);

        $this->get("/app/exams/results?exam={$id}&class=Class%206")->assertInertia(fn (Assert $p) => $p
            ->where('rows.0.name', 'Top')->where('rows.0.gpa', 5)->where('rows.0.class_position', 1)
            ->where('rows.1.name', 'Mid')->where('rows.1.gpa', 3)->where('rows.1.class_position', 2)
            ->where('rows.2.name', 'Fail')->where('rows.2.gpa', 0)->where('rows.2.failed', true)->where('rows.2.class_position', 3)
            ->where('stats.passed', 2)->where('stats.failed', 1)->where('stats.pass_rate', 67)->where('published', false));

        $this->get("/app/exams/results?exam={$id}&class=Class%206&section=B")->assertInertia(fn (Assert $p) => $p->has('rows', 1)->where('rows.0.name', 'Mid')->where('rows.0.section_position', 1));

        $this->post('/app/exams/publish', ['exam_id' => $id, 'class_name' => 'Class 6'])->assertSessionHasNoErrors();
        $profile = $top->profile()->first();
        $this->assertSame(['Mid Term 2026', 5.0, 1, 1], [$profile->exam_name, $profile->gpa, $profile->class_position, $profile->section_position]);
        $this->assertSame([2, 1], [$mid->profile()->first()->class_position, $mid->profile()->first()->section_position]);

        $this->post('/app/exams/publish', ['exam_id' => $id, 'class_name' => 'Class 6'])->assertSessionHas('error');
        $this->post('/app/exams/marks', ['exam_id' => $id, 'class_name' => 'Class 6', 'subject_id' => $this->subject('BAN'), 'entries' => [['user_id' => $top->id, 'marks' => 10]]])->assertSessionHas('error');
        $this->delete("/app/exams/{$id}")->assertSessionHas('error');

        $this->actingAs(User::where('role', 'admin')->first());
        $this->post('/app/exams/unpublish', ['exam_id' => $id, 'class_name' => 'Class 6'])->assertSessionHasNoErrors();
        $this->enter($id, 'BAN', [['user_id' => $top->id, 'marks' => 95]]);
    }

    public function test_published_results_can_be_checked_on_the_public_site(): void
    {
        $this->admin();
        $id = $this->exam();
        $s = $this->student('Public Kid');
        $this->enter($id, 'BAN', [['user_id' => $s->id, 'marks' => 88]]);
        $this->enter($id, 'MAT', [['user_id' => $s->id, 'marks' => 40]]);

        $this->get('/results')->assertInertia(fn (Assert $p) => $p->where('page.exams', fn ($e) => collect($e)->doesntContain('exam', 'Mid Term 2026')));
        $this->post('/app/exams/publish', ['exam_id' => $id, 'class_name' => 'Class 6']);

        $value = 'exam-'.$id.'-class-6';
        $this->post('/logout');
        $this->get("/results?exam={$value}&roll=1")->assertInertia(fn (Assert $p) => $p
            ->where('page.result.found', true)->where('page.result.name', 'Public Kid')->has('page.result.rows', 2));
    }

    public function test_admit_cards(): void
    {
        $this->admin();
        $id = $this->exam();
        $this->student('Card Kid');

        $this->get("/app/exams/admit-cards?exam={$id}&class=Class%206")->assertInertia(fn (Assert $p) => $p
            ->has('schedule', 2)->where('schedule.0.subject', 'Bangla')->where('schedule.0.date', '2026-11-02')->has('students', 1)->where('students.0.name', 'Card Kid'));
        $this->get("/app/exams/admit-cards?exam={$id}&class=Class%206&roll=99")->assertInertia(fn (Assert $p) => $p->has('students', 0));
        $this->get("/app/exams/admit-cards?exam={$id}&class=Class%207")->assertInertia(fn (Assert $p) => $p->has('schedule', 0));
    }

    public function test_staff_can_only_enter_marks_for_their_assigned_subjects(): void
    {
        $this->admin();
        $id = $this->exam();
        $s = $this->student('Kid');
        $teacher = User::factory()->create(['role' => 'teacher']);
        DB::table('teaching_assignments')->insert(['user_id' => $teacher->id, 'class_name' => 'Class 6', 'section' => null, 'subject_id' => $this->subject('BAN'), 'created_at' => now(), 'updated_at' => now()]);

        $this->actingAs($teacher);
        $this->get("/app/exams/marks?exam={$id}&class=Class%206")->assertInertia(fn (Assert $p) => $p->has('subjects', 1)->where('subjects.0.name', 'Bangla')->where('canUnlock', false));
        $this->post('/app/exams/marks', ['exam_id' => $id, 'class_name' => 'Class 6', 'subject_id' => $this->subject('BAN'), 'entries' => [['user_id' => $s->id, 'marks' => 60]]])->assertSessionHasNoErrors();
        $this->post('/app/exams/marks', ['exam_id' => $id, 'class_name' => 'Class 6', 'subject_id' => $this->subject('MAT'), 'entries' => [['user_id' => $s->id, 'marks' => 40]]])->assertForbidden();

        $this->get("/app/exams/results?exam={$id}&class=Class%206")->assertOk()->assertInertia(fn (Assert $p) => $p->where('canPublish', false));
        $this->post('/app/exams/publish', ['exam_id' => $id, 'class_name' => 'Class 6'])->assertForbidden();
        foreach (['/app/exams', '/app/exams/admit-cards'] as $path) {
            $this->get($path)->assertForbidden();
        }
        $this->post('/app/exams/schedule', ['exam_id' => $id, 'class_name' => 'Class 6', 'rows' => []])->assertForbidden();
    }

    public function test_a_scheduled_subject_with_marks_cannot_be_removed(): void
    {
        $this->admin();
        $id = $this->exam();
        $s = $this->student('Kid');
        $this->enter($id, 'MAT', [['user_id' => $s->id, 'marks' => 30]]);

        $this->post('/app/exams/schedule', ['exam_id' => $id, 'class_name' => 'Class 6', 'rows' => [
            ['subject_id' => $this->subject('BAN'), 'include' => true, 'exam_date' => '2026-11-02', 'full_marks' => 100, 'pass_marks' => 33],
        ]])->assertSessionHas('error');
        $this->assertSame(2, DB::table('exam_subjects')->where('exam_id', $id)->count());
    }
}
