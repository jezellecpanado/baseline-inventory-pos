<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class InventoryTransaction extends Model
{
    protected $fillable = ['reference', 'submission_key', 'type', 'status', 'location', 'customer_name', 'payment_method', 'subtotal', 'discount', 'total', 'promotion_id', 'reason', 'notes', 'metadata', 'created_by', 'created_by_name', 'completed_at', 'voided_by', 'voided_by_name', 'voided_at', 'void_reason'];

    protected function casts(): array
    {
        return ['subtotal' => 'decimal:2', 'discount' => 'decimal:2', 'total' => 'decimal:2', 'metadata' => 'array', 'completed_at' => 'datetime', 'voided_at' => 'datetime'];
    }

    public function lines(): HasMany
    {
        return $this->hasMany(TransactionLine::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
