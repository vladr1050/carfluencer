<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('impression_coefficients', function (Blueprint $table) {
            $table->id();
            $table->string('version', 32)->unique();
            $table->decimal('vehicle_visibility_share', 8, 6)->default(0.08);
            $table->decimal('pedestrian_visibility_share', 8, 6)->default(0.03);
            $table->decimal('pedestrian_parking_share', 8, 6)->default(0.12);
            $table->decimal('roadside_vehicle_share', 8, 6)->default(0.02);
            $table->decimal('speed_factor_low', 8, 4)->default(1.15);
            $table->decimal('speed_factor_medium', 8, 4)->default(1.00);
            $table->decimal('speed_factor_high', 8, 4)->default(0.75);
            $table->decimal('speed_factor_very_high', 8, 4)->default(0.55);
            $table->decimal('dwell_factor_short', 8, 4)->default(0.8);
            $table->decimal('dwell_factor_medium', 8, 4)->default(1.0);
            $table->decimal('dwell_factor_long', 8, 4)->default(1.1);
            $table->timestamps();
        });

        DB::table('impression_coefficients')->insert([
            'version' => 'v1.0',
            'vehicle_visibility_share' => 0.08,
            'pedestrian_visibility_share' => 0.03,
            'pedestrian_parking_share' => 0.12,
            'roadside_vehicle_share' => 0.02,
            'speed_factor_low' => 1.15,
            'speed_factor_medium' => 1.00,
            'speed_factor_high' => 0.75,
            'speed_factor_very_high' => 0.55,
            'dwell_factor_short' => 0.8,
            'dwell_factor_medium' => 1.0,
            'dwell_factor_long' => 1.1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('impression_coefficients');
    }
};
