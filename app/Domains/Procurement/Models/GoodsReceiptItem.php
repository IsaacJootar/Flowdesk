<?php

namespace App\Domains\Procurement\Models;

use App\Traits\CompanyScoped;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoodsReceiptItem extends Model
{
    use CompanyScoped;
    use HasFactory;

    protected $fillable = [
        'company_id',
        'goods_receipt_id',
        'purchase_order_item_id',
        'received_quantity',
        'received_unit_cost',
        'received_total',
        'notes',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'received_quantity' => 'decimal:2',
            'received_unit_cost' => 'integer',
            'received_total' => 'integer',
            'metadata' => 'array',
        ];
    }

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(GoodsReceipt::class, 'goods_receipt_id');
    }

    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrderItem::class, 'purchase_order_item_id');
    }
}
