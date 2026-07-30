<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\TokodaringUserResource;
use App\Services\Interfaces\WhatsappUserServiceInterface;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WhatsappUserController extends Controller
{
    protected $whatsappUserService;

    public function __construct(WhatsappUserServiceInterface $whatsappUserService)
    {
        $this->whatsappUserService = $whatsappUserService;
    }

    /**
     * Sinkronkan nomor telepon pengirim WhatsApp dengan tabel user Tokodaring.
     * Endpoint: POST /api/whatsapp/sync-user
     *
     * Ini adalah lookup read-only: jika nomor cocok dengan akun marketplace
     * yang sudah ada, profilnya dikembalikan. Jika tidak cocok, tidak ada
     * akun baru yang dibuat (kolom username/email/password di tabel user
     * wajib & unik sehingga tidak aman diisi otomatis hanya dari nomor WA).
     */
    public function sync(Request $request)
    {
        $request->validate([
            'phone_number' => 'required|string',
        ]);

        try {
            $user = $this->whatsappUserService->findByPhoneNumber($request->input('phone_number'));

            if (!$user) {
                return response()->json([
                    'matched' => false,
                    'user' => null,
                ]);
            }

            return response()->json([
                'matched' => true,
                'user' => new TokodaringUserResource($user),
            ]);
        } catch (Exception $e) {
            Log::error('Error saat sync user WhatsApp: ' . $e->getMessage());

            return response()->json([
                'message' => 'Terjadi kesalahan pada server.',
            ], 500);
        }
    }
}
