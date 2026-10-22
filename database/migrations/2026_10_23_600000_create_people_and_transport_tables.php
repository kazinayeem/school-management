<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->boolean('is_active')->default(true);
        });

        Schema::create('staff_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('employee_no')->unique();
            $table->string('designation')->nullable();
            $table->string('department')->nullable();
            $table->date('joined_on')->nullable();
            $table->string('address')->nullable();
            $table->timestamps();
        });

        Schema::create('transport_routes', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->unsignedInteger('fare')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('transport_stops', function (Blueprint $table) {
            $table->id();
            $table->foreignId('route_id')->constrained('transport_routes')->cascadeOnDelete();
            $table->string('name');
            $table->string('landmark')->nullable();
            $table->time('pickup_time')->nullable();
            $table->time('drop_time')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('transport_vehicles', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('plate')->nullable()->unique();
            $table->unsignedInteger('capacity')->default(40);
            $table->string('driver_name')->nullable();
            $table->string('driver_phone')->nullable();
            $table->foreignId('route_id')->nullable()->constrained('transport_routes')->nullOnDelete();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::table('bus_assignments', function (Blueprint $table) {
            $table->unsignedBigInteger('route_id')->nullable();
            $table->unsignedBigInteger('stop_id')->nullable();
            $table->unsignedBigInteger('vehicle_id')->nullable();
        });

        // Turn the text on the existing assignments into real routes, stops and vehicles.
        $now = now();
        foreach (DB::table('bus_assignments')->get() as $a) {
            $route = DB::table('transport_routes')->where('name', $a->route)->value('id')
                ?? DB::table('transport_routes')->insertGetId(['name' => $a->route, 'fare' => $a->fare, 'active' => true, 'created_at' => $now, 'updated_at' => $now]);
            $stop = DB::table('transport_stops')->where('route_id', $route)->where('name', $a->stop)->value('id')
                ?? DB::table('transport_stops')->insertGetId(['route_id' => $route, 'name' => $a->stop, 'landmark' => $a->landmark, 'pickup_time' => $a->pickup_time, 'drop_time' => $a->drop_time, 'sort_order' => 1, 'created_at' => $now, 'updated_at' => $now]);
            $vehicle = $a->vehicle
                ? (DB::table('transport_vehicles')->where('name', $a->vehicle)->value('id')
                    ?? DB::table('transport_vehicles')->insertGetId(['name' => $a->vehicle, 'capacity' => 40, 'driver_name' => $a->driver, 'driver_phone' => $a->driver_phone, 'route_id' => $route, 'active' => true, 'created_at' => $now, 'updated_at' => $now]))
                : null;
            DB::table('bus_assignments')->where('id', $a->id)->update(['route_id' => $route, 'stop_id' => $stop, 'vehicle_id' => $vehicle]);
        }

        // Give every existing staff member an employee number.
        foreach (DB::table('users')->where('role', 'staff')->orderBy('id')->get(['id']) as $i => $u) {
            DB::table('staff_profiles')->insert(['user_id' => $u->id, 'employee_no' => 'EMP-'.str_pad((string) ($i + 1), 4, '0', STR_PAD_LEFT), 'designation' => 'Teacher', 'created_at' => $now, 'updated_at' => $now]);
        }
    }

    public function down(): void
    {
        Schema::table('bus_assignments', function (Blueprint $table) {
            $table->dropColumn(['route_id', 'stop_id', 'vehicle_id']);
        });
        foreach (['transport_vehicles', 'transport_stops', 'transport_routes', 'staff_profiles'] as $t) {
            Schema::dropIfExists($t);
        }
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('is_active');
        });
    }
};
