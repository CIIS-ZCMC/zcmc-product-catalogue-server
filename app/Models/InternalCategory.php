<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class InternalCategory extends Model
{
    protected $fillable = [
        'name',
        'code',
    ];

    /**
     * Products categorized under this internal category.
     */
    public function products()
    {
        return $this->hasMany(Product::class, 'internal_category_id');
    }
}
