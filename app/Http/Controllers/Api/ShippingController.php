<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Interfaces\CheckoutServiceInterface;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ShippingController extends Controller
{
    protected $checkoutService;

    public function __construct(CheckoutServiceInterface $checkoutService)
    {
        $this->checkoutService = $checkoutService;
    }

    /**
     * GET /api/shipping/cities?search=
     */
    public function cities(Request $request)
    {
        $request->validate([
            'search' => 'required|string|min:2',
        ]);

        try {
            $result = $this->checkoutService->searchCity($request->input('search'));

            if ($result['error']) {
                return response()->json(['message' => $result['message']], 502);
            }

            return response()->json(['cities' => $result['cities']]);
        } catch (Exception $e) {
            Log::error('Error saat mencari kota: ' . $e->getMessage());

            return response()->json(['message' => 'Terjadi kesalahan pada server.'], 500);
        }
    }

    /**
     * POST /api/shipping/cost
     */
    public function cost(Request $request)
    {
        $request->validate([
            'store_id' => 'required|integer',
            'address_id' => 'required|integer',
            'courier' => 'required|string',
            'items' => 'required|array|min:1',
            'items.*.product_id' => 'required|integer',
            'items.*.quantity' => 'required|integer|min:1',
        ]);

        try {
            $result = $this->checkoutService->getShippingOptions(
                (int) $request->input('store_id'),
                (int) $request->input('address_id'),
                $request->input('items'),
                $request->input('courier')
            );

            if ($result['error']) {
                return response()->json(['message' => $result['message']], 422);
            }

            return response()->json(['options' => $result['options']]);
        } catch (Exception $e) {
            Log::error('Error saat menghitung ongkir: ' . $e->getMessage());

            return response()->json(['message' => 'Terjadi kesalahan pada server.'], 500);
        }
    }
}
