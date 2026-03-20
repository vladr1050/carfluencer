<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Aligns with docs/ARCHITECTURE/05_telemetry_pipeline.md:
 * device_locations → stop_sessions → zone attribution → daily_impressions / daily_zone_impressions.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telemetry_sync_cursors', function (Blueprint $table) {
            $table->id();
            $table->string('cursor_key')->unique();
            $table->timestamp('last_event_at', 6)->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('device_locations', function (Blueprint $table) {
            $table->id();
            $table->string('device_id', 64)->index();
            /** Pipeline field "timestamp" — stored as event_at (UTC). */
            $table->timestamp('event_at', 6)->index();
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);
            $table->decimal('speed', 8, 2)->nullable();
            $table->unsignedSmallInteger('battery')->nullable();
            $table->unsignedSmallInteger('gsm_signal')->nullable();
            $table->decimal('odometer', 14, 3)->nullable();
            $table->boolean('ignition')->nullable();
            $table->json('extra_json')->nullable();
            $table->timestamps();

            $table->unique(['device_id', 'event_at']);
        });

        Schema::create('geo_zones', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->string('name');
            $table->decimal('min_lat', 10, 7);
            $table->decimal('max_lat', 10, 7);
            $table->decimal('min_lng', 10, 7);
            $table->decimal('max_lng', 10, 7);
            $table->boolean('active')->default(true);
            $table->json('metadata')->nullable();
            $table->timestamps();
        });

        Schema::create('stop_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('device_id', 64)->index();
            $table->timestamp('started_at', 6)->index();
            $table->timestamp('ended_at', 6);
            $table->decimal('center_latitude', 10, 7);
            $table->decimal('center_longitude', 10, 7);
            $table->unsignedInteger('point_count')->default(0);
            /** driving | parking */
            $table->string('kind', 16)->index();
            $table->timestamps();

            $table->index(['device_id', 'started_at', 'ended_at']);
        });

        Schema::create('stop_session_zone', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stop_session_id')->constrained('stop_sessions')->cascadeOnDelete();
            $table->foreignId('zone_id')->constrained('geo_zones')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['stop_session_id', 'zone_id']);
        });

        Schema::create('daily_impressions', function (Blueprint $table) {
            $table->id();
            $table->date('stat_date')->index();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('impressions')->default(0);
            $table->decimal('driving_distance_km', 12, 3)->nullable();
            $table->unsignedInteger('parking_minutes')->nullable();
            $table->timestamps();

            $table->unique(['stat_date', 'campaign_id', 'vehicle_id']);
        });

        Schema::create('daily_zone_impressions', function (Blueprint $table) {
            $table->id();
            $table->date('stat_date')->index();
            $table->foreignId('zone_id')->constrained('geo_zones')->cascadeOnDelete();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->unsignedBigInteger('impressions')->default(0);
            $table->timestamps();

            $table->unique(['stat_date', 'zone_id', 'campaign_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_zone_impressions');
        Schema::dropIfExists('daily_impressions');
        Schema::dropIfExists('stop_session_zone');
        Schema::dropIfExists('stop_sessions');
        Schema::dropIfExists('geo_zones');
        Schema::dropIfExists('device_locations');
        Schema::dropIfExists('telemetry_sync_cursors');
    }
};
