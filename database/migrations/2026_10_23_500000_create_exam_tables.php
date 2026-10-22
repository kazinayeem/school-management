<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exams', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->date('starts_on');
            $table->date('ends_on');
            $table->timestamps();
        });

        Schema::create('exam_subjects', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->constrained()->cascadeOnDelete();
            $table->string('class_name');
            $table->foreignId('subject_id')->constrained('academic_subjects')->cascadeOnDelete();
            $table->date('exam_date');
            $table->time('starts_at')->nullable();
            $table->unsignedInteger('full_marks')->default(100);
            $table->unsignedInteger('pass_marks')->default(33);
            $table->string('room')->nullable();
            $table->timestamps();
            $table->unique(['exam_id', 'class_name', 'subject_id']);
        });

        Schema::create('exam_publications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('exam_id')->constrained()->cascadeOnDelete();
            $table->string('class_name');
            $table->timestamp('published_at')->useCurrent();
            $table->unique(['exam_id', 'class_name']);
        });

        Schema::table('exam_results', function (Blueprint $table) {
            $table->unsignedBigInteger('exam_id')->nullable();
            $table->unsignedBigInteger('subject_id')->nullable();
            $table->boolean('absent')->default(false);
            $table->decimal('grade_point', 3, 2)->nullable();
            $table->unique(['user_id', 'exam_id', 'subject_id']);
        });
    }

    public function down(): void
    {
        Schema::table('exam_results', function (Blueprint $table) {
            $table->dropUnique(['user_id', 'exam_id', 'subject_id']);
            $table->dropColumn(['exam_id', 'subject_id', 'absent', 'grade_point']);
        });
        Schema::dropIfExists('exam_publications');
        Schema::dropIfExists('exam_subjects');
        Schema::dropIfExists('exams');
    }
};
