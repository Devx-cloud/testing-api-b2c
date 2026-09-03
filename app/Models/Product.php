<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    protected $table = 'product';

    public function productfiles(): HasMany
    {
        return $this->hasMany(ProductFile::class);
    }

    public function store(): BelongsTo
    {
        return $this->belongsTo(Store::class, 'store_id');
    }

    public function categoryRelation(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'category');
    }

    /**
     * Ubah string ID kategori (mis. "1,5") menjadi daftar nama kategori.
     * Jika service sudah menyuntikkan 'category_models', pakai itu agar hemat query.
     */
    public function getCategoryDataAttribute()
    {
        if (isset($this->category_models)) {
            return $this->category_models->map(function ($category) {
                return ['name' => $category->name];
            })->toArray();
        }

        if (empty($this->category)) {
            return [];
        }

        $ids = explode(',', $this->category);

        return ProductCategory::whereIn('id', $ids)
            ->get(['name', 'slug'])
            ->map(function ($category) {
                return ['name' => $category->name];
            })
            ->toArray();
    }
}
