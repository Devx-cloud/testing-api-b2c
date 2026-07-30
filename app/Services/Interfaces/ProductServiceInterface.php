<?php

namespace App\Services\Interfaces;

use App\Models\Product;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

interface ProductServiceInterface
{
    /**
     * Kontrak untuk mengambil semua produk dengan paginasi.
     *
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function getAllProductsPaginated(): LengthAwarePaginator;

    /**
     * Kontrak untuk mencari satu produk berdasarkan ID-nya.
     *
     * @param int $id
     * @return \App\Models\Product
     */
    public function findProductById($id): Product;

    /**
     * Kontrak untuk mencari produk berdasarkan namanya.
     *
     * @param string $name
     * @return \Illuminate\Contracts\Pagination\LengthAwarePaginator
     */
    public function findProductByname(string $name): LengthAwarePaginator;

    /**
     * Mencari produk berdasarkan Nama Produk dan Nama Kategori.
     */
    public function searchProductsByCategory(?string $productName, ?string $categoryName): LengthAwarePaginator;
}
