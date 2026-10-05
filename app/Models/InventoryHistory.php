<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryHistory extends Model
{
    public $timestamps = false;

    protected $fillable = ['reference', 'movement_type', 'product_id', 'product_name', 'category', 'variant', 'size', 'barcode', 'location', 'quantity_change', 'inventory_transaction_id', 'user_id', 'user_name', 'reason', 'notes', 'created_at'];

    protected function casts(): array
    {
        return ['quantity_change' => 'integer', 'created_at' => 'datetime'];
    }

    public function transaction(): BelongsTo
    {
        return $this->belongsTo(InventoryTransaction::class, 'inventory_transaction_id');
    }
}
