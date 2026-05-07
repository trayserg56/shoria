<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('gift_certificates', function (Blueprint $table) {
            $table->foreignId('owner_user_id')->nullable()->after('id')->constrained('users')->nullOnDelete();
            $table->foreignId('purchased_order_id')->nullable()->after('owner_user_id')->constrained('orders')->nullOnDelete();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->string('checkout_kind', 32)->default('cart')->after('session_id');
            $table->timestamp('gift_certificate_issued_at')->nullable()->after('marketing_review_reminder_sent_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['checkout_kind', 'gift_certificate_issued_at']);
        });

        Schema::table('gift_certificates', function (Blueprint $table) {
            $table->dropConstrainedForeignId('purchased_order_id');
            $table->dropConstrainedForeignId('owner_user_id');
        });
    }
};
