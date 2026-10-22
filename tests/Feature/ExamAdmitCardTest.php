<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ExamAdmitCardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    private function u(string $n): User
    {
        return User::where('username', $n)->firstOrFail();
    }

    public function test_one_form_creates_an_exam_with_a_timetable_for_the_chosen_classes(): void
    {
        $this->actingAs($this->u('superadmin'));
        $this->post('/app/exams/quick', ['name' => 'Model Test', 'starts_on' => '2027-02-01', 'ends_on' => '2027-02-20', 'starts_at' => '09:30', 'classes' => ['Class 1', 'Class 2']])->assertSessionHasNoErrors();
        $id = DB::table('exams')->where('name', 'Model Test')->value('id');
        $rows = DB::table('exam_subjects')->where('exam_id', $id)->get();
        $this->assertSame(['Class 1', 'Class 2'], $rows->pluck('class_name')->unique()->sort()->values()->all());
        $this->assertSame(['Class 1' => 1], ['Class 1' => $rows->where('class_name', 'Class 1')->groupBy('exam_date')->map->count()->max()], 'one subject per day');
        $this->assertTrue($rows->every(fn ($r) => ! in_array((int) date('w', strtotime($r->exam_date)), [5, 6], true) && $r->starts_at === '09:30'));

        $this->post('/app/exams/quick', ['name' => 'Too short', 'starts_on' => '2027-03-01', 'ends_on' => '2027-03-02', 'starts_at' => '10:00', 'classes' => ['Class 1']])->assertSessionHasErrors('ends_on');
        $this->post('/app/exams/quick', ['name' => 'Model Test', 'starts_on' => '2027-03-01', 'ends_on' => '2027-03-30', 'starts_at' => '10:00', 'classes' => ['Class 1']])->assertSessionHasErrors('name');
        $this->post('/app/exams/quick', ['name' => 'No class', 'starts_on' => '2027-03-01', 'ends_on' => '2027-03-30', 'starts_at' => '10:00', 'classes' => []])->assertSessionHasErrors('classes');
        $this->actingAs($this->u('jsmith'))->post('/app/exams/quick', ['name' => 'X', 'starts_on' => '2027-03-01', 'ends_on' => '2027-03-30', 'starts_at' => '10:00', 'classes' => ['Class 1']])->assertForbidden();
    }

    public function test_student_and_parent_see_the_next_exam_admit_card_until_dues_are_too_high(): void
    {
        $student = $this->u('student');
        $exam = DB::table('exams')->where('ends_on', '>=', today())->orderBy('starts_on')->first();
        $this->assertNotNull($exam, 'an upcoming exam is seeded');

        $this->actingAs($student)->get('/student/admit-card')->assertOk()->assertInertia(fn (Assert $p) => $p->where('data.exams.0.name', $exam->name)->where('data.exams.0.allowed', true)->has('data.exams.0.schedule'));
        $this->get("/admit-card/{$exam->id}.pdf")->assertOk();
        $this->actingAs($this->u('parent'))->get('/parent/admit-card')->assertOk()->assertInertia(fn (Assert $p) => $p->where('data.exams.0.allowed', true));
        $this->get("/admit-card/{$exam->id}.pdf?child={$student->id}")->assertOk();
        $this->get("/admit-card/{$exam->id}.pdf?child=".User::where('role', 'student')->where('id', '!=', $student->id)->value('id'))->assertNotFound();

        // The school holds cards back above a limit; the student owes more than that.
        DB::table('fee_invoices')->where('user_id', $student->id)->update(['paid' => 0]);
        $due = \App\Support\AdmitCards::due($student->id);
        $this->assertGreaterThan(0, $due, 'the demo student has dues');
        $this->actingAs($this->u('superadmin'))->post('/app/exams/admit-settings', ['admit_due_limit' => max(0, $due - 1)])->assertSessionHasNoErrors();
        $this->actingAs($student)->get('/student/admit-card')->assertInertia(fn (Assert $p) => $p->where('data.exams.0.allowed', false)->where('data.exams.0.due', $due));
        $this->get("/admit-card/{$exam->id}.pdf")->assertForbidden();
        $this->actingAs($this->u('parent'))->get("/admit-card/{$exam->id}.pdf?child={$student->id}")->assertForbidden();

        // The office releases it for one student, then takes the release back.
        $this->actingAs($this->u('superadmin'))->post('/app/exams/admit-override', ['exam_id' => $exam->id, 'user_id' => $student->id, 'allow' => true])->assertSessionHasNoErrors();
        $this->actingAs($student)->get("/admit-card/{$exam->id}.pdf")->assertOk();
        $this->actingAs($this->u('superadmin'))->post('/app/exams/admit-override', ['exam_id' => $exam->id, 'user_id' => $student->id, 'allow' => false]);
        $this->actingAs($student)->get("/admit-card/{$exam->id}.pdf")->assertForbidden();

        // A limit that covers the dues, or no limit at all, opens it again.
        $this->actingAs($this->u('superadmin'))->post('/app/exams/admit-settings', ['admit_due_limit' => ''])->assertSessionHasNoErrors();
        $this->actingAs($student)->get("/admit-card/{$exam->id}.pdf")->assertOk();
        $this->actingAs($this->u('jsmith'))->post('/app/exams/admit-settings', ['admit_due_limit' => 5])->assertForbidden();
        $this->actingAs($student)->post('/app/exams/admit-override', ['exam_id' => $exam->id, 'user_id' => $student->id, 'allow' => true])->assertForbidden();
    }
}
