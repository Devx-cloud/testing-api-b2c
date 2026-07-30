<?php

use App\Http\Controllers\Api\AddressController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\ShippingController;
use App\Http\Controllers\Api\WhatsappUserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
    return $request->user();
});

Route::middleware('auth.apikey')->group(function () {
    Route::post('/whatsapp/sync-user', [WhatsappUserController::class, 'sync']);

    Route::get('/products/search-category', [ProductController::class, 'searchByCategory']);
    Route::get('/products/search', [ProductController::class, 'search']);
    Route::apiResource('products', ProductController::class)->only([
        'index', 'show',
    ]);

    Route::get('/user-addresses', [AddressController::class, 'index']);
    Route::post('/user-addresses', [AddressController::class, 'store']);

    Route::get('/shipping/cities', [ShippingController::class, 'cities']);
    Route::post('/shipping/cost', [ShippingController::class, 'cost']);

    Route::post('/orders', [OrderController::class, 'store']);
    Route::get('/orders/{invoice}', [OrderController::class, 'show']);
});
