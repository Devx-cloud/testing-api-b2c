<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'store_id' => $this->store_id,
            'alias' => $this->name,
            'product_name' => $this->name,
            'type' => null,
            'unit' => $this->unit,
            'price' => $this->price,
            'price_range' => null,
            'category' => $this->category_data,
            'img_url' => $this->whenLoaded('productfiles') && $this->productfiles->isNotEmpty()
                ? 'tokodaring.balimall.id/sftp/file/' . $this->productfiles->first()->file_path
                : null,
            'stock' => $this->quantity,
            'urutan' => null,
        ];
    }
}
