<?php

namespace App\Services;

use App\Models\TokodaringUser;
use App\Services\Interfaces\WhatsappUserServiceInterface;

class WhatsappUserService implements WhatsappUserServiceInterface
{
    public function findByPhoneNumber(string $phoneNumber): ?TokodaringUser
    {
        $candidates = $this->buildPhoneCandidates($phoneNumber);

        if (empty($candidates)) {
            return null;
        }

        return TokodaringUser::whereIn('phone_number', $candidates)->first();
    }

    /**
     * Kolom phone_number di tabel 'user' tersimpan dalam beberapa format
     * berbeda (mis. "+6281234567893", "081234567893"), sedangkan nomor dari
     * JID WhatsApp selalu berupa "628xxxxxxxxx" tanpa tanda plus. Fungsi ini
     * membangun daftar kandidat format supaya pencocokan tetap akurat.
     *
     * @return string[]
     */
    private function buildPhoneCandidates(string $rawPhone): array
    {
        $digits = preg_replace('/\D/', '', $rawPhone);

        if ($digits === '') {
            return [];
        }

        if (substr($digits, 0, 2) === '62') {
            $international = $digits;
            $national = '0' . substr($digits, 2);
        } elseif (substr($digits, 0, 1) === '0') {
            $national = $digits;
            $international = '62' . substr($digits, 1);
        } else {
            // Tidak ada awalan negara/nol, asumsikan nomor lokal Indonesia
            $national = '0' . $digits;
            $international = '62' . $digits;
        }

        return array_unique([
            $international,
            '+' . $international,
            $national,
        ]);
    }
}
