<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('promo_codes', function (Blueprint $table): void {
            $table->string('applies_to', 16)->default('order')->after('discount_value');
            $table->string('items_match_mode', 16)->default('any')->after('applies_to');
            $table->json('included_product_ids')->nullable()->after('items_match_mode');
            $table->json('included_category_ids')->nullable()->after('included_product_ids');
            $table->json('included_brand_ids')->nullable()->after('included_category_ids');
            $table->unsignedInteger('min_items_count')->nullable()->after('min_subtotal');
            $table->decimal('max_discount_amount', 10, 2)->nullable()->after('min_items_count');
            $table->boolean('free_delivery')->default(false)->after('max_discount_amount');
            $table->boolean('first_order_only')->default(false)->after('free_delivery');
        });
    }

    public function down(): void
    {
        Schema::table('promo_codes', function (Blueprint $table): void {
            $table->dropColumn([
                'applies_to',
                'items_match_mode',
                'included_product_ids',
                'included_category_ids',
                'included_brand_ids',
                'min_items_count',
                'max_discount_amount',
                'free_delivery',
                'first_order_only',
            ]);
        });
    }
};

