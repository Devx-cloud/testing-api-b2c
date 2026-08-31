<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * Identitas kanonik seorang pengirim WhatsApp untuk keperluan memori AI Agent.
 * Ditautkan lunak ke tabel `user` lewat kolom user_id (tanpa foreign key), sama
 * seperti model lain di aplikasi terpisah ini yang menunjuk tabel milik sistem
 * Tokodaring/Balimall. Tabel `wa_*` dibuat manual lewat database/sql/wa_memory_schema.sql.
 */
class WaCustomer extends Model
{
    protected $table = 'wa_customer';

    protected $guarded = ['id'];

    protected $casts = [
        'jids' => 'array',
        'last_matched_at' => 'datetime',
    ];

    public function conversations(): HasMany
    {
        return $this->hasMany(WaConversation::class, 'customer_id');
    }

    public function facts(): HasMany
    {
        return $this->hasMany(WaCustomerFact::class, 'customer_id');
    }

    public function messages(): HasMany
    {
        return $this->hasMany(WaMessage::class, 'customer_id');
    }

    public function sessionState(): HasOne
    {
        return $this->hasOne(WaSessionState::class, 'customer_id');
    }
}
