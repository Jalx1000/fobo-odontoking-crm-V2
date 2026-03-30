<x-admin::layouts>
    <x-slot:title>
        @lang('admin::app.dashboard.index.title')
    </x-slot>
    <!-- Head Details Section -->
    {!! view_render_event('admin.dashboard.index.header.before') !!}

    <div class="mb-5 flex items-center justify-between gap-4 max-sm:flex-wrap">
        {!! view_render_event('admin.dashboard.index.header.left.before') !!}

        <div class="grid gap-1.5">
            <p class="text-2xl font-semibold dark:text-white">
                @lang('admin::app.dashboard.index.title')
            </p>
        </div>

        {!! view_render_event('admin.dashboard.index.header.left.after') !!}

        <!-- Actions -->
        {!! view_render_event('admin.dashboard.index.header.right.before') !!}

        <v-dashboard-filters>
            <!-- Shimmer -->
            <div class="flex gap-1.5">
                <div class="light-shimmer-bg dark:shimmer h-[39px] w-[140px] rounded-md"></div>
                <div class="light-shimmer-bg dark:shimmer h-[39px] w-[140px] rounded-md"></div>
            </div>
        </v-dashboard-filters>

        {!! view_render_event('admin.dashboard.index.header.right.after') !!}
    </div>

    {!! view_render_event('admin.dashboard.index.header.after') !!}

    <!-- Body Component -->
    {!! view_render_event('admin.dashboard.index.content.before') !!}

    @php
        $user = auth()->guard('user')->user();
        $isAdminRole = strtolower(optional($user->role)->name) === 'admin';
    @endphp

    <div class="mt-3.5 flex gap-4 max-xl:flex-wrap">
        <!-- Left Section -->
        {!! view_render_event('admin.dashboard.index.content.left.before') !!}

        <div class="flex flex-1 flex-col gap-4 max-xl:flex-auto">
            {{-- @include('admin::dashboard.index.revenue') --}}
            @include('admin::dashboard.index.over-all')
            @include('admin::dashboard.index.total-leads')

            @if ($isAdminRole)
                <div class="grid grid-cols-3 gap-4 w-full">
                    <div class="col-span-1 flex flex-col gap-4">
                        @include('admin::dashboard.index.ventas')
                    </div>
                    <div class="col-span-1 flex flex-col gap-4">
                        @include('admin::dashboard.index.leads-by-users')
                    </div>
                </div>
            @else
                @include('admin::dashboard.index.leads-by-users')
                @include('admin::dashboard.index.tiempo-por-vendedor')
            @endif
        </div>

        {!! view_render_event('admin.dashboard.index.content.left.after') !!}

        <!-- Right Section -->
        {!! view_render_event('admin.dashboard.index.content.right.before') !!}

        <div class="flex w-[378px] max-w-full flex-col gap-4 max-sm:w-full">
            {{-- Funel anterior --}}
            {{-- @include('admin::dashboard.index.open-leads-by-states') --}}

            {{-- Funel actual --}}
            @include('admin::dashboard.index.open-leads-by-states-fixed')

            {{-- @include('admin::dashboard.index.revenue-by-sources') --}}

            {{-- @include('admin::dashboard.index.revenue-by-types') --}}

            @include('admin::dashboard.index.quantity-products-pie')
        </div>

        {!! view_render_event('admin.dashboard.index.content.right.after') !!}
    </div>

    {!! view_render_event('admin.dashboard.index.content.after') !!}

    @pushOnce('scripts')
        <script type="module" src="{{ vite()->asset('js/chart.js') }}"></script>

        <script type="module" src="https://cdn.jsdelivr.net/npm/chartjs-chart-funnel@4.2.1/build/index.umd.min.js"></script>

        <script
            type="text/x-template"
            id="v-dashboard-filters-template"
        >
            {!! view_render_event('admin.dashboard.index.date_filters.before') !!}

            <div class="flex gap-1.5">
                <x-admin::flat-picker.date
                    class="!w-[140px]"
                    ::allow-input="false"
                    ::max-date="filters.end"
                >
                    <input
                        class="flex min-h-[39px] w-full rounded-md border px-3 py-2 text-sm text-gray-600 transition-all hover:border-gray-400 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300 dark:hover:border-gray-400"
                        v-model="filters.start"
                        placeholder="@lang('admin::app.dashboard.index.start-date')"
                    />
                </x-admin::flat-picker.date>

                <x-admin::flat-picker.date
                    class="!w-[140px]"
                    ::allow-input="false"
                    ::max-date="filters.end"
                >
                    <input
                        class="flex min-h-[39px] w-full rounded-md border px-3 py-2 text-sm text-gray-600 transition-all hover:border-gray-400 dark:border-gray-800 dark:bg-gray-900 dark:text-gray-300 dark:hover:border-gray-400"
                        v-model="filters.end"
                        placeholder="@lang('admin::app.dashboard.index.end-date')"
                    />
                </x-admin::flat-picker.date>
                
            </div>

            {!! view_render_event('admin.dashboard.index.date_filters.after') !!}
        </script>

        <script type="module">
            app.component('v-dashboard-filters', {
                template: '#v-dashboard-filters-template',

                data() {
                    return {
                        filters: {
                            channel: '',

                            start: "{{ $startDate->format('Y-m-d') }}",

                            end: "{{ $endDate->format('Y-m-d') }}"
                        }
                    }
                },

                watch: {
                    filters: {
                        handler() {
                            this.$emitter.emit('reporting-filter-updated', this.filters);
                        },

                        deep: true
                    }
                },


            });
        </script>
    @endPushOnce
</x-admin::layouts>
