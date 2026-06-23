<!-- Custom Range Selector Only -->
<div class="flex items-center gap-2 border-l pl-2 dark:border-gray-800">
    <v-custom-date-filter
        date-from="{{ request('date_from') }}"
        date-to="{{ request('date_to') }}"
        date-filter="{{ request('date_filter') }}"
    ></v-custom-date-filter>
</div>

@pushOnce('scripts')
    <script type="text/x-template" id="v-custom-date-filter-template">
        <div class="flex items-center gap-2">
            <div class="flex items-center gap-2">
                <div class="w-[120px]">
                    <v-date-picker @on-change="mutableDateFrom = $event">
                        <input 
                            type="text" 
                            :value="mutableDateFrom"
                            class="flex min-h-[39px] w-full rounded-md border px-3 py-2 text-sm text-gray-600 transition-all hover:border-gray-400 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300 dark:hover:border-gray-400" 
                            placeholder="Inicio"
                        />
                    </v-date-picker>
                </div>
                <span class="text-gray-400">-</span>
                <div class="w-[120px]">
                    <v-date-picker @on-change="mutableDateTo = $event">
                        <input 
                            type="text" 
                            :value="mutableDateTo"
                            class="flex min-h-[39px] w-full rounded-md border px-3 py-2 text-sm text-gray-600 transition-all hover:border-gray-400 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300 dark:hover:border-gray-400" 
                            placeholder="Fin"
                        />
                    </v-date-picker>
                </div>
                <button 
                    type="button"
                    @click="applyFilter"
                    class="primary-button"
                >
                    Aplicar
                </button>
            </div>

            <a 
                v-if="dateFilter == 'custom' || dateFrom || dateTo"
                :href="getClearUrl()" 
                class="icon-cross-large cursor-pointer text-2xl text-rose-600"
                title="Limpiar rango"
            >
            </a>
        </div>
    </script>

    <script type="module">
        app.component('v-custom-date-filter', {
            template: '#v-custom-date-filter-template',

            props: ['dateFrom', 'dateTo', 'dateFilter'],

            data() {
                return {
                    mutableDateFrom: this.dateFrom || '',
                    mutableDateTo: this.dateTo || '',
                }
            },
            
            methods: {
                getClearUrl() {
                    const url = new URL(window.location.href);
                    // "none" tells the server to forget the shared global_date_range
                    // cookie instead of falling back to it on the next load.
                    url.searchParams.set('date_filter', 'none');
                    url.searchParams.delete('date_from');
                    url.searchParams.delete('date_to');
                    return url.toString();
                },

                applyFilter() {
                    if (!this.mutableDateFrom || !this.mutableDateTo) {
                        this.$emitter.emit('add-flash', { 
                            type: 'error', 
                            message: 'Por favor selecciona ambas fechas.' 
                        });
                        return;
                    }

                    if (new Date(this.mutableDateFrom) > new Date(this.mutableDateTo)) {
                        this.$emitter.emit('add-flash', { 
                            type: 'error', 
                            message: 'La fecha de inicio no puede ser posterior a la fecha de fin.' 
                        });
                        return;
                    }

                    const url = new URL(window.location.href);
                    url.searchParams.set('date_filter', 'custom');
                    url.searchParams.set('date_from', this.mutableDateFrom);
                    url.searchParams.set('date_to', this.mutableDateTo);
                    
                    window.location.href = url.toString();
                }
            }
        });
    </script>
@endpushOnce
