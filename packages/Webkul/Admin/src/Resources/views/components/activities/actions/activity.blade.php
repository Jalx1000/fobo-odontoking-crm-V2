@props([
    'entity'            => null,
    'entityControlName' => null,
])

<!-- Activity Button -->
<div>
    {!! view_render_event('admin.components.activities.actions.activity.create_btn.before') !!}

    <button
        class="flex h-[74px] w-[84px] flex-col items-center justify-center gap-1 rounded-lg border border-transparent bg-blue-200 font-medium text-blue-800 transition-all hover:border-blue-400"
        @click="$refs.actionComponent.openModal('mail')"
    >
        <span class="icon-activity text-2xl dark:!text-blue-800"></span>

        @lang('admin::app.components.activities.actions.activity.btn')
    </button>

    {!! view_render_event('admin.components.activities.actions.activity.create_btn.after') !!}

    {!! view_render_event('admin.components.activities.actions.activity.before') !!}

    <!-- Note Action Vue Component -->
    <v-activity
        ref="actionComponent"
        :entity="{{ json_encode($entity) }}"
        entity-control-name="{{ $entityControlName }}"
    ></v-activity>

    {!! view_render_event('admin.components.activities.actions.activity.after') !!}
</div>


@pushOnce('scripts')
    <script type="text/x-template" id="v-activity-template">
        <Teleport to="body">
            {!! view_render_event('admin.components.activities.actions.activity.form_controls.before') !!}

            <x-admin::form
                v-slot="{ meta, errors, handleSubmit }"
                as="div"
                ref="modalForm"
            >
                <form @submit="handleSubmit($event, save)">
                    {!! view_render_event('admin.components.activities.actions.activity.form_controls.modal.before') !!}

                    <x-admin::modal
                        ref="activityModal"
                        position="bottom-right"
                    >
                        <x-slot:header>
                            {!! view_render_event('admin.components.activities.actions.activity.form_controls.modal.header.dropdown.before') !!}

                            <x-admin::dropdown>
                                <x-slot:toggle>
                                    <h3 class="flex cursor-pointer items-center gap-1 text-base font-semibold dark:text-white">
                                        @lang('admin::app.components.activities.actions.activity.title') - @{{ selectedType.label }}

                                        <span class="icon-down-arrow text-2xl"></span>
                                    </h3>
                                </x-slot>

                                <x-slot:menu>
                                    {!! view_render_event('admin.components.activities.actions.activity.form_controls.modal.header.dropdown.menu_item.before') !!}

                                    <x-admin::dropdown.menu.item
                                        ::class="{ 'bg-gray-100 dark:bg-gray-950': selectedType.value === type.value }"
                                        v-for="type in availableTypes"
                                        @click="selectedType = type"
                                    >
                                        @{{ type.label }}
                                    </x-admin::dropdown.menu.item>

                                    {!! view_render_event('admin.components.activities.actions.activity.form_controls.modal.header.dropdown.menu_item.after') !!}
                                </x-slot>
                            </x-admin::dropdown>

                            {!! view_render_event('admin.components.activities.actions.activity.form_controls.modal.header.dropdown.after') !!}
                        </x-slot>

                        <x-slot:content>
                            {!! view_render_event('admin.components.activities.actions.activity.form_controls.modal.content.controls.before') !!}

                            <!-- Activity Type -->
                            <x-admin::form.control-group.control
                                type="hidden"
                                name="type"
                                v-model="selectedType.value"
                            />

                            <!-- Id -->
                            <x-admin::form.control-group.control
                                type="hidden"
                                ::name="entityControlName"
                                ::value="entity.id"
                            />

                            <!-- Title -->
                            <x-admin::form.control-group>
                                <x-admin::form.control-group.label class="required">
                                    <template v-if="selectedType.value === 'meeting'">Motivo de la Consulta</template>
                                    <template v-else>@lang('admin::app.components.activities.actions.activity.title-control')</template>
                                </x-admin::form.control-group.label>
                                
                                <x-admin::form.control-group.control
                                    type="text"
                                    name="title"
                                    rules="required|max:80"
                                    ::label="selectedType.value === 'meeting' ? 'Motivo de la Consulta' : translations.title"
                                    placeholder="Motivo de la Consulta"
                                />

                                <x-admin::form.control-group.error control-name="title" />
                            </x-admin::form.control-group>

                            <!-- Product (Servicio) - Only for Meeting -->
                            <x-admin::form.control-group v-if="selectedType.value === 'meeting'">
                                <x-admin::form.control-group.label class="required">
                                    Servicio o Tratamiento
                                </x-admin::form.control-group.label>
                                
                                <div class="relative">
                                    <div v-if="selectedProduct" class="flex items-center justify-between rounded border border-gray-200 px-2.5 py-2 text-sm text-gray-800 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300">
                                        <span>@{{ selectedProduct.name }}</span>
                                        <span class="icon-cross-large cursor-pointer text-xl" @click="removeProduct"></span>
                                    </div>
                                    <div v-else>
                                        <input
                                            type="text"
                                            class="w-full rounded border border-gray-200 px-2.5 py-2 text-sm text-gray-800 transition-all hover:border-gray-400 focus:border-gray-400 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300 dark:hover:border-gray-400 dark:focus:border-gray-400"
                                            placeholder="Buscar servicio..."
                                            v-model="productSearchTerm"
                                        />
                                        <div v-if="searchedProducts.length" class="absolute z-10 w-full rounded bg-white shadow-lg dark:bg-gray-900 border dark:border-gray-800 mt-1">
                                            <ul>
                                                <li 
                                                    v-for="product in searchedProducts" 
                                                    @click="selectProduct(product)"
                                                    class="cursor-pointer px-3 py-2 hover:bg-gray-100 dark:hover:bg-gray-800 text-sm"
                                                >
                                                    @{{ product.name }}
                                                </li>
                                            </ul>
                                        </div>
                                        <div v-if="isSearchingProducts" class="absolute right-3 top-2">
                                            <x-admin::spinner />
                                        </div>
                                    </div>
                                </div>
                                <input type="hidden" name="product_id" :value="selectedProduct ? selectedProduct.id : ''">
                            </x-admin::form.control-group>

                            <!-- Description -->
                            <x-admin::form.control-group>
                                <x-admin::form.control-group.label>
                                    <template v-if="selectedType.value === 'meeting'">Detalles o Observaciones Médicas</template>
                                    <template v-else>@lang('admin::app.components.activities.actions.activity.description')</template>
                                </x-admin::form.control-group.label>
                                
                                <x-admin::form.control-group.control
                                    type="textarea"
                                    name="comment"
                                    rules="max:500"
                                    ::label="selectedType.value === 'meeting' ? 'Detalles o Observaciones Médicas' : translations.description"
                                />

                                <x-admin::form.control-group.error control-name="comment" />
                            </x-admin::form.control-group>

                            <!-- Participants -->
                            <x-admin::form.control-group>
                                <x-admin::form.control-group.label>
                                    @lang('admin::app.components.activities.actions.activity.participants.title')
                                </x-admin::form.control-group.label>

                                <x-admin::activities.actions.activity.participants />
                            </x-admin::form.control-group>

                            <!-- Schedule Date -->
                            <div class="flex gap-4">
                                <!-- Started From -->
                                <x-admin::form.control-group class="w-full">
                                    <x-admin::form.control-group.label class="required">
                                        @lang('admin::app.components.activities.actions.activity.schedule-from')
                                    </x-admin::form.control-group.label>
                                    
                                    <x-admin::form.control-group.control
                                        type="datetime"
                                        name="schedule_from"
                                        rules="required"
                                        :label="trans('admin::app.components.activities.actions.activity.schedule-from')"
                                    />

                                    <x-admin::form.control-group.error control-name="schedule_from" />
                                </x-admin::form.control-group>
                                
                                <!-- Started To -->
                                <x-admin::form.control-group class="w-full">
                                    <x-admin::form.control-group.label class="required">
                                        @lang('admin::app.components.activities.actions.activity.schedule-to')
                                    </x-admin::form.control-group.label>
                                    
                                    <x-admin::form.control-group.control
                                        type="datetime"
                                        name="schedule_to"
                                        rules="required"
                                        :label="trans('admin::app.components.activities.actions.activity.schedule-to')"
                                    />

                                    <x-admin::form.control-group.error control-name="schedule_to" />
                                </x-admin::form.control-group>
                            </div>

                            <!-- Location -->
                            <x-admin::form.control-group class="!mb-0">
                                <x-admin::form.control-group.label>
                                    @lang('admin::app.components.activities.actions.activity.location')
                                </x-admin::form.control-group.label>
                                
                                <x-admin::form.control-group.control
                                    type="text"
                                    name="location"
                                />
                            </x-admin::form.control-group>

                            {!! view_render_event('admin.components.activities.actions.activity.form_controls.modal.content.controls.after') !!}
                        </x-slot>

                        <x-slot:footer>
                            {!! view_render_event('admin.components.activities.actions.activity.form_controls.modal.footer.save_button.before') !!}

                            <x-admin::button
                                class="primary-button"
                                :title="trans('admin::app.components.activities.actions.activity.save-btn')"
                                ::loading="isStoring"
                                ::disabled="isStoring"
                            />

                            {!! view_render_event('admin.components.activities.actions.activity.form_controls.modal.footer.save_button.after') !!}
                        </x-slot>
                    </x-admin::modal>

                    {!! view_render_event('admin.components.activities.actions.activity.form_controls.modal.after') !!}
                </form>
            </x-admin::form>

            {!! view_render_event('admin.components.activities.actions.activity.form_controls.after') !!}
        </Teleport>
    </script>

    <script type="module">
        app.component('v-activity', {
            template: '#v-activity-template',

            props: {
                entity: {
                    type: Object,
                    required: true,
                    default: () => {}
                },

                entityControlName: {
                    type: String,
                    required: true,
                    default: ''
                }
            },

            data: function () {
                return {
                    isStoring: false,
                    
                    selectedType: {
                        label: {!! json_encode(trans('admin::app.components.activities.actions.activity.call')) !!},
                        value: 'call'
                    },

                    translations: {
                        title: {!! json_encode(trans('admin::app.components.activities.actions.activity.title-control')) !!},
                        description: {!! json_encode(trans('admin::app.components.activities.actions.activity.description')) !!}
                    },

                    isSearchingProducts: false,

                    productSearchTerm: '',

                    searchedProducts: [],

                    selectedProduct: null,

                    availableTypes: [
                        {
                            label: {!! json_encode(trans('admin::app.components.activities.actions.activity.call')) !!},
                            value: 'call'
                        }, {
                            label: {!! json_encode(trans('admin::app.components.activities.actions.activity.meeting')) !!},
                            value: 'meeting'
                        },
                    ]
                }
            },

            watch: {
                productSearchTerm(newVal) {
                    this.searchProducts(newVal);
                },
            },

            methods: {
                openModal(type) {
                    this.selectedProduct = null;
                    this.productSearchTerm = '';
                    this.searchedProducts = [];
                    this.$refs.activityModal.open();
                },

                searchProducts(term) {
                    if (term.length < 2) {
                        this.searchedProducts = [];
                        return;
                    }

                    this.isSearchingProducts = true;

                    this.$axios.get("{{ route('admin.products.search') }}", {
                            params: { query: term }
                        })
                        .then(response => {
                            this.searchedProducts = response.data.data;
                            this.isSearchingProducts = false;
                        })
                        .catch(error => {
                            this.isSearchingProducts = false;
                        });
                },

                selectProduct(product) {
                    this.selectedProduct = product;
                    this.productSearchTerm = '';
                    this.searchedProducts = [];
                },

                removeProduct() {
                    this.selectedProduct = null;
                },

                save(params) {
                    this.isStoring = true;

                    if (this.selectedProduct) {
                        params.product_id = this.selectedProduct.id;
                    }

                    this.$axios.post("{{ route('admin.activities.store') }}", params)
                        .then (response => {
                            this.isStoring = false;

                            this.$emitter.emit('add-flash', { type: 'success', message: response.data.message });

                            this.$emitter.emit('on-activity-added', response.data.data);

                            this.$refs.activityModal.close();
                        })
                        .catch (error => {
                            this.isStoring = false;

                            if (error.response.status == 422) {
                                // Mostramos el mensaje de error de validación (ej: fuera de horario laboral)
                                this.$emitter.emit('add-flash', { 
                                    type: 'error', 
                                    message: error.response.data.message 
                                });
                            } else {
                                this.$emitter.emit('add-flash', { type: 'error', message: error.response.data.message });

                                this.$refs.activityModal.close();
                            }
                        });
                },
            },
        });
    </script>
@endPushOnce
