<?php

namespace App\Http\Controllers\Api;

use App\Models\Order;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StockLevel;
use App\Models\Warehouse;
use App\Services\Onec\OrdersExporter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Routing\Controller;

class OnecRestController extends Controller
{
    public function products(): JsonResponse
    {
        $products = Product::query()
            ->with('variants:id,product_id,external_id,sku,size_label,color_label,price,stock,is_active')
            ->whereNotNull('external_id')
            ->get(['id', 'external_id', 'sku', 'name', 'description', 'price', 'stock', 'is_active', 'category_id']);

        return response()->json([
            'data' => $products->map(fn ($p) => [
                'id' => $p->id,
                'external_id' => $p->external_id,
                'sku' => $p->sku,
                'name' => $p->name,
                'price' => (float) $p->price,
                'stock' => $p->stock,
                'is_active' => $p->is_active,
                'variants' => $p->variants->map(fn ($v) => [
                    'id' => $v->id,
                    'external_id' => $v->external_id,
                    'sku' => $v->sku,
                    'name' => $v->size_label . ($v->color_label ? " / {$v->color_label}" : ''),
                    'price' => $v->price !== null ? (float) $v->price : null,
                    'stock' => $v->stock,
                    'is_active' => $v->is_active,
                ]),
            ]),
            'total' => $products->count(),
        ]);
    }

    public function stock(): JsonResponse
    {
        $levels = StockLevel::query()
            ->with(['warehouse:id,name,code,external_id', 'product:id,external_id', 'variant:id,external_id'])
            ->where('qty', '>', 0)
            ->get();

        return response()->json([
            'data' => $levels->map(fn ($l) => [
                'warehouse_external_id' => $l->warehouse?->external_id,
                'warehouse_code' => $l->warehouse?->code,
                'product_external_id' => $l->product?->external_id,
                'variant_external_id' => $l->variant?->external_id,
                'qty' => $l->qty,
                'reserved_qty' => $l->reserved_qty,
                'available_qty' => max(0, $l->qty - $l->reserved_qty),
            ]),
            'total' => $levels->count(),
        ]);
    }

    public function orders(Request $request, OrdersExporter $exporter): Response
    {
        $since = $request->string('since')->toString() ?: now()->subDay()->toDateString();
        $format = $request->string('format')->toString();

        if ($format === 'xml') {
            return response($exporter->export($since), 200, [
                'Content-Type' => 'application/xml; charset=utf-8',
            ]);
        }

        $orders = Order::query()
            ->with('items:id,order_id,product_id,product_variant_id,qty,price')
            ->where('created_at', '>=', $since)
            ->latest()
            ->get();

        return response(json_encode([
            'data' => $orders->map(fn ($o) => [
                'id' => $o->id,
                'order_number' => $o->order_number,
                'onec_uuid' => $o->onec_uuid,
                'order_status' => $o->order_status,
                'payment_status' => $o->payment_status,
                'total' => (float) $o->total,
                'customer_name' => $o->customer_name,
                'customer_phone' => $o->customer_phone,
                'created_at' => $o->created_at?->toIso8601String(),
                'items' => $o->items->map(fn ($i) => [
                    'product_id' => $i->product_id,
                    'variant_id' => $i->product_variant_id,
                    'qty' => $i->qty,
                    'price' => (float) $i->price,
                ]),
            ]),
            'total' => $orders->count(),
        ], JSON_UNESCAPED_UNICODE), 200, ['Content-Type' => 'application/json']);
    }

    public function updateOrder(Request $request, string $orderNumber): JsonResponse
    {
        $order = Order::where('order_number', $orderNumber)->firstOrFail();

        $validated = $request->validate([
            'onec_uuid' => 'nullable|string|max:255',
            'carrier_code' => 'nullable|string|max:64',
            'tracking_number' => 'nullable|string|max:255',
            'tracking_url' => 'nullable|url|max:2048',
            'shipped_at' => 'nullable|date',
            'delivered_at' => 'nullable|date',
        ]);

        $order->fill(array_filter($validated, fn ($v) => $v !== null))->save();

        return response()->json(['ok' => true, 'order_number' => $order->order_number]);
    }
}
