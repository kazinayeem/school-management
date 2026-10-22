<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\OfficeMenu;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MenuLinksTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_menu_link_opens_for_its_role(): void
    {
        $this->seed(DatabaseSeeder::class);

        foreach (['jsmith' => 'teacher', 'accountant' => 'staff', 'superadmin' => 'admin'] as $username => $role) {
            $this->actingAs(User::where('username', $username)->firstOrFail());
            $urls = [];
            $menu = OfficeMenu::for($role);
            array_walk_recursive($menu, function ($value, $key) use (&$urls) {
                if ($key === 'href' && is_string($value) && str_starts_with($value, '/app')) {
                    $urls[] = $value;
                }
            });
            $this->assertNotEmpty($urls, $role);
            foreach (array_unique($urls) as $url) {
                $this->assertContains($this->get($url)->getStatusCode(), [200, 302], "$role $url");
            }
        }
    }
}
