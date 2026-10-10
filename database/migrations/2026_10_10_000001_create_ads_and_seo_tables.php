<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Ad units (Adsterra, Monetag, AdSense, custom code) managed in the admin.
        Schema::create('ad_units', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            $table->string('network', 20);
            $table->string('format', 30);
            $table->string('placement', 30);
            $table->longText('code')->nullable();
            $table->string('url', 2048)->nullable();
            $table->json('options')->nullable();
            $table->string('devices', 10)->default('all');
            $table->string('page_scope', 10)->default('all');
            $table->json('tools')->nullable();
            $table->boolean('is_active')->default(true);
            $table->integer('sort_order')->default(0);
            $table->timestamps();
            $table->index(['is_active', 'placement']);
        });

        // 301/302/410 redirects for changed or removed URLs.
        Schema::create('redirects', function (Blueprint $table) {
            $table->id();
            $table->string('source', 255)->unique();
            $table->string('target', 2048)->nullable();
            $table->unsignedSmallInteger('status_code')->default(301);
            $table->unsignedInteger('hits')->default(0);
            $table->timestamp('last_hit_at')->nullable();
            $table->timestamps();
        });

        // Aggregated "page not found" hits (path + counter only, no visitor data).
        Schema::create('not_found_logs', function (Blueprint $table) {
            $table->id();
            $table->string('path', 255)->unique();
            $table->unsignedInteger('hits')->default(1);
            $table->timestamp('last_seen_at')->nullable();
            $table->index('last_seen_at');
        });

        $this->importLegacyAdSlots();
    }

    public function down(): void
    {
        Schema::dropIfExists('not_found_logs');
        Schema::dropIfExists('redirects');
        Schema::dropIfExists('ad_units');
    }

    /**
     * Earlier versions stored one HTML snippet per slot in the settings table.
     */
    private function importLegacyAdSlots(): void
    {
        $legacy = json_decode((string) DB::table('settings')->where('key', 'ads')->value('value'), true);

        foreach ((array) $legacy as $slot => $code) {
            if (is_string($code) && trim($code) !== '' && in_array($slot, ['header', 'tool_top', 'tool_bottom', 'footer'], true)) {
                DB::table('ad_units')->insert([
                    'name' => 'Imported: '.$slot,
                    'network' => 'custom',
                    'format' => 'custom',
                    'placement' => $slot,
                    'code' => $code,
                    'devices' => 'all',
                    'page_scope' => 'all',
                    'is_active' => true,
                    'sort_order' => 0,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }

        DB::table('settings')->where('key', 'ads')->delete();
    }
};
