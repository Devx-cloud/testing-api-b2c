<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Satu thread percakapan milik seorang wa_customer. Thread lama diarsipkan
 * (status = 'archived') saat pelanggan kembali setelah jeda lama; fakta pelanggan
 * ikut lintas-thread karena tersimpan per customer_id, bukan per conversation.
 */
class WaConversation extends Model
{
    protected $table = 'wa_conversation';

    protected $guarded = ['id'];

    protected $casts = [
        'thread_no' => 'integer',
        'last_summarized_message_id' => 'integer',
        'summary_updated_at' => 'datetime',
        'last_active_at' => 'datetime',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(WaCustomer::class, 'customer_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(WaMessage::class, 'conversation_id');
    }
}
