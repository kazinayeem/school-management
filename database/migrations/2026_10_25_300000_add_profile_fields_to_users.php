<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('address', 255)->nullable();
            $table->string('alt_phone', 30)->nullable();
            $table->string('emergency_name', 100)->nullable();
            $table->string('emergency_phone', 30)->nullable();
            $table->string('blood_group', 5)->nullable();
            $table->date('date_of_birth')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['address', 'alt_phone', 'emergency_name', 'emergency_phone', 'blood_group', 'date_of_birth']);
        });
    }
};
