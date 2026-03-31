<?php

use App\Models\CampaignImpressionStat;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('campaign_impression_stats', function (Blueprint $table) {
            $table->string('status', 16)
                ->default(CampaignImpressionStat::STATUS_DONE)
                ->after('input_fingerprint');
            $table->text('error_message')->nullable()->after('status');
        });
    }

    public function down(): void
    {
        Schema::table('campaign_impression_stats', function (Blueprint $table) {
            $table->dropColumn(['status', 'error_message']);
        });
    }
};
