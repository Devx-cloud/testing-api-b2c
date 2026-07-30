<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserAddressResource;
use App\Services\Interfaces\CheckoutServiceInterface;
use Exception;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AddressController extends Controller
{
    protected $checkoutService;

    public function __construct(CheckoutServiceInterface $checkoutService)
    {
        $this->checkoutService = $checkoutService;
    }

    /**
     * GET /api/user-addresses?user_id=
     */
    public function index(Request $request)
    {
        $request->validate([
            'user_id' => 'required|integer',
        ]);

        try {
            $addresses = $this->checkoutService->getAddressesByUserId((int) $request->input('user_id'));

            return UserAddressResource::collection($addresses);
        } catch (Exception $e) {
            Log::error('Error saat mengambil user-addresses: ' . $e->getMessage());

            return response()->json([
                'message' => 'Terjadi kesalahan pada server.',
            ], 500);
        }
    }

    /**
     * POST /api/user-addresses
     */
    public function store(Request $request)
    {
        $request->validate([
            'user_id' => 'required|integer',
            'title' => 'nullable|string|max:255',
            'address' => 'required|string|max:255',
            'city' => 'required|string|max:100',
            'city_id' => 'required|integer',
            'district' => 'nullable|string|max:100',
            'district_id' => 'nullable|integer',
            'province' => 'required|string|max:100',
            'province_id' => 'required|integer',
            'country' => 'nullable|string|max:100',
            'country_id' => 'nullable|integer',
            'post_code' => 'nullable|string|max:10',
        ]);

        try {
            $address = $this->checkoutService->createAddress(
                (int) $request->input('user_id'),
                $request->only([
                    'title', 'address', 'city', 'city_id', 'district', 'district_id',
                    'province', 'province_id', 'country', 'country_id', 'post_code',
                ])
            );

            return response()->json([
                'address' => new UserAddressResource($address),
            ], 201);
        } catch (Exception $e) {
            Log::error('Error saat menyimpan user-address: ' . $e->getMessage());

            return response()->json([
                'message' => 'Terjadi kesalahan pada server.',
            ], 500);
        }
    }
}
