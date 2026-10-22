<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fee_heads', function (Blueprint $table) {
            $table->string('category', 30)->default('tuition');
            $table->string('frequency', 20)->default('monthly');
            $table->boolean('optional')->default(false);
            $table->string('description', 255)->nullable();
        });
        DB::table('fee_heads')->where('name', 'like', '%Exam%')->update(['category' => 'exam', 'frequency' => 'per_exam']);

        Schema::table('fee_waivers', function (Blueprint $table) {
            $table->string('applies_to', 30)->default('tuition');
            $table->string('type', 30)->default('other');
        });

        Schema::create('fee_invoice_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')->constrained('fee_invoices')->cascadeOnDelete();
            $table->foreignId('fee_head_id')->nullable()->constrained('fee_heads')->nullOnDelete();
            $table->string('category', 30)->default('other');
            $table->string('label', 150);
            $table->unsignedInteger('amount');
            $table->unsignedInteger('discount')->default(0);
            $table->unsignedInteger('net');
            $table->string('discount_note', 150)->nullable();
        });

        Schema::create('fee_assignments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('fee_head_id')->constrained('fee_heads')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['user_id', 'fee_head_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fee_assignments');
        Schema::dropIfExists('fee_invoice_items');
        Schema::table('fee_waivers', fn (Blueprint $t) => $t->dropColumn(['applies_to', 'type']));
        Schema::table('fee_heads', fn (Blueprint $t) => $t->dropColumn(['category', 'frequency', 'optional', 'description']));
    }
};
