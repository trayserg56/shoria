<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->index(['is_active', 'category_id'], 'products_active_category_idx');
            $table->index(['is_active', 'price'], 'products_active_price_idx');
            $table->index(['is_active', 'is_featured', 'sort_order'], 'products_active_featured_sort_idx');
        });

        Schema::table('categories', function (Blueprint $table): void {
            $table->index(['parent_id', 'is_active'], 'categories_parent_active_idx');
        });

        Schema::table('category_product', function (Blueprint $table): void {
            $table->index('product_id', 'category_product_product_id_idx');
        });

        Schema::table('product_variants', function (Blueprint $table): void {
            $table->index(['is_active', 'product_id'], 'variants_active_product_idx');
        });

        Schema::table('order_items', function (Blueprint $table): void {
            $table->index('product_id', 'order_items_product_id_idx');
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->index(['status', 'placed_at'], 'orders_status_placed_at_idx');
        });

        Schema::table('tracking_events', function (Blueprint $table): void {
            $table->index(['event_name', 'occurred_at'], 'tracking_events_name_occurred_idx');
            $table->index(['session_id', 'event_name'], 'tracking_events_session_event_idx');
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->dropIndex('products_active_category_idx');
            $table->dropIndex('products_active_price_idx');
            $table->dropIndex('products_active_featured_sort_idx');
        });

        Schema::table('categories', function (Blueprint $table): void {
            $table->dropIndex('categories_parent_active_idx');
        });

        Schema::table('category_product', function (Blueprint $table): void {
            $table->dropIndex('category_product_product_id_idx');
        });

        Schema::table('product_variants', function (Blueprint $table): void {
            $table->dropIndex('variants_active_product_idx');
        });

        Schema::table('order_items', function (Blueprint $table): void {
            $table->dropIndex('order_items_product_id_idx');
        });

        Schema::table('orders', function (Blueprint $table): void {
            $table->dropIndex('orders_status_placed_at_idx');
        });

        Schema::table('tracking_events', function (Blueprint $table): void {
            $table->dropIndex('tracking_events_name_occurred_idx');
            $table->dropIndex('tracking_events_session_event_idx');
        });
    }
};
