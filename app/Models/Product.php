<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Product extends Model
{
    protected $fillable = ['design_name', 'category', 'variant', 'size', 'barcode', 'standard_price', 'photo_path', 'active', 'promotion_id'];

    protected function casts(): array
    {
        return ['standard_price' => 'decimal:2', 'active' => 'boolean'];
    }

    public function scopeOrderedForDisplay(\Illuminate\Database\Eloquent\Builder $query): \Illuminate\Database\Eloquent\Builder
    {
        return $query->orderBy('design_name')
            ->orderBy('variant')
            ->orderByRaw("CASE size WHEN 'XS' THEN 0 WHEN 'S' THEN 1 WHEN 'M' THEN 2 WHEN 'L' THEN 3 WHEN 'XL' THEN 4 WHEN '2XL' THEN 5 WHEN '3XL' THEN 6 ELSE 99 END");
    }

    public function balance(): HasOne
    {
        return $this->hasOne(InventoryBalance::class);
    }

    public function promotion(): BelongsTo
    {
        return $this->belongsTo(Promotion::class);
    }
}
