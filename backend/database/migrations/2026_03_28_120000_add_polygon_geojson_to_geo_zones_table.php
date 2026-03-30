<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('geo_zones', function (Blueprint $table) {
            $table->json('polygon_geojson')->nullable()->after('max_lng');
        });
    }

    public function down(): void
    {
        Schema::table('geo_zones', function (Blueprint $table) {
            $table->dropColumn('polygon_geojson');
        });
    }
};
