<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Wrapper tipis untuk API RajaOngkir (akun "pro" / Komerce), dipakai untuk
 * menghitung ongkir & mencari kota saat checkout. Lihat referensi asli di
 * tokodaring-pdc: src/Service/RajaOngkirService.php.
 */
class RajaOngkirService
{
    protected $baseUrl;
    protected $apiKey;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.rajaongkir.base_url'), '/');
        $this->apiKey = config('services.rajaongkir.api_key');
    }

    /**
     * @return array{error: bool, message: ?string, data: array}
     */
    public function getCost(string $originCityId, string $destinationCityId, int $weightGrams, string $courier): array
    {
        return $this->post('api/v1/calculate/domestic-cost', [
            'origin' => $originCityId,
            'destination' => $destinationCityId,
            'weight' => max($weightGrams, 1),
            'courier' => $courier,
            'price' => 'lowest',
            'originType' => 'city',
            'destinationType' => 'city',
        ]);
    }

    /**
     * @return array{error: bool, message: ?string, data: array}
     */
    public function getProvinces(): array
    {
        return $this->get('api/v1/destination/province', []);
    }

    /**
     * Endpoint RajaOngkir tidak menyediakan "cari kota di seluruh Indonesia" secara
     * langsung untuk skema city_id lama (yang dipakai tabel store/user_address) -
     * hanya "kota per provinsi". Jadi harus di-fetch per provinsi.
     *
     * @return array{error: bool, message: ?string, data: array}
     */
    public function getCitiesByProvince(string $provinceId): array
    {
        return $this->get("api/v1/destination/city/{$provinceId}", []);
    }

    private function post(string $path, array $params): array
    {
        return $this->request('post', $path, $params);
    }

    private function get(string $path, array $params): array
    {
        return $this->request('get', $path, $params);
    }

    private function request(string $method, string $path, array $params): array
    {
        $response = [
            'error' => true,
            'message' => null,
            'data' => [],
        ];

        try {
            $http = Http::withHeaders(['key' => $this->apiKey])->asForm();
            $result = $method === 'get'
                ? Http::withHeaders(['key' => $this->apiKey])->get("{$this->baseUrl}/{$path}", $params)
                : $http->post("{$this->baseUrl}/{$path}", $params);

            $body = $result->json();

            if ($result->successful() && isset($body['data'])) {
                $response['error'] = false;
                $response['data'] = $body['data'];
            } else {
                $response['message'] = $body['meta']['message'] ?? 'RajaOngkir request gagal.';
            }
        } catch (\Throwable $e) {
            $response['message'] = 'RajaOngkir API exception: ' . $e->getMessage();
            Log::error($response['message']);
        }

        return $response;
    }
}
