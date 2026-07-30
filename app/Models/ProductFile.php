<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProductFile extends Model
{
    protected $table = 'product_file';

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
