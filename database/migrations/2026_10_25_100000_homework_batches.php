<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('homework_items', function (Blueprint $table) {
            $table->string('batch', 40)->nullable()->index();
            $table->string('grade', 20)->nullable();
            $table->timestamp('marked_at')->nullable();
        });

        // Group existing per-student rows into assignments.
        foreach (DB::table('homework_items')->get(['id', 'title', 'subject', 'teacher', 'due_on']) as $row) {
            DB::table('homework_items')->where('id', $row->id)->update(['batch' => md5($row->title.'|'.$row->subject.'|'.$row->teacher.'|'.$row->due_on)]);
        }
    }

    public function down(): void
    {
        Schema::table('homework_items', function (Blueprint $table) {
            $table->dropColumn(['batch', 'grade', 'marked_at']);
        });
    }
};
