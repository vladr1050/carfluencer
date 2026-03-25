<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pre-aggregated heatmap cells (daily × zoom tier × mode × device).
 * See docs/ARCHITECTURE/06_heatmap_rollup.md.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('heatmap_cells_daily', function (Blueprint $table) {
            $table->id();
            $table->date('day');
            /** driving | parking — matches advertiser API mode names */
            $table->string('mode', 16);
            /** Index into config telemetry.heatmap.rollup.zoom_tiers (0 = coarsest). */
            $table->unsignedSmallInteger('zoom_tier');
            $table->double('lat_bucket');
            $table->double('lng_bucket');
            /** IMEI / device_id string from device_locations */
            $table->string('device_id', 64);
            $table->unsignedInteger('samples_count')->default(0);
            /** MVP: same as samples_count; reserved for weighted / session-based rollups */
            $table->decimal('weight_value', 14, 4)->default(0);
            $table->timestamps();

            $table->unique(
                ['day', 'mode', 'zoom_tier', 'lat_bucket', 'lng_bucket', 'device_id'],
                'heatmap_cells_daily_cell_uidx'
            );
            $table->index(['mode', 'zoom_tier', 'day', 'device_id'], 'heatmap_cells_daily_lookup_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('heatmap_cells_daily');
    }
};
