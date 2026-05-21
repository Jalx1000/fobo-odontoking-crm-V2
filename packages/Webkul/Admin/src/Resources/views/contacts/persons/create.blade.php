<x-admin::layouts>
    <x-slot:title>
        @lang('admin::app.contacts.persons.create.title')
    </x-slot>

    {!! view_render_event('admin.persons.create.form.before') !!}

    <x-admin::form
        :action="route('admin.contacts.persons.store')"
        enctype="multipart/form-data"
    >
        <div class="flex flex-col gap-4" id="person-create-form">

            <!-- Header -->
            <div class="flex items-center justify-between rounded-lg border border-gray-200 bg-white px-4 py-2 text-sm dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
                <div class="flex flex-col gap-2">
                    {!! view_render_event('admin.persons.create.breadcrumbs.before') !!}
                    <x-admin::breadcrumbs name="contacts.persons.create" />
                    {!! view_render_event('admin.persons.create.breadcrumbs.after') !!}
                    <div class="text-xl font-bold dark:text-white">
                        @lang('admin::app.contacts.persons.create.title')
                    </div>
                </div>

                <div class="flex items-center gap-x-3">
                    {{-- Chip de estado SMD --}}
                    <div id="smd-status-chip" class="hidden items-center gap-1.5 rounded-full border px-3 py-1 text-xs font-medium transition-all duration-200">
                        <span id="smd-status-dot" class="h-2 w-2 rounded-full"></span>
                        <span id="smd-status-text"></span>
                    </div>

                    {!! view_render_event('admin.persons.create.create_button.before') !!}
                    <button type="submit" class="primary-button">
                        @lang('admin::app.contacts.persons.create.save-btn')
                    </button>
                    {!! view_render_event('admin.persons.create.create_button.after') !!}
                </div>
            </div>

            <!-- Form fields -->
            <div class="flex flex-col gap-4">
                {!! view_render_event('admin.persons.create.form_controls.before') !!}

                <!-- Sección 1: Datos del Paciente -->
                <div class="box-shadow rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                    <div class="mb-4 flex items-center justify-between">
                        <h4 class="text-base font-bold dark:text-white">Datos del Paciente</h4>
                        {{-- Botón SMD del partial --}}
                        @include('admin::contacts.persons.partials.smd-checker', [
                            'searchUrl' => route('admin.contacts.persons.search_smd'),
                            'mode'      => 'create',
                        ])
                    </div>

                    <x-admin::attributes
                        :custom-attributes="app('Webkul\Attribute\Repositories\AttributeRepository')->findWhere([
                            ['code', 'IN', ['name', 'job_title', 'emails', 'contact_numbers']],
                            'entity_type' => 'persons',
                        ])"
                        :custom-validations="[
                            'name'      => ['min:2', 'max:100'],
                            'job_title' => ['max:100'],
                        ]"
                    />
                </div>

                <!-- Banner resultado SMD -->
                <div id="smd-result-banner" class="rounded-lg border px-4 py-3 text-sm transition-all duration-300">
                    <div class="flex items-start justify-between gap-4">
                        <div class="flex items-start gap-3">
                            <span id="smd-banner-icon" class="mt-0.5 shrink-0"></span>
                            <div>
                                <p id="smd-banner-title" class="font-semibold"></p>
                                <p id="smd-banner-detail" class="mt-0.5 text-xs opacity-80"></p>
                            </div>
                        </div>
                        <div class="flex shrink-0 items-center gap-2">
                            <button
                                id="smd-autofill-btn"
                                type="button"
                                class="hidden rounded-md bg-white px-3 py-1.5 text-xs font-semibold shadow-sm ring-1 ring-inset transition hover:bg-gray-50 focus:outline-none"
                                onclick="smdAutofill()"
                            >Autocompletar</button>
                            <button type="button" class="text-xs opacity-60 hover:opacity-100 focus:outline-none" onclick="smdDismiss()" aria-label="Cerrar">
                                <svg class="h-4 w-4" viewBox="0 0 20 20" fill="currentColor"><path d="M6.28 5.22a.75.75 0 00-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 101.06 1.06L10 11.06l3.72 3.72a.75.75 0 101.06-1.06L11.06 10l3.72-3.72a.75.75 0 00-1.06-1.06L10 8.94 6.28 5.22z"/></svg>
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Sección 2: Datos del Seguro -->
                <div class="box-shadow rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                    <div class="mb-4 flex items-center justify-between">
                        <h4 class="text-base font-bold dark:text-white">Datos del Seguro</h4>

                        {{-- Botón verificar seguro (modo quick: sin person_id) --}}
                        <v-insurance-quick
                            verify-url="{{ route('admin.contacts.persons.verify_insurance_quick') }}"
                        />
                    </div>

                    <x-admin::attributes
                        :custom-attributes="app('Webkul\Attribute\Repositories\AttributeRepository')->findWhere([
                            ['code', 'IN', ['ci_paciente', 'seguro_paciente', 'estado_seguro_paciente']],
                            'entity_type' => 'persons',
                        ])"
                    />
                </div>

                <!-- Sección 3: Equipo de Atención -->
                <div class="box-shadow rounded-lg border border-gray-200 bg-white p-4 dark:border-gray-800 dark:bg-gray-900">
                    <h4 class="mb-4 text-base font-bold dark:text-white">Equipo de Atención</h4>

                    <x-admin::attributes
                        :custom-attributes="app('Webkul\Attribute\Repositories\AttributeRepository')->findWhere([
                            ['code', 'IN', ['person_doctor', 'user_id']],
                            'entity_type' => 'persons',
                        ])"
                    />
                </div>

                {!! view_render_event('admin.persons.create.form_controls.after') !!}
            </div>
        </div>
    </x-admin::form>

    {!! view_render_event('admin.persons.create.form.after') !!}

    @pushOnce('scripts')
        <script type="text/x-template" id="v-organization-template">
            <div>
                <x-admin::attributes
                    :custom-attributes="app('Webkul\Attribute\Repositories\AttributeRepository')->findWhere([
                        ['code', 'IN', ['organization_id']],
                        'entity_type' => 'persons',
                    ])"
                />
                <template v-if="organizationName">
                    <x-admin::form.control-group.control
                        type="hidden"
                        name="organization_name"
                        v-model="organizationName"
                    />
                </template>
            </div>
        </script>

        <script type="module">
            app.component('v-organization', {
                template: '#v-organization-template',
                data() { return { organizationName: null }; },
                methods: {
                    handleLookupAdded(event) { this.organizationName = event?.name || null; },
                },
            });

            // Componente verificación de seguro rápida (sin person_id)
            app.component('v-insurance-quick', {
                props: ['verifyUrl'],
                data() {
                    return { loading: false };
                },
                template: `
                    <button
                        type="button"
                        @click="verify"
                        :disabled="loading"
                        class="inline-flex cursor-pointer items-center gap-1.5 rounded-md border border-orange-300 bg-orange-50 px-3 py-1.5 text-xs font-medium text-orange-700 transition-colors duration-150 hover:bg-orange-100 focus:outline-none focus:ring-2 focus:ring-orange-300 disabled:opacity-50 dark:border-orange-700 dark:bg-orange-900/30 dark:text-orange-300"
                    >
                        <svg class="h-3.5 w-3.5" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M2.166 4.999A11.954 11.954 0 0010 1.944 11.954 11.954 0 0017.834 5c.11.65.166 1.32.166 2.001 0 5.225-3.34 9.67-8 11.317C5.34 16.67 2 12.225 2 7c0-.682.057-1.35.166-2.001zm11.541 3.708a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z"/>
                        </svg>
                        <span v-if="loading">Verificando...</span>
                        <span v-else>Verificar Seguro</span>
                    </button>
                `,
                methods: {
                    async verify() {
                        const ci     = document.querySelector('[name="ci_paciente"]')?.value?.trim();
                        const seguro = document.querySelector('[name="seguro_paciente"]')?.value;

                        if (! ci || ! seguro) {
                            this.$emitter.emit('add-flash', {
                                type:    'warning',
                                message: 'Completa el Carnet de Identidad y el Seguro antes de verificar.',
                            });
                            return;
                        }

                        this.loading = true;
                        try {
                            const { data } = await this.$axios.post(this.verifyUrl, {
                                ci_paciente:     ci,
                                seguro_paciente: seguro,
                            });

                            const type = data.status === 'VIGENTE'    ? 'success'
                                       : data.status === 'EN_MORA'    ? 'warning'
                                       : data.status === 'SIN_SEGURO' ? 'warning'
                                       : 'info';

                            this.$emitter.emit('add-flash', { type, message: data.message });
                        } catch (err) {
                            const msg = err.response?.data?.message || 'Error al verificar el seguro.';
                            this.$emitter.emit('add-flash', { type: 'error', message: msg });
                        } finally {
                            this.loading = false;
                        }
                    },
                },
            });
        </script>
    @endPushOnce
</x-admin::layouts>
