<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Salinan tahan-restart dari state runtime AI Agent (sesi checkout yang sedang
 * berjalan, cache hasil sync user, jendela rate-limit). Sumber kebenaran saat
 * proses hidup adalah Redis di sisi agent; baris ini adalah fallback durable
 * yang ditulis-tembus tiap ada perubahan. Satu baris per customer.
 */
class WaSessionState extends Model
{
    protected $table = 'wa_session_state';

    protected $guarded = ['id'];

    public $timestamps = false;

    protected $casts = [
        'checkout_state' => 'array',
        'matched_user_cache' => 'array',
        'rate_limit' => 'array',
        'extra' => 'array',
        'matched_user_cached_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(WaCustomer::class, 'customer_id');
    }
}
