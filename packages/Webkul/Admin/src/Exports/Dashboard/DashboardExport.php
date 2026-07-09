<?php

namespace Webkul\Admin\Exports\Dashboard;

use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Webkul\Admin\Helpers\Dashboard;

/**
 * Multi-sheet dashboard export: a KPI "Resumen" sheet plus a "Detalle" sheet
 * with the underlying lead/pedido rows and their custom attributes.
 *
 * Multi-sheet output requires a spreadsheet format (xls/xlsx); CSV can only
 * hold a single sheet, so the controller exports just the detail sheet for CSV.
 */
class DashboardExport implements WithMultipleSheets
{
    /**
     * Create a new export instance.
     */
    public function __construct(protected Dashboard $dashboard) {}

    /**
     * Sheets composing the workbook.
     */
    public function sheets(): array
    {
        return [
            new DashboardSummarySheet($this->dashboard),
            new DashboardLeadsSheet,
        ];
    }
}
