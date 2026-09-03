<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\TokodaringUser;
use App\Models\UserAddress;
use App\Services\Interfaces\CheckoutServiceInterface;
use App\Support\InvoiceNumber;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Aturan penomoran satu checkout, apa pun jumlah toko dan barangnya:
 *
 *   order.invoice        -> satu per TOKO
 *   order.shared_invoice -> satu per TRANSAKSI (nomor yang dicari pelanggan)
 *
 * Halaman riwayat transaksi nusantaramall-b2c menampilkan satu kartu per
 * shared_id, dengan sub-kartu per invoice di dalamnya. Kalau satu checkout
 * menghasilkan lebih dari satu shared_invoice, transaksinya pecah jadi beberapa
 * kartu dan `getOrderDetailBySharedInvoice()` hanya menemukan sebagian pesanan.
 */
class SharedInvoiceGroupingTest extends TestCase
{
    use DatabaseTransactions;

    private function buyer(): TokodaringUser
    {
        $buyer = TokodaringUser::whereIn('role', ['ROLE_USER', 'ROLE_USER_BUYER', 'ROLE_USER_BUSINESS'])
            ->whereIn('id', UserAddress::query()->select('user_id'))
            ->first();

        if (!$buyer) {
            $this->markTestSkipped('Tidak ada buyer B2C beralamat di database ini.');
        }

        return $buyer;
    }

    /** Stok minimal 2: sebagian uji melakukan lebih dari satu checkout atas produk yang sama.
     * @return Product[] */
    private function productsFromOneStore(int $count = 2): array
    {
        $storeId = Product::where('status', 'publish')->where('quantity', '>=', 2)
            ->groupBy('store_id')
            ->havingRaw('COUNT(*) >= ?', [$count])
            ->orderByRaw('COUNT(*) DESC')
            ->value('store_id');

        if (!$storeId) {
            return [];
        }

        return Product::where('status', 'publish')->where('quantity', '>=', 2)
            ->where('store_id', $storeId)
            ->limit($count)
            ->get()
            ->all();
    }

    /** Stok minimal 2: sebagian uji melakukan lebih dari satu checkout atas produk yang sama.
     * @return Product[] */
    private function productsFromTwoStores(): array
    {
        $storeIds = Product::where('status', 'publish')->where('quantity', '>=', 2)
            ->groupBy('store_id')
            ->orderByRaw('COUNT(*) DESC')
            ->limit(2)
            ->pluck('store_id');

        if ($storeIds->count() < 2) {
            return [];
        }

        return $storeIds->map(function ($storeId) {
            return Product::where('status', 'publish')->where('quantity', '>=', 2)
                ->where('store_id', $storeId)
                ->first();
        })->all();
    }

    private function checkout(array $storesPayload): array
    {
        $buyer = $this->buyer();
        $address = UserAddress::where('user_id', $buyer->id)->firstOrFail();

        return $this->app->make(CheckoutServiceInterface::class)
            ->createOrder($buyer->id, $address->id, $storesPayload, 'uji penomoran transaksi');
    }

    private function storePayload(int $storeId, array $products): array
    {
        return [
            'store_id' => $storeId,
            'courier' => 'jne',
            'service' => 'REG',
            'shipping_price' => 10000,
            'items' => array_map(function (Product $p) {
                return ['product_id' => $p->id, 'quantity' => 1];
            }, $products),
        ];
    }

    public function test_dua_barang_satu_toko_jadi_satu_order_dan_satu_nomor(): void
    {
        $products = $this->productsFromOneStore(2);

        if (count($products) < 2) {
            $this->markTestSkipped('Tidak ada toko dengan dua produk tayang di database ini.');
        }

        $result = $this->checkout([$this->storePayload($products[0]->store_id, $products)]);

        $this->assertCount(1, $result['orders'], 'satu toko harus menghasilkan satu order');

        $order = Order::find($result['orders'][0]['order_id']);
        $this->assertCount(2, $order->orderProducts, 'kedua barang harus berada di order yang sama');
        $this->assertNotNull($order->shared_invoice);
        $this->assertSame($result['shared_invoice'], $order->shared_invoice);
    }

    public function test_dua_barang_beda_toko_berbagi_satu_nomor_transaksi(): void
    {
        $products = $this->productsFromTwoStores();

        if (count($products) < 2 || $products[0] === null || $products[1] === null) {
            $this->markTestSkipped('Perlu dua toko berbeda dengan produk tayang.');
        }

        $result = $this->checkout([
            $this->storePayload($products[0]->store_id, [$products[0]]),
            $this->storePayload($products[1]->store_id, [$products[1]]),
        ]);

        $this->assertCount(2, $result['orders'], 'dua toko harus menghasilkan dua order');

        $orders = Order::whereIn('id', array_column($result['orders'], 'order_id'))->get();

        // Nomor invoice BERBEDA - tiap toko memproses pesanannya sendiri.
        $invoices = $orders->pluck('invoice')->unique();
        $this->assertCount(2, $invoices, 'tiap toko punya nomor invoice sendiri');

        // Nomor transaksi SAMA - pembeli melakukan satu kali checkout.
        $sharedInvoices = $orders->pluck('shared_invoice')->unique();
        $this->assertCount(1, $sharedInvoices, 'satu checkout hanya boleh punya satu nomor transaksi');
        $this->assertSame($result['shared_invoice'], $sharedInvoices->first());

        // shared_id juga menyatukan keduanya - inilah yang dipakai halaman riwayat
        // transaksi untuk mengelompokkan kartu.
        $this->assertCount(1, $orders->pluck('shared_id')->unique());

        // Nomornya dihitung SEKALI dari invoice order pertama, bukan diturunkan
        // ulang per order. Kalau diturunkan per order, checkout yang kebetulan
        // melewati pergantian bulan menghasilkan dua nomor berbeda.
        $pertama = $orders->sortBy('id')->first();
        $this->assertSame(
            InvoiceNumber::shared($orders->pluck('id')->all(), $pertama->invoice, config('services.invoice.hashids_alphabet')),
            $sharedInvoices->first()
        );
    }

    public function test_dua_checkout_terpisah_menghasilkan_nomor_transaksi_berbeda(): void
    {
        $products = $this->productsFromOneStore(2);

        if (count($products) < 2) {
            $this->markTestSkipped('Tidak ada toko dengan dua produk tayang di database ini.');
        }

        $pertama = $this->checkout([$this->storePayload($products[0]->store_id, [$products[0]])]);
        $kedua = $this->checkout([$this->storePayload($products[1]->store_id, [$products[1]])]);

        $this->assertNotSame(
            $pertama['shared_invoice'],
            $kedua['shared_invoice'],
            'checkout terpisah adalah transaksi terpisah, nomornya tidak boleh sama'
        );
    }

    public function test_nomor_transaksi_meng_encode_seluruh_order_dalam_transaksi(): void
    {
        $products = $this->productsFromTwoStores();

        if (count($products) < 2 || $products[0] === null || $products[1] === null) {
            $this->markTestSkipped('Perlu dua toko berbeda dengan produk tayang.');
        }

        $duaToko = $this->checkout([
            $this->storePayload($products[0]->store_id, [$products[0]]),
            $this->storePayload($products[1]->store_id, [$products[1]]),
        ]);

        $satuToko = $this->checkout([$this->storePayload($products[0]->store_id, [$products[0]])]);

        // Hash transaksi dua toko dibangun dari dua order id sekaligus, jadi tidak
        // mungkin sama dengan hash transaksi satu order.
        $this->assertNotSame($duaToko['shared_invoice'], $satuToko['shared_invoice']);
    }
}
