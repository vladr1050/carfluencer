<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaign_impression_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->date('date_from');
            $table->date('date_to');
            $table->unsignedInteger('vehicles_count')->default(0);
            $table->unsignedBigInteger('driving_impressions')->default(0);
            $table->unsignedBigInteger('parking_impressions')->default(0);
            $table->unsignedBigInteger('total_gross_impressions')->default(0);
            $table->decimal('campaign_price', 14, 2)->default(0);
            $table->decimal('cpm', 14, 4)->nullable();
            $table->string('calculation_version', 32);
            $table->string('mobility_data_version', 64);
            $table->string('coefficients_version', 32);
            $table->unsignedSmallInteger('telemetry_sampling_seconds');
            $table->string('input_fingerprint', 64)->unique();
            $table->unsignedBigInteger('matched_direct_count')->default(0);
            $table->unsignedBigInteger('matched_fallback_count')->default(0);
            $table->unsignedBigInteger('unmatched_count')->default(0);
            $table->timestamps();

            $table->index(['campaign_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_impression_stats');
    }
};
