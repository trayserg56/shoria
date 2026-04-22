<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('delivery_methods')->updateOrInsert(
            ['code' => 'courier'],
            [
                'name' => 'Курьер',
                'fee' => 490,
                'is_active' => true,
                'sort_order' => 1,
                'updated_at' => $now,
                'created_at' => $now,
            ],
        );

        DB::table('delivery_methods')->updateOrInsert(
            ['code' => 'pickup'],
            [
                'name' => 'Самовывоз',
                'fee' => 0,
                'is_active' => true,
                'sort_order' => 2,
                'updated_at' => $now,
                'created_at' => $now,
            ],
        );

        DB::table('promo_codes')->updateOrInsert(
            ['code' => 'WELCOME10'],
            [
                'name' => 'Приветственная скидка 10%',
                'discount_type' => 'fixed_percent',
                'discount_value' => 10,
                'min_subtotal' => null,
                'usage_limit' => null,
                'used_count' => 0,
                'starts_at' => null,
                'ends_at' => null,
                'is_active' => true,
                'updated_at' => $now,
                'created_at' => $now,
            ],
        );
    }

    public function down(): void
    {
        DB::table('delivery_methods')
            ->whereIn('code', ['courier', 'pickup'])
            ->delete();

        DB::table('promo_codes')
            ->where('code', 'WELCOME10')
            ->delete();
    }
};
