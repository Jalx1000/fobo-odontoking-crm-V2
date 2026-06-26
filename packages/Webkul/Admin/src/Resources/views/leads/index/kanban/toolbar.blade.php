{!! view_render_event('admin.leads.index.kanban.toolbar.before') !!}

<div class="flex justify-between gap-2 max-md:flex-wrap">
    <div class="flex w-full items-center gap-x-1.5 max-md:justify-between">
        {!! view_render_event('admin.leads.index.kanban.toolbar.search.before') !!}

        <!-- Search Panel -->
        @include('admin::leads.index.kanban.search')

        {!! view_render_event('admin.leads.index.kanban.toolbar.search.after') !!}

        {!! view_render_event('admin.leads.index.kanban.toolbar.filter.before') !!}

        <!-- Filter -->
        @include('admin::leads.index.kanban.filter')

        {!! view_render_event('admin.leads.index.kanban.toolbar.filter.after') !!}

        {!! view_render_event('admin.leads.index.kanban.toolbar.date_range.before') !!}

        <!-- Date Range Quick Filter -->
        <div class="flex items-center gap-1 rounded-md border border-gray-200 p-0.5 dark:border-gray-800">
            <template v-for="range in [
                { value: '7', label: '@lang('admin::app.leads.index.kanban.toolbar.date-range.last-7-days')' },
                { value: '30', label: '@lang('admin::app.leads.index.kanban.toolbar.date-range.last-30-days')' },
                { value: '90', label: '@lang('admin::app.leads.index.kanban.toolbar.date-range.last-90-days')' },
                { value: 'month', label: '@lang('admin::app.leads.index.kanban.toolbar.date-range.this-month')' },
            ]">
                <button
                    type="button"
                    class="whitespace-nowrap rounded px-2.5 py-1 text-xs font-semibold transition-all"
                    :class="applied.dateRange === range.value
                        ? 'bg-sky-100 text-sky-600 dark:bg-brandColor dark:text-white'
                        : 'text-gray-600 hover:bg-gray-100 dark:text-gray-300 dark:hover:bg-gray-800'"
                    @click="applyDateRange(range.value)"
                    v-text="range.label"
                >
                </button>
            </template>
        </div>

        {!! view_render_event('admin.leads.index.kanban.toolbar.date_range.after') !!}

        <div class="z-10 hidden w-full divide-y divide-gray-100 rounded bg-white shadow dark:bg-gray-900"></div>
    </div>

    {!! view_render_event('admin.leads.index.kanban.toolbar.switcher.before') !!}

    <!-- View Switcher -->
    @include('admin::leads.index.view-switcher')

    {!! view_render_event('admin.leads.index.kanban.toolbar.switcher.after') !!}
</div>

{!! view_render_event('admin.leads.index.kanban.toolbar.after') !!}
