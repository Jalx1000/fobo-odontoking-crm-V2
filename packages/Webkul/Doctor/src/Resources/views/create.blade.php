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
                            Nº (Número de identificación)
                        </x-admin::form.control-group.label>

                        <x-admin::form.control-group.control
                            type="text"
                            id="number"
                            name="number"
                            :label="trans('Nº')"
                            :placeholder="trans('Nº')"
                            :value="old('number', isset($doctor) ? $doctor->number : '')"
                            rules="max:50"
                            v-debounce="500"
                        />

                        <x-admin::form.control-group.error control-name="number" />
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
                            Título profesional
                        </x-admin::form.control-group.label>

                        <x-admin::form.control-group.control
                            type="text"
                            id="title"
                            name="title"
                            rules="max:100"
                            :label="trans('Título profesional')"
                            :placeholder="trans('Ej.: Doctor en Odontología')"
                             :value="old('title', isset($doctor) ? $doctor->title : '')"
                            v-debounce="500"
                        />

                        <x-admin::form.control-group.error control-name="title" />
                    </x-admin::form.control-group>

                    <x-admin::form.control-group>
                        <x-admin::form.control-group.label>
                            Especialidades
                        </x-admin::form.control-group.label>

                        <x-admin::form.control-group.control
                            type="select"
                            id="specialties"
                            name="specialties[]"
                            multiple
                            :label="trans('Especialidades')"
                            :placeholder="trans('Selecciona especialidades')"
                        >
                            @php
                                $selectedSpecialties = old('specialties', isset($doctor) ? $doctor->specialties->pluck('id')->toArray() : []);
                            @endphp

                            @foreach ($specialties as $sp)
                                <option
                                    value="{{ $sp->id }}"
                                    {{ in_array($sp->id, $selectedSpecialties) ? 'selected' : '' }}
                                >
                                    {{ $sp->name }}
                                </option>
                            @endforeach
                        </x-admin::form.control-group.control>

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
        </div>
    </x-admin::form>

</x-admin::layouts>
