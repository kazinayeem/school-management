<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class ListExportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
        $this->actingAs(User::where('username', 'superadmin')->firstOrFail());
        // Pad the school to 130 active students so paging has several pages (60 seeded + 70).
        $now = now();
        for ($i = 1; $i <= 70; $i++) {
            $id = DB::table('users')->insertGetId(['name' => "Extra Kid {$i}", 'username' => "extra{$i}", 'email' => "extra{$i}@t.test", 'role' => 'student', 'password' => 'x', 'is_active' => 1, 'created_at' => $now, 'updated_at' => $now]);
            DB::table('student_profiles')->insert(['user_id' => $id, 'admission_no' => sprintf('2026-9%04d', $i), 'class_name' => 'Class 5', 'section' => 'A', 'roll_no' => (string) (100 + $i), 'session' => '2026', 'status' => 'active', 'attendance_present' => 0, 'attendance_working' => 0, 'dues_total' => 0, 'dues_count' => 0, 'guardian_mobile' => '01700000000', 'created_at' => $now, 'updated_at' => $now]);
        }
    }

    private function csv(array $body): array
    {
        $r = $this->post('/export/students', $body + ['format' => 'csv']);
        $r->assertOk();
        $this->assertStringContainsString('text/csv', (string) $r->headers->get('content-type'));
        $lines = array_values(array_filter(preg_split('/\r?\n/', ltrim($r->streamedContent(), "\xEF\xBB\xBF"))));

        return $lines;
    }

    public function test_lists_page_on_the_server_with_20_rows_by_default(): void
    {
        $this->get('/app/students')->assertInertia(fn (Assert $p) => $p->has('module.rows', 20)->where('module.pagination.total', 130)->where('module.pagination.limit', 20)->where('module.pagination.totalPages', 7)->where('module.pagination.from', 1)->where('module.pagination.to', 20));
        $this->get('/app/students?limit=50&page=3')->assertInertia(fn (Assert $p) => $p->has('module.rows', 30)->where('module.pagination.page', 3)->where('module.pagination.from', 101)->where('module.pagination.to', 130));
        $this->get('/app/students?limit=10')->assertInertia(fn (Assert $p) => $p->has('module.rows', 10)->where('module.pagination.totalPages', 13));
        // Unsupported sizes fall back to 20; pages past the end clamp to the last page.
        $this->get('/app/students?limit=1000')->assertInertia(fn (Assert $p) => $p->where('module.pagination.limit', 20));
        $this->get('/app/students?page=999')->assertInertia(fn (Assert $p) => $p->where('module.pagination.page', 7)->has('module.rows', 10));
        // Filters change the total, and sorting is whitelisted.
        $this->get('/app/students?class=Class%205&section=A&q=Extra')->assertInertia(fn (Assert $p) => $p->where('module.pagination.total', 70)->where('module.pagination.totalPages', 4));
        $this->get('/app/students?sort=name&dir=desc')->assertInertia(fn (Assert $p) => $p->where('module.pagination.sort', 'name')->where('module.pagination.dir', 'desc'));
        $this->get('/app/students?sort=password;drop')->assertOk()->assertInertia(fn (Assert $p) => $p->where('module.pagination.sort', 'admission_no'));
    }

    public function test_export_scopes_page_selected_and_all_matching(): void
    {
        // All matching ignores paging and includes every filtered row (header + 70).
        $this->assertCount(71, $this->csv(['scope' => 'all', 'class' => 'Class 5', 'section' => 'A', 'q' => 'Extra', 'page' => 2, 'limit' => 10]));
        $this->assertCount(131, $this->csv(['scope' => 'all']));
        // The current page only.
        $this->assertCount(11, $this->csv(['scope' => 'page', 'limit' => 10, 'page' => 2]));
        // Ticked rows only, even when the current filters would hide them.
        $ids = User::where('name', 'like', 'Extra Kid%')->orderBy('id')->limit(3)->pluck('id')->all();
        $this->assertCount(4, $this->csv(['scope' => 'selected', 'ids' => $ids, 'class' => 'Class 1']));
        // All matching except the ones the user un-ticked.
        $this->assertCount(69, $this->csv(['scope' => 'all', 'q' => 'Extra', 'exclude' => array_slice($ids, 0, 2)]));
        // Chosen columns only, in readable headings.
        $lines = $this->csv(['scope' => 'page', 'columns' => ['name', 'admission_no']]);
        $this->assertSame('Student,"Admission no"', $lines[0]);
    }

    public function test_exports_are_safe_valid_and_unicode(): void
    {
        $uid = User::where('name', 'Extra Kid 1')->value('id');
        DB::table('users')->where('id', $uid)->update(['name' => '=HYPERLINK("http://evil","x")']);
        $csv = $this->csv(['scope' => 'selected', 'ids' => [$uid]]);
        $this->assertStringContainsString("\"'=HYPERLINK", $csv[1], 'formulas are neutralised');

        DB::table('users')->where('id', $uid)->update(['name' => 'তানভীর আহমেদ']);
        $this->assertStringContainsString('তানভীর আহমেদ', $this->csv(['scope' => 'selected', 'ids' => [$uid]])[1]);

        $xlsx = $this->post('/export/students', ['format' => 'xlsx', 'scope' => 'all']);
        $xlsx->assertOk();
        $this->assertStringContainsString('spreadsheetml', (string) $xlsx->headers->get('content-type'));
        $this->assertStringStartsWith('PK', $xlsx->streamedContent());

        $pdf = $this->post('/export/students', ['format' => 'pdf', 'scope' => 'page']);
        $pdf->assertOk()->assertHeader('content-type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', $pdf->getContent());

        $html = $this->post('/export/students', ['format' => 'html', 'scope' => 'page'])->assertOk();
        $this->assertStringContainsString('<table', $html->getContent());
        $this->post('/export/students', ['format' => 'exe', 'scope' => 'page'])->assertSessionHasErrors('format');
        $this->post('/export/students', ['format' => 'csv', 'scope' => 'selected', 'ids' => [-5]])->assertStatus(422);
    }

    public function test_exports_follow_permissions(): void
    {
        $this->post('/export/nothing', ['format' => 'csv', 'scope' => 'all'])->assertNotFound();

        // A student or parent may not export lists at all.
        foreach (['student', 'parent'] as $who) {
            $this->actingAs(User::where('username', $who)->first());
            $this->post('/export/students', ['format' => 'csv', 'scope' => 'all'])->assertNotFound();
        }

        // A teacher exports only the students in their own sections, even when asking for ids outside them.
        $teacher = User::where('username', 'jsmith')->first();
        $this->actingAs($teacher);
        $mine = TeacherScopeHelper::count($teacher);
        $this->assertCount($mine + 1, $this->csv(['scope' => 'all']));
        $outsider = DB::table('student_profiles')->where('class_name', 'Class 3')->value('user_id');
        $this->post('/export/students', ['format' => 'csv', 'scope' => 'selected', 'ids' => [$outsider]])->assertStatus(422);

        // The accounts list is for administrators only.
        $this->post('/export/users', ['format' => 'csv', 'scope' => 'all'])->assertNotFound();
        $this->post('/logout');
        $this->postJson('/export/students', ['format' => 'csv', 'scope' => 'all'])->assertUnauthorized();
    }

    public function test_every_other_list_pages_and_exports(): void
    {
        $this->get('/app/staff?limit=10')->assertInertia(fn (Assert $p) => $p->has('rows', 10)->where('pagination.total', 30)->where('pagination.totalPages', 3));
        $this->get('/app/system/parents?limit=50')->assertInertia(fn (Assert $p) => $p->where('pagination.limit', 50)->has('rows', 50));
        $this->get('/app/system/users')->assertInertia(fn (Assert $p) => $p->has('rows', 20)->where('pagination.total', DB::table('users')->count()));
        $this->get('/app/admissions')->assertInertia(fn (Assert $p) => $p->has('pagination.total'));

        foreach (['staff' => 31, 'parents' => 1 + DB::table('users')->where('role', 'parent')->count(), 'users' => 1 + DB::table('users')->count(), 'admissions' => 1 + DB::table('admission_applications')->count()] as $key => $lines) {
            $r = $this->post("/export/{$key}", ['format' => 'csv', 'scope' => 'all'])->assertOk();
            $this->assertCount($lines, array_values(array_filter(preg_split('/\r?\n/', $r->streamedContent()))), $key);
        }
        $this->assertCount(7, array_values(array_filter(preg_split('/\r?\n/', $this->post('/export/staff', ['format' => 'csv', 'scope' => 'all', 'type' => 'staff'])->streamedContent()))), 'six office staff plus a header');
    }
}

class TeacherScopeHelper
{
    public static function count(User $teacher): int
    {
        $q = DB::table('student_profiles')->where('status', 'active')->where('session', '2026');
        \App\Support\TeacherScope::restrict($q, $teacher);

        return $q->count();
    }
}
