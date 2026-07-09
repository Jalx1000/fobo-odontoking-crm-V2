<?php

namespace Webkul\Admin\Helpers;

use Illuminate\Support\Carbon;
use Webkul\Attribute\Repositories\AttributeRepository;

/**
 * Resolves a raw stored custom-attribute value into a human-readable label for
 * exports (CSV/XLS/XLSX).
 *
 * The resolution mirrors how the attribute "view" Blade components render each
 * type, so an exported cell matches what the user sees in the UI. In
 * particular, `select`/`multiselect`/`checkbox` attributes may be backed either
 * by their own `attribute_options` or by a `lookup_type` (e.g. the city
 * attribute is a select whose options are pipelines); resolving both cases is
 * what turns a stored id into its label instead of leaking the raw id.
 */
class CustomAttributeValueResolver
{
    /**
     * Memoized lookup labels, keyed by "{lookup_type}:{id}".
     *
     * Lookup values (cities, pipelines, sales owners) repeat heavily across
     * rows, so caching them collapses what would be thousands of per-row
     * queries during an export into a handful.
     */
    protected array $lookupCache = [];

    /**
     * Create a new resolver instance.
     */
    public function __construct(protected AttributeRepository $attributeRepository) {}

    /**
     * Resolve a stored value into its human-readable label.
     */
    public function resolve($attribute, $value): string
    {
        if (is_null($value) || $value === '') {
            return '';
        }

        return match ($attribute->type) {
            'select'                  => $this->resolveSelect($attribute, $value),
            'multiselect', 'checkbox' => $this->resolveMultiple($attribute, $value),
            'lookup'                  => $this->resolveLookup($attribute, $value),
            'boolean'                 => filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 'Sí' : 'No',
            'date'                    => $this->formatDate($value, 'Y-m-d'),
            'datetime'                => $this->formatDate($value, 'Y-m-d H:i:s'),
            'price'                   => core()->formatBasePrice($value),
            default                   => (string) $value,
        };
    }

    /**
     * Resolve a single-select value. A select can be backed by a lookup entity
     * (its options are another table, e.g. pipelines) or by its own options.
     */
    protected function resolveSelect($attribute, $value): string
    {
        if ($attribute->lookup_type) {
            return $this->resolveLookup($attribute, $value);
        }

        $option = $attribute->options->firstWhere('id', $value);

        return $option->name ?? (string) $value;
    }

    /**
     * Resolve a multi-value attribute (multiselect/checkbox), stored as a
     * comma-separated list of option ids.
     */
    protected function resolveMultiple($attribute, $value): string
    {
        $ids = collect(explode(',', (string) $value))
            ->map(fn ($id) => trim($id))
            ->filter(fn ($id) => $id !== '');

        if ($ids->isEmpty()) {
            return '';
        }

        if ($attribute->lookup_type) {
            $labels = collect($this->attributeRepository->getLookUpEntity($attribute->lookup_type, $ids->all()))
                ->pluck('name', 'id');
        } else {
            $labels = $attribute->options->pluck('name', 'id');
        }

        return $ids->map(fn ($id) => $labels[$id] ?? $id)->implode(', ');
    }

    /**
     * Resolve a lookup value (foreign entity) into its label, memoized.
     */
    protected function resolveLookup($attribute, $value): string
    {
        $key = $attribute->lookup_type.':'.$value;

        if (! array_key_exists($key, $this->lookupCache)) {
            $entity = $this->attributeRepository->getLookUpEntity($attribute->lookup_type, $value);

            $this->lookupCache[$key] = $entity?->name ?? (string) $value;
        }

        return $this->lookupCache[$key];
    }

    /**
     * Format a date/datetime value, falling back to the raw value if unparsable.
     */
    protected function formatDate($value, string $format): string
    {
        try {
            return Carbon::parse($value)->format($format);
        } catch (\Exception $e) {
            return (string) $value;
        }
    }
}
