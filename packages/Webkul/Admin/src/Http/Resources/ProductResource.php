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
        /**
         * The CustomAttribute trait's attributesToArray() overwrites the real
         * table columns (name, sku, price, ...) with their EAV custom-attribute
         * value. When a product only has the column populated (and no matching
         * row in attribute_values), those keys come back as null. We restore the
         * real column values from the raw model attributes so the lookup can show
         * and select products correctly.
         */
        return array_merge($this->resource->attributesToArray(), [
            'id'          => $this->id,
            'sku'         => $this->resource->getRawOriginal('sku'),
            'name'        => $this->resource->getRawOriginal('name'),
            'description' => $this->resource->getRawOriginal('description'),
            'quantity'    => $this->resource->getRawOriginal('quantity'),
            'price'       => $this->resource->getRawOriginal('price'),
            'created_at'  => $this->created_at,
            'updated_at'  => $this->updated_at,
        ]);
    }
}
