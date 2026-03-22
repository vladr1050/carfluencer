<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->string('color_key', 40)->nullable()->after('year');
        });

        DB::table('vehicles')->whereIn('status', ['inactive', 'archived'])->update(['status' => 'not_available']);

        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropColumn('color');
        });
    }

    public function down(): void
    {
        Schema::table('vehicles', function (Blueprint $table) {
            $table->string('color')->nullable()->after('year');
        });

        Schema::table('vehicles', function (Blueprint $table) {
            $table->dropColumn('color_key');
        });
    }
};
