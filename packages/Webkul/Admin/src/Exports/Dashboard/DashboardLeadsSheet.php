<?php

namespace Webkul\Admin\Exports\Dashboard;

use Maatwebsite\Excel\Concerns\WithTitle;
use Webkul\Admin\DataGrids\Lead\LeadDataGrid;
use Webkul\DataGrid\Exports\DataGridExport;

/**
 * "Detalle" sheet of the dashboard export: the lead/pedido rows that feed the
 * KPIs, WITH all their custom attributes.
 *
 * It reuses LeadDataGrid (and therefore the shared custom-attribute resolution)
 * so the detail always matches the Prospectos/Pedidos grid. The caller is
 * responsible for setting the request params LeadDataGrid reads (pipeline_id,
 * date_filter/date_from/date_to) before instantiating this sheet.
 */
class DashboardLeadsSheet extends DataGridExport implements WithTitle
{
    /**
     * Build the sheet from a prepared LeadDataGrid.
     */
    public function __construct()
    {
        $dataGrid = app(LeadDataGrid::class);

        /**
         * Populate columns and the base query without going through the full
         * request pipeline (no pagination): the filters that matter live in the
         * query params LeadDataGrid reads inside prepareQueryBuilder().
         */
        $dataGrid->prepareColumns();

        $dataGrid->setQueryBuilder();

        parent::__construct($dataGrid);
    }

    /**
     * Sheet title.
     */
    public function title(): string
    {
        return 'Detalle';
    }
}
