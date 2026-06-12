<?php

namespace App\Services\Stock;

use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockLevel;
use App\Models\StockMovement;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;

class StockService
{
    public function availableQty(int $productId, ?int $variantId = null, ?int $warehouseId = null): int
    {
        $query = StockLevel::where('product_id', $productId)
            ->where('product_variant_id', $variantId);

        if ($warehouseId) {
            $query->where('warehouse_id', $warehouseId);
        }

        return (int) $query->sum(DB::raw('qty - reserved_qty'));
    }

    public function reserve(int $productId, ?int $variantId, int $qty, Order $order): bool
    {
        return DB::transaction(function () use ($productId, $variantId, $qty, $order) {
            $warehouse = $this->resolveWarehouse();

            $level = StockLevel::where('warehouse_id', $warehouse->id)
                ->where('product_id', $productId)
                ->where('product_variant_id', $variantId)
                ->lockForUpdate()
                ->first();

            if (! $level || ($level->qty - $level->reserved_qty) < $qty) {
                return false;
            }

            $before = $level->qty;
            $level->increment('reserved_qty', $qty);

            $this->logMovement($warehouse->id, $productId, $variantId, StockMovement::TYPE_RESERVE, 0, $before, $level->qty, $order->id);

            return true;
        });
    }

    public function release(int $productId, ?int $variantId, int $qty, Order $order): void
    {
        DB::transaction(function () use ($productId, $variantId, $qty, $order) {
            $warehouse = $this->resolveWarehouse();

            $level = StockLevel::where('warehouse_id', $warehouse->id)
                ->where('product_id', $productId)
                ->where('product_variant_id', $variantId)
                ->lockForUpdate()
                ->first();

            if (! $level) {
                return;
            }

            $before = $level->qty;
            $level->decrement('reserved_qty', min($qty, $level->reserved_qty));

            $this->logMovement($warehouse->id, $productId, $variantId, StockMovement::TYPE_RELEASE, 0, $before, $level->qty, $order->id);
        });
    }

    public function deduct(int $productId, ?int $variantId, int $qty, Order $order): void
    {
        DB::transaction(function () use ($productId, $variantId, $qty, $order) {
            $warehouse = $this->resolveWarehouse();

            $level = StockLevel::where('warehouse_id', $warehouse->id)
                ->where('product_id', $productId)
                ->where('product_variant_id', $variantId)
                ->lockForUpdate()
                ->first();

            if (! $level) {
                return;
            }

            $before = $level->qty;
            $level->decrement('qty', $qty);
            $level->decrement('reserved_qty', min($qty, $level->reserved_qty));
            $after = $level->fresh()->qty;

            $this->logMovement($warehouse->id, $productId, $variantId, StockMovement::TYPE_SALE, -$qty, $before, $after, $order->id);

            // Синхронизируем денормализованный кэш
            $this->syncDenormalized($productId, $variantId);
        });
    }

    public function adjust(int $warehouseId, int $productId, ?int $variantId, int $newQty, string $comment = '', ?string $externalRef = null): void
    {
        DB::transaction(function () use ($warehouseId, $productId, $variantId, $newQty, $comment, $externalRef) {
            $level = StockLevel::firstOrCreate(
                ['warehouse_id' => $warehouseId, 'product_id' => $productId, 'product_variant_id' => $variantId],
                ['qty' => 0, 'reserved_qty' => 0]
            );
            $level->lockForUpdate();

            $before = $level->qty;
            $change = $newQty - $before;
            $level->update(['qty' => $newQty]);

            $this->logMovement($warehouseId, $productId, $variantId, StockMovement::TYPE_1C_SYNC, $change, $before, $newQty, null, $comment, $externalRef);

            $this->syncDenormalized($productId, $variantId);
        });
    }

    private function syncDenormalized(int $productId, ?int $variantId): void
    {
        if ($variantId) {
            $total = StockLevel::where('product_variant_id', $variantId)->sum(DB::raw('qty - reserved_qty'));
            ProductVariant::where('id', $variantId)->update(['stock' => max(0, $total)]);
        } else {
            $total = StockLevel::where('product_id', $productId)->whereNull('product_variant_id')->sum(DB::raw('qty - reserved_qty'));
            Product::where('id', $productId)->update(['stock' => max(0, $total)]);
        }
    }

    private function resolveWarehouse(?string $city = null): Warehouse
    {
        return Warehouse::where('is_active', true)->where('is_default', true)->first()
            ?? Warehouse::where('is_active', true)->orderBy('priority')->firstOrFail();
    }

    private function logMovement(int $warehouseId, int $productId, ?int $variantId, string $type, int $change, int $before, int $after, ?int $orderId = null, string $comment = '', ?string $externalRef = null): void
    {
        StockMovement::create([
            'warehouse_id' => $warehouseId,
            'product_id' => $productId,
            'product_variant_id' => $variantId,
            'type' => $type,
            'qty_change' => $change,
            'qty_before' => $before,
            'qty_after' => $after,
            'order_id' => $orderId,
            'external_ref' => $externalRef,
            'comment' => $comment,
            'created_by' => auth()->id(),
        ]);
    }
}
