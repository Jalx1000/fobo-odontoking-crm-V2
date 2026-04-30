<v-custom-date-filter
    date-from="{{ request('date_from') }}"
    date-to="{{ request('date_to') }}"
    date-filter="{{ request('date_filter') }}"
></v-custom-date-filter>

@pushOnce('scripts')
    <script type="text/x-template" id="v-custom-date-filter-template">
        <div class="flex items-center gap-2">
            <!-- Quick Filters -->
            <div class="flex gap-2">
                <a 
                    :href="getQuickFilterUrl('today')" 
                    class="secondary-button"
                    :style="dateFilter == 'today' ? 'background: #eee;' : ''"
                >
                    @lang('admin::app.components.datagrid.filters.date-options.today')
                </a>
                <a 
                    :href="getQuickFilterUrl('week')" 
                    class="secondary-button"
                    :style="dateFilter == 'week' ? 'background: #eee;' : ''"
                >
                    @lang('admin::app.components.datagrid.filters.date-options.this-week')
                </a>
                <a 
                    :href="getQuickFilterUrl('month')" 
                    class="secondary-button"
                    :style="dateFilter == 'month' ? 'background: #eee;' : ''"
                >
                    @lang('admin::app.components.datagrid.filters.date-options.this-month')
                </a>
            </div>

            <!-- Custom Range Selector -->
            <div class="flex items-center gap-2 border-l pl-2 dark:border-gray-800">
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
            </div>

            <a 
                v-if="dateFilter || dateFrom || dateTo"
                :href="getClearUrl()" 
                class="icon-cross-large cursor-pointer text-2xl text-rose-600"
                title="Limpiar filtros"
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
                getQuickFilterUrl(filter) {
                    const url = new URL(window.location.href);
                    url.searchParams.set('date_filter', filter);
                    url.searchParams.delete('date_from');
                    url.searchParams.delete('date_to');
                    return url.toString();
                },

                getClearUrl() {
                    const url = new URL(window.location.href);
                    url.searchParams.delete('date_filter');
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
