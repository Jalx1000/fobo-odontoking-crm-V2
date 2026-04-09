<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Database\Eloquent\Relations\Relation;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        Relation::morphMap([
            'leads'         => 'Webkul\Lead\Models\Lead',
            'persons'       => 'Webkul\Contact\Models\Person',
            'organizations' => 'Webkul\Contact\Models\Organization',
            'products'      => 'Webkul\Product\Models\Product',
            'quotes'        => 'Webkul\Quote\Models\Quote',
            'warehouses'    => 'Webkul\Warehouse\Models\Warehouse',
            'doctors'       => 'Webkul\Doctor\Models\Doctor',
        ]);
    }
}
