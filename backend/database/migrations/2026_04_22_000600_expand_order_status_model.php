<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table): void {
            $table->string('order_status', 32)->default('placed')->after('status');
            $table->string('payment_status', 32)->default('unpaid')->after('order_status');
            $table->string('fulfillment_status', 32)->default('pending')->after('payment_status');
            $table->string('refund_status', 32)->default('none')->after('fulfillment_status');
            $table->timestamp('confirmed_at')->nullable()->after('placed_at');
            $table->timestamp('cancelled_at')->nullable()->after('confirmed_at');
            $table->timestamp('completed_at')->nullable()->after('cancelled_at');
        });

        DB::table('orders')
            ->select(['id', 'status'])
            ->orderBy('id')
            ->chunkById(100, function ($orders): void {
                foreach ($orders as $order) {
                    $orderStatus = match ($order->status) {
                        'cancelled' => 'cancelled',
                        'completed' => 'completed',
                        default => 'placed',
                    };
                    $paymentStatus = match ($order->status) {
                        'paid', 'completed' => 'paid',
                        'cancelled' => 'cancelled',
                        default => 'unpaid',
                    };
                    $fulfillmentStatus = match ($order->status) {
                        'processing' => 'processing',
                        'completed' => 'delivered',
                        default => 'pending',
                    };

                    DB::table('orders')
                        ->where('id', $order->id)
                        ->update([
                            'order_status' => $orderStatus,
                            'payment_status' => $paymentStatus,
                            'fulfillment_status' => $fulfillmentStatus,
                            'refund_status' => 'none',
                        ]);
                }
            });

        Schema::create('order_status_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('field', 32);
            $table->string('old_value', 64)->nullable();
            $table->string('new_value', 64);
            $table->string('source', 32)->default('system');
            $table->text('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('order_status_logs');

        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn([
                'order_status',
                'payment_status',
                'fulfillment_status',
                'refund_status',
                'confirmed_at',
                'cancelled_at',
                'completed_at',
            ]);
        });
    }
};
