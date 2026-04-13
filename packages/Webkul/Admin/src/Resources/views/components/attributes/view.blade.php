@props([
    'customAttributes' => [],
    'entity'           => null,
    'allowEdit'        => false,
    'url'              => null,
])

<div class="flex flex-col gap-1">
    @foreach ($customAttributes as $attribute)
        @if (view()->exists($typeView = 'admin::components.attributes.view.' . $attribute->type))
            <div class="grid grid-cols-[1fr_2fr] items-center gap-1">
                <div class="label dark:text-white">{{ $attribute->name }}</div>

                <div class="font-medium dark:text-white">
                    @php
                        $value = isset($entity) ? $entity[$attribute->code] : null;

                        if (in_array($attribute->code, ['lead_value', 'price']) && is_numeric($value)) {
                            $value = number_format((float) $value, 2, '.', '');
                        }
                    @endphp

                    @include ($typeView, [
                        'attribute' => $attribute,
                        'value'     => $value,
                        'allowEdit' => $allowEdit,
                        'url'       => $url,
                    ])
                </div>
            </div>
        @endif
    @endforeach
</div>