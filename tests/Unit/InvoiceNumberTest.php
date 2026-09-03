<?php

namespace Tests\Unit;

use App\Support\InvoiceNumber;
use PHPUnit\Framework\TestCase;

/**
 * Nilai acuan diambil dari order sungguhan di database testing_tokodaring_b2c yang
 * dibuat lewat web nusantaramall-b2c (Symfony). Kalau salah satu test di sini gagal,
 * artinya penomoran Laravel sudah menyimpang dari Symfony dan nomor transaksi hasil
 * checkout AI agent tidak akan cocok dengan yang dicari halaman riwayat transaksi.
 */
class InvoiceNumberTest extends TestCase
{
    private const ALPHABET = 'abcdefghijklmnopqrstuvwxyz1234567890';
    private const BASE_FORMAT = 'BM-INVOICE/%s/%s/%s';

    public function test_invoice_per_order_sama_dengan_yang_dibuat_symfony(): void
    {
        // order id 2117 -> BM-INVOICE/09/2026/m2e72m
        $this->assertSame(
            'BM-INVOICE/09/2026/m2e72m',
            InvoiceNumber::forOrder(2117, self::ALPHABET, self::BASE_FORMAT, '09', '2026')
        );

        // order id 2116 -> BM-INVOICE/08/2026/m0lwjm
        $this->assertSame(
            'BM-INVOICE/08/2026/m0lwjm',
            InvoiceNumber::forOrder(2116, self::ALPHABET, self::BASE_FORMAT, '08', '2026')
        );
    }

    public function test_shared_invoice_sama_dengan_yang_dibuat_symfony(): void
    {
        $cases = [
            2117 => ['BM-INVOICE/09/2026/m2e72m', 'BM-INVOICE/09/2026/71wlv5x7pz7x42gynr'],
            2118 => ['BM-INVOICE/09/2026/mj5r7n', 'BM-INVOICE/09/2026/73z1kl98q1598g6jy5'],
            2119 => ['BM-INVOICE/09/2026/n1p96n', 'BM-INVOICE/09/2026/2gwv70911709p6z83k'],
        ];

        foreach ($cases as $orderId => [$invoice, $expected]) {
            $this->assertSame(
                $expected,
                InvoiceNumber::shared([$orderId], $invoice, self::ALPHABET),
                "shared_invoice untuk order {$orderId} tidak cocok dengan data Symfony"
            );
        }
    }

    public function test_shared_invoice_mempertahankan_prefiks_bulan_dan_tahun(): void
    {
        $shared = InvoiceNumber::shared([2117], 'BM-INVOICE/09/2026/m2e72m', self::ALPHABET);

        $this->assertStringStartsWith('BM-INVOICE/09/2026/', $shared);
    }

    public function test_checkout_multi_toko_menghasilkan_satu_nomor_untuk_semua_order(): void
    {
        // Dua order dari satu checkout harus berbagi nomor transaksi yang sama persis,
        // karena getOrderDetailBySharedInvoice() di B2C mencari dengan nilai ini.
        $orderIds = [3001, 3002];

        $first = InvoiceNumber::shared($orderIds, 'BM-INVOICE/09/2026/aaaaaa', self::ALPHABET);
        $second = InvoiceNumber::shared($orderIds, 'BM-INVOICE/09/2026/bbbbbb', self::ALPHABET);

        $this->assertSame(
            substr($first, strrpos($first, '/') + 1),
            substr($second, strrpos($second, '/') + 1)
        );
    }

    public function test_hash_gabungan_berbeda_dengan_hash_per_order(): void
    {
        // Nomor transaksi meng-encode SEMUA order id sekaligus, bukan hanya yang pertama.
        $gabungan = InvoiceNumber::shared([3001, 3002], 'BM-INVOICE/09/2026/aaaaaa', self::ALPHABET);
        $tunggal = InvoiceNumber::shared([3001], 'BM-INVOICE/09/2026/aaaaaa', self::ALPHABET);

        $this->assertNotSame($gabungan, $tunggal);
    }

    public function test_panjang_hash_minimal_delapan_belas_karakter(): void
    {
        $shared = InvoiceNumber::shared([1], 'BM-INVOICE/09/2026/abc', self::ALPHABET);
        $hash = substr($shared, strrpos($shared, '/') + 1);

        $this->assertGreaterThanOrEqual(InvoiceNumber::SHARED_MIN_LENGTH, strlen($hash));
    }

    public function test_invoice_duplikat_memakai_salt_berbeda(): void
    {
        $normal = InvoiceNumber::forOrder(2117, self::ALPHABET, self::BASE_FORMAT, '09', '2026');
        $duplikat = InvoiceNumber::forDuplicateOrder(2117, self::ALPHABET, self::BASE_FORMAT, '09', '2026', '20260901120000');

        $this->assertNotSame($normal, $duplikat);
    }
}
