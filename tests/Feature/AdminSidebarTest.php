<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\OfficeMenu;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminSidebarTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_admin_sidebar_link_opens_for_admin(): void
    {
        $this->seed();
        $this->actingAs(User::where('role', 'admin')->firstOrFail());

        $links = OfficeMenu::flat('admin');
        $this->assertGreaterThan(60, count($links));

        $failed = [];
        foreach ($links as $link) {
            $status = $this->get($link['href'])->getStatusCode();
            if ($status !== 200) {
                $failed[] = "{$link['label']} {$link['href']} => {$status}";
            }
        }

        $this->assertSame([], $failed, implode("\n", $failed));
    }

    public function test_admin_menu_has_the_expected_groups_and_counts(): void
    {
        $captions = array_column(OfficeMenu::for('admin'), 'caption');
        $this->assertSame([null, 'Academic', 'People', 'Finance', 'Campus', 'Communication', 'System'], $captions);

        $this->assertNotNull(OfficeMenu::find('admin', '/app/academic/shifts'));
        $this->assertNotNull(OfficeMenu::find('admin', '/app/system/roles'));
    }

    public function test_staff_cannot_open_admin_only_pages(): void
    {
        $this->seed();
        $this->actingAs(User::where('role', 'staff')->firstOrFail());

        foreach (['/app/fees/generate', '/app/system/roles', '/app/website/pages', '/app/settings/general', '/app/accounting'] as $path) {
            $this->assertContains($this->get($path)->getStatusCode(), [403, 404], $path);
        }
    }
}
