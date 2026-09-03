<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Services\Interfaces\ProductServiceInterface;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Pencarian produk adalah pintu masuk seluruh percakapan AI agent: kalau kata kunci
 * pelanggan tidak menemukan apa pun, bot menjawab "produk tidak ada" untuk barang
 * yang sebenarnya dijual.
 */
class ProductSearchTest extends TestCase
{
    use DatabaseTransactions;

    private function search(string $keyword)
    {
        return $this->app->make(ProductServiceInterface::class)->findProductByname($keyword);
    }

    public function test_kata_kunci_dengan_karakter_regex_tidak_membuat_server_error(): void
    {
        // Kata kunci datang dari pesan WhatsApp bebas. Sebelum diperbaiki, "(" dan "["
        // membuat MySQL menolak polanya dan endpoint balas HTTP 500 - yang di sisi bot
        // terbaca sebagai gangguan sistem, jadi pelanggan diberi tahu pencarian rusak.
        foreach (['pc (murah', 'pc [', 'laptop )', 'a|b', 'harga*', 'kartu{2}'] as $keyword) {
            $hasil = $this->search($keyword);
            $this->assertNotNull($hasil, "kata kunci '{$keyword}' seharusnya tidak melempar error");
        }
    }

    public function test_wildcard_like_diperlakukan_sebagai_teks_biasa(): void
    {
        // "%" tidak boleh berubah jadi wildcard yang cocok dengan seluruh katalog.
        $semua = Product::where('status', 'publish')->where('quantity', '>', 0)->count();
        $hasil = $this->search('%%%%');

        $this->assertLessThan($semua, $hasil->total(), 'wildcard mentah tidak boleh mengembalikan seluruh katalog');
    }

    public function test_kata_majemuk_ditemukan_lewat_pencocokan_substring(): void
    {
        $target = Product::where('status', 'publish')
            ->where('quantity', '>', 0)
            ->where('name', 'LIKE', '%EliteBook%')
            ->first();

        if (!$target) {
            $this->markTestSkipped('Database ini tidak punya produk dengan kata majemuk yang diuji.');
        }

        // "book" tidak berdiri di batas kata pada "EliteBook", jadi pencocokan batas
        // kata saja melewatkannya.
        $ids = $this->search('book')->pluck('id')->all();

        $this->assertContains($target->id, $ids);
    }

    public function test_kecocokan_batas_kata_diperingkat_lebih_dulu(): void
    {
        // Client hanya membaca 25 baris teratas, jadi urutan menentukan apa yang
        // benar-benar sampai ke pelanggan.
        $hasil = $this->search('book');

        if ($hasil->total() < 2) {
            $this->markTestSkipped('Perlu lebih dari satu hasil untuk menguji urutan.');
        }

        $pertama = $hasil->first();
        $this->assertMatchesRegularExpression('/\bbook/i', $pertama->name);
    }

    public function test_kata_pendek_tidak_dilonggarkan_jadi_substring(): void
    {
        // "ac" sebagai substring melompat dari 14 hasil menjadi 99 karena ikut
        // mencomot "Rack", "Package", "Backup" - derau seperti itu mendorong produk
        // yang benar-benar relevan keluar dari halaman pertama.
        $ketat = Product::where('status', 'publish')
            ->where('quantity', '>', 0)
            ->where('name', 'REGEXP', '\\bac')
            ->count();
        $longgar = Product::where('status', 'publish')
            ->where('quantity', '>', 0)
            ->where('name', 'LIKE', '%ac%')
            ->count();

        if ($longgar <= $ketat) {
            $this->markTestSkipped('Database ini tidak punya cukup derau untuk menguji batas ini.');
        }

        $this->assertLessThanOrEqual($ketat, $this->search('ac')->total());
    }

    public function test_pencarian_juga_menelusuri_nama_kategori(): void
    {
        $kategori = \App\Models\ProductCategory::whereNotNull('name')->where('name', '<>', '')->get()
            ->first(function ($c) {
                return Product::where('status', 'publish')
                    ->where('quantity', '>', 0)
                    ->where('category', 'REGEXP', '\\b' . $c->id . '\\b')
                    ->exists();
            });

        if (!$kategori) {
            $this->markTestSkipped('Tidak ada kategori berisi produk tayang di database ini.');
        }

        $ids = $this->search($kategori->name)->pluck('id')->all();

        $this->assertNotEmpty($ids, "pencarian nama kategori '{$kategori->name}' harus mengembalikan produk di dalamnya");
    }
}
