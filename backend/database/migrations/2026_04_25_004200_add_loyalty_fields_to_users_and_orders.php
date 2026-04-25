<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->unsignedInteger('loyalty_points_balance')->default(0)->after('phone');
            $table->decimal('loyalty_total_spent', 12, 2)->default(0)->after('loyalty_points_balance');
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->unsignedInteger('loyalty_points_spent')->default(0)->after('promo_code');
            $table->decimal('loyalty_discount_total', 10, 2)->default(0)->after('discount_total');
            $table->unsignedInteger('loyalty_points_earned')->default(0)->after('loyalty_points_spent');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn([
                'loyalty_points_spent',
                'loyalty_discount_total',
                'loyalty_points_earned',
            ]);
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn([
                'loyalty_points_balance',
                'loyalty_total_spent',
            ]);
        });
    }
};
