<?php

namespace Tests\Feature;

use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PublicSiteTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);
    }

    public function test_the_landing_page_lists_public_services(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('public/site')
                ->where('page.kind', 'home')
                ->where('page.ticker.title', 'Admission for the upcoming academic session is open'));
    }

    public function test_a_family_can_apply_and_track_admission(): void
    {
        $this->post('/admission', [
            'class_name' => 'Class 6',
            'first_name' => 'Ayaan',
            'last_name' => 'Rahman',
            'date_of_birth' => '2015-04-02',
            'gender' => 'male',
            'religion' => 'islam',
            'address' => 'Dhanmondi, Dhaka',
            'guardian_name' => 'Karim Rahman',
            'relation' => 'father',
            'mobile' => '01712000999',
        ])->assertRedirect('/admission')->assertSessionHas('tracking');

        $tracking = session('tracking');

        $this->post('/admission/track', [
            'tracking_no' => $tracking,
            'mobile' => '01712000999',
        ])->assertRedirect('/admission')->assertSessionHas('status.found', true);
    }

    public function test_a_published_result_can_be_checked_by_roll_number(): void
    {
        $this->get('/results?exam=half-yearly-8&roll=1')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('page.result.found', true)
                ->where('page.result.name', 'Ethan Parker')
                ->has('page.result.rows'));
    }

    public function test_the_office_receives_a_contact_message(): void
    {
        $this->post('/contact', [
            'name' => 'Amelia Parker',
            'email' => 'amelia@example.com',
            'message' => 'Please confirm the picnic bus time.',
        ])->assertRedirect('/contact');

        $this->assertDatabaseHas('contact_messages', ['email' => 'amelia@example.com']);
    }
}
