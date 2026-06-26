{!! view_render_event('admin.dashboard.index.services_requested.before') !!}

<v-dashboard-services-requested>
    <x-admin::shimmer.dashboard.index.top-selling-products />
</v-dashboard-services-requested>

{!! view_render_event('admin.dashboard.index.services_requested.after') !!}

@pushOnce('scripts')
    <script type="text/x-template" id="v-dashboard-services-requested-template">
        <template v-if="isLoading">
            <x-admin::shimmer.dashboard.index.top-selling-products />
        </template>

        <template v-else>
            <div class="w-full rounded-lg border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
                <div class="flex items-center justify-between p-4">
                    <p class="text-base font-semibold text-gray-600 dark:text-gray-300">
                        @lang('admin::app.dashboard.index.services-requested.title')
                    </p>

                    <span class="rounded-full bg-violet-100 px-2.5 py-0.5 text-xs font-semibold text-violet-700 dark:bg-violet-900 dark:text-violet-200">
                        @{{ report.total }}
                    </span>
                </div>

                <div class="flex flex-col divide-y divide-gray-200 dark:divide-gray-800" v-if="report.statistics.length">
                    <div
                        class="flex items-center justify-between gap-2 px-4 py-3"
                        v-for="stat in report.statistics"
                        :key="stat.id"
                    >
                        <div class="flex min-w-0 flex-col">
                            <p class="truncate text-sm font-medium text-gray-800 dark:text-gray-200" :title="stat.name">
                                @{{ stat.name }}
                            </p>
                            <p class="text-xs text-gray-400">
                                @{{ stat.leads_count }} @lang('admin::app.dashboard.index.services-requested.leads')
                            </p>
                        </div>

                        <span class="shrink-0 rounded-md bg-gray-100 px-2.5 py-1 text-sm font-semibold text-gray-700 dark:bg-gray-800 dark:text-gray-200">
                            @{{ stat.total_qty_ordered }}
                        </span>
                    </div>
                </div>

                <div class="flex flex-col gap-8 p-4" v-else>
                    <div class="grid justify-center justify-items-center gap-3.5 py-2.5">
                        <img src="{{ vite()->asset('images/empty-placeholders/products.svg') }}" class="dark:mix-blend-exclusion dark:invert">
                        <div class="flex flex-col items-center">
                            <p class="text-base font-semibold text-gray-400">@lang('admin::app.dashboard.index.services-requested.empty-title')</p>
                            <p class="text-gray-400">@lang('admin::app.dashboard.index.services-requested.empty-info')</p>
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </script>

    <script type="module">
        app.component('v-dashboard-services-requested', {
            template: '#v-dashboard-services-requested-template',

            data() {
                return {
                    report: { statistics: [], total: 0 },
                    isLoading: true,
                }
            },

            mounted() {
                // All-time card: load once and do NOT subscribe to the date filter.
                this.getStats();
            },

            methods: {
                getStats() {
                    this.isLoading = true;

                    this.$axios.get("{{ route('admin.dashboard.stats') }}", {
                            params: { type: 'services-requested' }
                        })
                        .then(response => {
                            this.report = response.data.statistics;
                            this.isLoading = false;
                        })
                        .catch(error => { this.isLoading = false; console.error(error); });
                },
            }
        });
    </script>
@endPushOnce
