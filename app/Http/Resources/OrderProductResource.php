<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class OrderProductResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'product_id' => $this->product_id,
            'product_name' => $this->original_name,
            'quantity' => (int) $this->quantity,
            'price' => $this->price,
            'total_price' => $this->total_price,
        ];
    }
}
