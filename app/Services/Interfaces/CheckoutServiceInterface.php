<?php

namespace App\Services\Interfaces;

use App\Models\UserAddress;
use Illuminate\Database\Eloquent\Collection;

interface CheckoutServiceInterface
{
    /**
     * Ambil semua alamat tersimpan milik seorang user Tokodaring.
     */
    public function getAddressesByUserId(int $userId): Collection;

    /**
     * Simpan alamat baru untuk user (dipakai saat AI mengumpulkan alamat lewat chat).
     */
    public function createAddress(int $userId, array $data): UserAddress;

    /**
     * Hitung opsi ongkir untuk sekumpulan item dari satu toko, ke satu alamat tujuan.
     *
     * @param array $items [{product_id, quantity}]
     * @return array{error: bool, message: ?string, options: array}
     */
    public function getShippingOptions(int $storeId, int $addressId, array $items, string $courier): array;

    /**
     * Cari kota (dipakai untuk resolve nama kota -> city_id saat mengumpulkan alamat baru).
     */
    public function searchCity(string $keyword): array;

    /**
     * Buat order (bisa lebih dari satu, satu per toko) untuk sebuah checkout.
     *
     * @param array $stores [{store_id, items: [{product_id, quantity}], courier, service, shipping_price}]
     * @return array hasil per toko: {store_id, invoice, total, shipping_price, grand_total}
     */
    public function createOrder(int $userId, int $addressId, array $stores, ?string $note = null): array;
}
