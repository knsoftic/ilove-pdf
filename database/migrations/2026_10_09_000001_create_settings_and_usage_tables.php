<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Key/value site settings managed from the admin panel (JSON values).
        Schema::create('settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->longText('value')->nullable();
            $table->timestamps();
        });

        // Anonymous aggregated usage counters – one row per tool, engine and day.
        Schema::create('tool_usages', function (Blueprint $table) {
            $table->id();
            $table->string('tool', 64);
            $table->string('engine', 16);
            $table->date('date');
            $table->unsignedInteger('count')->default(0);
            $table->unique(['tool', 'engine', 'date']);
            $table->index('date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tool_usages');
        Schema::dropIfExists('settings');
    }
};
