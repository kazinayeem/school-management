<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DocumentRequestTest extends TestCase
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

    public function test_family_applies_office_approves_and_the_document_downloads(): void
    {
        $student = $this->u('student');
        $this->actingAs($this->u('parent'))->post('/parent/requests', ['kind' => 'transfer_certificate', 'student_id' => $student->id, 'purpose' => 'Moving to Chattogram'])->assertSessionHasNoErrors();
        $r = DB::table('document_requests')->first();
        $this->assertMatchesRegularExpression('/^REQ-\d{2}-\d{5}$/', $r->reference);
        $this->assertSame(1, DB::table('app_notifications')->where('user_id', $this->u('superadmin')->id)->where('url', '/app/certificates/requests')->count());
        $this->post('/parent/requests', ['kind' => 'transfer_certificate', 'student_id' => $student->id, 'purpose' => 'again'])->assertSessionHasErrors('kind');
        $this->get("/requests/{$r->id}/download")->assertNotFound();

        $this->actingAs($this->u('superadmin'))->get('/app/certificates/requests')->assertOk()->assertInertia(fn (Assert $p) => $p->component('comms/requests')->has('rows', 1));
        $this->post("/app/certificates/requests/{$r->id}", ['decision' => 'reject'])->assertSessionHasErrors('note');
        $this->post("/app/certificates/requests/{$r->id}", ['decision' => 'approve', 'note' => 'Collect the original from the office.'])->assertSessionHasNoErrors();
        $r = DB::table('document_requests')->find($r->id);
        $this->assertSame('approved', $r->status);
        $this->assertNotNull($r->certificate_id);
        $this->assertSame('Transfer Certificate', DB::table('certificates')->where('id', $r->certificate_id)->value('title'));
        $this->post("/app/certificates/requests/{$r->id}", ['decision' => 'reject', 'note' => 'x']);
        $this->assertSame('approved', DB::table('document_requests')->find($r->id)->status, 'a decision is final');
        $this->assertGreaterThanOrEqual(1, DB::table('app_notifications')->where('user_id', $this->u('parent')->id)->where('title', 'like', '%approved')->count());

        $this->actingAs($this->u('parent'))->get('/parent/requests')->assertInertia(fn (Assert $p) => $p->where('rows.0.download', "/requests/{$r->id}/download"));
        $this->get("/requests/{$r->id}/download")->assertOk();
        $this->actingAs($student)->get("/requests/{$r->id}/download")->assertOk();
        $this->actingAs($this->u('jsmith'))->get("/requests/{$r->id}/download")->assertNotFound();
        $other = User::where('role', 'student')->where('id', '!=', $student->id)->first();
        $this->actingAs($other)->get("/requests/{$r->id}/download")->assertNotFound();
    }

    public function test_transcript_requests_decline_and_cancel_and_access_rules(): void
    {
        $student = $this->u('student');
        $this->actingAs($student)->post('/student/requests', ['kind' => 'transcript', 'purpose' => 'Scholarship'])->assertSessionHasNoErrors();
        $id = DB::table('document_requests')->value('id');
        $this->actingAs($this->u('superadmin'))->post("/app/certificates/requests/{$id}", ['decision' => 'approve'])->assertSessionHasNoErrors();
        $this->actingAs($student)->get("/requests/{$id}/download")->assertRedirect("/students/{$student->id}/transcript.pdf");

        $this->post('/student/requests', ['kind' => 'testimonial', 'purpose' => 'Job'])->assertSessionHasNoErrors();
        $t = DB::table('document_requests')->where('kind', 'testimonial')->value('id');
        $this->post("/student/requests/{$t}/cancel");
        $this->assertSame('cancelled', DB::table('document_requests')->find($t)->status);
        $this->post('/student/requests', ['kind' => 'bogus', 'purpose' => 'x'])->assertSessionHasErrors('kind');
        $this->post('/student/requests', ['kind' => 'other', 'purpose' => ''])->assertSessionHasErrors('purpose');

        // A parent cannot ask for somebody else's child, and nobody outside the family can see or cancel it.
        $other = User::where('role', 'student')->where('id', '!=', $student->id)->first();
        $this->actingAs($this->u('parent'))->post('/parent/requests', ['kind' => 'other', 'student_id' => $other->id, 'purpose' => 'x'])->assertForbidden();
        $this->actingAs($other)->post("/student/requests/{$id}/cancel")->assertNotFound();
        $this->actingAs($this->u('jsmith'))->get('/student/requests')->assertForbidden();
        $this->actingAs($student)->get('/app/certificates/requests')->assertForbidden();
        $this->actingAs($student)->get('/student/requests')->assertOk();
    }

    public function test_attendance_can_be_filtered_by_date_range(): void
    {
        $student = $this->u('student');
        $this->actingAs($student)->get('/student/attendance?from='.today()->subDays(40)->toDateString().'&to='.today()->toDateString())->assertOk()
            ->assertInertia(fn (Assert $p) => $p->where('data.from', today()->subDays(40)->toDateString())->where('data.to', today()->toDateString())->has('data.records')->where('data.records', fn ($r) => collect($r)->every(fn ($x) => $x['date'] >= today()->subDays(40)->toDateString())));
        $this->get('/student/attendance?from=garbage&to=nonsense')->assertOk();
        $this->get('/student/attendance?from='.today()->toDateString().'&to='.today()->subDays(5)->toDateString())->assertOk()->assertInertia(fn (Assert $p) => $p->where('data.from', today()->subDays(5)->toDateString()));
        $child = $student->id;
        $this->actingAs($this->u('parent'))->get("/parent/attendance?child={$child}&from=2026-01-01&to=2026-03-31")->assertOk()->assertInertia(fn (Assert $p) => $p->where('data.from', '2026-01-01')->where('data.days', []));
        $this->postJson('/pdf/table', ['title' => 'Attendance report', 'format' => 'csv', 'sections' => [['columns' => [['key' => 'date', 'label' => 'Date']], 'rows' => [['date' => '2026-01-01']]]]])->assertOk();
    }
}
