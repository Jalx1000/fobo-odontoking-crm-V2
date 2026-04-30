<div class="flex gap-2">
    <a 
        href="{{ request()->fullUrlWithQuery(['date_filter' => 'today']) }}" 
        class="secondary-button {{ request('date_filter') == 'today' ? 'active' : '' }}"
        style="{{ request('date_filter') == 'today' ? 'background: #eee;' : '' }}"
    >
        Hoy
    </a>
    <a 
        href="{{ request()->fullUrlWithQuery(['date_filter' => 'week']) }}" 
        class="secondary-button {{ request('date_filter') == 'week' ? 'active' : '' }}"
        style="{{ request('date_filter') == 'week' ? 'background: #eee;' : '' }}"
    >
        Semana
    </a>
    <a 
        href="{{ request()->fullUrlWithQuery(['date_filter' => 'month']) }}" 
        class="secondary-button {{ request('date_filter') == 'month' ? 'active' : '' }}"
        style="{{ request('date_filter') == 'month' ? 'background: #eee;' : '' }}"
    >
        Mes
    </a>
    @if(request('date_filter'))
        <a 
            href="{{ request()->fullUrlWithQuery(['date_filter' => null]) }}" 
            class="icon-cross-large cursor-pointer text-2xl"
            title="Limpiar filtro"
        >
        </a>
    @endif
</div>
