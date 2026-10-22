<?php

namespace App\Http\Controllers;

use App\Support\Audit;
use App\Support\OfficeMenu;
use App\Support\TransportSync;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class TransportController extends Controller
{
    // ---- who rides

    public function riders(Request $request): Response
    {
        $this->admin($request);
        $route = (int) $request->query('route', 0);
        $search = trim((string) $request->query('search', ''));

        $rows = DB::table('bus_assignments')
            ->join('users', 'users.id', '=', 'bus_assignments.user_id')
            ->leftJoin('student_profiles', 'student_profiles.user_id', '=', 'users.id')
            ->leftJoin('transport_vehicles', 'transport_vehicles.id', '=', 'bus_assignments.vehicle_id')
            ->when($route > 0, fn ($q) => $q->where('bus_assignments.route_id', $route))
            ->when($search !== '', fn ($q) => $q->where(fn ($q) => $q->where('users.name', 'like', "%{$search}%")->orWhere('student_profiles.admission_no', 'like', "%{$search}%")))
            ->orderBy('bus_assignments.route')->orderBy('bus_assignments.stop')->orderBy('users.name')->limit(500)
            ->get(['users.id', 'users.name', 'student_profiles.admission_no', 'student_profiles.class_name', 'student_profiles.section', 'bus_assignments.route', 'bus_assignments.stop', 'transport_vehicles.name as vehicle', 'bus_assignments.fare']);

        return Inertia::render('office/who-rides', [
            ...$this->shared(),
            'rows' => $rows,
            'routes' => DB::table('transport_routes')->where('active', true)->orderBy('name')->get(['id', 'name', 'fare']),
            'stops' => DB::table('transport_stops')->orderBy('sort_order')->orderBy('name')->get(['id', 'route_id', 'name']),
            'vehicles' => DB::table('transport_vehicles')->where('active', true)->orderBy('name')
                ->get(['id', 'name', 'route_id', 'capacity'])->map(function ($v) {
                    $v->riders = DB::table('bus_assignments')->where('vehicle_id', $v->id)->count();

                    return $v;
                }),
            'filters' => ['route' => $route, 'search' => $search],
            'totals' => ['riders' => DB::table('bus_assignments')->count(), 'monthly' => (int) DB::table('bus_assignments')->sum('fare')],
        ]);
    }

    public function assign(Request $request): RedirectResponse
    {
        $this->admin($request);
        $data = $request->validate([
            'student' => ['required', 'string', 'max:100'],
            'route_id' => ['required', 'integer', Rule::exists('transport_routes', 'id')->where('active', 1)],
            'stop_id' => ['required', 'integer', 'exists:transport_stops,id'],
            'vehicle_id' => ['nullable', 'integer', Rule::exists('transport_vehicles', 'id')->where('active', 1)],
        ]);

        $student = DB::table('student_profiles')->join('users', 'users.id', '=', 'student_profiles.user_id')->where('student_profiles.status', 'active')
            ->where(fn ($q) => $q->where('student_profiles.admission_no', $data['student'])->orWhere('users.username', $data['student']))->first(['users.id', 'users.name']);
        if ($student === null) {
            return back()->withErrors(['student' => 'No active student found with that admission number or username.']);
        }
        if (! DB::table('transport_stops')->where('id', $data['stop_id'])->where('route_id', $data['route_id'])->exists()) {
            return back()->withErrors(['stop_id' => 'That stop is not on the chosen route.']);
        }
        if (! empty($data['vehicle_id'])) {
            $vehicle = DB::table('transport_vehicles')->find($data['vehicle_id']);
            $taken = DB::table('bus_assignments')->where('vehicle_id', $vehicle->id)->where('user_id', '!=', $student->id)->count();
            if ($taken >= $vehicle->capacity) {
                return back()->withErrors(['vehicle_id' => 'That vehicle is full.']);
            }
        }

        $existing = DB::table('bus_assignments')->where('user_id', $student->id)->first();
        DB::table('bus_assignments')->updateOrInsert(['user_id' => $student->id], [
            'route_id' => $data['route_id'], 'stop_id' => $data['stop_id'], 'vehicle_id' => $data['vehicle_id'] ?? null,
            'route' => '', 'stop' => '', 'since' => $existing->since ?? now()->format('F Y'),
        ]);
        TransportSync::refresh();
        Audit::log($existing ? 'updated' : 'created', 'Bus assignment', $student->name);

        return back()->with('status', $existing ? 'Assignment updated.' : 'Student added to the bus.');
    }

    public function unassign(Request $request, int $userId): RedirectResponse
    {
        $this->admin($request);
        DB::table('bus_assignments')->where('user_id', $userId)->delete();
        Audit::log('deleted', 'Bus assignment', "User #{$userId}");

        return back()->with('status', 'Entry deleted.');
    }

    // ---- daily manifest

    public function manifest(Request $request): Response
    {
        $this->admin($request);
        $routes = DB::table('transport_routes')->orderBy('name')->get(['id', 'name']);
        $routeId = (int) $request->query('route', $routes->first()->id ?? 0);
        $trip = $request->query('trip') === 'drop' ? 'drop' : 'pickup';
        $time = $trip === 'drop' ? 'drop_time' : 'pickup_time';

        $sections = [];
        $total = 0;
        foreach (DB::table('transport_stops')->where('route_id', $routeId)->orderBy($time)->orderBy('sort_order')->get() as $stop) {
            $rows = DB::table('bus_assignments')->join('users', 'users.id', '=', 'bus_assignments.user_id')->leftJoin('student_profiles', 'student_profiles.user_id', '=', 'users.id')
                ->where('bus_assignments.stop_id', $stop->id)->orderBy('users.name')
                ->get(['users.name', 'student_profiles.class_name', 'student_profiles.section', 'student_profiles.roll_no', 'student_profiles.guardian_name', 'student_profiles.guardian_mobile'])
                ->map(fn ($r) => ['name' => $r->name, 'class' => trim(($r->class_name ?? '').' '.($r->section ?? '')), 'roll' => $r->roll_no, 'guardian' => $r->guardian_name, 'phone' => $r->guardian_mobile])->all();
            $total += count($rows);
            $sections[] = [
                'title' => $stop->name.($stop->{$time} ? ' — '.substr($stop->{$time}, 0, 5) : ''),
                'columns' => [['key' => 'name', 'label' => 'Student'], ['key' => 'class', 'label' => 'Class'], ['key' => 'roll', 'label' => 'Roll'], ['key' => 'guardian', 'label' => 'Guardian'], ['key' => 'phone', 'label' => 'Phone']],
                'rows' => $rows,
            ];
        }

        $vehicles = DB::table('transport_vehicles')->where('route_id', $routeId)->where('active', true)->get(['name', 'driver_name', 'driver_phone']);

        return Inertia::render('office/report', [
            ...$this->shared(),
            'title' => 'Daily Manifest',
            'subtitle' => 'The students to collect or drop at each stop, in order.',
            'filters' => [
                ['name' => 'route', 'label' => 'Route', 'type' => 'select', 'options' => $routes->map(fn ($r) => ['value' => (string) $r->id, 'label' => $r->name])->all()],
                ['name' => 'trip', 'label' => 'Trip', 'type' => 'select', 'options' => [['value' => 'pickup', 'label' => 'Morning pickup'], ['value' => 'drop', 'label' => 'Afternoon drop']]],
            ],
            'values' => ['route' => (string) $routeId, 'trip' => $trip],
            'sections' => $sections,
            'summary' => [['label' => 'Students on this route', 'value' => $total], ['label' => 'Stops', 'value' => count($sections)], ['label' => 'Vehicles', 'value' => $vehicles->count()]],
            'notes' => $vehicles->map(fn ($v) => "{$v->name}".($v->driver_name ? " — {$v->driver_name}" : '').($v->driver_phone ? " ({$v->driver_phone})" : ''))->all(),
        ]);
    }

    // ---- routes & stops

    public function routes(Request $request): Response
    {
        $this->admin($request);
        $routes = DB::table('transport_routes')->orderBy('name')->get()->map(function ($r) {
            $r->stops = DB::table('transport_stops')->where('route_id', $r->id)->orderBy('sort_order')->orderBy('pickup_time')->get()->map(function ($s) {
                $s->pickup_time = $s->pickup_time ? substr($s->pickup_time, 0, 5) : '';
                $s->drop_time = $s->drop_time ? substr($s->drop_time, 0, 5) : '';
                $s->riders = DB::table('bus_assignments')->where('stop_id', $s->id)->count();

                return $s;
            });
            $r->riders = DB::table('bus_assignments')->where('route_id', $r->id)->count();

            return $r;
        });

        return Inertia::render('office/routes-stops', [...$this->shared(), 'routes' => $routes, 'open' => (int) $request->query('route', $routes->first()->id ?? 0)]);
    }

    public function storeRoute(Request $request): RedirectResponse
    {
        $this->admin($request);
        $id = DB::table('transport_routes')->insertGetId($this->routeData($request) + ['created_at' => now(), 'updated_at' => now()]);
        Audit::log('created', 'Route', "#{$id}");

        return redirect("/app/transport/routes?route={$id}")->with('status', 'Route added.');
    }

    public function updateRoute(Request $request, int $id): RedirectResponse
    {
        $this->admin($request);
        abort_unless(DB::table('transport_routes')->where('id', $id)->exists(), 404);
        DB::table('transport_routes')->where('id', $id)->update($this->routeData($request, $id) + ['updated_at' => now()]);
        TransportSync::refresh();
        Audit::log('updated', 'Route', "#{$id}");

        return back()->with('status', 'Route updated.');
    }

    public function destroyRoute(Request $request, int $id): RedirectResponse
    {
        $this->admin($request);
        if (DB::table('bus_assignments')->where('route_id', $id)->exists()) {
            return back()->with('error', 'Students ride this route. Move them first.');
        }
        DB::table('transport_routes')->where('id', $id)->delete();
        Audit::log('deleted', 'Route', "#{$id}");

        return redirect('/app/transport/routes')->with('status', 'Entry deleted.');
    }

    public function storeStop(Request $request): RedirectResponse
    {
        $this->admin($request);
        $data = $this->stopData($request);
        DB::table('transport_stops')->insert($data + ['created_at' => now(), 'updated_at' => now()]);
        Audit::log('created', 'Stop', $data['name']);

        return back()->with('status', 'Stop added.');
    }

    public function updateStop(Request $request, int $id): RedirectResponse
    {
        $this->admin($request);
        $stop = DB::table('transport_stops')->find($id);
        abort_if($stop === null, 404);
        $data = $this->stopData($request);
        if ($data['route_id'] !== (int) $stop->route_id && DB::table('bus_assignments')->where('stop_id', $id)->exists()) {
            return back()->withErrors(['route_id' => 'Students use this stop, so it cannot move to another route.']);
        }
        DB::table('transport_stops')->where('id', $id)->update($data + ['updated_at' => now()]);
        TransportSync::refresh();
        Audit::log('updated', 'Stop', "#{$id}");

        return back()->with('status', 'Stop updated.');
    }

    public function destroyStop(Request $request, int $id): RedirectResponse
    {
        $this->admin($request);
        if (DB::table('bus_assignments')->where('stop_id', $id)->exists()) {
            return back()->with('error', 'Students use this stop. Move them first.');
        }
        DB::table('transport_stops')->where('id', $id)->delete();
        Audit::log('deleted', 'Stop', "#{$id}");

        return back()->with('status', 'Entry deleted.');
    }

    private function routeData(Request $request, ?int $id = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('transport_routes', 'name')->ignore($id)],
            'fare' => ['required', 'integer', 'min:0', 'max:1000000'],
            'active' => ['boolean'],
        ]);
        $data['active'] = (bool) ($data['active'] ?? false);

        return $data;
    }

    private function stopData(Request $request): array
    {
        $data = $request->validate([
            'route_id' => ['required', 'integer', 'exists:transport_routes,id'],
            'name' => ['required', 'string', 'max:100'],
            'landmark' => ['nullable', 'string', 'max:150'],
            'pickup_time' => ['nullable', 'date_format:H:i'],
            'drop_time' => ['nullable', 'date_format:H:i'],
            'sort_order' => ['nullable', 'integer', 'min:0', 'max:1000'],
        ]);
        $data['route_id'] = (int) $data['route_id'];
        $data['sort_order'] = (int) ($data['sort_order'] ?? 0);
        $data['landmark'] = ($data['landmark'] ?? null) ?: null;
        $data['pickup_time'] = ($data['pickup_time'] ?? null) ?: null;
        $data['drop_time'] = ($data['drop_time'] ?? null) ?: null;

        return $data;
    }

    private function shared(): array
    {
        return ['navigation' => OfficeMenu::for('admin'), 'roleLabel' => 'Super Admin'];
    }

    private function admin(Request $request): void
    {
        abort_unless($request->user()->role === 'admin', 403);
    }
}
