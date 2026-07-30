<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class UserAddressResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'address' => $this->address,
            'city' => $this->city,
            'city_id' => $this->city_id,
            'district' => $this->district,
            'district_id' => $this->district_id,
            'province' => $this->province,
            'province_id' => $this->province_id,
            'country' => $this->country,
            'post_code' => $this->post_code,
        ];
    }
}
