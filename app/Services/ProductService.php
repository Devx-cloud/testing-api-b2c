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
        $products = Product::with('productfiles:id,product_id,file_name,file_path,file_status')
            ->where('status', 'publish')
            ->where('quantity', '>', 0)
            ->paginate(25);

        $this->injectCategoryModels($products);

        return $products;
    }

    public function findProductById($id): Product
    {
        try {
            $product = Product::with('productfiles:id,product_id,file_name,file_path,file_status')
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

    public function findProductByname(string $name): LengthAwarePaginator
    {
        $matchingCategoryIds = ProductCategory::where('name', 'REGEXP', '\\b' . $name)
            ->pluck('id')
            ->toArray();

        $products = Product::with('productfiles:id,product_id,file_name,file_path,file_status')
            ->where('status', 'publish')
            ->where('quantity', '>', 0)
            ->where(function (Builder $query) use ($name, $matchingCategoryIds) {
                $query->where('name', 'REGEXP', '\\b' . $name);

                if (!empty($matchingCategoryIds)) {
                    $categoryRegexp = '\\b(' . implode('|', $matchingCategoryIds) . ')\\b';
                    $query->orWhere('category', 'REGEXP', $categoryRegexp);
                }
            })
            ->paginate(25);

        $this->injectCategoryModels($products);

        return $products;
    }

    public function searchProductsByCategory(?string $productName, ?string $categoryName): LengthAwarePaginator
    {
        $query = Product::with(['categoryRelation', 'productfiles']);

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
