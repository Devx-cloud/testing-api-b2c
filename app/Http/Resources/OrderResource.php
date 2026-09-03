<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray($request)
    {
        $taxTotal = (float) $this->orderProducts->sum('tax_nominal');

        return [
            'id' => $this->id,
            'invoice' => $this->invoice,
            'shared_id' => $this->shared_id,
            // Nomor transaksi gabungan; ini yang dipakai halaman riwayat
            // transaksi nusantaramall-b2c untuk membuka "Detail Transaksi".
            'shared_invoice' => $this->shared_invoice,
            'store_id' => $this->store_id,
            'status' => $this->status,
            'total' => $this->total,
            'shipping_courier' => $this->shipping_courier,
            'shipping_service' => $this->shipping_service,
            'shipping_price' => $this->shipping_price,
            'tax_total' => $taxTotal,
            // Nilai yang benar-benar ditagih ke pembeli. Rumusnya sama dengan
            // grand_total di index_v2.html.twig: total + ongkir + PPN per item.
            'grand_total' => (float) $this->total + (float) $this->shipping_price + $taxTotal,
            'name' => $this->name,
            'phone' => $this->phone,
            'address' => $this->address,
            'city' => $this->city,
            'note' => $this->note,
            'items' => OrderProductResource::collection($this->whenLoaded('orderProducts')),
            'created_at' => $this->created_at,
        ];
    }
}
