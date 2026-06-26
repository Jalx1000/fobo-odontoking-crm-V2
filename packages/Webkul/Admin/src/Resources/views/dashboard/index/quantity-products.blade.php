{!! view_render_event('admin.dashboard.index.quantity_products.before') !!}

<v-dashboard-quantity-products>
    <x-admin::shimmer.dashboard.index.top-selling-products />
</v-dashboard-quantity-products>

{!! view_render_event('admin.dashboard.index.quantity_products.after') !!}

@pushOnce('scripts')
    <script type="text/x-template" id="v-dashboard-quantity-products-template">
        <template v-if="isLoading">
            <x-admin::shimmer.dashboard.index.top-selling-products />
        </template>

        <template v-else>
            <div class="w-full rounded-lg border border-gray-200 bg-white dark:border-gray-800 dark:bg-gray-900">
                <div class="flex items-center justify-between p-4">
                    <p class="text-base font-semibold text-gray-600 dark:text-gray-300">
                        @lang('admin::app.dashboard.index.quantity-products.title')
                    </p>
                </div>

                <div class="flex flex-col" v-if="report.statistics.length">
                    <a :href="`{{route('admin.products.view', '')}}/${item.id}`" class="flex gap-2.5 border-b p-4 transition-all last:border-b-0 hover:bg-gray-50 dark:border-gray-800 dark:hover:bg-gray-950" target="_blank" v-for="item in report.statistics">
                        <div class="flex w-full flex-col gap-1.5">
                            <p class="text-gray-600 dark:text-gray-300" v-text="item.name"></p>
                            <div class="flex justify-between">
                                <p class="font-medium text-gray-800 dark:text-white">@{{ item.formatted_price }}</p>
                                <p class="font-normal text-gray-800 dark:text-white">@{{ item.total_qty_ordered }}</p>
                                <p class="font-normal text-gray-800 dark:text-white">@{{ $admin.formatPrice((item.price || 0) * (item.total_qty_ordered || 0)) }}</p>
                            </div>
                        </div>
                    </a>
                </div>

                <div class="flex flex-col gap-8 p-4" v-else>
                    <div class="grid justify-center justify-items-center gap-3.5 py-2.5">
                        <img src="{{ vite()->asset('images/empty-placeholders/products.svg') }}" class="dark:mix-blend-exclusion dark:invert">
                        <div class="flex flex-col items-center">
                            <p class="text-base font-semibold text-gray-400">@lang('admin::app.dashboard.index.top-selling-products.empty-title')</p>
                            <p class="text-gray-400">@lang('admin::app.dashboard.index.top-selling-products.empty-info')</p>
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </script>

    <script type="module">
        app.component('v-dashboard-quantity-products', {
            template: '#v-dashboard-quantity-products-template',

            data() {
                return {
                    report: [],
                    isLoading: true,
                }
            },

            mounted() {
                this.getStats({});
                this.$emitter.on('reporting-filter-updated', this.getStats);
            },

            methods: {
                getStats(filters) {
                    this.isLoading = true;

                    var filters = Object.assign({}, filters);
                    filters.type = 'quantity-products';

                    this.$axios.get("{{ route('admin.dashboard.stats') }}", { params: filters })
                        .then(response => {
                            this.report = response.data;
                            this.isLoading = false;
                        })
                        .catch(error => { this.isLoading = false; console.error(error); });
                }
            }
        });
    </script>
@endPushOnce
