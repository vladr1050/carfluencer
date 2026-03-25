<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('telemetry_sync_events', function (Blueprint $table) {
            $table->id();
            $table->timestampTz('occurred_at')->index();
            $table->string('source', 32);
            $table->string('action', 64)->index();
            $table->string('status', 16);
            $table->string('summary', 512)->nullable();
            $table->text('error_message')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('telemetry_sync_events');
    }
};
