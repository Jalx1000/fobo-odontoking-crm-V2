@props([
    'attribute'   => '',
    'value'       => '',
    'validations' => '',
])

@switch($attribute->type)
    @case('text')
        @if ($attribute->code == 'combos_productos_cantidad')
            <v-field
                name="{{ $attribute->code }}"
                :rules="'{{ $validations }}'"
                label="{{ $attribute->name }}"
                v-slot="{ field, errors, setValue }"
            >
                <v-combo-manager
                    :model-value="field.value !== undefined ? field.value : {{ json_encode(old($attribute->code) ?: ($value ?: '[]')) }}"
                    @update:model-value="setValue($event)"
                    name="{{ $attribute->code }}"
                ></v-combo-manager>

                <p class="mt-1 text-xs text-red-600" v-if="errors.length">
                    @{{ errors[0] }}
                </p>
            </v-field>
        @else
            <x-admin::attributes.edit.text
                :attribute="$attribute"
                :value="$value"
                :validations="$validations"
            />
        @endif

        @break

    @case('email')
        <x-admin::attributes.edit.email
            :attribute="$attribute"
            :value="$value"
            :validations="$validations"
        />

        @break

    @case('phone')
        <x-admin::attributes.edit.phone
            :attribute="$attribute"
            :value="$value"
            :validations="$validations"
        />

        @break

    @case('lookup')
        <x-admin::attributes.edit.lookup
            :attribute="$attribute"
            :value="$value"
            :validations="$validations"
            can-add-new="true"
        />

        @break

    @case('select')
        <x-admin::attributes.edit.select
            :attribute="$attribute"
            :value="$value"
            :validations="$validations"
        />

        @break
    
    @case('multiselect')
        @if ($attribute->code == 'combos_productos')
            {{-- Oculto para centralizar en combos_productos_cantidad --}}
        @else
            <x-admin::attributes.edit.multiselect
                :attribute="$attribute"
                :value="$value"
                :validations="$validations"
            />
        @endif
        
        @break

    @case('price')
        <x-admin::attributes.edit.price
            :attribute="$attribute"
            :value="$value"
            :validations="$validations"
        />

        @break

    @case('image')
        <x-admin::attributes.edit.image
            :attribute="$attribute"
            :value="$value"
            :validations="$validations"
        />

        @break

    @case('file')
        <x-admin::attributes.edit.file
            :attribute="$attribute"
            :value="$value"
            :validations="$validations"
        />

        @break

    @case('textarea')
        <x-admin::attributes.edit.textarea
            :attribute="$attribute"
            :value="$value"
            :validations="$validations"
        />

        @break

    @case('address')
        <x-admin::attributes.edit.address
            :attribute="$attribute"
            :value="$value"
            :validations="$validations"
        />

        @break

    @case('date')
        <x-admin::attributes.edit.date
            :attribute="$attribute"
            :value="$value"
            :validations="$validations"
        />

        @break

    @case('datetime')
        <x-admin::attributes.edit.datetime
            :attribute="$attribute"
            :value="$value"
            :validations="$validations"
        />

        @break

    @case('boolean')
        <x-admin::attributes.edit.boolean
            :attribute="$attribute"
            :value="$value"
            :validations="$validations"
        />

        @break

    @case('checkbox')
        <x-admin::attributes.edit.checkbox
            :attribute="$attribute"
            :value="$value"
            :validations="$validations"
        />

        @break
@endswitch