<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StockMovement extends Model
{
    protected $fillable = [
        'warehouse_id',
        'product_id',
        'product_variant_id',
        'type',
        'qty_change',
        'qty_before',
        'qty_after',
        'order_id',
        'external_ref',
        'comment',
        'created_by',
    ];

    protected $casts = [
        'qty_change' => 'integer',
        'qty_before' => 'integer',
        'qty_after' => 'integer',
    ];

    const TYPE_RECEIPT = 'receipt';
    const TYPE_SALE = 'sale';
    const TYPE_RETURN = 'return';
    const TYPE_ADJUSTMENT = 'adjustment';
    const TYPE_TRANSFER_IN = 'transfer_in';
    const TYPE_TRANSFER_OUT = 'transfer_out';
    const TYPE_1C_SYNC = '1c_sync';
    const TYPE_RESERVE = 'reserve';
    const TYPE_RELEASE = 'release';

    public function warehouse(): BelongsTo
    {
        return $this->belongsTo(Warehouse::class);
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
