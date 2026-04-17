@php
    $options = $attribute->lookup_type
        ? app('Webkul\Attribute\Repositories\AttributeRepository')->getLookUpOptions($attribute->lookup_type)
        : $attribute->options()->orderBy('sort_order')->get();

    $selectedOption = old($attribute->code) ?: $value;
@endphp

<v-field
    name="{{ $attribute->code }}[]"
    rules="{{ $validations }}"
    label="{{ $attribute->name }}"
    v-slot="{ field, errors, setValue }"
>
    <v-multiselect
        :items="{{ json_encode($options) }}"
        :model-value="field.value || {{ json_encode(is_array($selectedOption) ? $selectedOption : explode(',', $selectedOption)) }}"
        @update:model-value="setValue($event)"
        placeholder="{{ $attribute->name }}"
        name="{{ $attribute->code }}[]"
    ></v-multiselect>

    <p
        class="mt-1 text-xs text-red-600"
        v-if="errors.length"
    >
        @{{ errors[0] }}
    </p>
</v-field>