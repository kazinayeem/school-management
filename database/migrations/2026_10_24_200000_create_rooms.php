<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rooms', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('kind')->default('classroom');
            $table->unsignedInteger('capacity')->default(40);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        // Rooms already written into routines and exam schedules become real rooms.
        $names = DB::table('class_periods')->whereNotNull('room')->where('room', '!=', '')->distinct()->pluck('room')
            ->merge(DB::table('exam_subjects')->whereNotNull('room')->where('room', '!=', '')->distinct()->pluck('room'))->unique();

        foreach ($names as $name) {
            DB::table('rooms')->insert(['name' => $name, 'kind' => 'classroom', 'capacity' => 40, 'active' => true, 'created_at' => now(), 'updated_at' => now()]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('rooms');
    }
};
