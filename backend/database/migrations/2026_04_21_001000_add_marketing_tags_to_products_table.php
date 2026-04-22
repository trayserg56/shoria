<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->boolean('is_hit')->default(false)->after('is_featured');
            $table->boolean('is_new')->default(false)->after('is_hit');
            $table->boolean('is_customer_choice')->default(false)->after('is_new');
        });

        DB::table('products')
            ->where('slug', 'vault-signature')
            ->update(['is_hit' => true, 'is_new' => true, 'is_customer_choice' => false]);

        DB::table('products')
            ->where('slug', 'neon-track-x')
            ->update(['is_hit' => true, 'is_new' => false, 'is_customer_choice' => true]);

        DB::table('products')
            ->where('slug', 'cloud-step-v2')
            ->update(['is_hit' => false, 'is_new' => true, 'is_customer_choice' => true]);
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['is_hit', 'is_new', 'is_customer_choice']);
        });
    }
};
