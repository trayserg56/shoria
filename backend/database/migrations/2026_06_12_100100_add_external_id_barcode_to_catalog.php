<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->string('external_id')->nullable()->unique()->after('sku')->comment('ID товара в 1С');
            $table->string('barcode')->nullable()->after('external_id')->comment('Штрихкод / EAN');
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->string('external_id')->nullable()->unique()->after('sku')->comment('ID варианта в 1С');
            $table->string('barcode')->nullable()->after('external_id')->comment('Штрихкод варианта');
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->string('external_id')->nullable()->unique()->after('slug')->comment('ID категории в 1С');
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('external_id');
        });
        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropColumn(['external_id', 'barcode']);
        });
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['external_id', 'barcode']);
        });
    }
};
