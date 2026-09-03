<?php

namespace App\Support;

use Hashids\Hashids;

/**
 * Penomoran invoice yang WAJIB identik dengan nusantaramall-b2c (Symfony), karena
 * kedua sistem menulis ke tabel `order` yang sama dan halaman riwayat transaksi B2C
 * membaca kolom yang dihasilkan di sini.
 *
 * Ada dua nomor dengan dua algoritme berbeda:
 *
 * | Kolom            | Cakupan                  | Salt Hashids                                        | Min. panjang |
 * |------------------|--------------------------|-----------------------------------------------------|--------------|
 * | `invoice`        | satu order (satu toko)   | App\Entity\Order                                    | 6            |
 * | `shared_invoice` | satu transaksi (n order) | App\EventListener\SetOrderSharedInvoiceEntityListener | 18           |
 *
 * Sumber di sisi Symfony: `OrderEntityListener` dan `SetOrderSharedInvoiceEntityListener`
 * (run_type "single", dipanggil OrderController::process setelah semua order tersimpan).
 *
 * Salt-nya adalah nama kelas Symfony secara literal. JANGAN diganti ke nama kelas
 * Laravel - hash-nya akan berbeda dan nomor transaksi tidak lagi konsisten lintas
 * sistem, sementara kolomnya dipakai sebagai kunci pencarian di B2C.
 */
class InvoiceNumber
{
    public const ORDER_SALT = 'App\Entity\Order';
    public const ORDER_MIN_LENGTH = 6;

    public const SHARED_SALT = 'App\EventListener\SetOrderSharedInvoiceEntityListener';
    public const SHARED_MIN_LENGTH = 18;

    /**
     * Nomor invoice per order, mis. "BM-INVOICE/09/2026/m2e72m".
     */
    public static function forOrder(int $orderId, string $alphabet, string $baseFormat, string $month, string $year): string
    {
        $encoder = new Hashids(self::ORDER_SALT, self::ORDER_MIN_LENGTH, $alphabet);

        return sprintf($baseFormat, $month, $year, $encoder->encode($orderId));
    }

    /**
     * Cadangan kalau nomor hasil forOrder() ternyata sudah dipakai order lain
     * (pernah terjadi 29 Sept 2020 karena beda besar-kecil huruf). Salt-nya
     * mengandung timestamp supaya hash-nya pasti berbeda.
     */
    public static function forDuplicateOrder(int $orderId, string $alphabet, string $baseFormat, string $month, string $year, string $timestamp): string
    {
        $encoder = new Hashids('App\Entity\DuplicateOrder-' . $timestamp, 7, $alphabet);

        return sprintf($baseFormat, $month, $year, $encoder->encode($orderId));
    }

    /**
     * Nomor transaksi bersama untuk SEMUA order dalam satu checkout, mis.
     * "BM-INVOICE/09/2026/71wlv5x7pz7x42gynr".
     *
     * Halaman riwayat transaksi B2C (`templates/public/user/order/index_v2.html.twig`)
     * memakai nilai ini untuk dua hal: teks biru di samping kata "Transaksi", dan
     * target tombol "Detail Transaksi" (route `user_order_shared`). Kalau kolomnya
     * NULL, teksnya hilang dan tombolnya jatuh ke `javascript:void(0);`.
     *
     * Segmen terakhir invoice diganti hash gabungan seluruh order id, persis seperti
     * `str_replace()` di listener Symfony - termasuk sifatnya yang mengganti semua
     * kemunculan substring itu. Sengaja ditiru apa adanya supaya hasilnya sama.
     */
    public static function shared(array $orderIds, string $invoice, string $alphabet): string
    {
        $encoder = new Hashids(self::SHARED_SALT, self::SHARED_MIN_LENGTH, $alphabet);
        $hash = $encoder->encode(array_map('intval', array_values($orderIds)));

        $parts = explode('/', $invoice);
        $search = end($parts);

        return str_replace($search, $hash, $invoice);
    }
}
