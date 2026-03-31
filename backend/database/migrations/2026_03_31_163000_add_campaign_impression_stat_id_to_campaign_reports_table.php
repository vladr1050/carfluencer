<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaign_reports', function (Blueprint $table) {
            $table->foreignId('campaign_impression_stat_id')
                ->nullable()
                ->after('date_to')
                ->constrained('campaign_impression_stats')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('campaign_reports', function (Blueprint $table) {
            $table->dropConstrainedForeignId('campaign_impression_stat_id');
        });
    }
};
