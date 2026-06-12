<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('warehouses', function (Blueprint $table) {
            $table->foreignId('price_type_id')
                ->nullable()
                ->after('external_id')
                ->constrained('price_types')
                ->nullOnDelete()
                ->comment('Тип цены этого склада. Null = использовать розничный тип по умолчанию');
        });
    }

    public function down(): void
    {
        Schema::table('warehouses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('price_type_id');
        });
    }
};
