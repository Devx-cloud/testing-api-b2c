<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class OrderResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'invoice' => $this->invoice,
            'shared_id' => $this->shared_id,
            'store_id' => $this->store_id,
            'status' => $this->status,
            'total' => $this->total,
            'shipping_courier' => $this->shipping_courier,
            'shipping_service' => $this->shipping_service,
            'shipping_price' => $this->shipping_price,
            'grand_total' => (float) $this->total + (float) $this->shipping_price,
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
