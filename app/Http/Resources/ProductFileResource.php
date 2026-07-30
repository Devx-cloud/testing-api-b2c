<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ProductFileResource extends JsonResource
{
    public function toArray($request)
    {
        return [
            'url' => 'http://tokodaring.balimall.id/sftp/file/' . $this->file_path,
        ];
    }
}
