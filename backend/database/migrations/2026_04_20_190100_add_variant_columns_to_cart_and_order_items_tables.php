<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('cart_items', function (Blueprint $table) {
            $table->foreignId('product_variant_id')->nullable()->after('product_id')->constrained('product_variants')->nullOnDelete();
            $table->string('variant_label', 64)->nullable()->after('product_slug');
            $table->dropUnique('cart_items_cart_id_product_id_unique');
            $table->index(['cart_id', 'product_id', 'product_variant_id'], 'cart_items_cart_product_variant_idx');
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->foreignId('product_variant_id')->nullable()->after('product_id')->constrained('product_variants')->nullOnDelete();
            $table->string('variant_label', 64)->nullable()->after('product_slug');
            $table->index(['order_id', 'product_variant_id'], 'order_items_order_variant_idx');
        });
    }

    public function down(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            $table->dropIndex('order_items_order_variant_idx');
            $table->dropConstrainedForeignId('product_variant_id');
            $table->dropColumn('variant_label');
        });

        Schema::table('cart_items', function (Blueprint $table) {
            $table->dropIndex('cart_items_cart_product_variant_idx');
            $table->dropConstrainedForeignId('product_variant_id');
            $table->dropColumn('variant_label');
            $table->unique(['cart_id', 'product_id']);
        });
    }
};
