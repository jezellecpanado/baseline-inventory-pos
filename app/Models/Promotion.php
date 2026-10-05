<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Promotion extends Model
{
    protected $fillable = ['name', 'required_quantity', 'bundle_price', 'active'];

    protected function casts(): array
    {
        return ['required_quantity' => 'integer', 'bundle_price' => 'decimal:2', 'active' => 'boolean'];
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }
}
