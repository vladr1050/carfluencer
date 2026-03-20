<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_owner_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('company_name');
            $table->string('phone')->nullable();
            $table->string('registration_number')->nullable();
            $table->text('address')->nullable();
            $table->timestamps();
        });

        Schema::create('advertiser_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('company_name');
            $table->string('phone')->nullable();
            $table->string('registration_number')->nullable();
            $table->text('address')->nullable();
            $table->decimal('discount_percent', 5, 2)->nullable();
            $table->decimal('agency_commission_percent', 5, 2)->nullable();
            $table->timestamps();
        });

        Schema::create('ad_placement_policies', function (Blueprint $table) {
            $table->id();
            $table->string('size_class', 2);
            $table->decimal('base_price', 12, 2);
            $table->string('currency', 3)->default('EUR');
            $table->boolean('active')->default(true);
            $table->text('description')->nullable();
            $table->timestamps();
            $table->unique('size_class');
        });

        Schema::create('vehicles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('media_owner_id')->constrained('users')->cascadeOnDelete();
            $table->string('brand');
            $table->string('model');
            $table->unsignedSmallInteger('year')->nullable();
            $table->string('color')->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->string('image_path')->nullable();
            $table->string('imei');
            $table->string('status', 32)->default('active');
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->unique('imei');
        });

        Schema::create('campaigns', function (Blueprint $table) {
            $table->id();
            $table->foreignId('advertiser_id')->constrained('users')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('status', 32)->default('draft');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->boolean('created_by_admin')->default(false);
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('discount_percent', 5, 2)->nullable();
            $table->decimal('platform_commission_percent', 5, 2)->nullable();
            $table->decimal('agency_commission_percent', 5, 2)->nullable();
            $table->decimal('total_price', 14, 2)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('campaign_vehicles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->string('placement_size_class', 2);
            $table->decimal('agreed_price', 12, 2)->nullable();
            $table->string('status', 32)->default('pending');
            $table->timestamps();
            $table->unique(['campaign_id', 'vehicle_id']);
        });

        Schema::create('campaign_proofs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('vehicle_id')->constrained()->cascadeOnDelete();
            $table->foreignId('uploaded_by_user_id')->constrained('users')->cascadeOnDelete();
            $table->string('file_path');
            $table->string('status', 32)->default('uploaded');
            $table->text('comment')->nullable();
            $table->timestamps();
        });

        Schema::create('platform_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });

        Schema::create('content_blocks', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('title');
            $table->longText('body')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('content_blocks');
        Schema::dropIfExists('platform_settings');
        Schema::dropIfExists('campaign_proofs');
        Schema::dropIfExists('campaign_vehicles');
        Schema::dropIfExists('campaigns');
        Schema::dropIfExists('vehicles');
        Schema::dropIfExists('ad_placement_policies');
        Schema::dropIfExists('advertiser_profiles');
        Schema::dropIfExists('media_owner_profiles');
    }
};
