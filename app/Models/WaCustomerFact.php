<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Fakta terstruktur jangka panjang per pelanggan (nama, bahasa, kurir favorit,
 * ringkasan order terakhir, dst). Di-upsert deterministik dari hasil sync user
 * dan dari langkah-langkah checkout, lalu disuntikkan ke konteks LLM tiap giliran.
 * Unik per (customer_id, fact_key).
 */
class WaCustomerFact extends Model
{
    protected $table = 'wa_customer_fact';

    protected $guarded = ['id'];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(WaCustomer::class, 'customer_id');
    }
}
