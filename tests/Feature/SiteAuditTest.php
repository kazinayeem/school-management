<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/** Opens every parameter-free page for every role and fails on any server error. */
class SiteAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_no_page_returns_a_server_error_for_any_role(): void
    {
        $this->seed(DatabaseSeeder::class);

        $student = User::where('username', 'student')->firstOrFail();
        $urls = collect(Route::getRoutes()->getRoutes())
            ->filter(fn ($r) => in_array('GET', $r->methods(), true))
            ->map(fn ($r) => '/'.ltrim($r->uri(), '/'))
            ->reject(fn ($u) => str_contains($u, '{') || str_starts_with($u, '/_') || str_starts_with($u, '/storage') || $u === '/up')
            ->unique()->values();

        // Parameterised pages with real ids, plus each tab of the student file.
        $extra = collect(['transcript', 'file'])->map(fn ($m) => "/students/{$student->id}/{$m}.pdf");
        foreach (['overview', 'results', 'attendance', 'fees', 'library', 'transport', 'certificates', 'leave', 'documents', 'notes-conduct', 'bogus'] as $tab) {
            $extra->push("/app/students/{$student->id}?tab={$tab}");
        }
        foreach (['profile', 'attendance', 'leave', 'pay', 'bogus'] as $tab) {
            $extra->push("/app/me?tab={$tab}");
        }
        $extra->push("/app/students/{$student->id}/transcript", "/app/students/{$student->id}/print", "/app/students/{$student->id}/export", "/app/fees/student?student={$student->id}", '/app/timetable?class=Class%203&section=B', '/app/timetable/free-rooms?day=2&period=3');
        $urls = $urls->merge($extra)->unique()->values();

        $bad = [];
        foreach (['superadmin', 'jsmith', 'accountant', 'student', 'parent', null] as $username) {
            $username ? $this->actingAs(User::where('username', $username)->firstOrFail()) : $this->app['auth']->forgetGuards();
            foreach ($urls as $url) {
                $status = $this->get($url)->getStatusCode();
                if ($status >= 500) {
                    $bad[] = ($username ?? 'guest')." {$url} => {$status}";
                }
            }
        }

        $this->assertSame([], $bad);
    }
}
