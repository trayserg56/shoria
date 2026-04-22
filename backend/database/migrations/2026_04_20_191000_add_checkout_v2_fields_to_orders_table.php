<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('delivery_method', 32)->default('courier')->after('status');
            $table->string('payment_method', 32)->default('card')->after('delivery_method');
            $table->string('promo_code', 64)->nullable()->after('comment');
            $table->decimal('discount_total', 10, 2)->default(0)->after('subtotal');
            $table->decimal('delivery_total', 10, 2)->default(0)->after('discount_total');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn([
                'delivery_method',
                'payment_method',
                'promo_code',
                'discount_total',
                'delivery_total',
            ]);
        });
    }
};
