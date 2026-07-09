<v-contacts-filters
    pipeline-id="{{ request('pipeline_id') }}"
    date-from="{{ request('start') }}"
    date-to="{{ request('end') }}"
    :pipelines="{{ json_encode($pipelines->map(fn ($pipeline) => ['id' => $pipeline->id, 'name' => $pipeline->name])) }}"
>
    <!-- Shimmer -->
    <div class="flex gap-1.5">
        <div class="light-shimmer-bg dark:shimmer h-[39px] w-[140px] rounded-md"></div>
        <div class="light-shimmer-bg dark:shimmer h-[39px] w-[140px] rounded-md"></div>
    </div>
</v-contacts-filters>

@pushOnce('scripts')
    <script
        type="text/x-template"
        id="v-contacts-filters-template"
    >
        <div class="flex flex-wrap items-center gap-1.5">
            <!-- City -->
            <select
                class="flex min-h-[39px] w-[160px] rounded-md border px-3 py-2 text-sm text-gray-600 transition-all hover:border-gray-400 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300 dark:hover:border-gray-400"
                v-model="city"
                @change="apply()"
            >
                <option value="">Todas las ciudades</option>
                <option
                    v-for="pipeline in pipelines"
                    :key="pipeline.id"
                    :value="pipeline.id"
                >
                    @{{ pipeline.name }}
                </option>
            </select>

            <!-- Quick ranges -->
            <div class="flex gap-1">
                <button
                    type="button"
                    class="flex min-h-[39px] items-center rounded-md border px-3 text-sm text-gray-600 transition-all hover:border-gray-400 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300 dark:hover:border-gray-400"
                    @click="setQuickRange(7)"
                >
                    7 días
                </button>

                <button
                    type="button"
                    class="flex min-h-[39px] items-center rounded-md border px-3 text-sm text-gray-600 transition-all hover:border-gray-400 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300 dark:hover:border-gray-400"
                    @click="setQuickRange(30)"
                >
                    30 días
                </button>

                <button
                    type="button"
                    class="flex min-h-[39px] items-center rounded-md border px-3 text-sm text-gray-600 transition-all hover:border-gray-400 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300 dark:hover:border-gray-400"
                    @click="setQuickRange(90)"
                >
                    90 días
                </button>

                <button
                    type="button"
                    class="flex min-h-[39px] items-center rounded-md border px-3 text-sm text-gray-600 transition-all hover:border-gray-400 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300 dark:hover:border-gray-400"
                    @click="setCurrentMonth()"
                >
                    Este mes
                </button>
            </div>

            <!-- Custom range -->
            <x-admin::flat-picker.date
                class="!w-[140px]"
                ::allow-input="false"
                ::max-date="end"
            >
                <input
                    class="flex min-h-[39px] w-full rounded-md border px-3 py-2 text-sm text-gray-600 transition-all hover:border-gray-400 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300 dark:hover:border-gray-400"
                    v-model="start"
                    placeholder="Fecha inicio"
                />
            </x-admin::flat-picker.date>

            <x-admin::flat-picker.date
                class="!w-[140px]"
                ::allow-input="false"
                ::min-date="start"
            >
                <input
                    class="flex min-h-[39px] w-full rounded-md border px-3 py-2 text-sm text-gray-600 transition-all hover:border-gray-400 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300 dark:hover:border-gray-400"
                    v-model="end"
                    placeholder="Fecha fin"
                />
            </x-admin::flat-picker.date>

            <button
                type="button"
                class="primary-button"
                @click="apply()"
            >
                Aplicar
            </button>

            <a
                v-if="start || end"
                @click="clear()"
                class="icon-cross-large cursor-pointer text-2xl text-rose-600"
                title="Limpiar rango"
            ></a>
        </div>
    </script>

    <script type="module">
        app.component('v-contacts-filters', {
            template: '#v-contacts-filters-template',

            props: ['pipelineId', 'dateFrom', 'dateTo', 'pipelines'],

            data() {
                return {
                    city: this.pipelineId || '',
                    start: this.dateFrom || '',
                    end: this.dateTo || '',
                };
            },

            methods: {
                formatDate(date) {
                    const year = date.getFullYear();
                    const month = String(date.getMonth() + 1).padStart(2, '0');
                    const day = String(date.getDate()).padStart(2, '0');

                    return `${year}-${month}-${day}`;
                },

                setQuickRange(days) {
                    const end = new Date();
                    const start = new Date();

                    start.setDate(end.getDate() - (days - 1));

                    this.start = this.formatDate(start);
                    this.end = this.formatDate(end);

                    this.apply();
                },

                setCurrentMonth() {
                    const end = new Date();
                    const start = new Date(end.getFullYear(), end.getMonth(), 1);

                    this.start = this.formatDate(start);
                    this.end = this.formatDate(end);

                    this.apply();
                },

                buildUrl(overrides) {
                    const url = new URL(window.location.href);

                    // City: '' means "Todas" -> explicit empty so the server
                    // persists "no city filter" instead of the saved cookie.
                    url.searchParams.set('pipeline_id', this.city ?? '');

                    if (this.start && this.end) {
                        url.searchParams.set('start', this.start);
                        url.searchParams.set('end', this.end);
                    } else {
                        url.searchParams.delete('start');
                        url.searchParams.delete('end');
                    }

                    url.searchParams.delete('clear_date');

                    // Reset to the first page whenever the filter changes.
                    url.searchParams.delete('page');

                    for (const [key, value] of Object.entries(overrides || {})) {
                        if (value === null) {
                            url.searchParams.delete(key);
                        } else {
                            url.searchParams.set(key, value);
                        }
                    }

                    return url.toString();
                },

                apply() {
                    if ((this.start && ! this.end) || (! this.start && this.end)) {
                        this.$emitter.emit('add-flash', {
                            type: 'error',
                            message: 'Por favor selecciona ambas fechas.',
                        });

                        return;
                    }

                    if (this.start && this.end && new Date(this.start) > new Date(this.end)) {
                        this.$emitter.emit('add-flash', {
                            type: 'error',
                            message: 'La fecha de inicio no puede ser posterior a la fecha de fin.',
                        });

                        return;
                    }

                    window.location.href = this.buildUrl();
                },

                clear() {
                    this.start = '';
                    this.end = '';

                    window.location.href = this.buildUrl({ clear_date: '1' });
                },
            },
        });
    </script>
@endPushOnce
