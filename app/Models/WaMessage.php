<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Satu baris transkrip percakapan. `role` = user | assistant | tool | system_note.
 * `system_note` dipakai untuk menyisipkan kejadian penting non-percakapan
 * (mis. milestone checkout) ke dalam memori tanpa dikirim sebagai pesan chat.
 */
class WaMessage extends Model
{
    protected $table = 'wa_message';

    protected $guarded = ['id'];

    protected $casts = [
        'tool_calls' => 'array',
        'token_estimate' => 'integer',
        'summarized' => 'boolean',
    ];

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(WaConversation::class, 'conversation_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(WaCustomer::class, 'customer_id');
    }
}
