<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

class TransportSync
{
    /**
     * The student portals read route, stop and vehicle details straight from the
     * assignment row, so copy the current details across after anything changes.
     */
    public static function refresh(): void
    {
        $routes = DB::table('transport_routes')->get()->keyBy('id');
        $stops = DB::table('transport_stops')->get()->keyBy('id');
        $vehicles = DB::table('transport_vehicles')->get()->keyBy('id');

        foreach (DB::table('bus_assignments')->get() as $a) {
            $route = $routes[$a->route_id] ?? null;
            $stop = $stops[$a->stop_id] ?? null;
            $vehicle = $a->vehicle_id ? ($vehicles[$a->vehicle_id] ?? null) : null;
            if ($route === null || $stop === null) {
                continue;
            }

            DB::table('bus_assignments')->where('id', $a->id)->update([
                'route' => $route->name, 'fare' => $route->fare, 'stop' => $stop->name, 'landmark' => $stop->landmark,
                'pickup_time' => $stop->pickup_time ? substr($stop->pickup_time, 0, 5) : null, 'drop_time' => $stop->drop_time ? substr($stop->drop_time, 0, 5) : null,
                'vehicle' => $vehicle?->name, 'driver' => $vehicle?->driver_name, 'driver_phone' => $vehicle?->driver_phone,
            ]);
        }
    }
}
