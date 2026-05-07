<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gift_certificates', function (Blueprint $table) {
            $table->id();
            $table->string('code', 64)->unique();
            $table->decimal('initial_amount', 10, 2);
            $table->decimal('balance_remaining', 10, 2);
            $table->char('currency', 3)->default('RUB');
            $table->string('status', 32)->default('active')->index();
            $table->timestamp('expires_at')->nullable()->index();
            $table->string('recipient_email')->nullable()->index();
            $table->text('admin_note')->nullable();
            $table->timestamps();
        });

        Schema::create('gift_certificate_redemptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('gift_certificate_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->decimal('amount', 10, 2);
            $table->timestamps();
        });

        Schema::table('carts', function (Blueprint $table) {
            $table->timestamp('abandoned_cart_reminded_at')->nullable()->after('updated_at');
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->timestamp('marketing_review_reminder_sent_at')->nullable()->after('completed_at');
            $table->foreignId('gift_certificate_id')->nullable()->after('promo_code')->constrained('gift_certificates')->nullOnDelete();
            $table->decimal('gift_certificate_discount_total', 10, 2)->default(0)->after('gift_certificate_id');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('gift_certificate_id');
            $table->dropColumn(['gift_certificate_discount_total', 'marketing_review_reminder_sent_at']);
        });

        Schema::table('carts', function (Blueprint $table) {
            $table->dropColumn('abandoned_cart_reminded_at');
        });

        Schema::dropIfExists('gift_certificate_redemptions');
        Schema::dropIfExists('gift_certificates');
    }
};
