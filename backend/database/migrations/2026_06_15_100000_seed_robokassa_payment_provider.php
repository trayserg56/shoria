<?php

use App\Models\PaymentProvider;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        if (PaymentProvider::where('driver', 'robokassa')->exists()) {
            return;
        }

        PaymentProvider::create([
            'name'       => 'Робокасса',
            'code'       => 'robokassa',
            'driver'     => 'robokassa',
            'mode'       => 'sandbox',
            'config'     => [],
            'is_active'  => true,
            'is_default' => false,
            'sort_order' => 10,
        ]);
    }

    public function down(): void
    {
        PaymentProvider::where('driver', 'robokassa')->delete();
    }
};
