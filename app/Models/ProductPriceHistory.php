<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ProductPriceHistory extends Model
{
    protected $fillable = [
        'product_id',
        'old_price',
        'new_price',
        'price_change',
        'percentage_change',
        'reason',
        'changed_by',
        'effective_date',
    ];

    protected $casts = [
        'old_price' => 'float',
        'new_price' => 'float',
        'price_change' => 'float',
        'percentage_change' => 'float',
        'effective_date' => 'datetime',
    ];

    public function product()
    {
        return $this->belongsTo(Product::class);
    }
}
