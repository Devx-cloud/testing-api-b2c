<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Vektor embedding satu pesan atau satu ringkasan, disimpan sebagai JSON.
 * MySQL 8 / Laravel 8 tidak punya tipe vektor, jadi pencarian kemiripan (cosine)
 * dihitung di PHP hanya atas baris milik satu customer (jumlahnya kecil).
 * Append-only: tidak memakai updated_at (pola sama seperti OrderProduct).
 */
class WaMessageEmbedding extends Model
{
    protected $table = 'wa_message_embedding';

    protected $guarded = ['id'];

    public $timestamps = false;

    protected $casts = [
        'vector' => 'array',
        'dim' => 'integer',
        'norm' => 'float',
        'created_at' => 'datetime',
    ];

    public function message(): BelongsTo
    {
        return $this->belongsTo(WaMessage::class, 'message_id');
    }
}
