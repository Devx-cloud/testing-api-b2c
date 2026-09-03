<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\Product;
use App\Models\Store;
use App\Models\TokodaringUser;
use App\Models\UserAddress;
use App\Services\Interfaces\CheckoutServiceInterface;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Uji checkout API terhadap database yang sama dengan nusantaramall-b2c.
 *
 * Memakai DatabaseTransactions (bukan RefreshDatabase) karena skema tabel `order`
 * dimiliki aplikasi Symfony dan tidak punya migration di sini - database tidak boleh
 * di-drop. Semua order yang dibuat test ini di-rollback setelah test selesai.
 */
class CheckoutSharedInvoiceTest extends TestCase
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

    private function productFromStore(bool $pkp): ?Product
    {
        $storeIds = Store::where('is_pkp', $pkp ? 1 : 0)->pluck('id');

        return Product::where('status', 'publish')
            ->where('quantity', '>', 0)
            ->whereIn('store_id', $storeIds)
            ->first();
    }

    private function checkout(Product $product, int $shippingPrice = 10000, int $quantity = 1): array
    {
        $buyer = $this->buyer();
        $address = UserAddress::where('user_id', $buyer->id)->firstOrFail();

        /** @var CheckoutServiceInterface $service */
        $service = $this->app->make(CheckoutServiceInterface::class);

        return $service->createOrder($buyer->id, $address->id, [[
            'store_id' => $product->store_id,
            'courier' => 'jne',
            'service' => 'REG',
            'shipping_price' => $shippingPrice,
            'items' => [['product_id' => $product->id, 'quantity' => $quantity]],
        ]], 'test otomatis');
    }

    public function test_order_baru_selalu_punya_shared_invoice(): void
    {
        $product = $this->productFromStore(false) ?? $this->productFromStore(true);

        if (!$product) {
            $this->markTestSkipped('Tidak ada produk publish berstok di database ini.');
        }

        $result = $this->checkout($product);
        $order = Order::find($result['orders'][0]['order_id']);

        $this->assertNotNull($order->shared_invoice, 'shared_invoice wajib terisi, kalau NULL tombol "Detail Transaksi" di B2C mati.');
        $this->assertSame($result['shared_invoice'], $order->shared_invoice);
        $this->assertStringStartsWith('BM-INVOICE/', $order->shared_invoice);
    }

    public function test_shared_invoice_berbagi_prefiks_dengan_invoice_ordernya(): void
    {
        $product = $this->productFromStore(false) ?? $this->productFromStore(true);

        if (!$product) {
            $this->markTestSkipped('Tidak ada produk publish berstok di database ini.');
        }

        $result = $this->checkout($product);
        $order = Order::find($result['orders'][0]['order_id']);

        $prefix = substr($order->invoice, 0, strrpos($order->invoice, '/') + 1);

        $this->assertStringStartsWith($prefix, $order->shared_invoice);
        $this->assertNotSame($order->invoice, $order->shared_invoice);
    }

    public function test_toko_pkp_kena_ppn_pada_produk_dan_ongkir(): void
    {
        $product = $this->productFromStore(true);

        if (!$product) {
            $this->markTestSkipped('Tidak ada toko PKP dengan produk berstok di database ini.');
        }

        $result = $this->checkout($product, 10000);
        $order = Order::find($result['orders'][0]['order_id']);
        $orderProduct = OrderProduct::where('order_id', $order->id)->firstOrFail();

        $price = (float) $product->price;
        $taxValue = (float) config('services.order.tax_value', 11);

        $this->assertTrue((bool) $orderProduct->with_tax);
        $this->assertEqualsWithDelta($price * $taxValue / 100, (float) $orderProduct->tax_nominal, 0.01);
        // order.total TIDAK memuat PPN - konsisten dengan alur checkout Symfony.
        $this->assertEqualsWithDelta($price, (float) $order->total, 0.01);
        // Ongkir toko PKP disimpan sudah termasuk PPN.
        $this->assertEqualsWithDelta(10000 * (1 + $taxValue / 100), (float) $order->shipping_price, 0.01);
    }

    public function test_toko_non_pkp_tidak_kena_ppn(): void
    {
        $product = $this->productFromStore(false);

        if (!$product) {
            $this->markTestSkipped('Tidak ada toko non-PKP dengan produk berstok di database ini.');
        }

        $result = $this->checkout($product, 10000);
        $order = Order::find($result['orders'][0]['order_id']);
        $orderProduct = OrderProduct::where('order_id', $order->id)->firstOrFail();

        $this->assertFalse((bool) $orderProduct->with_tax);
        $this->assertEqualsWithDelta(0.0, (float) $orderProduct->tax_nominal, 0.01);
        $this->assertEqualsWithDelta(10000.0, (float) $order->shipping_price, 0.01);
    }

    public function test_grand_total_sama_dengan_rumus_halaman_riwayat_transaksi_b2c(): void
    {
        $product = $this->productFromStore(true) ?? $this->productFromStore(false);

        if (!$product) {
            $this->markTestSkipped('Tidak ada produk publish berstok di database ini.');
        }

        $result = $this->checkout($product, 10000, 2);
        $order = Order::find($result['orders'][0]['order_id']);

        // index_v2.html.twig: grand_total = o_total + o_shippingPrice + sum(op_taxNominal)
        $expected = (float) $order->total
            + (float) $order->shipping_price
            + (float) OrderProduct::where('order_id', $order->id)->sum('tax_nominal');

        $this->assertEqualsWithDelta($expected, (float) $result['grand_total'], 0.01);
    }
}
