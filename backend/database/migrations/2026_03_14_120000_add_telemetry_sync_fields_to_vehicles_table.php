<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->boolean('telemetry_pull_enabled')->default(true)->after('notes');
            $table->timestamp('telemetry_last_incremental_at')->nullable()->after('telemetry_pull_enabled');
            $table->timestamp('telemetry_last_historical_at')->nullable()->after('telemetry_last_incremental_at');
            $table->timestamp('telemetry_last_success_at')->nullable()->after('telemetry_last_historical_at');
            $table->text('telemetry_last_error')->nullable()->after('telemetry_last_success_at');
        });
    }

    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropColumn([
                'telemetry_pull_enabled',
                'telemetry_last_incremental_at',
                'telemetry_last_historical_at',
                'telemetry_last_success_at',
                'telemetry_last_error',
            ]);
        });
    }
};
