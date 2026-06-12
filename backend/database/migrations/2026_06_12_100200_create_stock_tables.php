<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_levels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedInteger('qty')->default(0);
            $table->unsignedInteger('reserved_qty')->default(0);
            $table->timestamps();

            $table->unique(['warehouse_id', 'product_id', 'product_variant_id'], 'stock_levels_unique');
            $table->index(['product_id', 'product_variant_id']);
        });

        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained();
            $table->foreignId('product_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type')->comment('receipt,sale,return,adjustment,transfer_in,transfer_out,1c_sync,reserve,release');
            $table->integer('qty_change');
            $table->unsignedInteger('qty_before');
            $table->unsignedInteger('qty_after');
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('external_ref')->nullable()->comment('Ссылка на документ в 1С');
            $table->string('comment')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['product_id', 'product_variant_id']);
            $table->index('type');
            $table->index('created_at');
        });

        Schema::create('product_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('price_type_id')->constrained()->cascadeOnDelete();
            $table->decimal('price', 12, 2);
            $table->string('currency', 3)->default('RUB');
            $table->timestamps();

            $table->unique(['product_id', 'product_variant_id', 'price_type_id'], 'product_prices_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_prices');
        Schema::dropIfExists('stock_movements');
        Schema::dropIfExists('stock_levels');
    }
};
