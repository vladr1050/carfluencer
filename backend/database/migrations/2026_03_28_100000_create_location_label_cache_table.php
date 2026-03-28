<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('location_label_cache', function (Blueprint $table) {
            $table->id();
            /** Stable bucket identity (4 dp) aligned with report top_locations rounding */
            $table->decimal('lat_bucket', 10, 6);
            $table->decimal('lng_bucket', 10, 6);
            $table->string('provider', 32);
            $table->string('label', 192)->nullable();
            $table->json('raw_response_json')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->unique(['lat_bucket', 'lng_bucket', 'provider'], 'location_label_cache_cell_uidx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('location_label_cache');
    }
};
