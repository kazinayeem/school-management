<?php

namespace Tests\Feature;

use App\Models\StudentProfile;
use App\Models\User;
use App\Support\StudentEnrollment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class AcademicAndAdmissionTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $admin = User::factory()->create(['role' => 'admin']);
        $this->actingAs($admin);

        return $admin;
    }

    private function student(string $class = 'Class 6', string $section = 'A'): User
    {
        return StudentEnrollment::enroll([
            'name' => 'Test Student '.random_int(1, 99999),
            'class_name' => $class,
            'section' => $section,
            'gender' => 'Male',
        ]);
    }

    public function test_lookup_pages_render_with_default_rows(): void
    {
        $this->admin();

        foreach (['groups' => 3, 'shifts' => 2, 'mediums' => 2] as $resource => $count) {
            $this->get("/app/academic/{$resource}")->assertOk()->assertInertia(
                fn (Assert $p) => $p->component('office/website')->where('baseUrl', "/app/academic/{$resource}")->has('rows', $count)
            );
        }
    }

    public function test_group_crud_and_unique_name(): void
    {
        $this->admin();

        $this->post('/app/academic/groups', ['name' => 'Vocational', 'active' => true])->assertRedirect();
        $id = DB::table('academic_groups')->where('name', 'Vocational')->value('id');
        $this->assertNotNull($id);

        $this->post('/app/academic/groups', ['name' => 'Vocational'])->assertSessionHasErrors('name');
        $this->put("/app/academic/groups/{$id}", ['name' => 'Vocational', 'description' => 'Trades', 'active' => false])->assertSessionHasNoErrors();
        $this->assertSame('Trades', DB::table('academic_groups')->where('id', $id)->value('description'));

        $this->delete("/app/academic/groups/{$id}")->assertRedirect();
        $this->assertNull(DB::table('academic_groups')->where('id', $id)->first());
    }

    public function test_shift_end_must_be_after_start(): void
    {
        $this->admin();

        $this->post('/app/academic/shifts', ['name' => 'Evening', 'starts_at' => '18:00', 'ends_at' => '17:00'])->assertSessionHasErrors('ends_at');
        $this->post('/app/academic/shifts', ['name' => 'Evening', 'starts_at' => '17:00', 'ends_at' => '20:00'])->assertSessionHasNoErrors();
    }

    public function test_bulk_import_creates_valid_rows_and_reports_bad_ones(): void
    {
        $this->admin();
        $csv = "name,class_name,section,gender,date_of_birth,guardian_name,guardian_mobile\n"
            ."Rahim Uddin,Class 6,A,Male,2014-03-21,Karim Uddin,01700000000\n"
            ."Nusrat Jahan,7,b,female,,,\n"
            ."Bad Row,Class 99,A,Male,,,\n"
            .",Class 6,A,Male,,,\n";

        $this->post('/app/students/import', ['file' => UploadedFile::fake()->createWithContent('students.csv', $csv)])
            ->assertSessionHas('import', fn ($r) => $r['created'] === 2 && $r['failed'] === 2 && $r['errors'][0]['row'] === 4);

        $this->assertSame(2, StudentProfile::count());
        $this->assertSame('Class 7', StudentProfile::where('section', 'B')->value('class_name'));
        $this->assertSame(['1', '1'], StudentProfile::orderBy('id')->pluck('roll_no')->all());
    }

    public function test_bulk_import_rejects_missing_columns_and_empty_input(): void
    {
        $this->admin();

        $this->post('/app/students/import', ['pasted' => "name,gender\nA,Male"])->assertSessionHasErrors('file');
        $this->post('/app/students/import', [])->assertSessionHasErrors('file');
        $this->get('/app/students/import/template')->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    public function test_promotion_moves_students_and_assigns_new_roll_numbers(): void
    {
        $this->admin();
        $existing = $this->student('Class 7', 'A');
        $a = $this->student('Class 6', 'A');
        $b = $this->student('Class 6', 'A');

        $this->post('/app/students/promotion', ['from_class' => 'Class 6', 'to_class' => 'Class 7', 'student_ids' => [$a->id, $b->id]])
            ->assertRedirect()
            ->assertSessionHas('promotion', ['moved' => 2, 'to' => 'Class 7']);

        $this->assertSame('Class 7', $a->profile()->first()->class_name);
        $this->assertSame(['2', '3'], [$a->profile()->first()->roll_no, $b->profile()->first()->roll_no]);
        $this->assertSame('1', $existing->profile()->first()->roll_no);
    }

    public function test_promotion_to_passed_out_marks_alumni_and_validates(): void
    {
        $this->admin();
        $s = $this->student('Class 10', 'A');

        $this->post('/app/students/promotion', ['from_class' => 'Class 10', 'to_class' => 'Passed out', 'student_ids' => [$s->id]])->assertRedirect();
        $this->assertSame('alumni', $s->profile()->first()->status);

        $this->post('/app/students/promotion', ['from_class' => 'Class 6', 'to_class' => 'Class 7', 'student_ids' => []])->assertSessionHasErrors('student_ids');
    }

    private function application(array $over = []): int
    {
        return DB::table('admission_applications')->insertGetId($over + [
            'tracking_no' => 'ADM-2026-'.random_int(1000, 9999),
            'class_name' => 'Class 6', 'first_name' => 'Sara', 'last_name' => 'Khan', 'date_of_birth' => '2014-05-05',
            'gender' => 'female', 'religion' => 'islam', 'address' => 'Dhaka', 'guardian_name' => 'Imran Khan',
            'relation' => 'father', 'mobile' => '01711111111', 'status' => 'received', 'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    public function test_admitting_an_application_creates_a_student(): void
    {
        $this->admin();
        $id = $this->application();

        $this->put("/app/admissions/{$id}", ['status' => 'admitted', 'section' => 'B'])->assertSessionHasNoErrors();

        $row = DB::table('admission_applications')->find($id);
        $this->assertSame('admitted', $row->status);
        $profile = StudentProfile::where('user_id', $row->student_user_id)->first();
        $this->assertSame(['Class 6', 'B', 'Female'], [$profile->class_name, $profile->section, $profile->gender]);

        $this->put("/app/admissions/{$id}", ['status' => 'rejected'])->assertSessionHasErrors('status');
        $this->assertSame(1, StudentProfile::count());
    }

    public function test_rejecting_and_filtering_applications(): void
    {
        $this->admin();
        $a = $this->application();
        $this->application(['first_name' => 'Zed']);

        $this->put("/app/admissions/{$a}", ['status' => 'rejected', 'admin_note' => 'Seats full'])->assertSessionHasNoErrors();
        $this->assertSame(0, StudentProfile::count());

        $this->get('/app/admissions?status=rejected')->assertInertia(fn (Assert $p) => $p->has('rows', 1)->where('counts.rejected', 1));
        $this->get('/app/admissions?search=Zed')->assertInertia(fn (Assert $p) => $p->has('rows', 1));
    }

    public function test_admission_settings_are_saved(): void
    {
        $this->admin();

        $this->post('/app/admissions/settings', ['admission_open' => false, 'admission_fee' => 500, 'admission_deadline' => '2026-12-31'])->assertSessionHasNoErrors();
        $this->get('/app/admissions/settings')->assertInertia(
            fn (Assert $p) => $p->where('values.admission_open', false)->where('values.admission_fee', '500')
        );
    }

    public function test_these_pages_are_admin_only(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'staff']));

        foreach (['/app/academic/groups', '/app/students/import', '/app/students/promotion', '/app/admissions', '/app/admissions/settings'] as $path) {
            $this->get($path)->assertForbidden();
        }
        $this->post('/app/students/import', ['pasted' => 'x'])->assertForbidden();
    }
}
