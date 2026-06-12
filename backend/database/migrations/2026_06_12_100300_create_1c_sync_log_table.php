<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('onec_exchange_sessions', function (Blueprint $table) {
            $table->id();
            $table->string('direction')->comment('import, export');
            $table->string('type')->comment('products, prices, stock, orders');
            $table->string('status')->default('started')->comment('started, success, error');
            $table->unsignedInteger('records_processed')->default(0);
            $table->unsignedInteger('records_created')->default(0);
            $table->unsignedInteger('records_updated')->default(0);
            $table->unsignedInteger('records_skipped')->default(0);
            $table->text('error_message')->nullable();
            $table->json('meta')->nullable()->comment('Доп. данные: filename, checksum и т.п.');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->index(['direction', 'type', 'status']);
            $table->index('created_at');
        });

        Schema::create('onec_webhook_log', function (Blueprint $table) {
            $table->id();
            $table->string('event')->comment('orders.created, orders.status_changed, orders.payment_confirmed');
            $table->foreignId('order_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status')->default('pending')->comment('pending, delivered, failed');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->text('payload')->nullable();
            $table->text('response')->nullable();
            $table->timestamp('next_retry_at')->nullable();
            $table->timestamps();

            $table->index(['status', 'next_retry_at']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->string('onec_uuid')->nullable()->after('order_number')->comment('UUID заказа в 1С');
            $table->string('carrier_code')->nullable()->after('delivery_method')->comment('Код ТК');
            $table->string('tracking_number')->nullable()->after('carrier_code');
            $table->string('tracking_url')->nullable()->after('tracking_number');
            $table->timestamp('shipped_at')->nullable()->after('completed_at');
            $table->timestamp('delivered_at')->nullable()->after('shipped_at');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['onec_uuid', 'carrier_code', 'tracking_number', 'tracking_url', 'shipped_at', 'delivered_at']);
        });
        Schema::dropIfExists('onec_webhook_log');
        Schema::dropIfExists('onec_exchange_sessions');
    }
};
