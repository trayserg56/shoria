<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_variants', function (Blueprint $table): void {
            $table->dropUnique('product_variants_product_id_size_label_unique');
            $table->index(['product_id', 'size_label'], 'product_variants_product_size_idx');
        });
    }

    public function down(): void
    {
        Schema::table('product_variants', function (Blueprint $table): void {
            $table->dropIndex('product_variants_product_size_idx');
            $table->unique(['product_id', 'size_label']);
        });
    }
};

