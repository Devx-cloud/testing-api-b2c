<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Services\Interfaces\ProductServiceInterface;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class ProductService implements ProductServiceInterface
{
    public function getAllProductsPaginated(): LengthAwarePaginator
    {
        $products = Product::with(['productfiles:id,product_id,file_name,file_path,file_status', 'store:id,name,is_pkp'])
            ->where('status', 'publish')
            ->where('quantity', '>', 0)
            ->paginate(25);

        $this->injectCategoryModels($products);

        return $products;
    }

    public function findProductById($id): Product
    {
        try {
            $product = Product::with(['productfiles:id,product_id,file_name,file_path,file_status', 'store:id,name,is_pkp'])
                ->where('status', 'publish')
                ->where('quantity', '>', 0)
                ->findOrFail($id);

            if (!empty($product->category)) {
                $categoryIds = explode(',', $product->category);
                $product->category_models = ProductCategory::whereIn('id', $categoryIds)->get();
            } else {
                $product->category_models = collect();
            }

            return $product;
        } catch (ModelNotFoundException $e) {
            throw new NotFoundHttpException("Produk dengan ID {$id} tidak ditemukan.");
        }
    }

    /**
     * Panjang minimal kata kunci sebelum boleh dicocokkan sebagai substring.
     *
     * Kata pendek meledak kalau dicari di tengah kata: "ac" melompat dari 14 hasil
     * (batas kata) menjadi 99 karena ikut mencomot "Rack", "Package", "Backup".
     * Kata sepanjang ini ke atas jauh lebih jarang muncul menyelip di kata lain.
     */
    private const LOOSE_SEARCH_MIN_LENGTH = 4;

    public function findProductByname(string $name): LengthAwarePaginator
    {
        $products = $this->productsMatching(trim($name))->paginate(25);

        $this->injectCategoryModels($products);

        return $products;
    }

    /**
     * Cari produk lewat tiga jalur sekaligus: nama pada batas kata, nama sebagai
     * substring, dan nama kategori.
     *
     * Batas kata saja melewatkan kata majemuk - "book" tidak menemukan
     * "HP EliteBook 830 G5". Substring saja terlalu berisik untuk kata pendek.
     * Jadi keduanya digabung, dengan pelonggaran substring hanya untuk kata kunci
     * yang cukup panjang, lalu hasilnya diurutkan supaya kecocokan batas kata selalu
     * berada di halaman pertama - client hanya membaca 25 baris teratas.
     */
    private function productsMatching(string $keyword): Builder
    {
        $regex = '\\b' . $this->escapeRegex($keyword);
        $like = '%' . $this->escapeLike($keyword) . '%';
        $loose = mb_strlen($keyword) >= self::LOOSE_SEARCH_MIN_LENGTH;

        $matchingCategoryIds = ProductCategory::where('name', 'REGEXP', $regex)
            ->when($loose, function ($query) use ($like) {
                return $query->orWhere('name', 'LIKE', $like);
            })
            ->pluck('id')
            ->toArray();

        $query = Product::with(['productfiles:id,product_id,file_name,file_path,file_status', 'store:id,name,is_pkp'])
            ->where('status', 'publish')
            ->where('quantity', '>', 0)
            ->where(function (Builder $inner) use ($regex, $like, $loose, $matchingCategoryIds) {
                $inner->where('name', 'REGEXP', $regex);

                if ($loose) {
                    $inner->orWhere('name', 'LIKE', $like);
                }

                if (!empty($matchingCategoryIds)) {
                    $inner->orWhere('category', 'REGEXP', '\\b(' . implode('|', $matchingCategoryIds) . ')\\b');
                }
            });

        // Kecocokan pada batas kata lebih kuat daripada kecocokan di tengah kata,
        // dan keduanya lebih kuat daripada sekadar sekategori.
        return $query->orderByRaw('CASE WHEN name REGEXP ? THEN 0 WHEN name LIKE ? THEN 1 ELSE 2 END', [$regex, $like]);
    }

    /**
     * Kata kunci datang dari pesan WhatsApp pelanggan, jadi bisa memuat karakter
     * apa pun. Tanpa dilolosi, "cari pc (yang murah)" membuat MySQL menolak polanya
     * dan endpoint balas HTTP 500 - di sisi bot itu terbaca sebagai gangguan sistem,
     * sehingga pelanggan diberi tahu pencarian sedang rusak padahal tidak.
     */
    private function escapeRegex(string $value): string
    {
        return addcslashes($value, '\\^$.[]|()*+?{}-/');
    }

    private function escapeLike(string $value): string
    {
        return addcslashes($value, '\\%_');
    }

    public function searchProductsByCategory(?string $productName, ?string $categoryName): LengthAwarePaginator
    {
        $query = Product::with(['categoryRelation', 'productfiles', 'store:id,name,is_pkp']);

        if (!empty($productName)) {
            $query->where('name', 'LIKE', '%' . $productName . '%');
        }

        if (!empty($categoryName)) {
            $query->whereHas('categoryRelation', function (Builder $q) use ($categoryName) {
                $q->where('name', 'LIKE', '%' . $categoryName . '%');
            });
        }

        return $query->paginate(25);
    }

    /**
     * Kolom 'category' di tabel product menyimpan ID kategori berupa string
     * "1,5". Fungsi ini mengambil semua kategori yang relevan dalam satu
     * query lalu menyuntikkannya ke masing-masing produk agar accessor
     * category_data di model tidak melakukan query berulang per produk.
     */
    private function injectCategoryModels(LengthAwarePaginator $products): void
    {
        $allCategoryIds = $products
            ->pluck('category')
            ->flatMap(function ($categoryString) {
                return explode(',', $categoryString ?? '');
            })
            ->unique()
            ->filter();

        $categories = ProductCategory::whereIn('id', $allCategoryIds)->get()->keyBy('id');

        $products->each(function ($product) use ($categories) {
            $productCategoryIds = explode(',', $product->category ?? '');
            $product->category_models = collect($productCategoryIds)
                ->map(function ($id) use ($categories) {
                    return $categories->get($id);
                })
                ->filter();
        });
    }
}
