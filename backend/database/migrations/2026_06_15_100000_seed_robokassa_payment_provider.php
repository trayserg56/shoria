<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $exists = DB::table('payment_providers')->where('driver', 'robokassa')->exists();
        if ($exists) {
            return;
        }

        DB::table('payment_providers')->insert([
            'name'       => 'Робокасса',
            'code'       => 'robokassa',
            'driver'     => 'robokassa',
            'mode'       => 'sandbox',
            'config'     => json_encode([]),
            'is_active'  => true,
            'is_default' => false,
            'sort_order' => 10,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        DB::table('payment_providers')->where('driver', 'robokassa')->delete();
    }
};
