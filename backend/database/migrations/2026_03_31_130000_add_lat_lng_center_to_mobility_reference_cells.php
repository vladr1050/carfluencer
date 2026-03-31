<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mobility_reference_cells', function (Blueprint $table) {
            $table->decimal('lat_center', 10, 7)->nullable()->after('cell_id');
            $table->decimal('lng_center', 10, 7)->nullable()->after('lat_center');
        });
    }

    public function down(): void
    {
        Schema::table('mobility_reference_cells', function (Blueprint $table) {
            $table->dropColumn(['lat_center', 'lng_center']);
        });
    }
};
