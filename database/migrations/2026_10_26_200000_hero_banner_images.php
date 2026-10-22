<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('hero_banners', function (Blueprint $table) {
            $table->string('image_url', 500)->nullable();
            $table->string('eyebrow', 80)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('hero_banners', fn (Blueprint $t) => $t->dropColumn(['image_url', 'eyebrow']));
    }
};
