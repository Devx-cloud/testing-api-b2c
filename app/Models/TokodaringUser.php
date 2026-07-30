<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Model ini menunjuk ke tabel 'user' (bukan 'users') yang sudah ada di database
 * testing_tokodaring. Tabel ini adalah data akun marketplace asli (pembeli,
 * seller, pemerintah/B2G), BUKAN tabel auth Laravel bawaan.
 *
 * Model ini hanya dipakai untuk pencarian/lookup, tidak untuk membuat akun baru,
 * karena kolom username/email/password di tabel ini wajib & unik sehingga tidak
 * aman diisi otomatis hanya dari nomor WhatsApp.
 */
class TokodaringUser extends Model
{
    use HasFactory;

    protected $table = 'user';

    protected $guarded = ['id'];

    protected $hidden = [
        'password',
        'activation_code',
        'forgot_password_code',
        'lkpp_token',
        'lkpp_jwt_token',
        'balimall_token',
        'secure_random_code',
        'user_signature',
        'user_stamp',
        'ktp_file',
        'npwp_file',
        'surat_ijin_file',
        'dokumen_file',
    ];
}
