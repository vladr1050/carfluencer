<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaign_vehicle_exposure_hourly', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->unsignedTinyInteger('hour');
            $table->string('cell_id', 32);
            $table->string('mode', 16);
            $table->unsignedInteger('exposure_seconds')->default(0);
            $table->decimal('avg_vehicle_speed', 8, 2)->nullable();
            $table->timestamps();

            $table->unique(
                ['campaign_id', 'vehicle_id', 'date', 'hour', 'cell_id', 'mode'],
                'campaign_vehicle_exposure_hourly_uidx'
            );
            $table->index(['campaign_id', 'date', 'hour'], 'campaign_vehicle_exposure_campaign_date_hour_idx');
            $table->index('cell_id', 'campaign_vehicle_exposure_cell_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_vehicle_exposure_hourly');
    }
};
