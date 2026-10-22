<?php

namespace Tests\Feature;

use App\Models\User;
use App\Support\StudentEnrollment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class PeopleTransportTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->actingAs($admin = User::factory()->create(['role' => 'admin', 'username' => 'boss']));

        return $admin;
    }

    private function student(string $name = 'Kid', string $class = 'Class 6', string $section = 'A'): User
    {
        return StudentEnrollment::enroll(['name' => $name, 'class_name' => $class, 'section' => $section, 'gender' => 'Male', 'guardian_name' => 'Guardian', 'guardian_mobile' => '01911111111']);
    }

    private function adm(User $s): string
    {
        return (string) $s->profile()->first()->admission_no;
    }

    // ---- staff

    public function test_staff_can_be_added_edited_and_deactivated(): void
    {
        $this->admin();

        $this->post('/app/staff', ['name' => 'New Teacher', 'password' => 'short'])->assertSessionHasErrors('password');
        $this->post('/app/staff', ['name' => 'New Teacher', 'password' => 'Secret123', 'designation' => 'Math Teacher', 'phone' => '01700000001', 'joined_on' => '2026-01-05'])->assertRedirect('/app/staff');

        $user = User::where('name', 'New Teacher')->first();
        $this->assertSame(['staff', 'new.teacher', 'new.teacher@borno.test', true], [$user->role, $user->username, $user->email, $user->is_active]);
        $this->assertMatchesRegularExpression('/^EMP-\d{4}$/', (string) DB::table('staff_profiles')->where('user_id', $user->id)->value('employee_no'));

        $this->post('/app/staff', ['name' => 'Other', 'username' => 'new.teacher', 'password' => 'Secret123'])->assertSessionHasErrors('username');
        $this->post('/app/staff', ['name' => 'Same Name', 'password' => 'Secret123'])->assertSessionHasNoErrors();
        $this->post('/app/staff', ['name' => 'Same Name', 'password' => 'Secret123'])->assertSessionHasNoErrors();
        $this->assertSame(['same.name', 'same.name.2'], User::where('name', 'Same Name')->orderBy('id')->pluck('username')->all());

        $this->put("/app/staff/{$user->id}", ['name' => 'New Teacher', 'designation' => 'Head of Maths', 'department' => 'Science', 'is_active' => true])->assertRedirect('/app/staff');
        $this->assertSame('Head of Maths', DB::table('staff_profiles')->where('user_id', $user->id)->value('designation'));
        $this->assertTrue(\Hash::check('Secret123', $user->fresh()->password), 'an empty password keeps the old one');

        $this->get("/app/staff/{$user->id}")->assertInertia(fn (Assert $p) => $p->where('staff.designation', 'Head of Maths'));
        $this->get('/app/staff?search=Head')->assertInertia(fn (Assert $p) => $p->has('rows', 1)->where('counts.active', fn ($n) => $n >= 1));

        $this->put("/app/staff/{$user->id}/status")->assertSessionHasNoErrors();
        $this->assertFalse($user->fresh()->is_active);
        $this->get('/app/staff?status=inactive')->assertInertia(fn (Assert $p) => $p->has('rows', 1));
    }

    public function test_inactive_accounts_cannot_sign_in_or_stay_signed_in(): void
    {
        $admin = $this->admin();
        $teacher = User::factory()->create(['role' => 'staff', 'username' => 'tina', 'password' => 'Secret123']);

        $this->put("/app/staff/{$teacher->id}/status");
        $this->post('/logout');
        $this->post('/login', ['login' => 'tina', 'password' => 'Secret123', 'role' => 'staff'])->assertSessionHasErrors('login');
        $this->assertGuest();

        $teacher->update(['is_active' => true]);
        $this->actingAs($teacher)->get('/app')->assertOk();
        $teacher->update(['is_active' => false]);
        $this->get('/app')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_you_cannot_deactivate_yourself_or_the_last_admin(): void
    {
        $admin = $this->admin();

        $this->put("/app/system/users/{$admin->id}/status")->assertSessionHas('error');
        $this->assertTrue($admin->fresh()->is_active);

        $second = User::factory()->create(['role' => 'admin']);
        $this->put("/app/system/users/{$second->id}/status")->assertSessionHasNoErrors();
        $this->assertFalse($second->fresh()->is_active);

        $this->actingAs($second->fresh()->forceFill(['is_active' => true]));
        DB::table('users')->where('id', $admin->id)->update(['is_active' => false]);
        $other = User::factory()->create(['role' => 'admin']);
        DB::table('users')->where('id', $other->id)->update(['is_active' => false]);
        $this->put("/app/system/users/{$admin->id}/status")->assertSessionHas('status');
    }

    // ---- parents and users

    public function test_parent_accounts_link_children_by_admission_number(): void
    {
        $this->admin();
        $a = $this->student('Child A');
        $b = $this->student('Child B');

        $this->post('/app/system/parents', ['name' => 'Mr Parent', 'phone' => '01712345678', 'password' => 'Secret123', 'children' => $this->adm($a).', '.$this->adm($b)])->assertSessionHasNoErrors();
        $parent = User::where('name', 'Mr Parent')->first();
        $this->assertSame('parent', $parent->role);
        $this->assertSame(2, DB::table('guardian_student')->where('guardian_id', $parent->id)->count());

        $this->post('/app/system/parents', ['name' => 'Bad Link', 'password' => 'Secret123', 'children' => '2026-99999'])->assertSessionHasErrors('children');
        $this->assertNull(User::where('name', 'Bad Link')->first());

        $this->put("/app/system/parents/{$parent->id}", ['name' => 'Mr Parent', 'children' => $this->adm($b)])->assertSessionHasNoErrors();
        $this->assertSame([$b->id], DB::table('guardian_student')->where('guardian_id', $parent->id)->pluck('student_id')->map(fn ($v) => (int) $v)->all());

        $this->get('/app/system/parents?search=Mr')->assertInertia(fn (Assert $p) => $p->has('rows', 1)->where('rows.0.children.0.name', 'Child B'));
    }

    public function test_user_accounts_filter_reset_passwords_and_add_admins(): void
    {
        $this->admin();
        $staff = User::factory()->create(['role' => 'staff', 'username' => 'sam', 'password' => 'OldPass123']);

        $this->get('/app/system/users?role=staff&search=sam')->assertInertia(fn (Assert $p) => $p->has('rows', 1)->where('rows.0.username', 'sam')->where('counts.admin', 1));

        $this->put("/app/system/users/{$staff->id}/password", ['password' => 'short'])->assertSessionHasErrors('password');
        $this->put("/app/system/users/{$staff->id}/password", ['password' => 'BrandNew123'])->assertSessionHasNoErrors();
        $this->assertTrue(\Hash::check('BrandNew123', $staff->fresh()->password));

        $this->post('/app/system/users', ['name' => 'Second Admin', 'username' => 'admin2', 'password' => 'Secret123'])->assertSessionHasNoErrors();
        $this->assertSame('admin', User::where('username', 'admin2')->value('role'));
        $this->post('/app/system/users', ['name' => 'Dup', 'username' => 'admin2', 'password' => 'Secret123'])->assertSessionHasErrors('username');

        $this->assertTrue(DB::table('audit_logs')->where('subject', 'Account')->exists());
    }

    // ---- roll numbers & ID cards

    public function test_roll_numbers_are_saved_and_must_be_unique(): void
    {
        $this->admin();
        $a = $this->student('Zed');
        $b = $this->student('Amy');
        $rolls = fn (array $r) => ['class_name' => 'Class 6', 'section' => 'A', 'rolls' => $r];

        $this->post('/app/students/roll-numbers', $rolls([['user_id' => $a->id, 'roll_no' => 2], ['user_id' => $b->id, 'roll_no' => 2]]))->assertSessionHasErrors('rolls');
        $this->post('/app/students/roll-numbers', $rolls([['user_id' => $a->id, 'roll_no' => 2]]))->assertSessionHas('error');
        $this->post('/app/students/roll-numbers', $rolls([['user_id' => $a->id, 'roll_no' => 2], ['user_id' => $b->id, 'roll_no' => 1]]))->assertSessionHasNoErrors();

        $this->assertSame(['2', '1'], [$a->profile()->first()->roll_no, $b->profile()->first()->roll_no]);
        $this->get('/app/students/roll-numbers?class=Class%206&section=A')->assertInertia(fn (Assert $p) => $p->where('students.0.name', 'Amy')->where('students.1.name', 'Zed'));
    }

    public function test_id_cards_filter_by_class_section_and_roll(): void
    {
        $this->admin();
        $a = $this->student('Alpha', 'Class 6', 'A');
        $this->student('Beta', 'Class 6', 'B');
        $this->student('Gamma', 'Class 7', 'A');

        $this->get('/app/students/id-cards?class=Class%206')->assertInertia(fn (Assert $p) => $p->has('students', 2));
        $this->get('/app/students/id-cards?class=Class%206&section=B')->assertInertia(fn (Assert $p) => $p->has('students', 1)->where('students.0.name', 'Beta'));
        $this->get('/app/students/id-cards?class=Class%206&roll=1')->assertInertia(fn (Assert $p) => $p->has('students', 2));
        $this->get('/app/students/id-cards?class=Class%206&section=A&roll=99')->assertInertia(fn (Assert $p) => $p->has('students', 0));
        $this->get('/app/students/id-cards?class=Class%206')->assertInertia(fn (Assert $p) => $p->where('students.0.admission_no', $this->adm($a))->has('school.name'));
    }

    // ---- transport

    private function route(string $name = 'North Line', int $fare = 1000): array
    {
        $this->post('/app/transport/routes', ['name' => $name, 'fare' => $fare, 'active' => true])->assertSessionHasNoErrors();
        $route = (int) DB::table('transport_routes')->where('name', $name)->value('id');
        $this->post('/app/transport/stops', ['route_id' => $route, 'name' => 'Stop One', 'pickup_time' => '07:00', 'drop_time' => '15:00', 'sort_order' => 1])->assertSessionHasNoErrors();
        $this->post('/app/transport/stops', ['route_id' => $route, 'name' => 'Stop Two', 'pickup_time' => '07:20', 'drop_time' => '14:40', 'sort_order' => 2])->assertSessionHasNoErrors();
        $this->assertSame(2, DB::table('transport_stops')->where('route_id', $route)->count(), 'both stops were saved');

        return [$route, (int) DB::table('transport_stops')->where('route_id', $route)->where('name', 'Stop One')->value('id'), (int) DB::table('transport_stops')->where('route_id', $route)->where('name', 'Stop Two')->value('id')];
    }

    public function test_routes_and_stops_are_validated_and_guarded(): void
    {
        $this->admin();
        [$route, $stop1, $stop2] = $this->route();

        $this->post('/app/transport/routes', ['name' => 'North Line', 'fare' => 5])->assertSessionHasErrors('name');
        $this->post('/app/transport/stops', ['route_id' => $route, 'name' => 'X', 'pickup_time' => '7am'])->assertSessionHasErrors('pickup_time');
        $this->put("/app/transport/stops/{$stop2}", ['route_id' => $route, 'name' => 'Stop Two', 'pickup_time' => '07:25', 'sort_order' => 2])->assertSessionHasNoErrors();
        $this->assertSame('07:25', substr((string) DB::table('transport_stops')->where('id', $stop2)->value('pickup_time'), 0, 5));

        $s = $this->student();
        $this->post('/app/transport/riders', ['student' => $this->adm($s), 'route_id' => $route, 'stop_id' => $stop1])->assertSessionHasNoErrors();
        $this->delete("/app/transport/stops/{$stop1}")->assertSessionHas('error');
        $this->delete("/app/transport/routes/{$route}")->assertSessionHas('error');
        $this->delete("/app/transport/stops/{$stop2}")->assertSessionHas('status');

        $this->delete("/app/transport/riders/{$s->id}")->assertRedirect();
        $this->delete("/app/transport/routes/{$route}")->assertRedirect('/app/transport/routes');
        $this->assertSame(0, DB::table('transport_stops')->count());
    }

    public function test_assigning_students_checks_stop_vehicle_capacity_and_syncs_the_portal(): void
    {
        $this->admin();
        [$route, $stop1] = $this->route('East Line', 1500);
        [$other, $otherStop] = $this->route('West Line', 900);
        $this->post('/app/transport/vehicles', ['name' => 'Mini Bus', 'capacity' => 1, 'driver_name' => 'Driver Dan', 'driver_phone' => '01800000000', 'route_id' => $route, 'active' => true])->assertSessionHasNoErrors();
        $vehicle = (int) DB::table('transport_vehicles')->where('name', 'Mini Bus')->value('id');

        $a = $this->student('Rider A');
        $b = $this->student('Rider B');

        $this->post('/app/transport/riders', ['student' => 'nobody', 'route_id' => $route, 'stop_id' => $stop1])->assertSessionHasErrors('student');
        $this->post('/app/transport/riders', ['student' => $this->adm($a), 'route_id' => $route, 'stop_id' => $otherStop])->assertSessionHasErrors('stop_id');
        $this->post('/app/transport/riders', ['student' => $this->adm($a), 'route_id' => $route, 'stop_id' => $stop1, 'vehicle_id' => $vehicle])->assertSessionHasNoErrors();
        $this->post('/app/transport/riders', ['student' => $this->adm($b), 'route_id' => $route, 'stop_id' => $stop1, 'vehicle_id' => $vehicle])->assertSessionHasErrors('vehicle_id');
        $this->post('/app/transport/riders', ['student' => $this->adm($a), 'route_id' => $route, 'stop_id' => $stop1, 'vehicle_id' => $vehicle])->assertSessionHasNoErrors();

        $row = DB::table('bus_assignments')->where('user_id', $a->id)->first();
        $this->assertSame(['East Line', 'Stop One', 'Mini Bus', 'Driver Dan', 1500, '07:00'], [$row->route, $row->stop, $row->vehicle, $row->driver, (int) $row->fare, $row->pickup_time]);

        $this->put("/app/transport/routes/{$route}", ['name' => 'East Express', 'fare' => 1800, 'active' => true]);
        $this->put("/app/transport/stops/{$stop1}", ['route_id' => $route, 'name' => 'Stop Uno', 'pickup_time' => '06:55', 'sort_order' => 1]);
        $this->assertSame(['East Express', 'Stop Uno', 1800, '06:55'], (function () use ($a) {
            $r = DB::table('bus_assignments')->where('user_id', $a->id)->first();

            return [$r->route, $r->stop, (int) $r->fare, $r->pickup_time];
        })());

        $this->actingAs($a);
        $this->get('/student/transport')->assertOk()->assertSee('East Express');
    }

    public function test_vehicles_page_counts_riders_and_guards_delete(): void
    {
        $this->admin();
        [$route, $stop] = $this->route();
        $this->post('/app/transport/vehicles', ['name' => 'Van', 'capacity' => 10, 'plate' => 'DHK-1', 'active' => true]);
        $this->post('/app/transport/vehicles', ['name' => 'Van 2', 'capacity' => 10, 'plate' => 'DHK-1'])->assertSessionHasErrors('plate');
        $vehicle = (int) DB::table('transport_vehicles')->where('name', 'Van')->value('id');
        $s = $this->student();
        $this->post('/app/transport/riders', ['student' => $this->adm($s), 'route_id' => $route, 'stop_id' => $stop, 'vehicle_id' => $vehicle]);

        $this->get('/app/transport/vehicles')->assertInertia(fn (Assert $p) => $p->where('rows.0.riders', 1)->where('rows.0.name', 'Van'));
        $this->delete("/app/transport/vehicles/{$vehicle}")->assertSessionHas('error');
        $this->delete("/app/transport/riders/{$s->id}");
        $this->delete("/app/transport/vehicles/{$vehicle}")->assertSessionHas('status');
    }

    public function test_who_rides_filters_and_manifest_orders_stops(): void
    {
        $this->admin();
        [$route, $stop1, $stop2] = $this->route();
        [$other, $otherStop] = $this->route('South Line');
        $a = $this->student('Alice');
        $b = $this->student('Bob');
        $c = $this->student('Cara');
        $this->post('/app/transport/riders', ['student' => $this->adm($a), 'route_id' => $route, 'stop_id' => $stop2]);
        $this->post('/app/transport/riders', ['student' => $this->adm($b), 'route_id' => $route, 'stop_id' => $stop1]);
        $this->post('/app/transport/riders', ['student' => $this->adm($c), 'route_id' => $other, 'stop_id' => $otherStop]);

        $this->get('/app/transport')->assertInertia(fn (Assert $p) => $p->has('rows', 3)->where('totals.riders', 3)->where('totals.monthly', 3000));
        $this->get("/app/transport?route={$route}")->assertInertia(fn (Assert $p) => $p->has('rows', 2));
        $this->get('/app/transport?search=cara')->assertInertia(fn (Assert $p) => $p->has('rows', 1)->where('rows.0.name', 'Cara'));

        $this->get("/app/transport/manifest?route={$route}&trip=pickup")->assertInertia(fn (Assert $p) => $p
            ->where('sections.0.title', 'Stop One — 07:00')->where('sections.0.rows.0.name', 'Bob')
            ->where('sections.1.title', 'Stop Two — 07:20')->where('sections.1.rows.0.name', 'Alice')->where('summary.0.value', 2));
        $this->get("/app/transport/manifest?route={$route}&trip=drop")->assertInertia(fn (Assert $p) => $p->where('sections.0.title', 'Stop Two — 14:40')->where('sections.1.title', 'Stop One — 15:00'));
    }

    public function test_all_of_these_pages_are_admin_only(): void
    {
        $staff = User::factory()->create(['role' => 'staff']);
        $this->actingAs($staff);

        foreach (['/app/staff', '/app/staff/new', "/app/staff/{$staff->id}", '/app/system/parents', '/app/system/users', '/app/students/roll-numbers', '/app/students/id-cards', '/app/transport', '/app/transport/manifest', '/app/transport/routes', '/app/transport/vehicles'] as $path) {
            $this->assertContains($this->get($path)->getStatusCode(), [403, 404], $path);
        }
        $this->post('/app/staff', ['name' => 'x', 'password' => 'Secret123'])->assertForbidden();
        $this->put("/app/system/users/{$staff->id}/password", ['password' => 'Secret123'])->assertForbidden();
        $this->post('/app/transport/riders', ['student' => 'x'])->assertForbidden();
    }
}
