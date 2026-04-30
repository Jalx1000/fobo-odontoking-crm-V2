{!! view_render_event('admin.leads.index.table.before') !!}

<x-admin::datagrid :src="route('admin.leads.index')">
    <!-- DataGrid Shimmer -->
    <x-admin::shimmer.datagrid />

    <x-slot:toolbar-left-after>
        @include('admin::leads.index.date-filters')
    </x-slot:toolbar-left-after>

    <x-slot:toolbar-right-after>
        @include('admin::leads.index.view-switcher')
    </x-slot>
</x-admin::datagrid>

@push('styles')
    <style>
        .toolbarLeft > div > div > div > .relative.flex.cursor-pointer.items-center {
            display: none !important;
        }
    </style>
@endpush

{!! view_render_event('admin.leads.index.table.after') !!}