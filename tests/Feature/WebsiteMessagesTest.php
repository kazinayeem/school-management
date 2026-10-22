<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class WebsiteMessagesTest extends TestCase
{
    use RefreshDatabase;

    private function message(string $name, string $text, ?string $phone = null): int
    {
        return DB::table('contact_messages')->insertGetId(['name' => $name, 'email' => strtolower($name).'@example.com', 'phone' => $phone, 'message' => $text, 'created_at' => now(), 'updated_at' => now()]);
    }

    public function test_messages_from_the_website_arrive_as_new_and_can_be_handled(): void
    {
        $this->post('/contact', ['name' => 'Amelia', 'email' => 'amelia@example.com', 'message' => 'Please confirm the picnic bus time.'])->assertRedirect('/contact');
        $this->assertSame('new', DB::table('contact_messages')->value('status'));

        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $id = (int) DB::table('contact_messages')->value('id');
        $other = $this->message('Zed', 'Do you offer evening classes?', '01712345678');

        $this->get('/app/website/messages')->assertInertia(fn (Assert $p) => $p->component('office/website-messages')->has('rows', 2)->where('counts.new', 2));

        $this->put("/app/website/messages/{$id}", ['status' => 'read'])->assertSessionHasNoErrors();
        $this->put("/app/website/messages/{$id}", ['status' => 'replied'])->assertSessionHasNoErrors();
        $this->assertNotNull(DB::table('contact_messages')->where('id', $id)->value('handled_at'));
        $this->put("/app/website/messages/{$id}", ['status' => 'new']);
        $this->assertNull(DB::table('contact_messages')->where('id', $id)->value('handled_at'));
        $this->put("/app/website/messages/{$id}", ['status' => 'archived'])->assertSessionHasErrors('status');
        $this->put('/app/website/messages/9999', ['status' => 'read'])->assertNotFound();

        $this->get('/app/website/messages?status=new&search=picnic')->assertInertia(fn (Assert $p) => $p->has('rows', 1)->where('rows.0.name', 'Amelia'));
        $this->get('/app/website/messages?search=01712')->assertInertia(fn (Assert $p) => $p->has('rows', 1)->where('rows.0.id', $other));

        $this->delete("/app/website/messages/{$other}")->assertRedirect();
        $this->assertSame(1, DB::table('contact_messages')->count());
    }

    public function test_unread_messages_are_listed_first_and_the_page_is_admin_only(): void
    {
        $this->actingAs(User::factory()->create(['role' => 'admin']));
        $old = $this->message('Old', 'x');
        $this->message('Newer', 'y');
        DB::table('contact_messages')->where('id', $old)->update(['status' => 'replied']);

        $this->get('/app/website/messages')->assertInertia(fn (Assert $p) => $p->where('rows.0.name', 'Newer')->where('rows.1.name', 'Old'));

        $this->actingAs(User::factory()->create(['role' => 'teacher']));
        $this->get('/app/website/messages')->assertForbidden();
        $this->put("/app/website/messages/{$old}", ['status' => 'read'])->assertForbidden();
        $this->delete("/app/website/messages/{$old}")->assertForbidden();
    }
}
