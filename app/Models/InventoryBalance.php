<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InventoryBalance extends Model
{
    protected $fillable = ['product_id', 'warehouse_quantity', 'pos_quantity'];

    protected function casts(): array
    {
        return ['warehouse_quantity' => 'integer', 'pos_quantity' => 'integer'];
    }

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
