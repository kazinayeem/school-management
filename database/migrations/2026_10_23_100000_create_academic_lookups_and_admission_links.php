<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_groups', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('description')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('academic_shifts', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->time('starts_at')->nullable();
            $table->time('ends_at')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('academic_mediums', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::table('admission_applications', function (Blueprint $table) {
            $table->unsignedBigInteger('student_user_id')->nullable();
            $table->text('admin_note')->nullable();
        });

        $now = now();
        DB::table('academic_groups')->insert([
            ['name' => 'Science', 'description' => 'Physics, Chemistry, Biology, Higher Math', 'active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Commerce', 'description' => 'Accounting, Finance, Business studies', 'active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Arts', 'description' => 'Humanities and social science', 'active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);
        DB::table('academic_shifts')->insert([
            ['name' => 'Morning', 'starts_at' => '08:00', 'ends_at' => '12:30', 'active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'Day', 'starts_at' => '13:00', 'ends_at' => '17:30', 'active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);
        DB::table('academic_mediums')->insert([
            ['name' => 'Bangla', 'active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['name' => 'English', 'active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);
    }

    public function down(): void
    {
        Schema::table('admission_applications', function (Blueprint $table) {
            $table->dropColumn(['student_user_id', 'admin_note']);
        });
        Schema::dropIfExists('academic_mediums');
        Schema::dropIfExists('academic_shifts');
        Schema::dropIfExists('academic_groups');
    }
};
