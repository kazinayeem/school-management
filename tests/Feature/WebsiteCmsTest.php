<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class WebsiteCmsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        return User::factory()->create(['role' => 'admin']);
    }

    public function test_every_resource_page_renders_for_admin(): void
    {
        $this->actingAs($this->admin());

        foreach (['pages', 'banners', 'events', 'gallery', 'testimonials', 'faqs', 'settings'] as $resource) {
            $this->get("/app/website/{$resource}")
                ->assertOk()
                ->assertInertia(fn (Assert $p) => $p->component('office/website')->where('resource', $resource));
        }
    }

    public function test_staff_and_guests_are_blocked(): void
    {
        $this->get('/app/website/faqs')->assertRedirect('/login');

        $this->actingAs(User::factory()->create(['role' => 'teacher']))
            ->get('/app/website/faqs')->assertForbidden();
    }

    public function test_faq_crud(): void
    {
        $this->actingAs($this->admin());

        $this->post('/app/website/faqs', ['question' => 'When do classes start?', 'answer' => 'At 8 AM.', 'sort_order' => 2, 'published' => true])
            ->assertRedirect();
        $id = DB::table('faqs')->value('id');
        $this->assertSame(1, (int) DB::table('faqs')->value('published'));

        $this->put("/app/website/faqs/{$id}", ['question' => 'When does school start?', 'answer' => 'At 8 AM.', 'sort_order' => 1, 'published' => false])
            ->assertRedirect();
        $this->assertSame('When does school start?', DB::table('faqs')->value('question'));
        $this->assertSame(0, (int) DB::table('faqs')->value('published'));

        $this->delete("/app/website/faqs/{$id}")->assertRedirect();
        $this->assertSame(0, DB::table('faqs')->count());
    }

    public function test_validation_rejects_missing_fields(): void
    {
        $this->actingAs($this->admin())
            ->post('/app/website/testimonials', ['name' => 'A', 'rating' => 9])
            ->assertSessionHasErrors(['quote', 'rating']);
    }

    public function test_page_slug_is_generated_and_unique(): void
    {
        $this->actingAs($this->admin());

        $this->post('/app/website/pages', ['title' => 'About Us', 'kind' => 'page', 'body' => 'x', 'published' => true]);
        $this->assertSame(1, DB::table('website_pages')->where('slug', 'about-us')->count());

        $this->post('/app/website/pages', ['title' => 'Other', 'kind' => 'page', 'slug' => 'about-us'])
            ->assertSessionHasErrors('slug');
    }

    public function test_policies_are_edited_with_rich_text_and_shown_publicly(): void
    {
        $this->actingAs($this->admin());
        $this->post('/app/website/pages', ['title' => 'Bus policy', 'kind' => 'policy', 'sort_order' => 9, 'body' => '<p>Be <strong>on time</strong>.</p><script>alert(1)</script>', 'published' => true])->assertSessionHasNoErrors();
        $body = DB::table('website_pages')->where('slug', 'bus-policy')->value('body');
        $this->assertStringNotContainsString('script', $body);

        $this->post('/logout');
        $this->get('/policies/bus-policy')->assertOk()->assertInertia(fn ($p) => $p->where('page.html', fn ($h) => str_contains($h, '<strong>on time</strong>'))->where('page.policies', fn ($l) => collect($l)->contains('slug', 'bus-policy')));
        \App\Support\SitePages::seed();
        $this->get('/fees')->assertOk()->assertInertia(fn ($p) => $p->where('page.kind', 'fees')->has('page.classes')->where('page.policies', fn ($l) => collect($l)->contains('slug', 'refund-policy')));
        $this->get('/privacy-policy')->assertOk();
        $this->get('/policies/nope')->assertNotFound();
    }

    public function test_settings_are_saved(): void
    {
        $this->actingAs($this->admin());

        $this->post('/app/website/settings', ['school_name' => 'Borno High', 'phone' => '123', 'admission_open' => false])
            ->assertRedirect();

        $this->assertSame('Borno High', DB::table('site_settings')->where('key', 'school_name')->value('value'));
        $this->assertSame('0', DB::table('site_settings')->where('key', 'admission_open')->value('value'));

        $this->get('/app/website/settings')->assertInertia(fn (Assert $p) => $p->where('values.school_name', 'Borno High')->where('values.admission_open', false));
    }
}
