<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouses', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('code')->unique();
            $table->string('city')->nullable();
            $table->string('region')->nullable();
            $table->string('address')->nullable();
            $table->unsignedSmallInteger('priority')->default(100);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->string('external_id')->nullable()->unique()->comment('ID склада в 1С');
            $table->timestamps();
        });

        Schema::create('price_types', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique()->comment('retail, wholesale, promo, vip');
            $table->string('name');
            $table->boolean('is_default')->default(false);
            $table->string('external_id')->nullable()->unique()->comment('ID типа цены в 1С');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('price_types');
        Schema::dropIfExists('warehouses');
    }
};
