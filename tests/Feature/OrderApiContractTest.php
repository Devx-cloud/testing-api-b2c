<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\Store;
use App\Models\TokodaringUser;
use App\Models\UserAddress;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

/**
 * Kontrak HTTP yang dipakai AI agent WhatsApp. Yang diuji di sini bukan sekadar
 * "endpoint jalan", tapi field-field yang tanpanya agent tidak bisa mengutip total
 * yang benar atau menyebut nomor transaksi ke pelanggan.
 */
class OrderApiContractTest extends TestCase
{
    use DatabaseTransactions;

    private function headers(): array
    {
        return ['Token' => config('services.api.key')];
    }

    private function anyPublishedProduct(): ?Product
    {
        return Product::where('status', 'publish')->where('quantity', '>', 0)->first();
    }

    public function test_post_orders_mengembalikan_nomor_transaksi(): void
    {
        $product = $this->anyPublishedProduct();
        $buyer = TokodaringUser::whereIn('role', ['ROLE_USER', 'ROLE_USER_BUYER', 'ROLE_USER_BUSINESS'])
            ->whereIn('id', UserAddress::query()->select('user_id'))
            ->first();

        if (!$product || !$buyer) {
            $this->markTestSkipped('Database ini tidak punya produk/buyer yang bisa dipakai.');
        }

        $address = UserAddress::where('user_id', $buyer->id)->firstOrFail();

        $response = $this->withHeaders($this->headers())->postJson('/api/orders', [
            'user_id' => $buyer->id,
            'address_id' => $address->id,
            'note' => 'test kontrak API',
            'stores' => [[
                'store_id' => $product->store_id,
                'courier' => 'jne',
                'service' => 'REG',
                'shipping_price' => 10000,
                'items' => [['product_id' => $product->id, 'quantity' => 1]],
            ]],
        ]);

        $response->assertStatus(201);
        $response->assertJsonStructure([
            'shared_id',
            'shared_invoice',
            'grand_total',
            'orders' => [['order_id', 'invoice', 'shared_invoice', 'subtotal', 'tax_total', 'shipping_price', 'grand_total']],
        ]);

        $body = $response->json();
        $this->assertNotNull($body['shared_invoice'], 'Tanpa shared_invoice, "Detail Transaksi" di B2C mati.');
        $this->assertSame($body['shared_invoice'], $body['orders'][0]['shared_invoice']);
    }

    public function test_get_order_membawa_shared_invoice_dan_ppn(): void
    {
        $product = $this->anyPublishedProduct();
        $buyer = TokodaringUser::whereIn('role', ['ROLE_USER', 'ROLE_USER_BUYER', 'ROLE_USER_BUSINESS'])
            ->whereIn('id', UserAddress::query()->select('user_id'))
            ->first();

        if (!$product || !$buyer) {
            $this->markTestSkipped('Database ini tidak punya produk/buyer yang bisa dipakai.');
        }

        $address = UserAddress::where('user_id', $buyer->id)->firstOrFail();

        $created = $this->withHeaders($this->headers())->postJson('/api/orders', [
            'user_id' => $buyer->id,
            'address_id' => $address->id,
            'stores' => [[
                'store_id' => $product->store_id,
                'courier' => 'jne',
                'shipping_price' => 10000,
                'items' => [['product_id' => $product->id, 'quantity' => 1]],
            ]],
        ])->json();

        $invoice = $created['orders'][0]['invoice'];

        $response = $this->withHeaders($this->headers())->getJson('/api/orders/' . $invoice);

        $response->assertStatus(200);
        $response->assertJsonPath('data.shared_invoice', $created['shared_invoice']);
        $response->assertJsonPath('data.grand_total', $created['orders'][0]['grand_total']);
    }

    public function test_pencarian_produk_membawa_status_pkp_toko(): void
    {
        $product = $this->anyPublishedProduct();

        if (!$product) {
            $this->markTestSkipped('Tidak ada produk publish berstok di database ini.');
        }

        $keyword = explode(' ', trim($product->name))[0];

        $response = $this->withHeaders($this->headers())->getJson('/api/products/search?name=' . urlencode($keyword));

        $response->assertStatus(200);

        $first = $response->json('data.0');
        $this->assertNotNull($first, 'Pencarian "' . $keyword . '" tidak mengembalikan produk apa pun.');
        // Tanpa dua field ini, agent tidak bisa menghitung PPN dan total yang
        // dikutip di WhatsApp akan berbeda dari yang tampil di web Balimall.
        $this->assertArrayHasKey('store_is_pkp', $first);
        $this->assertArrayHasKey('tax_value', $first);

        $store = Store::find($first['store_id']);
        $this->assertSame((bool) $store->is_pkp, $first['store_is_pkp']);
    }
}
