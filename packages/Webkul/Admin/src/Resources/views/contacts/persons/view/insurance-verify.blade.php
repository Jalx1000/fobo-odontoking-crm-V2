<v-insurance-activity
    :person-id="{{ $person->id }}"
    current-insurance="{{ $currentInsurance ?? '' }}"
/>

@pushOnce('scripts')
    <script type="text/x-template" id="v-insurance-activity-template">
        <div>
            <!-- Botón de Acción -->
            <div 
                class="flex cursor-pointer h-[74px] w-[84px] flex-col items-center justify-center gap-1 rounded-lg border border-transparent bg-orange-200 font-medium text-orange-800 transition-all hover:border-orange-400"
                @click="initVerification"
            >
                <span class="icon-success text-2xl text-orange-600"></span>
                <p class="text-xs font-medium text-orange-600 dark:text-gray-300">Seguro</p>
            </div>

            <!-- Modal de Confirmación de Borrado de Caché -->
            <x-admin::modal ref="clearCacheConfirmModal">
                <x-slot:header>
                    <p class="text-lg font-bold text-gray-800 dark:text-white">Confirmar</p>
                </x-slot:header>

                <x-slot:content>
                    <p class="text-sm text-gray-600 dark:text-gray-400">
                        ¿Estás seguro de que deseas borrar los datos guardados de esta verificación? Esto forzará una nueva consulta a la aseguradora.
                    </p>
                </x-slot:content>

                <x-slot:footer>
                    <div class="flex gap-2 justify-end">
                        <button class="secondary-button" @click="$refs.clearCacheConfirmModal.close()">Cancelar</button>
                        <button class="danger-button" @click="doClearCache(); $refs.clearCacheConfirmModal.close()">Confirmar</button>
                    </div>
                </x-slot:footer>
            </x-admin::modal>

            <!-- Modal de Captura de Datos -->
            <x-admin::modal ref="insuranceModal">
                <x-slot:header>
                    <div class="flex items-center justify-between w-full pr-8">
                        <p class="text-lg font-bold text-gray-800 dark:text-white">Verificación de Seguro</p>
                        
                        <button 
                            v-if="status !== 'CAMPOS_VACIOS'"
                            class="text-xs text-red-600 hover:underline flex items-center gap-1"
                            @click="clearCache"
                            title="Limpiar datos guardados para forzar nueva consulta"
                        >
                            <span class="icon-delete text-sm"></span>
                            Borrar Caché
                        </button>
                    </div>
                </x-slot:header>

                <x-slot:content>
                    <div class="flex flex-col gap-4">
                        <p v-if="status === 'CAMPOS_VACIOS'" class="text-sm text-gray-600 dark:text-gray-400">
                            Faltan datos para realizar la verificación. Por favor, complétalos a continuación:
                        </p>

                        <!-- CI -->
                        <x-admin::form.control-group>
                            <x-admin::form.control-group.label class="required">Carnet de Identidad</x-admin::form.control-group.label>
                            <input 
                                type="text" 
                                v-model="formData.ci_paciente"
                                class="flex min-h-[39px] w-full rounded-md border px-3 py-2 text-sm text-gray-600 transition-all hover:border-gray-400 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300"
                                placeholder="Ej: 5856583"
                            >
                        </x-admin::form.control-group>

                        <!-- Seguro -->
                        <x-admin::form.control-group>
                            <x-admin::form.control-group.label class="required">Empresa de Seguro</x-admin::form.control-group.label>
                            <select 
                                v-model="formData.seguro_paciente"
                                class="flex min-h-[39px] w-full rounded-md border px-3 py-2 text-sm text-gray-600 transition-all hover:border-gray-400 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300"
                            >
                                <option value="">Seleccione un seguro</option>
                                <option v-for="opt in options" :key="opt.id" :value="opt.id">@{{ opt.name }}</option>
                            </select>
                        </x-admin::form.control-group>
                    </div>
                </x-slot:content>

                <x-slot:footer>
                    <div class="flex gap-2 justify-end">
                        <button class="secondary-button" @click="$refs.insuranceModal.toggle()">Cancelar</button>
                        <button 
                            class="primary-button" 
                            @click="saveAndUpdate"
                            :disabled="isSaving || !formData.ci_paciente || !formData.seguro_paciente"
                        >
                            <span v-if="isSaving">Guardando...</span>
                            <span v-else>Guardar y Verificar</span>
                        </button>
                    </div>
                </x-slot:footer>
            </x-admin::modal>
        </div>
    </script>

    <script type="module">
        app.component('v-insurance-activity', {
            template: '#v-insurance-activity-template',
            props: ['personId', 'currentInsurance'],
            data() {
                return {
                    isLoading: false,
                    isSaving: false,
                    status: null,
                    options: [],
                    formData: {
                        ci_paciente: '',
                        seguro_paciente: ''
                    },
                    localInsuranceLabel: this.currentInsurance,
                    debounceTimer: null
                }
            },

            mounted() {
                // Escuchar cambios en atributos globales para validación en tiempo real
                this.$emitter.on('attribute-updated', (data) => {
                    if (data.name === 'seguro_paciente') {
                        this.handleInsuranceChange(data.label);
                    }
                });
            },

            methods: {
                handleInsuranceChange(newLabel) {
                    this.localInsuranceLabel = newLabel;
                    
                    if (this.debounceTimer) clearTimeout(this.debounceTimer);

                    this.debounceTimer = setTimeout(() => {
                        this.validateInsuranceRealtime(newLabel);
                    }, 500); // 500ms debounce
                },

                validateInsuranceRealtime(label) {
                    if (!label) return;

                    const cleanLabel = label.toLowerCase().trim();

                    // Regla 1: Paciente sin seguro
                    if (cleanLabel === 'no tiene') {
                        this.$emitter.emit('add-flash', { 
                            type: 'warning', 
                            message: 'Aviso: El paciente no tiene seguro. Se aplicarán tarifas de cliente particular.' 
                        });
                        return;
                    }

                    // Regla 2: Seguros con convenios específicos (Ejemplo de regla de negocio)
                    if (cleanLabel.includes('alianza')) {
                        this.$emitter.emit('add-flash', { 
                            type: 'info', 
                            message: 'Validación: Alianza requiere aprobación previa para tratamientos mayores.' 
                        });
                    }

                    // Disparar verificación automática si es una aseguradora válida
                    this.initVerification(true);
                },

                initVerification(isAuto = false) {
                    if (this.isLoading) return;

                    // Validación local: Si el seguro es "No tiene", no llamamos al backend
                    if (this.localInsuranceLabel && this.localInsuranceLabel.toLowerCase() === 'no tiene') {
                        if (!isAuto) {
                            this.$emitter.emit('add-flash', { 
                                type: 'warning', 
                                message: 'El paciente no cuenta con un seguro registrado en su perfil.' 
                            });
                        }
                        return;
                    }
                    
                    this.isLoading = true;
                    this.$axios.post("{{ route('admin.contacts.persons.verify_insurance', $person->id) }}")
                        .then(res => {
                            const type = res.data.status === 'SIN_SEGURO' ? 'warning' : 'success';
                            this.$emitter.emit('add-flash', { type: type, message: res.data.message });
                            
                            if (['VIGENTE', 'EN_MORA'].includes(res.data.status)) {
                                this.$emitter.emit('refresh-activities');
                            }
                        })
                        .catch(err => {
                            if (err.response && err.response.status === 422) {
                                this.status = 'CAMPOS_VACIOS';
                                this.options = err.response.data.options;
                                this.$refs.insuranceModal.toggle();
                            } else if (!isAuto) {
                                this.$emitter.emit('add-flash', { 
                                    type: 'error', 
                                    message: err.response?.data?.message || 'Error al conectar con n8n.' 
                                });
                            }
                        })
                        .finally(() => {
                            this.isLoading = false;
                        });
                },

                saveAndUpdate() {
                    this.isSaving = true;
                    this.$axios.post("{{ route('admin.contacts.persons.update_insurance', $person->id) }}", this.formData)
                        .then(res => {
                            this.$emitter.emit('add-flash', { type: 'success', message: 'Datos actualizados y seguro verificado.' });
                            this.$refs.insuranceModal.toggle();
                            this.$emitter.emit('refresh-activities');
                        })
                        .catch(err => {
                            this.$emitter.emit('add-flash', { 
                                type: 'error', 
                                message: err.response?.data?.message || 'Error al actualizar datos.' 
                            });
                        })
                        .finally(() => {
                            this.isSaving = false;
                        });
                },

                clearCache() {
                    this.$refs.clearCacheConfirmModal.open();
                },

                doClearCache() {
                    this.$axios.post("{{ route('admin.contacts.persons.clear_insurance_cache', $person->id) }}")
                        .then(res => {
                            this.$emitter.emit('add-flash', { type: 'success', message: res.data.message });
                            this.$refs.insuranceModal.toggle();
                            this.initVerification();
                        })
                        .catch(err => {
                            this.$emitter.emit('add-flash', {
                                type: 'error',
                                message: 'No se pudo limpiar la caché.'
                            });
                        });
                }
            }
        });
    </script>
@endPushOnce
