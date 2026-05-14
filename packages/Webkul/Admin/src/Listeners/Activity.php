<?php

namespace Webkul\Admin\Listeners;

use Webkul\Activity\Contracts\Activity as ActivityContract;
use Webkul\Contact\Repositories\PersonRepository;
use Webkul\Lead\Repositories\LeadRepository;
use Webkul\Product\Repositories\ProductRepository;
use Webkul\Warehouse\Repositories\WarehouseRepository;

class Activity
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(
        protected LeadRepository $leadRepository,
        protected PersonRepository $personRepository,
        protected ProductRepository $productRepository,
        protected WarehouseRepository $warehouseRepository
    ) {}

    /**
     * Link activity to lead or person.
     * Reads IDs from the HTTP request first, then falls back to the activity's
     * 'additional' JSON field so this listener works outside HTTP context
     * (e.g. webhooks, queue jobs, artisan commands).
     */
    public function afterUpdateOrCreate(ActivityContract $activity): void
    {
        $additional = is_string($activity->additional)
            ? json_decode($activity->additional, true)
            : (array) ($activity->additional ?? []);

        $leadId = request()->input('lead_id') ?? $additional['lead_id'] ?? null;
        $personId = request()->input('person_id') ?? $additional['person_id'] ?? null;
        $warehouseId = request()->input('warehouse_id') ?? $additional['warehouse_id'] ?? null;
        $productId = request()->input('product_id') ?? $additional['product_id'] ?? null;

        if ($leadId) {
            $lead = $this->leadRepository->find($leadId);

            if ($lead && ! $lead->activities->contains($activity->id)) {
                $lead->activities()->attach($activity->id);
            }
        } elseif ($personId) {
            $person = $this->personRepository->find($personId);

            if ($person && ! $person->activities->contains($activity->id)) {
                $person->activities()->attach($activity->id);
            }
        } elseif ($warehouseId) {
            $warehouse = $this->warehouseRepository->find($warehouseId);

            if ($warehouse && ! $warehouse->activities->contains($activity->id)) {
                $warehouse->activities()->attach($activity->id);
            }
        } elseif ($productId) {
            $product = $this->productRepository->find($productId);

            if ($product && ! $product->activities->contains($activity->id)) {
                $product->activities()->attach($activity->id);
            }
        }
    }
}
