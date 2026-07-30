<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiKeyMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 1. Ambil kunci API yang dikirim oleh klien dari header 'Token'
        $apiKey = $request->header('Token');

        // 2. Ambil kunci API rahasia yang kita simpan di server dari file config
        $secretKey = config('services.api.key');

        // 3. Periksa apakah kunci yang dikirim klien kosong atau tidak cocok dengan kunci rahasia kita
        if (!$apiKey || $apiKey !== $secretKey) {
            // 4. Jika tidak valid, tolak permintaan dengan pesan error 401 (Unauthorized)
            return response()->json(['message' => 'Unauthorized. Invalid API Key.'], 401);
        }

        // 5. Jika kunci valid, izinkan permintaan untuk melanjutkan ke Controller
        return $next($request);
    }
}
