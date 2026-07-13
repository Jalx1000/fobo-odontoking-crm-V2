<?php

namespace Webkul\Admin\Exports\Dashboard;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithColumnFormatting;
use Maatwebsite\Excel\Concerns\WithCustomValueBinder;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Cell\Cell;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Cell\DefaultValueBinder;
use PhpOffice\PhpSpreadsheet\Style\NumberFormat;
use Webkul\Lead\Repositories\LeadRepository;

/**
 * "Pedidos" sheet of the dashboard export: one row per lead (pedido) honoring
 * the active city/date filter, with the fields the operations team tracks:
 * ciudad, contacto, edad, el desglose del pedido, asesor y la fecha de alta del
 * contacto.
 *
 * It honors the same request params the controller merges for the detail sheet
 * (pipeline_id, date_filter=custom + date_from/date_to), so the three sheets
 * always describe the same slice of data.
 */
class DashboardOrdersSheet extends DefaultValueBinder implements FromArray, ShouldAutoSize, WithColumnFormatting, WithCustomValueBinder, WithHeadings, WithTitle
{
    /**
     * Canonical stage label per lead_pipeline_stage_id.
     *
     * Each city (pipeline) has its own stage rows, so the same logical stage
     * (e.g. "No atendido") has a different id per city. This map normalizes every
     * city's stage ids into the six shared labels, letting a single "Etapa" column
     * describe pedidos across cities. Keep in sync with lead_pipeline_stages.
     */
    private const STAGE_LABELS = [
        // No atendido
        1  => 'No atendido', 11 => 'No atendido', 15 => 'No atendido', 23 => 'No atendido',
        27 => 'No atendido', 31 => 'No atendido', 35 => 'No atendido', 40 => 'No atendido',
        // Confirmado
        2  => 'Confirmado', 12 => 'Confirmado', 16 => 'Confirmado', 24 => 'Confirmado',
        28 => 'Confirmado', 32 => 'Confirmado', 36 => 'Confirmado', 41 => 'Confirmado',
        // cliente-sin-inters
        39 => 'cliente-sin-inters', 45 => 'cliente-sin-inters', 50 => 'cliente-sin-inters', 49 => 'cliente-sin-inters',
        48 => 'cliente-sin-inters', 47 => 'cliente-sin-inters', 46 => 'cliente-sin-inters', 44 => 'cliente-sin-inters',
        // pedidos-entregados
        5  => 'pedidos-entregados', 13 => 'pedidos-entregados', 17 => 'pedidos-entregados', 25 => 'pedidos-entregados',
        29 => 'pedidos-entregados', 33 => 'pedidos-entregados', 37 => 'pedidos-entregados', 42 => 'pedidos-entregados',
        // Cancelado
        6  => 'Cancelado', 14 => 'Cancelado', 18 => 'Cancelado', 26 => 'Cancelado',
        30 => 'Cancelado', 34 => 'Cancelado', 38 => 'Cancelado', 43 => 'Cancelado',
        // Otros servicios
        51 => 'Otros servicios',
    ];

    /**
     * Sheet title.
     */
    public function title(): string
    {
        return 'Pedidos';
    }

    /**
     * Force the phone column (C) to text so Excel keeps leading zeros / "+"
     * prefixes and never renders it in scientific notation.
     */
    public function columnFormats(): array
    {
        return [
            'C' => NumberFormat::FORMAT_TEXT,
        ];
    }

    /**
     * Write the phone column as an explicit string. The text column format alone
     * only changes how a value is displayed; PhpSpreadsheet still stores a numeric
     * phone as a number (hence the "5.9E+11" scientific notation). Binding it as a
     * string keeps the digits verbatim.
     */
    public function bindValue(Cell $cell, $value): bool
    {
        if ($cell->getColumn() === 'C') {
            $cell->setValueExplicit((string) $value, DataType::TYPE_STRING);

            return true;
        }

        return parent::bindValue($cell, $value);
    }

    /**
     * Column headings.
     */
    public function headings(): array
    {
        return [
            'Ciudad',
            'Nombre',
            'Teléfono',
            'Email',
            'Edad',
            'Pedido',
            'Etapa',
            'Asesor asignado',
            'Fecha de creación del contacto',
        ];
    }

    /**
     * Data rows: every lead matching the active filter, one per row.
     */
    public function array(): array
    {
        $leads = app(LeadRepository::class)
            ->with(['person', 'products.product', 'user', 'pipeline', 'stage', 'attribute_values'])
            ->scopeQuery(fn ($query) => $this->applyFilters($query))
            ->all();

        return $leads->map(fn ($lead) => [
            $lead->pipeline?->name,
            $lead->person?->name,
            $this->joinPersonValues($lead->person?->contact_numbers),
            $this->joinPersonValues($lead->person?->emails),
            $lead->edad_lead,
            $this->formatOrder($lead),
            $this->resolveStage($lead),
            $lead->user?->name,
            $lead->person?->created_at?->format('Y-m-d H:i'),
        ])->all();
    }

    /**
     * Apply the same city + date scoping the export controller prepares, mirroring
     * LeadDataGrid so this sheet matches the "Detalle" sheet and the dashboard.
     */
    protected function applyFilters(Builder $query): Builder
    {
        if (is_numeric($pipelineId = request('pipeline_id'))) {
            $query->where('leads.lead_pipeline_id', $pipelineId);
        }

        if (
            request('date_filter') === 'custom'
            && ($from = request('date_from'))
            && ($to = request('date_to'))
        ) {
            try {
                $start = Carbon::parse($from)->startOfDay();
                $end = Carbon::parse($to)->endOfDay();

                if ($start->lte($end)) {
                    $query->whereBetween('leads.created_at', [$start, $end]);
                }
            } catch (\Exception $e) {
                // Ignore an invalid range, same as LeadDataGrid.
            }
        }

        if ($userIds = bouncer()->getAuthorizedUserIds()) {
            $query->whereIn('leads.user_id', $userIds);
        }

        return $query->orderBy('leads.id', 'desc');
    }

    /**
     * Resolve the pedido's stage into its canonical, city-independent label.
     *
     * Falls back to the raw stage name for any id not in the map (e.g. a stage
     * added after this map was written), so a new stage never shows up blank.
     */
    protected function resolveStage($lead): ?string
    {
        return self::STAGE_LABELS[$lead->lead_pipeline_stage_id] ?? $lead->stage?->name;
    }

    /**
     * Flatten a person's contact_numbers / emails JSON ([{value: ...}]) into a
     * single comma-separated cell.
     */
    protected function joinPersonValues($values): string
    {
        return collect($values ?? [])
            ->pluck('value')
            ->filter()
            ->implode(', ');
    }

    /**
     * Build the "Producto x cantidad x precio" breakdown for the lead's products,
     * with each product line separated by "; ".
     */
    protected function formatOrder($lead): string
    {
        return $lead->products
            ->map(fn ($item) => $item->name.' x'.(int) $item->quantity.' x '.core()->formatBasePrice($item->price))
            ->implode('; ');
    }
}
