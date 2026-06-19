<?php

namespace Webkul\Admin\Helpers\Reporting;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Webkul\Lead\Repositories\ProductRepository;
use Webkul\Lead\Repositories\StageRepository;

class Product extends AbstractReporting
{
    /**
     * The won (delivered/sold) stage ids. Matches the same convention used in Reporting\Lead.
     */
    protected array $wonStageIds;

    /**
     * Create a helper instance.
     *
     * @return void
     */
    public function __construct(
        protected ProductRepository $productRepository,
        protected StageRepository $stageRepository,
    ) {
        $this->wonStageIds = $this->stageRepository
            ->resetModel()
            ->where('code', 'won')
            ->orWhere('code', 'pedido-entregado')
            ->orWhereRaw("LOWER(name) LIKE '%won%'")
            ->orWhereRaw("LOWER(name) LIKE '%ganado%'")
            ->orWhereRaw("LOWER(name) LIKE '%entregado%'")
            ->pluck('id')
            ->toArray();

        parent::__construct();
    }

    /**
     * Gets top-selling products by revenue.
     *
     * @param  int  $limit
     */
    public function getTopSellingProductsByRevenue($limit = 200): Collection
    {
        $tablePrefix = DB::getTablePrefix();

        $items = $this->productRepository
            ->resetModel()
            ->with('product')
            ->when(request('user_id'), function ($q) {
                $q->leftJoin('leads', 'lead_products.lead_id', '=', 'leads.id')
                    ->where('leads.user_id', request('user_id'));
            })
            ->when(request('organization_id'), function ($q) {
                $q->leftJoin('persons', 'leads.person_id', '=', 'persons.id')
                    ->where('persons.organization_id', request('organization_id'));
            })
            ->leftJoin('leads', 'lead_products.lead_id', '=', 'leads.id')
            ->leftJoin('products', 'lead_products.product_id', '=', 'products.id')
            ->select('*')
            ->addSelect(DB::raw('SUM('.$tablePrefix.'lead_products.amount) as revenue'))
            ->whereBetween('leads.closed_at', [$this->startDate, $this->endDate])
            ->having(DB::raw('SUM('.$tablePrefix.'lead_products.amount)'), '>', 0)
            ->groupBy('product_id')
            ->orderBy('revenue', 'DESC')
            ->limit($limit)
            ->get();

        $items = $items->map(function ($item) {
            return [
                'id'                => $item->product_id,
                'name'              => $item->name,
                'price'             => $item->product?->price,
                'formatted_price'   => core()->formatBasePrice($item->price),
                'revenue'           => $item->revenue,
                'formatted_revenue' => core()->formatBasePrice($item->revenue),
            ];
        });

        return $items;
    }

    /**
     * Gets top-selling products by quantity.
     *
     * @param  int  $limit
     */
    public function getTopSellingProductsByQuantity($limit = null): Collection
    {
        $tablePrefix = DB::getTablePrefix();

        $items = $this->productRepository
            ->resetModel()
            ->with('product')
            ->leftJoin('leads', 'lead_products.lead_id', '=', 'leads.id')
            ->leftJoin('products', 'lead_products.product_id', '=', 'products.id')
            ->when(request('user_id'), function ($q) {
                $q->where('leads.user_id', request('user_id'));
            })
            ->when(request('organization_id'), function ($q) {
                $q->leftJoin('persons', 'leads.person_id', '=', 'persons.id')
                    ->where('persons.organization_id', request('organization_id'));
            })
            ->when(is_numeric(request('pipeline_id')) ? request('pipeline_id') : null, function ($q, $pipelineId) {
                $q->where('leads.lead_pipeline_id', $pipelineId);
            })
            ->select(
                'products.id as product_id',
                'products.name',
                'products.price'
            )
            ->addSelect(DB::raw('SUM('.$tablePrefix.'lead_products.quantity) as total_qty_ordered'))
            ->whereBetween('leads.created_at', [$this->startDate, $this->endDate])
            ->having(DB::raw('SUM('.$tablePrefix.'lead_products.quantity)'), '>', 0)
            ->groupBy('products.id', 'products.name', 'products.price')
            ->orderBy('total_qty_ordered', 'DESC')
            ->limit($limit)
            ->get();

        $items = $items->map(function ($item) {
            return [
                'id'                => $item->product_id,
                'name'              => $item->name,
                'price'             => $item->product?->price,
                'formatted_price'   => core()->formatBasePrice($item->price),
                'total_qty_ordered' => $item->total_qty_ordered,
            ];
        });

        return $items;
    }

    /**
     * Gets the most sold products by quantity, considering only leads in the
     * Won/Ganado stage (i.e. delivered orders) and an optional pipeline (ciudad) filter.
     *
     * @param  int  $limit
     */
    public function getTopSellingProductsByQuantitySold($limit = null): Collection
    {
        $tablePrefix = DB::getTablePrefix();
        $pipelineId = is_numeric(request('pipeline_id')) ? request('pipeline_id') : null;

        $items = $this->productRepository
            ->resetModel()
            ->with('product')
            ->leftJoin('leads', 'lead_products.lead_id', '=', 'leads.id')
            ->leftJoin('products', 'lead_products.product_id', '=', 'products.id')
            ->when(request('user_id'), function ($q) {
                $q->where('leads.user_id', request('user_id'));
            })
            ->when(request('organization_id'), function ($q) {
                $q->leftJoin('persons', 'leads.person_id', '=', 'persons.id')
                    ->where('persons.organization_id', request('organization_id'));
            })
            ->when($pipelineId, function ($q) use ($pipelineId) {
                $q->where('leads.lead_pipeline_id', $pipelineId);
            })
            ->select(
                'products.id as product_id',
                'products.name',
                'products.price'
            )
            ->addSelect(DB::raw('SUM('.$tablePrefix.'lead_products.quantity) as total_qty_ordered'))
            ->whereIn('leads.lead_pipeline_stage_id', $this->wonStageIds)
            ->whereRaw('COALESCE('.$tablePrefix.'leads.confirmed_at, '.$tablePrefix.'leads.created_at) BETWEEN ? AND ?', [$this->startDate, $this->endDate])
            ->having(DB::raw('SUM('.$tablePrefix.'lead_products.quantity)'), '>', 0)
            ->groupBy('products.id', 'products.name', 'products.price')
            ->orderBy('total_qty_ordered', 'DESC')
            ->limit($limit)
            ->get();

        return $items->map(function ($item) {
            return [
                'id'                => $item->product_id,
                'name'              => $item->name,
                'price'             => $item->product?->price,
                'formatted_price'   => core()->formatBasePrice($item->price),
                'total_qty_ordered' => $item->total_qty_ordered,
            ];
        });
    }

    /**
     * Retrieves total services (products sold) and their progress.
     */
    public function getTotalServicesProgress(): array
    {
        return [
            'previous' => $previous = $this->getTotalServices($this->lastStartDate, $this->lastEndDate),
            'current'  => $current = $this->getTotalServices($this->startDate, $this->endDate),
            'progress' => $this->getPercentageChange($previous, $current),
        ];
    }

    /**
     * Retrieves total services by date
     *
     * @param  \Carbon\Carbon  $startDate
     * @param  \Carbon\Carbon  $endDate
     */
    public function getTotalServices($startDate, $endDate): int
    {
        $tablePrefix = DB::getTablePrefix();

        return (int) $this->productRepository
            ->resetModel()
            ->leftJoin('leads', 'lead_products.lead_id', '=', 'leads.id')
            ->when(is_numeric(request('pipeline_id')) ? request('pipeline_id') : null, function ($q, $pipelineId) {
                $q->where('leads.lead_pipeline_id', $pipelineId);
            })
            ->whereBetween('leads.created_at', [$startDate, $endDate])
            ->sum(DB::raw($tablePrefix.'lead_products.quantity'));
    }

    /**
     * Returns total products sold over time
     *
     * @param  string  $period
     */
    public function getTotalProductsSoldOverTime($period = 'auto'): array
    {
        $period = $this->determinePeriod($period);

        $intervals = $this->generateTimeIntervals($this->startDate, $this->endDate, $period);

        $groupColumn = $this->getGroupColumn('leads.created_at', $period);

        $tablePrefix = DB::getTablePrefix();

        $query = $this->productRepository
            ->resetModel()
            ->leftJoin('leads', 'lead_products.lead_id', '=', 'leads.id')
            ->select(
                DB::raw("$groupColumn AS date"),
                DB::raw('SUM('.$tablePrefix.'lead_products.quantity) AS count')
            )
            ->whereBetween('leads.created_at', [$this->startDate, $this->endDate])
            ->groupBy(DB::raw($groupColumn))
            ->orderBy(DB::raw($groupColumn));

        $results = $query->get();
        $resultLookup = $results->keyBy('date');

        $stats = [];

        foreach ($intervals as $interval) {
            $result = $resultLookup->get($interval['key']);

            $stats[] = [
                'label' => $interval['label'],
                'count' => $result ? (int) $result->count : 0,
            ];
        }

        return $stats;
    }

    /**
     * Generate time intervals based on period
     */
    protected function generateTimeIntervals(\Carbon\Carbon $startDate, \Carbon\Carbon $endDate, string $period): array
    {
        $intervals = [];
        $current = $startDate->copy();

        while ($current <= $endDate) {
            $interval = [
                'key'   => $this->formatDateForGrouping($current, $period),
                'label' => $this->formatDateForLabel($current, $period),
            ];

            $intervals[] = $interval;

            switch ($period) {
                case 'day':
                    $current->addDay();
                    break;
                case 'week':
                    $current->addWeek();
                    break;
                case 'month':
                    $current->addMonth();
                    break;
                case 'year':
                    $current->addYear();
                    break;
            }
        }

        return $intervals;
    }

    protected function determinePeriod(string $period): string
    {
        if ($period !== 'auto') {
            return $period;
        }

        $days = $this->startDate->diffInDays($this->endDate);

        if ($days <= 31) {
            return 'day';
        } elseif ($days <= 90) {
            return 'week';
        } elseif ($days <= 365) {
            return 'month';
        }

        return 'year';
    }

    protected function getGroupColumn(string $dateColumn, string $period): string
    {
        return match ($period) {
            'day'   => "DATE_FORMAT($dateColumn, '%Y-%m-%d')",
            'week'  => "DATE_FORMAT(DATE_ADD($dateColumn, INTERVAL(1-DAYOFWEEK($dateColumn)) DAY), '%Y-%m-%d')",
            'month' => "DATE_FORMAT($dateColumn, '%Y-%m')",
            'year'  => "DATE_FORMAT($dateColumn, '%Y')",
            default => "DATE_FORMAT($dateColumn, '%Y-%m-%d')",
        };
    }

    protected function formatDateForGrouping(\Carbon\Carbon $date, string $period): string
    {
        return match ($period) {
            'day'   => $date->format('Y-m-d'),
            'week'  => clone $date->startOfWeek()->format('Y-m-d'),
            'month' => $date->format('Y-m'),
            'year'  => $date->format('Y'),
            default => $date->format('Y-m-d'),
        };
    }

    protected function formatDateForLabel(\Carbon\Carbon $date, string $period): string
    {
        return match ($period) {
            'day'   => $date->format('d M'),
            'week'  => clone $date->startOfWeek()->format('d M').' - '.clone $date->endOfWeek()->format('d M'),
            'month' => $date->format('M Y'),
            'year'  => $date->format('Y'),
            default => $date->format('d M'),
        };
    }
}
