<?php

namespace Webkul\Admin\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class ProductResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request
     * @return array
     */
    public function toArray($request)
    {
        return array_merge($this->resource->attributesToArray(), [
            'id'              => $this->id,
            'created_at'      => $this->created_at,
            'updated_at'      => $this->updated_at,
        ]);
    }
}
