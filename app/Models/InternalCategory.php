<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property string $name
 * @property string $code
 * @property Collection<int, Product> $products
 */
class InternalCategory extends Model
{
    protected $fillable = [
        'name',
        'code',
    ];

    /**
     * Products categorized under this internal category.
     *
     * @return HasMany<Product, $this>
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'internal_category_id');
    }
}
