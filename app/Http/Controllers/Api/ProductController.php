<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ProductResource;
use App\Services\Interfaces\ProductServiceInterface;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ProductController extends Controller
{
    protected $productService;

    public function __construct(ProductServiceInterface $productService)
    {
        $this->productService = $productService;
    }

    /**
     * Menampilkan daftar semua produk.
     */
    public function index()
    {
        $products = $this->productService->getAllProductsPaginated();

        return ProductResource::collection($products);
    }

    /**
     * Menampilkan detail satu produk.
     */
    public function show($id)
    {
        $product = $this->productService->findProductById($id);

        return new ProductResource($product);
    }

    public function search(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
        ]);

        try {
            $name = $request->input('name');
            $products = $this->productService->findProductByname($name);

            if ($products->isEmpty()) {
                return response()->json([
                    'message' => 'Produk tidak ditemukan.',
                ], 200);
            }

            return ProductResource::collection($products);
        } catch (Exception $e) {
            Log::error('Error saat search product: ' . $e->getMessage());

            return response()->json([
                'message' => 'Terjadi kesalahan pada server.',
            ], 500);
        }
    }

    public function searchByCategory(Request $request)
    {
        $request->validate([
            'product_name' => 'nullable|string',
            'category_name' => 'nullable|string',
        ]);

        try {
            $pName = $request->input('product_name');
            $cName = $request->input('category_name');

            $products = $this->productService->searchProductsByCategory($pName, $cName);

            if ($products->isEmpty()) {
                return response()->json([
                    'message' => 'Produk tidak ditemukan.',
                ], 200);
            }

            return ProductResource::collection($products);
        } catch (Exception $e) {
            return response()->json([
                'message' => 'Terjadi kesalahan: ' . $e->getMessage(),
            ], 500);
        }
    }
}
