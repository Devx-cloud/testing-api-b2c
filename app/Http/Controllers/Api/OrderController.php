<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\OrderResource;
use App\Models\Order;
use App\Services\Interfaces\CheckoutServiceInterface;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

class OrderController extends Controller
{
    protected $checkoutService;

    public function __construct(CheckoutServiceInterface $checkoutService)
    {
        $this->checkoutService = $checkoutService;
    }

    /**
     * POST /api/orders
     */
    public function store(Request $request)
    {
        $request->validate([
            'user_id' => 'required|integer',
            'address_id' => 'required|integer',
            'note' => 'nullable|string',
            'stores' => 'required|array|min:1',
            'stores.*.store_id' => 'required|integer',
            'stores.*.courier' => 'required|string',
            'stores.*.service' => 'nullable|string',
            'stores.*.shipping_price' => 'required|numeric|min:0',
            'stores.*.items' => 'required|array|min:1',
            'stores.*.items.*.product_id' => 'required|integer',
            'stores.*.items.*.quantity' => 'required|integer|min:1',
        ]);

        try {
            $result = $this->checkoutService->createOrder(
                (int) $request->input('user_id'),
                (int) $request->input('address_id'),
                $request->input('stores'),
                $request->input('note')
            );

            return response()->json($result, 201);
        } catch (InvalidArgumentException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        } catch (Exception $e) {
            Log::error('Error saat membuat order: ' . $e->getMessage());

            return response()->json(['message' => 'Terjadi kesalahan pada server.'], 500);
        }
    }

    /**
     * GET /api/orders/{invoice}
     */
    public function show(string $invoice)
    {
        $order = Order::with('orderProducts')->where('invoice', $invoice)->first();

        if (!$order) {
            return response()->json(['message' => 'Order tidak ditemukan.'], 404);
        }

        return new OrderResource($order);
    }
}
