<x-admin::layouts>
    <x-slot:title>
        {{ isset($doctor) ? 'Editar Doctor' : 'Registrar Doctor' }}
    </x-slot>

    <x-admin::form
        :action="isset($doctor) ? route('admin.doctor.update', $doctor->id) : route('admin.doctor.store')"
        method="{{ isset($doctor) ? 'PUT' : 'POST' }}"
        encType="multipart/form-data"
    >
        <div class="flex flex-col gap-4">
            <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
                <div class="flex flex-col gap-2">
                    <div class="text-xl font-bold dark:text-white">
                        {{ isset($doctor) ? 'Editar Doctor' : 'Registrar Doctor' }}
                    </div>
                </div>

                <div class="flex items-center gap-x-2.5">
                    <button type="submit" class="primary-button">
                        Guardar
                    </button>
                </div>
            </div>

            <div class="box-shadow rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                <div class="grid grid-cols-2 gap-4 max-lg:grid-cols-1">
                    <x-admin::form.control-group>
                        <x-admin::form.control-group.label>
                            Correo electrónico
                        </x-admin::form.control-group.label>

                        <x-admin::form.control-group.control
                            type="email"
                            id="email"
                            name="email"
                            :label="trans('Correo electrónico')"
                            :placeholder="trans('Ej.: doctor@odontoking.com')"
                             :value="old('email', isset($doctor) ? $doctor->email : '')"
                            v-debounce="500"
                        />

                        <x-admin::form.control-group.error control-name="email" />
                    </x-admin::form.control-group>

                    <x-admin::form.control-group>
                        <x-admin::form.control-group.label>
                            ID de ShareMeData (Hexadecimal)
                        </x-admin::form.control-group.label>

                        <x-admin::form.control-group.control
                            type="text"
                            id="unique_id"
                            name="unique_id"
                            :label="trans('ID de ShareMeData')"
                            :placeholder="trans('Ej.: 69bd9a9b7549b10008e0acfa')"
                            :value="old('unique_id', isset($doctor) ? $doctor->unique_id : '')"
                            rules="max:100"
                            v-debounce="500"
                            disabled="true"
                        />

                        <x-admin::form.control-group.error control-name="unique_id" />
                    </x-admin::form.control-group>

                    <x-admin::form.control-group>
                        <x-admin::form.control-group.label class="required">
                            Nombres completos
                        </x-admin::form.control-group.label>

                        <x-admin::form.control-group.control
                            type="text"
                            id="name"
                            name="name"
                            rules="required|min:2|max:150"
                            :label="trans('Nombres completos')"
                            :placeholder="trans('Nombres completos')"
                             :value="old('name', isset($doctor) ? $doctor->name : '')"
                            v-debounce="500"
                        />

                        <x-admin::form.control-group.error control-name="name" />
                    </x-admin::form.control-group>

                    <x-admin::form.control-group>
                        <x-admin::form.control-group.label>
                            Especialidades (Mantén presionado Ctrl/Cmd para seleccionar varias)
                        </x-admin::form.control-group.label>

                        @php
                            $selectedSpecialties = old('specialties', isset($doctor) ? $doctor->specialties->pluck('id')->toArray() : []);
                        @endphp

                        <select
                            name="specialties[]"
                            id="specialties"
                            class="flex min-h-[100px] w-full rounded-md border px-3 py-2 text-sm text-gray-600 transition-all hover:border-gray-400 focus:border-gray-400 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300 dark:hover:border-gray-400 dark:focus:border-gray-400"
                            multiple
                        >
                            @foreach ($specialties as $sp)
                                <option
                                    value="{{ $sp->id }}"
                                    {{ in_array($sp->id, $selectedSpecialties) ? 'selected' : '' }}
                                >
                                    {{ $sp->name }}
                                </option>
                            @endforeach
                        </select>

                        <x-admin::form.control-group.error control-name="specialties" />
                    </x-admin::form.control-group>

                    <x-admin::form.control-group>
                        <x-admin::form.control-group.label>
                            Estado
                        </x-admin::form.control-group.label>

                        <x-admin::form.control-group.control
                            type="switch"
                            id="is_active"
                            name="is_active"
                            :checked="old('is_active', isset($doctor) ? ($doctor->is_active ? '1' : '') : '1')"
                        />
                    </x-admin::form.control-group>
                </div>
            </div>

            <!-- Custom Attributes -->
            @if (count($attributes = app('Webkul\Attribute\Repositories\AttributeRepository')->findWhere(['entity_type' => 'doctors', 'quick_add' => 1])))
                <div class="box-shadow rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                    <p class="mb-4 text-base font-semibold text-gray-800 dark:text-white">
                        Información adicional
                    </p>

                    <x-admin::attributes
                        :custom-attributes="$attributes"
                        :entity="isset($doctor) ? $doctor : null"
                    />
                </div>
            @endif
        </div>
    </x-admin::form>

</x-admin::layouts>
