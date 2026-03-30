<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mobility_reference_cells', function (Blueprint $table) {
            $table->id();
            /** H3 index hex string (normalized length in app layer). */
            $table->string('cell_id', 32)->unique();
            $table->unsignedInteger('vehicle_aadt');
            $table->unsignedInteger('pedestrian_daily');
            $table->decimal('average_speed_kmh', 6, 2);
            $table->decimal('hourly_peak_factor', 8, 4);
            $table->string('data_version', 64);
            $table->unsignedInteger('records_count')->default(0);
            $table->timestamps();

            $table->index(['data_version', 'cell_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mobility_reference_cells');
    }
};
