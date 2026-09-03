<?php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderProduct;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Store;
use App\Models\TokodaringUser;
use App\Models\UserAddress;
use App\Services\Interfaces\CheckoutServiceInterface;
use App\Support\InvoiceNumber;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class CheckoutService implements CheckoutServiceInterface
{
    protected $rajaOngkir;

    public function __construct(RajaOngkirService $rajaOngkir)
    {
        $this->rajaOngkir = $rajaOngkir;
    }

    public function getAddressesByUserId(int $userId): Collection
    {
        return UserAddress::where('user_id', $userId)->orderByDesc('id')->get();
    }

    public function createAddress(int $userId, array $data): UserAddress
    {
        $address = new UserAddress();
        $address->user_id = $userId;
        $address->title = $data['title'] ?? 'Alamat';
        $address->address = $data['address'];
        $address->city = $data['city'];
        $address->city_id = $data['city_id'];
        $address->district = $data['district'] ?? null;
        $address->district_id = $data['district_id'] ?? 0;
        $address->province = $data['province'];
        $address->province_id = $data['province_id'];
        $address->country = $data['country'] ?? 'Indonesia';
        $address->country_id = $data['country_id'] ?? 0;
        $address->post_code = $data['post_code'] ?? null;
        $address->save();

        return $address;
    }

    public function searchCity(string $keyword): array
    {
        $cities = $this->getAllCitiesCached();

        if ($cities === null) {
            return ['error' => true, 'message' => 'Gagal mengambil data kota dari RajaOngkir.', 'cities' => []];
        }

        $needle = strtolower($keyword);
        $matches = array_values(array_filter($cities, function ($city) use ($needle) {
            return strpos(strtolower($city['city_name']), $needle) !== false;
        }));

        return ['error' => false, 'message' => null, 'cities' => array_slice($matches, 0, 15)];
    }

    /**
     * RajaOngkir tidak punya endpoint "cari kota di seluruh Indonesia" untuk skema
     * city_id lama yang dipakai tabel store/user_address - hanya "kota per provinsi".
     * Jadi seluruh kota di-fetch sekali (34 provinsi) lalu di-cache permanen, dan
     * pencarian dilakukan lokal supaya tidak memboroskan kuota API tiap kali dipanggil.
     */
    private function getAllCitiesCached(): ?array
    {
        $cached = Cache::get('rajaongkir_all_cities');
        if ($cached !== null) {
            return $cached;
        }

        $provincesResult = $this->rajaOngkir->getProvinces();
        if ($provincesResult['error']) {
            return null;
        }

        $allCities = [];
        foreach ($provincesResult['data'] as $province) {
            $citiesResult = $this->rajaOngkir->getCitiesByProvince((string) $province['id']);
            if ($citiesResult['error']) {
                continue;
            }
            foreach ($citiesResult['data'] as $city) {
                $allCities[] = [
                    'city_id' => (string) $city['id'],
                    'city_name' => $city['name'],
                    'province_id' => (string) $province['id'],
                    'province_name' => $province['name'],
                ];
            }
        }

        if (empty($allCities)) {
            return null;
        }

        Cache::forever('rajaongkir_all_cities', $allCities);

        return $allCities;
    }

    public function getShippingOptions(int $storeId, int $addressId, array $items, string $courier): array
    {
        $store = Store::find($storeId);
        if (!$store) {
            return ['error' => true, 'message' => 'Toko tidak ditemukan.', 'options' => []];
        }

        $address = UserAddress::find($addressId);
        if (!$address) {
            return ['error' => true, 'message' => 'Alamat tidak ditemukan.', 'options' => []];
        }

        $supportedCouriers = json_decode($store->delivery_couriers ?? '[]', true) ?: [];
        if (!in_array($courier, $supportedCouriers, true)) {
            return ['error' => true, 'message' => "Toko ini tidak mendukung kurir '{$courier}'.", 'options' => []];
        }

        $products = Product::whereIn('id', collect($items)->pluck('product_id'))->get()->keyBy('id');

        $totalWeightKg = 0;
        foreach ($items as $item) {
            $product = $products->get($item['product_id']);
            if (!$product) {
                return ['error' => true, 'message' => "Produk ID {$item['product_id']} tidak ditemukan.", 'options' => []];
            }
            $weightKg = (float) ($product->weight ?: 1);
            $totalWeightKg += $weightKg * (int) $item['quantity'];
        }

        if (!$store->city || !$address->city) {
            return ['error' => true, 'message' => 'Kota asal atau tujuan belum lengkap.', 'options' => []];
        }

        // Catatan: kolom city_id/province_id yang tersimpan di tabel store/user_address
        // tidak bisa dipercaya (data dummy dari seeding, tidak sinkron dengan skema ID
        // RajaOngkir yang aktif). Jadi city_id selalu di-resolve ulang dari teks
        // nama kota + provinsi tiap kali dipakai, bukan dibaca langsung dari kolomnya.
        $originCityId = $this->resolveCityId($store->city, $store->province);
        if (!$originCityId) {
            return ['error' => true, 'message' => "Kota asal toko ('{$store->city}') tidak dikenali RajaOngkir.", 'options' => []];
        }

        $destinationCityId = $this->resolveCityId($address->city, $address->province);
        if (!$destinationCityId) {
            return ['error' => true, 'message' => "Kota tujuan ('{$address->city}') tidak dikenali RajaOngkir.", 'options' => []];
        }

        $weightGrams = (int) round(max($totalWeightKg, 1) * 1000);

        $result = $this->rajaOngkir->getCost($originCityId, $destinationCityId, $weightGrams, $courier);

        if ($result['error']) {
            return ['error' => true, 'message' => $result['message'], 'options' => []];
        }

        return ['error' => false, 'message' => null, 'options' => $this->normalizeCostOptions($result['data'], $courier)];
    }

    /**
     * Cari city_id RajaOngkir yang valid dari nama kota (+provinsi kalau ada) secara
     * best-effort: exact match dulu (dibatasi provinsi kalau diketahui), lalu partial
     * match, lalu partial match tanpa filter provinsi sebagai upaya terakhir.
     */
    private function resolveCityId(string $cityName, ?string $provinceName): ?string
    {
        $cities = $this->getAllCitiesCached();
        if ($cities === null) {
            return null;
        }

        $needleCity = strtolower(trim($cityName));
        $needleProvince = $provinceName ? strtolower(trim($provinceName)) : null;

        foreach ($cities as $city) {
            if (strtolower($city['city_name']) === $needleCity
                && ($needleProvince === null || strtolower($city['province_name']) === $needleProvince)) {
                return $city['city_id'];
            }
        }

        foreach ($cities as $city) {
            $partial = strpos(strtolower($city['city_name']), $needleCity) !== false
                || strpos($needleCity, strtolower($city['city_name'])) !== false;
            if ($partial && ($needleProvince === null || strtolower($city['province_name']) === $needleProvince)) {
                return $city['city_id'];
            }
        }

        foreach ($cities as $city) {
            if (strpos(strtolower($city['city_name']), $needleCity) !== false) {
                return $city['city_id'];
            }
        }

        return null;
    }

    public function createOrder(int $userId, int $addressId, array $stores, ?string $note = null): array
    {
        $buyer = TokodaringUser::find($userId);
        if (!$buyer) {
            throw new InvalidArgumentException('User tidak ditemukan.');
        }

        // API ini khusus checkout B2C. Alur B2G (instansi pemerintah) punya syarat
        // tambahan (PPK, Satker, Bendahara, negosiasi, approval) yang sengaja tidak
        // diimplementasikan di sini - jangan biarkan buyer B2G lolos membuat order
        // yang datanya akan tidak lengkap/salah dibanding sistem aslinya.
        $allowedRoles = ['ROLE_USER', 'ROLE_USER_BUYER', 'ROLE_USER_BUSINESS'];
        if (!in_array($buyer->role, $allowedRoles, true)) {
            throw new InvalidArgumentException(
                $buyer->role === 'ROLE_USER_GOVERNMENT'
                    ? 'Checkout untuk instansi pemerintah (B2G) belum didukung di sistem ini.'
                    : 'Role akun ini tidak didukung untuk checkout B2C.'
            );
        }

        $address = UserAddress::find($addressId);
        if (!$address) {
            throw new InvalidArgumentException('Alamat tidak ditemukan.');
        }

        if (empty($stores)) {
            throw new InvalidArgumentException('Tidak ada item untuk di-checkout.');
        }

        return DB::transaction(function () use ($buyer, $address, $stores, $note) {
            $sharedId = sprintf('%d-%s', $buyer->id, Str::random(8));
            $taxValue = (float) config('services.order.tax_value', 11);
            /** @var Order[] $createdOrders */
            $createdOrders = [];
            $results = [];
            $grandTotal = 0;

            foreach ($stores as $storeInput) {
                $store = Store::find($storeInput['store_id']);
                if (!$store) {
                    throw new InvalidArgumentException("Toko ID {$storeInput['store_id']} tidak ditemukan.");
                }

                // PPN ditentukan status PKP toko, bukan input client - sama seperti
                // CartController::checkProductWithTaxByPKP() di nusantaramall-b2c.
                $withTax = (bool) $store->is_pkp;

                $lineItems = [];
                $subtotal = 0;
                $taxTotal = 0;

                foreach ($storeInput['items'] as $item) {
                    /** @var Product $product */
                    $product = Product::where('id', $item['product_id'])
                        ->where('store_id', $store->id)
                        ->lockForUpdate()
                        ->first();

                    if (!$product) {
                        throw new InvalidArgumentException("Produk ID {$item['product_id']} tidak ditemukan di toko ini.");
                    }
                    if ($product->status !== 'publish') {
                        throw new InvalidArgumentException("Produk '{$product->name}' sedang tidak tersedia.");
                    }
                    if ($product->quantity < $item['quantity']) {
                        throw new InvalidArgumentException("Stok '{$product->name}' tidak mencukupi (tersisa {$product->quantity}).");
                    }

                    // Harga & stok diambil dari database, bukan dari input client.
                    $price = (float) $product->price;
                    $totalPrice = $price * (int) $item['quantity'];
                    // Harga katalog belum termasuk PPN, jadi nominalnya disimpan
                    // terpisah di order_product.tax_nominal dan TIDAK ditambahkan ke
                    // order.total - persis seperti alur checkout B2C.
                    $taxNominal = $withTax ? $totalPrice * ($taxValue / 100) : 0.0;

                    $subtotal += $totalPrice;
                    $taxTotal += $taxNominal;

                    $lineItems[] = [
                        'product' => $product,
                        'quantity' => (int) $item['quantity'],
                        'price' => $price,
                        'total_price' => $totalPrice,
                        'tax_nominal' => $taxNominal,
                    ];
                }

                $shippingPrice = (float) ($storeInput['shipping_price'] ?? 0);
                // Ongkir yang dikirim client adalah tarif mentah dari /shipping/cost.
                // Toko PKP menagih ongkir sudah termasuk PPN - OrderController::process
                // di nusantaramall-b2c melakukan hal yang sama sebelum menyimpan order.
                if ($withTax) {
                    $shippingPrice += $shippingPrice * ($taxValue / 100);
                }

                $order = new Order();
                $order->store_id = $store->id;
                $order->user_id = $buyer->id;
                $order->invoice = 'TMP-' . Str::random(12);
                $order->total = $subtotal;
                $order->total_backup = $subtotal;
                $order->status = 'pending';
                $order->note = $note;
                $order->name = trim($buyer->first_name . ' ' . $buyer->last_name);
                $order->email = $buyer->email;
                $order->phone = $buyer->phone_number;
                $order->address = $address->address;
                $order->post_code = $address->post_code;
                $order->city = $address->city;
                $order->city_id = $address->city_id;
                $order->district = $address->district;
                $order->district_id = $address->district_id;
                $order->province = $address->province;
                $order->province_id = $address->province_id;
                $order->country = $address->country;
                $order->country_id = $address->country_id;
                $order->address_lat = $address->address_lat;
                $order->address_lng = $address->address_lng;
                $order->shipping_courier = $storeInput['courier'] ?? null;
                $order->shipping_service = $storeInput['service'] ?? null;
                $order->shipping_price = $shippingPrice;
                $order->shipping_price_backup = $shippingPrice;
                $order->shared_id = $sharedId;
                $order->is_b2g_transaction = false;
                $order->negotiation_status = 'none';
                $order->save();

                $order->invoice = $this->generateInvoice($order->id);
                $order->save();

                foreach ($lineItems as $lineItem) {
                    $product = $lineItem['product'];

                    $orderProduct = new OrderProduct();
                    $orderProduct->id = (string) Str::uuid();
                    $orderProduct->order_id = $order->id;
                    $orderProduct->product_id = $product->id;
                    $orderProduct->quantity = $lineItem['quantity'];
                    $orderProduct->price = $lineItem['price'];
                    $orderProduct->total_price = $lineItem['total_price'];
                    $orderProduct->base_price = (float) $product->base_price;
                    $orderProduct->with_tax = $withTax;
                    $orderProduct->tax_value = (string) ($withTax ? $taxValue : 0);
                    $orderProduct->tax_nominal = $lineItem['tax_nominal'];
                    $orderProduct->fee = $this->categoryFeeOf($product);
                    $orderProduct->original_id = $product->id;
                    $orderProduct->original_name = $product->name;
                    $orderProduct->price_before_negotiation = 0;
                    $orderProduct->price_shipping_negotiation = 0;
                    $orderProduct->save();

                    $product->decrement('quantity', $lineItem['quantity']);
                }

                // Yang ditagih ke pembeli = total + ongkir + PPN, sama dengan rumus
                // grand_total di templates/public/user/order/index_v2.html.twig.
                $orderGrandTotal = $subtotal + $shippingPrice + $taxTotal;
                $grandTotal += $orderGrandTotal;

                $createdOrders[] = $order;
                $results[] = [
                    'store_id' => $store->id,
                    'store_name' => $store->name,
                    'order_id' => $order->id,
                    'invoice' => $order->invoice,
                    'subtotal' => $subtotal,
                    'tax_total' => $taxTotal,
                    'shipping_price' => $shippingPrice,
                    'grand_total' => $orderGrandTotal,
                ];
            }

            $sharedInvoice = $this->assignSharedInvoice($createdOrders);

            foreach (array_keys($results) as $index) {
                $results[$index]['shared_invoice'] = $sharedInvoice;
            }

            return [
                'shared_id' => $sharedId,
                'shared_invoice' => $sharedInvoice,
                'orders' => $results,
                'grand_total' => $grandTotal,
            ];
        });
    }

    /**
     * Beri satu nomor transaksi (`shared_invoice`) untuk seluruh order pada checkout ini.
     *
     * Tanpa kolom ini order tetap muncul di riwayat transaksi B2C tapi lumpuh: nomor
     * transaksinya kosong dan tombol "Detail Transaksi" jatuh ke `javascript:void(0);`,
     * karena `index_v2.html.twig` baru membuat link `user_order_shared` kalau
     * `o_sharedInvoice` terisi. Di Symfony langkah ini dikerjakan
     * SetOrderSharedInvoiceEntityListener setelah semua order tersimpan; di sini
     * ditiru pada titik yang sama - sesudah loop toko, sebelum transaksi di-commit.
     *
     * @param Order[] $orders
     */
    private function assignSharedInvoice(array $orders): ?string
    {
        if (empty($orders)) {
            return null;
        }

        $alphabet = config('services.invoice.hashids_alphabet');
        $orderIds = array_map(function (Order $order) {
            return (int) $order->id;
        }, $orders);

        // Nomornya dihitung SEKALI dari invoice order pertama, lalu dipasang apa
        // adanya ke seluruh order. Menghitung ulang per order (memakai prefiks
        // invoice masing-masing) menghasilkan nomor berbeda kalau satu checkout
        // kebetulan melewati pergantian bulan - order pertama "BM-INVOICE/09/...",
        // order kedua "BM-INVOICE/10/...". Transaksinya lalu pecah jadi dua kartu
        // di riwayat transaksi dan halaman detail hanya menemukan sebagian pesanan.
        $sharedInvoice = InvoiceNumber::shared($orderIds, $orders[0]->invoice, $alphabet);

        foreach ($orders as $order) {
            $order->shared_invoice = $sharedInvoice;
            $order->save();
        }

        return $sharedInvoice;
    }

    /**
     * Fee kategori produk, ditulis ke order_product.fee seperti OrderEntityListener
     * di nusantaramall-b2c (dipakai perhitungan disbursement ke merchant).
     * Kolom `product.category` bisa berisi beberapa id ("1,5"); yang pertama dipakai.
     */
    private function categoryFeeOf(Product $product): float
    {
        $categoryId = (int) explode(',', (string) $product->category)[0];

        if ($categoryId < 1) {
            return 0.0;
        }

        $category = ProductCategory::find($categoryId);

        return (float) ($category->fee ?? 0);
    }

    /**
     * Sama persis dengan algoritme OrderEntityListener di nusantaramall-b2c (Symfony),
     * supaya format invoice konsisten lintas sistem meski dibuat dari Laravel -
     * keduanya menulis ke tabel `order` yang sama dan kolom `invoice` punya UNIQUE INDEX.
     */
    private function generateInvoice(int $orderId): string
    {
        $alphabet = config('services.invoice.hashids_alphabet');
        $baseFormat = config('services.invoice.base_format');
        $invoice = InvoiceNumber::forOrder($orderId, $alphabet, $baseFormat, now()->format('m'), now()->format('Y'));

        if (Order::where('invoice', $invoice)->exists()) {
            $invoice = InvoiceNumber::forDuplicateOrder(
                $orderId,
                $alphabet,
                $baseFormat,
                now()->format('m'),
                now()->format('Y'),
                now()->format('YmdHis')
            );
        }

        return $invoice;
    }

    private function normalizeCostOptions(array $data, string $courierFallback): array
    {
        $options = [];

        foreach ($data as $entry) {
            // Format lama RajaOngkir v1: {code, name, costs: [{service, description, cost: [{value, etd}]}]}
            if (isset($entry['costs'])) {
                $courierCode = $entry['code'] ?? $courierFallback;
                foreach ($entry['costs'] as $costEntry) {
                    $costValue = $costEntry['cost'][0]['value'] ?? null;
                    if ($costValue === null) {
                        continue;
                    }
                    $options[] = [
                        'courier' => $courierCode,
                        'service' => $costEntry['service'] ?? null,
                        'description' => $costEntry['description'] ?? null,
                        'cost' => (float) $costValue,
                        'etd' => $costEntry['cost'][0]['etd'] ?? null,
                    ];
                }
                continue;
            }

            // Format Komerce v1 domestic-cost: entri sudah flat per service
            if (isset($entry['cost'])) {
                $options[] = [
                    'courier' => $entry['code'] ?? $courierFallback,
                    'service' => $entry['service'] ?? null,
                    'description' => $entry['description'] ?? ($entry['name'] ?? null),
                    'cost' => (float) $entry['cost'],
                    'etd' => $entry['etd'] ?? null,
                ];
            }
        }

        return $options;
    }
}
