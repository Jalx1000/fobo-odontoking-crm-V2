<?php

namespace Webkul\Admin\Http\Controllers;

use Webkul\Admin\Helpers\Dashboard;
use Webkul\Lead\Repositories\PipelineRepository;

class DashboardController extends Controller
{
    /**
     * Request param functions
     *
     * @var array
     */
    protected $typeFunctions = [
        'over-all'                        => 'getOverAllStats',
        'revenue-stats'                   => 'getRevenueStats',
        'total-leads'                     => 'getTotalLeadsStats',
        'revenue-by-sources'              => 'getLeadsStatsBySources',
        'revenue-by-types'                => 'getLeadsStatsByTypes',
        'top-selling-products'            => 'getTopSellingProducts',
        'quantity-products'               => 'getTopSellingProductsByQuantity',
        'quantity-products-sold'          => 'getTopSellingProductsByQuantitySold',
        'top-persons'                     => 'getTopPersons',
        'open-leads-by-states'            => 'getOpenLeadsByStates',
        'open-leads-by-states-fixed'      => 'getOpenLeadsByStatesFixed',
        'vendedores'                      => 'getVendedoresStats',
        'ventas'                          => 'getVentasByUsersStats',
        'leads-by-users'                  => 'getLeadsByUsersStats',
        'tiempo-por-vendedor'             => 'getResponseTimeByUsersStats',
        'ventas-por-sucursal'             => 'getVentasByBranchesStats',
        'leads-por-sucursal'              => 'getLeadsByBranchesStats',
        'ventas-por-ciudad'               => 'getVentasByPipelinesStats',
        'ventas-por-pipeline'             => 'getVentasByPipelinesStats',
        'leads-por-ciudad'                => 'getLeadsByPipelinesStats',
        'leads-por-pipeline'              => 'getLeadsByPipelinesStats',
        'total-leads-by-stages'           => 'getTotalLeadsByStages',
        'total-leads-by-stages-over-time' => 'getTotalLeadsByStagesOverTime',
        'total-services'                  => 'getTotalServicesStats',
    ];

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct(
        protected Dashboard $dashboardHelper,
        protected PipelineRepository $pipelineRepository,
    ) {}

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        return view('admin::dashboard.index')->with([
            'startDate' => $this->dashboardHelper->getStartDate(),
            'endDate'   => $this->dashboardHelper->getEndDate(),
            'pipelines' => $this->pipelineRepository->all(['id', 'name']),
        ]);
    }

    /**
     * Display a listing of the resource.
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function stats()
    {
        $type = request()->query('type');

        if (! array_key_exists($type, $this->typeFunctions)) {
            return response()->json([
                'message' => 'Parámetro type inválido o ausente.',
            ], 422);
        }

        $stats = $this->dashboardHelper->{$this->typeFunctions[$type]}();

        return response()->json([
            'statistics' => $stats,
            'date_range' => $this->dashboardHelper->getDateRange(),
        ]);
    }
}
