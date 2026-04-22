<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_providers', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name', 120);
            $table->string('checkout_label', 160)->nullable();
            $table->string('driver', 32);
            $table->string('mode', 16)->default('sandbox');
            $table->boolean('is_active')->default(false);
            $table->boolean('is_default')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->text('config')->nullable();
            $table->timestamps();
        });

        Schema::create('delivery_providers', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 32)->unique();
            $table->string('name', 120);
            $table->string('driver', 32);
            $table->string('mode', 16)->default('sandbox');
            $table->boolean('is_active')->default(false);
            $table->boolean('is_default')->default(false);
            $table->boolean('supports_pickup_points')->default(false);
            $table->boolean('supports_tracking')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->text('config')->nullable();
            $table->timestamps();
        });

        Schema::table('delivery_methods', function (Blueprint $table): void {
            $table->string('provider_code', 32)->nullable()->after('code');
            $table->string('external_code', 64)->nullable()->after('provider_code');
            $table->string('method_type', 16)->default('courier')->after('name');
            $table->string('calculation_mode', 16)->default('flat')->after('fee');
        });
    }

    public function down(): void
    {
        Schema::table('delivery_methods', function (Blueprint $table): void {
            $table->dropColumn([
                'provider_code',
                'external_code',
                'method_type',
                'calculation_mode',
            ]);
        });

        Schema::dropIfExists('delivery_providers');
        Schema::dropIfExists('payment_providers');
    }
};
