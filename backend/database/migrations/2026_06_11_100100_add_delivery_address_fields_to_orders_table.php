<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('delivery_city', 120)->nullable()->after('delivery_total');
            $table->unsignedInteger('delivery_city_code')->nullable()->after('delivery_city');
            $table->string('delivery_address', 255)->nullable()->after('delivery_city_code');
            $table->string('delivery_pickup_point_code', 60)->nullable()->after('delivery_address');
            $table->string('delivery_pickup_point_address', 255)->nullable()->after('delivery_pickup_point_code');
            $table->unsignedInteger('delivery_period_min')->nullable()->after('delivery_pickup_point_address');
            $table->unsignedInteger('delivery_period_max')->nullable()->after('delivery_period_min');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'delivery_city',
                'delivery_city_code',
                'delivery_address',
                'delivery_pickup_point_code',
                'delivery_pickup_point_address',
                'delivery_period_min',
                'delivery_period_max',
            ]);
        });
    }
};
