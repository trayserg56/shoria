<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('loyalty_program_settings', function (Blueprint $table): void {
            $table->id();
            $table->boolean('is_enabled')->default(false);
            $table->decimal('base_accrual_percent', 5, 2)->default(5);
            $table->decimal('max_redeem_percent', 5, 2)->default(25);
            $table->decimal('point_value', 10, 2)->default(1);
            $table->decimal('min_order_total_for_redeem', 10, 2)->default(0);
            $table->json('tiers')->nullable();
            $table->text('terms_content')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('loyalty_program_settings');
    }
};
