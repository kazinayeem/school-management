<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('academic_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->date('starts_on');
            $table->date('ends_on');
            $table->boolean('is_current')->default(false);
            $table->timestamps();
        });

        Schema::create('academic_classes', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('academic_sections', function (Blueprint $table) {
            $table->id();
            $table->string('class_name');
            $table->string('name');
            $table->unsignedInteger('capacity')->default(40);
            $table->timestamps();
            $table->unique(['class_name', 'name']);
        });

        Schema::create('academic_subjects', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('class_name')->nullable();
            $table->string('kind')->default('theory');
            $table->unsignedInteger('full_marks')->default(100);
            $table->unsignedInteger('pass_marks')->default(33);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('teaching_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('class_name');
            $table->string('section')->nullable();
            $table->foreignId('subject_id')->constrained('academic_subjects')->cascadeOnDelete();
            $table->timestamps();
        });

        $now = now();
        DB::table('academic_sessions')->insert(['name' => '2026', 'starts_on' => '2026-01-01', 'ends_on' => '2026-12-31', 'is_current' => true, 'created_at' => $now, 'updated_at' => $now]);

        foreach (range(1, 10) as $n) {
            DB::table('academic_classes')->insert(['name' => "Class {$n}", 'sort_order' => $n, 'created_at' => $now, 'updated_at' => $now]);
            foreach (['A', 'B'] as $section) {
                DB::table('academic_sections')->insert(['class_name' => "Class {$n}", 'name' => $section, 'capacity' => 40, 'created_at' => $now, 'updated_at' => $now]);
            }
        }

        foreach ([['Bangla', 'BAN'], ['English', 'ENG'], ['Mathematics', 'MAT'], ['Science', 'SCI'], ['Social Science', 'SOC'], ['Religion', 'REL']] as [$name, $code]) {
            DB::table('academic_subjects')->insert(['name' => $name, 'code' => $code, 'class_name' => null, 'kind' => 'theory', 'full_marks' => 100, 'pass_marks' => 33, 'active' => true, 'created_at' => $now, 'updated_at' => $now]);
        }
    }

    public function down(): void
    {
        foreach (['teaching_assignments', 'academic_subjects', 'academic_sections', 'academic_classes', 'academic_sessions'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};
