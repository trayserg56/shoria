<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE payment_providers ALTER COLUMN config TYPE text USING config::text');
            DB::statement('ALTER TABLE delivery_providers ALTER COLUMN config TYPE text USING config::text');
        }
    }

    public function down(): void
    {
        $driver = DB::getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE payment_providers ALTER COLUMN config TYPE json USING config::json');
            DB::statement('ALTER TABLE delivery_providers ALTER COLUMN config TYPE json USING config::json');
        }
    }
};
