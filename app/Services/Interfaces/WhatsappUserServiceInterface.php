<?php

namespace App\Services\Interfaces;

use App\Models\TokodaringUser;

/**
 * Kontrak untuk service yang mencocokkan nomor telepon pengirim WhatsApp
 * dengan data akun marketplace Tokodaring yang sudah ada.
 */
interface WhatsappUserServiceInterface
{
    /**
     * Cari user Tokodaring berdasarkan nomor telepon (berbagai format).
     *
     * @param string $phoneNumber Nomor telepon mentah, misal dari JID WhatsApp (62812xxxxxxx)
     * @return TokodaringUser|null
     */
    public function findByPhoneNumber(string $phoneNumber): ?TokodaringUser;
}
