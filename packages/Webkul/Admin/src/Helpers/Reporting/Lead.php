<?php

namespace Webkul\Admin\Helpers\Reporting;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Webkul\Attribute\Repositories\AttributeRepository;
use Webkul\Lead\Repositories\LeadRepository;
use Webkul\Lead\Repositories\StageRepository;
use Webkul\Lead\Repositories\PipelineRepository;
use Webkul\User\Repositories\UserRepository;

class Lead extends AbstractReporting
{
    /**
     * The channel ids.
     */
    protected array $stageIds;

    /**
     * The all stage ids.
     */
    protected array $allStageIds;

    /**
     * The won stage ids.
     */
    protected array $wonStageIds;

    /**
     * The lost stage ids.
     */
    protected array $lostStageIds;

    /**
     * Create a helper instance.
     *
     * @return void
     */
    public function __construct(
        protected LeadRepository $leadRepository,
        protected StageRepository $stageRepository,
        protected PipelineRepository $pipelineRepository,
        protected UserRepository $userRepository,
        protected AttributeRepository $attributeRepository,
    ) {
        $this->allStageIds = $this->stageRepository->pluck('id')->toArray();

        $this->wonStageIds = $this->stageRepository
            ->resetModel()
            ->where('code', 'won')
            ->orWhereRaw("LOWER(name) LIKE '%won%'")
            ->orWhereRaw("LOWER(name) LIKE '%ganado%'")
            ->pluck('id')
            ->toArray();

        $this->lostStageIds = $this->stageRepository
            ->resetModel()
            ->where('code', 'lost')
            ->orWhereRaw("LOWER(name) LIKE '%lost%'")
            ->orWhereRaw("LOWER(name) LIKE '%perdido%'")
            ->pluck('id')
            ->toArray();

        parent::__construct();
    }

    /**
     * Returns current customers over time
     *
     * @param  string  $period
     */
    public function getTotalLeadsOverTime($period = 'auto'): array
    {
        $this->stageIds = array_values(array_diff($this->allStageIds, $this->lostStageIds));

        $period = $this->determinePeriod($period);

        return $this->getOverTimeStats($this->startDate, $this->endDate, 'leads.id', 'created_at', $period);
    }

    /**
     * Returns current customers over time
     *
     * @param  string  $period
     */
    public function getTotalWonLeadsOverTime($period = 'auto'): array
    {
        $this->stageIds = $this->wonStageIds;

        $period = $this->determinePeriod($period);

        return $this->getOverTimeStats($this->startDate, $this->endDate, 'leads.id', 'closed_at', $period);
    }

    /**
     * Returns current customers over time
     *
     * @param  string  $period
     */
    public function getTotalLostLeadsOverTime($period = 'auto'): array
    {
        $this->stageIds = $this->lostStageIds;

        $period = $this->determinePeriod($period);

        return $this->getOverTimeStats($this->startDate, $this->endDate, 'leads.id', 'closed_at', $period);
    }

    public function getVendedoresStats($limit = null)
    {
        $tablePrefix = DB::getTablePrefix();

        $items = $this->leadRepository
            ->resetModel()
            ->when(request('user_id'), function ($q) {
                $q->where('leads.user_id', request('user_id'));
            })
            ->when(request('organization_id'), function ($q) {
                $q->leftJoin('persons', 'leads.person_id', '=', 'persons.id')
                  ->where('persons.organization_id', request('organization_id'));
            })
            ->leftJoin('users', 'leads.user_id', '=', 'users.id')
            ->where('users.email', '!=', 'jmogro@sofopolis.com')
            ->leftJoin('lead_activities', 'leads.id', '=', 'lead_activities.lead_id')
            ->leftJoin('activities', 'lead_activities.activity_id', '=', 'activities.id')
            ->select(
                'users.id as user_id',
                'users.name as vendedor_name'
            )
            ->addSelect(DB::raw('COUNT(DISTINCT '.$tablePrefix.'leads.id) as total_leads'))
            ->addSelect(DB::raw('SUM(CASE WHEN '.$tablePrefix.'leads.lead_pipeline_stage_id IN ('.implode(',', $this->wonStageIds).') THEN 1 ELSE 0 END) as sales_count'))
            ->addSelect(DB::raw('SUM(CASE WHEN '.$tablePrefix.'leads.lead_pipeline_stage_id IN ('.implode(',', $this->wonStageIds).') THEN '.$tablePrefix.'leads.lead_value ELSE 0 END) as total_sales_amount'))
            ->addSelect(DB::raw('AVG(CASE WHEN '.$tablePrefix.'activities.created_at IS NOT NULL THEN TIMESTAMPDIFF(SECOND, '.$tablePrefix.'leads.created_at, '.$tablePrefix.'activities.created_at) END) as average_response_time_seconds'))
            ->whereBetween('leads.created_at', [$this->startDate, $this->endDate])
            ->groupBy('users.id', 'users.name')
            ->orderBy('total_sales_amount', 'DESC')
            ->limit($limit)
            ->get();

        $items = $items->map(function ($item) {
            return [
                'user_id'                       => $item->user_id,
                'name'                          => $item->vendedor_name,
                'total_leads'                   => (int) $item->total_leads,
                'sales_count'                   => (int) $item->sales_count,
                'total_sales_amount'            => (float) $item->total_sales_amount,
                'formatted_total_sales_amount'  => core()->formatBasePrice($item->total_sales_amount),
                'average_response_time_seconds' => $item->average_response_time_seconds ? (int) $item->average_response_time_seconds : null,
            ];
        });

        return $items;
    }

    public function getVentasOverTimeByUser($period = 'auto'): array
    {
        $period = $this->determinePeriod($period);

        $intervals = $this->generateTimeIntervals($this->startDate, $this->endDate, $period);

        $groupColumn = $this->getGroupColumn('closed_at', $period);

        $tablePrefix = DB::getTablePrefix();

        $query = $this->leadRepository
            ->resetModel()
            ->leftJoin('users', 'leads.user_id', '=', 'users.id')
            ->select(
                DB::raw("$groupColumn AS date"),
                'users.name as user_name',
                DB::raw('COUNT(DISTINCT CASE WHEN '.$tablePrefix.'leads.lead_pipeline_stage_id IN ('.implode(',', $this->wonStageIds).') THEN '.$tablePrefix.'leads.id END) AS count')
            )
            ->whereIn('leads.lead_pipeline_stage_id', $this->wonStageIds)
            ->where('users.email', '!=', 'jmogro@sofopolis.com')
            ->whereBetween('closed_at', [$this->startDate, $this->endDate])
            ->groupBy(DB::raw($groupColumn), 'users.id', 'users.name')
            ->orderBy(DB::raw($groupColumn));

        $results = $query->get();

        $byDate = [];

        foreach ($results as $row) {
            $key = $row->date;
            $user = $row->user_name ?? '—';
            $byDate[$key] ??= [];
            $byDate[$key][$user] = (int) $row->count;
        }

        $usersQuery = $this->userRepository->resetModel()->select('name')->where('email', '!=', 'jmogro@sofopolis.com');

        if (function_exists('bouncer') && ($userIds = bouncer()->getAuthorizedUserIds())) {
            $usersQuery->whereIn('id', $userIds);
        }

        $users = $usersQuery->pluck('name')->toArray();

        $stats = [];

        foreach ($intervals as $interval) {
            $dateKey = $interval['key'];
            $usersCounts = [];
            foreach ($users as $u) {
                $usersCounts[] = [
                    'name'  => $u,
                    'count' => (int) ($byDate[$dateKey][$u] ?? 0),
                ];
            }

            $stats[] = [
                'label' => $interval['label'],
                'users' => $usersCounts,
            ];
        }

        return [
            'over_time' => $stats,
            'users'     => $users,
        ];
    }

    public function getLeadsCountByUsers(): array
    {
        $tablePrefix = DB::getTablePrefix();

        $query = $this->leadRepository
            ->resetModel()
            ->leftJoin('users', 'leads.user_id', '=', 'users.id')
            ->select(
                'users.id as user_id',
                'users.name as user_name',
                DB::raw('COUNT(DISTINCT '.$tablePrefix.'leads.id) AS count')
            )
            ->whereBetween('leads.created_at', [$this->startDate, $this->endDate])
            ->whereNotIn('leads.lead_pipeline_stage_id', $this->lostStageIds)
            ->where('users.email', '!=', 'jmogro@sofopolis.com')
            ->groupBy('users.id', 'users.name')
            ->orderBy('user_name');

        $results = $query->get();

        $countsByUser = [];

        foreach ($results as $row) {
            $countsByUser[$row->user_name ?? '—'] = (int) $row->count;
        }

        $usersQuery = $this->userRepository->resetModel()->select('name')->where('email', '!=', 'jmogro@sofopolis.com');

        if (function_exists('bouncer') && ($userIds = bouncer()->getAuthorizedUserIds())) {
            $usersQuery->whereIn('id', $userIds);
        }

        $users = $usersQuery->pluck('name')->toArray();

        $labels = $users;
        $data = [];
        foreach ($users as $u) {
            $data[] = $countsByUser[$u] ?? 0;
        }

        return [
            'labels' => $labels,
            'data'   => $data,
            'users'  => $users,
        ];
    }

    public function getLeadsCountByBranches(): array
    {
        $tablePrefix = DB::getTablePrefix();

        $attribute = $this->attributeRepository->findWhere([
            'code'        => 'sucursal',
            'entity_type' => 'persons',
        ])->first();

        $query = $this->leadRepository
            ->resetModel()
            ->leftJoin('users', 'leads.user_id', '=', 'users.id')
            ->leftJoin('persons', 'leads.person_id', '=', 'persons.id')
            ->leftJoin('attribute_values as av', function ($join) use ($attribute, $tablePrefix) {
                $join->on('persons.id', '=', 'av.entity_id')
                     ->where('av.entity_type', '=', 'persons')
                     ->when($attribute, fn ($j) => $j->where('av.attribute_id', '=', $attribute->id));
            })
            ->select(
                DB::raw($tablePrefix.'av.integer_value as branch_id'),
                DB::raw($tablePrefix.'av.text_value as branch_text'),
                DB::raw('COUNT(DISTINCT '.$tablePrefix.'leads.id) AS count')
            )
            ->whereBetween('leads.created_at', [$this->startDate, $this->endDate])
            ->whereNotIn('leads.lead_pipeline_stage_id', $this->lostStageIds)
            ->groupBy('branch_id', 'branch_text');

        if (function_exists('bouncer') && ($userIds = bouncer()->getAuthorizedUserIds())) {
            $query->whereIn('users.id', $userIds);
        }

        $results = $query->get();

        $countsByName = [];

        foreach ($results as $row) {
            $name = null;

            if ($attribute && $attribute->lookup_type && $row->branch_id) {
                $entity = $this->attributeRepository->getLookUpEntity($attribute->lookup_type, $row->branch_id, ['id', 'name']);
                $name = $entity?->name;
            }

            $name = $name ?? ($row->branch_text ?: 'Sin sucursal');

            $countsByName[$name] = ($countsByName[$name] ?? 0) + (int) $row->count;
        }

        $labels = array_keys($countsByName);
        sort($labels, SORT_NATURAL | SORT_FLAG_CASE);

        $data = [];
        foreach ($labels as $label) {
            $data[] = $countsByName[$label];
        }

        return [
            'labels' => $labels,
            'data'   => $data,
        ];
    }

    public function getVentasCountByUsers(): array
    {
        $tablePrefix = DB::getTablePrefix();

        $query = $this->leadRepository
            ->resetModel()
            ->leftJoin('users', 'leads.user_id', '=', 'users.id')
            ->select(
                'users.id as user_id',
                'users.name as user_name',
                DB::raw('COUNT(DISTINCT CASE WHEN '.$tablePrefix.'leads.lead_pipeline_stage_id IN ('.implode(',', $this->wonStageIds).') THEN '.$tablePrefix.'leads.id END) AS count')
            )
            ->whereBetween('leads.closed_at', [$this->startDate, $this->endDate])
            ->where('users.email', '!=', 'jmogro@sofopolis.com')
            ->groupBy('users.id', 'users.name')
            ->orderBy('user_name');

        $results = $query->get();

        $countsByUser = [];

        foreach ($results as $row) {
            $countsByUser[$row->user_name ?? '—'] = (int) $row->count;
        }

        $usersQuery = $this->userRepository->resetModel()->select('name')->where('email', '!=', 'jmogro@sofopolis.com');

        if (function_exists('bouncer') && ($userIds = bouncer()->getAuthorizedUserIds())) {
            $usersQuery->whereIn('id', $userIds);
        }

        $users = $usersQuery->pluck('name')->toArray();

        $labels = $users;
        $data = [];
        foreach ($users as $u) {
            $data[] = $countsByUser[$u] ?? 0;
        }

        return [
            'labels' => $labels,
            'data'   => $data,
            'users'  => $users,
        ];
    }

    public function getAverageResponseTimeByUsers(): array
    {
        $tablePrefix = DB::getTablePrefix();

        $query = $this->leadRepository
            ->resetModel()
            ->leftJoin('users', 'leads.user_id', '=', 'users.id')
            ->leftJoin('lead_activities', 'leads.id', '=', 'lead_activities.lead_id')
            ->leftJoin('activities', 'lead_activities.activity_id', '=', 'activities.id')
            ->select(
                'users.id as user_id',
                'users.name as user_name',
                DB::raw('AVG(CASE WHEN '.$tablePrefix.'activities.created_at IS NOT NULL THEN TIMESTAMPDIFF(SECOND, '.$tablePrefix.'leads.created_at, '.$tablePrefix.'activities.created_at) END) as avg_response_seconds')
            )
            ->whereBetween('leads.created_at', [$this->startDate, $this->endDate])
            ->where('users.email', '!=', 'jmogro@sofopolis.com')
            ->groupBy('users.id', 'users.name')
            ->orderBy('user_name');

        $results = $query->get();

        $avgByUser = [];

        foreach ($results as $row) {
            $avgByUser[$row->user_name ?? '—'] = $row->avg_response_seconds ? (int) $row->avg_response_seconds : 0;
        }

        $usersQuery = $this->userRepository->resetModel()->select('name')->where('email', '!=', 'jmogro@sofopolis.com');

        if (function_exists('bouncer') && ($userIds = bouncer()->getAuthorizedUserIds())) {
            $usersQuery->whereIn('id', $userIds);
        }

        $users = $usersQuery->pluck('name')->toArray();

        $labels = $users;
        $data = [];
        foreach ($users as $u) {
            $data[] = $avgByUser[$u] ?? 0;
        }

        return [
            'labels' => $labels,
            'data'   => $data,
            'users'  => $users,
        ];
    }

    public function getVentasCountByBranches(): array
    {
        $tablePrefix = DB::getTablePrefix();

        $attribute = $this->attributeRepository->findWhere([
            'code'        => 'sucursal',
            'entity_type' => 'persons',
        ])->first();

        $query = $this->leadRepository
            ->resetModel()
            ->leftJoin('users', 'leads.user_id', '=', 'users.id')
            ->leftJoin('persons', 'leads.person_id', '=', 'persons.id')
            ->leftJoin('attribute_values as av', function ($join) use ($attribute, $tablePrefix) {
                $join->on('persons.id', '=', 'av.entity_id')
                     ->where('av.entity_type', '=', 'persons')
                     ->when($attribute, fn ($j) => $j->where('av.attribute_id', '=', $attribute->id));
            })
            ->select(
                DB::raw($tablePrefix.'av.integer_value as branch_id'),
                DB::raw($tablePrefix.'av.text_value as branch_text'),
                DB::raw('COUNT(DISTINCT CASE WHEN '.$tablePrefix.'leads.lead_pipeline_stage_id IN ('.implode(',', $this->wonStageIds).') THEN '.$tablePrefix.'leads.id END) AS count')
            )
            ->whereBetween('leads.closed_at', [$this->startDate, $this->endDate])
            ->groupBy('branch_id', 'branch_text');

        if (function_exists('bouncer') && ($userIds = bouncer()->getAuthorizedUserIds())) {
            $query->whereIn('users.id', $userIds);
        }

        $results = $query->get();

        $countsByName = [];

        foreach ($results as $row) {
            $name = null;

            if ($attribute && $attribute->lookup_type && $row->branch_id) {
                $entity = $this->attributeRepository->getLookUpEntity($attribute->lookup_type, $row->branch_id, ['id', 'name']);
                $name = $entity?->name;
            }

            $name = $name ?? ($row->branch_text ?: 'Sin sucursal');

            $countsByName[$name] = ($countsByName[$name] ?? 0) + (int) $row->count;
        }

        $labels = array_keys($countsByName);
        sort($labels, SORT_NATURAL | SORT_FLAG_CASE);

        $data = [];
        foreach ($labels as $label) {
            $data[] = $countsByName[$label];
        }

        return [
            'labels' => $labels,
            'data'   => $data,
        ];
    }

    /**
     * Determine the appropriate period based on date range
     *
     * @param  string  $period
     */
    protected function determinePeriod($period = 'auto'): string
    {
        if ($period !== 'auto') {
            return $period;
        }

        $diffInDays = $this->startDate->diffInDays($this->endDate);
        $diffInMonths = $this->startDate->diffInMonths($this->endDate);
        $diffInYears = $this->startDate->diffInYears($this->endDate);

        if ($diffInYears > 3) {
            return 'year';
        } elseif ($diffInMonths > 6) {
            return 'month';
        } elseif ($diffInDays > 60) {
            return 'week';
        } else {
            return 'day';
        }
    }

    /**
     * Retrieves total leads and their progress.
     */
    public function getTotalLeadsProgress(): array
    {
        return [
            'previous' => $previous = $this->getTotalLeads($this->lastStartDate, $this->lastEndDate),
            'current'  => $current = $this->getTotalLeads($this->startDate, $this->endDate),
            'progress' => $this->getPercentageChange($previous, $current),
        ];
    }

    /**
     * Retrieves total leads by date
     *
     * @param  \Carbon\Carbon  $startDate
     * @param  \Carbon\Carbon  $endDate
     */
    public function getTotalLeads($startDate, $endDate): int
    {
        return $this->leadRepository
            ->resetModel()
            ->whereBetween('created_at', [$startDate, $endDate])
            ->count();
    }

    /**
     * Retrieves average leads per day and their progress.
     */
    public function getAverageLeadsPerDayProgress(): array
    {
        return [
            'previous' => $previous = $this->getAverageLeadsPerDay($this->lastStartDate, $this->lastEndDate),
            'current'  => $current = $this->getAverageLeadsPerDay($this->startDate, $this->endDate),
            'progress' => $this->getPercentageChange($previous, $current),
        ];
    }

    /**
     * Retrieves average leads per day
     *
     * @param  \Carbon\Carbon  $startDate
     * @param  \Carbon\Carbon  $endDate
     */
    public function getAverageLeadsPerDay($startDate, $endDate): float
    {
        $days = $startDate->diffInDays($endDate);

        if ($days == 0) {
            return 0;
        }

        return $this->getTotalLeads($startDate, $endDate) / $days;
    }

    /**
     * Retrieves total lead value and their progress.
     */
    public function getTotalLeadValueProgress(): array
    {
        return [
            'previous'        => $previous = $this->getTotalLeadValue($this->lastStartDate, $this->lastEndDate),
            'current'         => $current = $this->getTotalLeadValue($this->startDate, $this->endDate),
            'formatted_total' => core()->formatBasePrice($current),
            'progress'        => $this->getPercentageChange($previous, $current),
        ];
    }

    /**
     * Retrieves total lead value
     *
     * @param  \Carbon\Carbon  $startDate
     * @param  \Carbon\Carbon  $endDate
     */
    public function getTotalLeadValue($startDate, $endDate): float
    {
        return $this->leadRepository
            ->resetModel()
            ->whereBetween('created_at', [$startDate, $endDate])
            ->sum('lead_value');
    }

    /**
     * Retrieves average lead value and their progress.
     */
    public function getAverageLeadValueProgress(): array
    {
        return [
            'previous'        => $previous = $this->getAverageLeadValue($this->lastStartDate, $this->lastEndDate),
            'current'         => $current = $this->getAverageLeadValue($this->startDate, $this->endDate),
            'formatted_total' => core()->formatBasePrice($current),
            'progress'        => $this->getPercentageChange($previous, $current),
        ];
    }

    /**
     * Retrieves average lead value
     *
     * @param  \Carbon\Carbon  $startDate
     * @param  \Carbon\Carbon  $endDate
     */
    public function getAverageLeadValue($startDate, $endDate): float
    {
        return $this->leadRepository
            ->resetModel()
            ->whereBetween('created_at', [$startDate, $endDate])
            ->avg('lead_value') ?? 0;
    }

    /**
     * Retrieves total won lead value and their progress.
     */
    public function getTotalWonLeadValueProgress(): array
    {
        return [
            'previous'        => $previous = $this->getTotalWonLeadValue($this->lastStartDate, $this->lastEndDate),
            'current'         => $current = $this->getTotalWonLeadValue($this->startDate, $this->endDate),
            'formatted_total' => core()->formatBasePrice($current),
            'progress'        => $this->getPercentageChange($previous, $current),
        ];
    }

    /**
     * Retrieves average won lead value
     *
     * @param  \Carbon\Carbon  $startDate
     * @param  \Carbon\Carbon  $endDate
     * @return array
     */
    public function getTotalWonLeadValue($startDate, $endDate): ?float
    {
        return $this->leadRepository
            ->resetModel()
            ->whereIn('lead_pipeline_stage_id', $this->wonStageIds)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->sum('lead_value');
    }

    /**
     * Retrieves average lost lead value and their progress.
     */
    public function getTotalLostLeadValueProgress(): array
    {
        return [
            'previous'        => $previous = $this->getTotalLostLeadValue($this->lastStartDate, $this->lastEndDate),
            'current'         => $current = $this->getTotalLostLeadValue($this->startDate, $this->endDate),
            'formatted_total' => core()->formatBasePrice($current),
            'progress'        => $this->getPercentageChange($previous, $current),
        ];
    }

    public function getTotalVentasCountProgress(): array
    {
        return [
            'previous' => $previous = $this->getVentasCount($this->lastStartDate, $this->lastEndDate),
            'current'  => $current = $this->getVentasCount($this->startDate, $this->endDate),
            'progress' => $this->getPercentageChange($previous, $current),
        ];
    }

    public function getVentasCount($startDate, $endDate): int
    {
        return $this->leadRepository
            ->resetModel()
            ->whereIn('lead_pipeline_stage_id', $this->wonStageIds)
            ->whereBetween('closed_at', [$startDate, $endDate])
            ->count('id');
    }

    /**
     * Retrieves average lost lead value
     *
     * @param  \Carbon\Carbon  $startDate
     * @param  \Carbon\Carbon  $endDate
     * @return array
     */
    public function getTotalLostLeadValue($startDate, $endDate): ?float
    {
        return $this->leadRepository
            ->resetModel()
            ->whereIn('lead_pipeline_stage_id', $this->lostStageIds)
            ->whereBetween('created_at', [$startDate, $endDate])
            ->sum('lead_value');
    }

    /**
     * Retrieves total lead value by sources.
     */
    public function getTotalWonLeadValueBySources()
    {
        return $this->leadRepository
            ->resetModel()
            ->when(request('user_id'), function ($q) {
                $q->where('leads.user_id', request('user_id'));
            })
            ->when(request('organization_id'), function ($q) {
                $q->leftJoin('persons', 'leads.person_id', '=', 'persons.id')
                  ->where('persons.organization_id', request('organization_id'));
            })
            ->select(
                'lead_sources.name',
                DB::raw('SUM(lead_value) as total')
            )
            ->leftJoin('lead_sources', 'leads.lead_source_id', '=', 'lead_sources.id')
            ->whereIn('lead_pipeline_stage_id', $this->wonStageIds)
            ->whereBetween('leads.created_at', [$this->startDate, $this->endDate])
            ->groupBy('lead_source_id')
            ->get();
    }

    /**
     * Retrieves total lead value by types.
     */
    public function getTotalWonLeadValueByTypes()
    {
        return $this->leadRepository
            ->resetModel()
            ->when(request('user_id'), function ($q) {
                $q->where('leads.user_id', request('user_id'));
            })
            ->when(request('organization_id'), function ($q) {
                $q->leftJoin('persons', 'leads.person_id', '=', 'persons.id')
                  ->where('persons.organization_id', request('organization_id'));
            })
            ->select(
                'lead_types.name',
                DB::raw('SUM(lead_value) as total')
            )
            ->leftJoin('lead_types', 'leads.lead_type_id', '=', 'lead_types.id')
            ->whereIn('lead_pipeline_stage_id', $this->wonStageIds)
            ->whereBetween('leads.created_at', [$this->startDate, $this->endDate])
            ->groupBy('lead_type_id')
            ->get();
    }

    /**
     * Retrieves open leads by states.
     */
    public function getOpenLeadsByStates()
    {
        return $this->leadRepository
            ->resetModel()
            ->when(request('user_id'), function ($q) {
                $q->where('leads.user_id', request('user_id'));
            })
            ->when(request('organization_id'), function ($q) {
                $q->leftJoin('persons', 'leads.person_id', '=', 'persons.id')
                  ->where('persons.organization_id', request('organization_id'));
            })
            ->select(
                'lead_pipeline_stages.name',
                DB::raw('COUNT(lead_value) as total')
            )
            ->leftJoin('lead_pipeline_stages', 'leads.lead_pipeline_stage_id', '=', 'lead_pipeline_stages.id')
            ->whereNotIn('lead_pipeline_stage_id', $this->wonStageIds)
            ->whereNotIn('lead_pipeline_stage_id', $this->lostStageIds)
            ->whereBetween('leads.created_at', [$this->startDate, $this->endDate])
            ->groupBy('lead_pipeline_stage_id')
            ->orderByDesc('total')
            ->get();
    }

    /**
     * Retrieves leads by stages for the default pipeline, keeping stage order fixed
     * and ensuring penultimate is Won and last is Lost.
     */
    public function getOpenLeadsByStatesFixed()
    {
        $pipeline = $this->pipelineRepository->getDefaultPipeline();
        $tablePrefix = DB::getTablePrefix();

        $stages = $pipeline->stages()->get();

        $wonCodes = ['won'];
        $lostCodes = ['lost'];

        $normalStages = $stages->filter(fn ($s) => ! in_array($s->code, array_merge($wonCodes, $lostCodes)))->values();

        $wonStage = $stages->first(fn ($s) => in_array($s->code, $wonCodes));
        $lostStage = $stages->first(fn ($s) => in_array($s->code, $lostCodes));

        $orderedStages = $normalStages;

        if ($wonStage) {
            $orderedStages = $orderedStages->push($wonStage);
        }

        if ($lostStage) {
            $orderedStages = $orderedStages->push($lostStage);
        }

        $counts = $this->leadRepository
            ->resetModel()
            ->when(request('user_id'), function ($q) {
                $q->where('leads.user_id', request('user_id'));
            })
            ->when(request('organization_id'), function ($q) {
                $q->leftJoin('persons', 'leads.person_id', '=', 'persons.id')
                  ->where('persons.organization_id', request('organization_id'));
            })
            ->select(
                'lead_pipeline_stages.id',
                'lead_pipeline_stages.name',
                DB::raw('COUNT('.$tablePrefix.'leads.id) as total')
            )
            ->leftJoin('lead_pipeline_stages', 'leads.lead_pipeline_stage_id', '=', 'lead_pipeline_stages.id')
            ->where('leads.lead_pipeline_id', $pipeline->id)
            ->whereBetween('leads.created_at', [$this->startDate, $this->endDate])
            ->groupBy('lead_pipeline_stages.id', 'lead_pipeline_stages.name')
            ->get()
            ->keyBy('id');

        return $orderedStages->map(function ($stage) use ($counts) {
            $stat = $counts->get($stage->id);

            return (object) [
                'name'  => $stage->name,
                'total' => $stat ? (int) $stat->total : 0,
            ];
        });
    }

    /**
     * Returns total leads grouped by stages for default pipeline, ordered by pipeline stages,
     * with Won as penultimate and Lost as last.
     */
    public function getTotalLeadsByStages(): array
    {
        $pipeline = $this->pipelineRepository->getDefaultPipeline();
        $tablePrefix = DB::getTablePrefix();

        $stages = $pipeline->stages()->get();

        $wonCodes = ['won'];
        $lostCodes = ['lost'];

        $normalStages = $stages->filter(fn ($s) => ! in_array($s->code, array_merge($wonCodes, $lostCodes)))->values();
        $wonStage = $stages->first(fn ($s) => in_array($s->code, $wonCodes));
        $lostStage = $stages->first(fn ($s) => in_array($s->code, $lostCodes));

        $orderedStages = $normalStages;
        if ($wonStage) { $orderedStages = $orderedStages->push($wonStage); }
        // Excluir la etapa Perdido de la vista "Etapas por fecha"

        $counts = $this->leadRepository
            ->resetModel()
            ->when(request('user_id'), function ($q) {
                $q->where('leads.user_id', request('user_id'));
            })
            ->when(request('organization_id'), function ($q) {
                $q->leftJoin('persons', 'leads.person_id', '=', 'persons.id')
                  ->where('persons.organization_id', request('organization_id'));
            })
            ->select(
                'lead_pipeline_stages.id',
                'lead_pipeline_stages.name',
                DB::raw('COUNT('.$tablePrefix.'leads.id) as total')
            )
            ->leftJoin('lead_pipeline_stages', 'leads.lead_pipeline_stage_id', '=', 'lead_pipeline_stages.id')
            ->where('leads.lead_pipeline_id', $pipeline->id)
            ->whereBetween('leads.created_at', [$this->startDate, $this->endDate])
            ->whereNotIn('leads.lead_pipeline_stage_id', $this->lostStageIds)
            ->groupBy('lead_pipeline_stages.id', 'lead_pipeline_stages.name')
            ->get()
            ->keyBy('id');

        return $orderedStages->map(function ($stage) use ($counts) {
            $stat = $counts->get($stage->id);
            return [
                'name'  => $stage->name,
                'total' => $stat ? (int) $stat->total : 0,
            ];
        })->toArray();
    }

    /**
     * Returns leads counts by stages over time for default pipeline.
     */
    public function getTotalLeadsByStagesOverTime(): array
    {
        $period = $this->determinePeriod('auto');
        $tablePrefix = DB::getTablePrefix();

        $pipeline = $this->pipelineRepository->getDefaultPipeline();

        $stages = $pipeline->stages()->get();

        $wonCodes = ['won'];
        $lostCodes = ['lost'];

        $normalStages = $stages->filter(fn ($s) => ! in_array($s->code, array_merge($wonCodes, $lostCodes)))->values();
        $wonStage = $stages->first(fn ($s) => in_array($s->code, $wonCodes));
        $lostStage = $stages->first(fn ($s) => in_array($s->code, $lostCodes));

        $orderedStages = $normalStages;
        if ($wonStage) { $orderedStages = $orderedStages->push($wonStage); }
        if ($lostStage) { $orderedStages = $orderedStages->push($lostStage); }

        $intervals = $this->generateTimeIntervals($this->startDate, $this->endDate, $period);
        $groupColumn = $this->getGroupColumn('leads.created_at', $period);

        $results = $this->leadRepository
            ->resetModel()
            ->when(request('user_id'), function ($q) {
                $q->where('leads.user_id', request('user_id'));
            })
            ->when(request('organization_id'), function ($q) {
                $q->leftJoin('persons', 'leads.person_id', '=', 'persons.id')
                  ->where('persons.organization_id', request('organization_id'));
            })
            ->leftJoin('lead_pipeline_stages', 'leads.lead_pipeline_stage_id', '=', 'lead_pipeline_stages.id')
            ->where('leads.lead_pipeline_id', $pipeline->id)
            ->whereBetween('leads.created_at', [$this->startDate, $this->endDate])
            ->select(
                DB::raw("$groupColumn AS date"),
                'lead_pipeline_stages.id as stage_id',
                DB::raw('COUNT('.$tablePrefix.'leads.id) as count')
            )
            ->groupBy(DB::raw($groupColumn), 'lead_pipeline_stages.id')
            ->orderBy(DB::raw($groupColumn))
            ->get();

        $byDateStage = [];
        foreach ($results as $row) {
            $dateKey = $row->date;
            $stageId = (int) $row->stage_id;
            $byDateStage[$dateKey][$stageId] = (int) $row->count;
        }

        $labels = array_map(fn ($i) => $i['label'], $intervals);

        $datasets = [];
        foreach ($orderedStages as $stage) {
            $data = [];
            foreach ($intervals as $interval) {
                $dateKey = $interval['key'];
                $data[] = (int) ($byDateStage[$dateKey][$stage->id] ?? 0);
            }

            $datasets[] = [
                'label' => $stage->name,
                'data'  => $data,
            ];
        }

        $total = 0;
        foreach ($datasets as $ds) {
            foreach ($ds['data'] as $v) { $total += $v; }
        }

        return [
            'labels'   => $labels,
            'datasets' => $datasets,
            'total'    => $total,
        ];
    }

    /**
     * Returns over time stats.
     *
     * @param  \Carbon\Carbon  $startDate
     * @param  \Carbon\Carbon  $endDate
     * @param  string  $valueColumn
     * @param  string  $dateColumn
     * @param  string  $period
     */
    public function getOverTimeStats($startDate, $endDate, $valueColumn, $dateColumn = 'created_at', $period = 'auto'): array
    {
        $period = $this->determinePeriod($period);

        $intervals = $this->generateTimeIntervals($startDate, $endDate, $period);

        $groupColumn = $this->getGroupColumn($dateColumn, $period);

        $query = $this->leadRepository
            ->resetModel()
            ->select(
                DB::raw("$groupColumn AS date"),
                DB::raw('COUNT(DISTINCT id) AS count'),
                DB::raw('SUM('.\DB::getTablePrefix()."$valueColumn) AS total")
            )
            ->whereIn('lead_pipeline_stage_id', $this->stageIds)
            ->whereBetween($dateColumn, [$startDate, $endDate])
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
                'total' => $result ? (float) $result->total : 0,
            ];
        }

        return $stats;
    }

    /**
     * Generate time intervals based on period
     */
    protected function generateTimeIntervals(Carbon $startDate, Carbon $endDate, string $period): array
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

    /**
     * Get the SQL group column based on period
     */
    protected function getGroupColumn(string $dateColumn, string $period): string
    {
        switch ($period) {
            case 'day':
                return "DATE($dateColumn)";
            case 'week':
                return "DATE_FORMAT($dateColumn, '%Y-%u')";
            case 'month':
                return "DATE_FORMAT($dateColumn, '%Y-%m')";
            case 'year':
                return "YEAR($dateColumn)";
            default:
                return "DATE($dateColumn)";
        }
    }

    /**
     * Format date for grouping key
     */
    protected function formatDateForGrouping(Carbon $date, string $period): string
    {
        switch ($period) {
            case 'day':
                return $date->format('Y-m-d');
            case 'week':
                return $date->format('Y-W');
            case 'month':
                return $date->format('Y-m');
            case 'year':
                return $date->format('Y');
            default:
                return $date->format('Y-m-d');
        }
    }

    /**
     * Format date for display label
     */
    protected function formatDateForLabel(Carbon $date, string $period): string
    {
        switch ($period) {
            case 'day':
                return $date->format('M d');
            case 'week':
                return 'Week '.$date->format('W, Y');
            case 'month':
                return $date->format('M Y');
            case 'year':
                return $date->format('Y');
            default:
                return $date->format('M d');
        }
    }

    /**
     * Calculates the average time it takes for a receptionist to move a patient between Kanban workflow stages.
     *
     * @param array $filters Optional filters for the calculation.
     *   - `start_date` (string): Start date for the analysis (YYYY-MM-DD HH:II:SS).
     *   - `end_date` (string): End date for the analysis (YYYY-MM-DD HH:II:SS).
     *   - `user_id` (int): ID of a specific receptionist to filter by.
     *   - `stage_id` (int): ID of a specific stage to filter by.
     * @return array An array containing the average time in seconds and a formatted time string (HH:MM).
     *   - `average_time` (float): The average time in seconds.
     *   - `formatted_time` (string): The average time formatted as HH:MM.
     * @throws \Exception If there is an error during the calculation.
     *
     * @example
     * // Get the average time for all receptionists in the last 30 days
     * $filters = [
     *     'start_date' => date('Y-m-d H:i:s', strtotime('-30 days')),
     *     'end_date'   => date('Y-m-d H:i:s'),
     * ];
     * $stats = $leadReporter->getAverageStageChangeTime($filters);
     * echo "Average stage change time: " . $stats['formatted_time'];
     */
    public function getAverageStageChangeTime(array $filters = []): array
    {
        $tablePrefix = DB::getTablePrefix();

        $query = DB::table('lead_activities')
            ->join('leads', 'lead_activities.lead_id', '=', 'leads.id')
            ->join('users', 'lead_activities.user_id', '=', 'users.id')
            ->join('roles', 'users.role_id', '=', 'roles.id')
            ->where('roles.name', 'Recepcionista')
            ->select(
                'leads.id as lead_id',
                'lead_activities.created_at as stage_change_time'
            )
            ->orderBy('leads.id')
            ->orderBy('lead_activities.created_at');

        if (isset($filters['start_date']) && isset($filters['end_date'])) {
            $query->whereBetween('lead_activities.created_at', [$filters['start_date'], $filters['end_date']]);
        }

        if (isset($filters['user_id'])) {
            $query->where('lead_activities.user_id', $filters['user_id']);
        }

        if (isset($filters['stage_id'])) {
            $query->where('leads.lead_pipeline_stage_id', $filters['stage_id']);
        }

        $stageChanges = $query->get();

        $timeDifferences = [];
        $lastLeadId = null;
        $lastStageChangeTime = null;

        foreach ($stageChanges as $change) {
            if ($lastLeadId === $change->lead_id) {
                $timeDifferences[] = strtotime($change->stage_change_time) - strtotime($lastStageChangeTime);
            }

            $lastLeadId = $change->lead_id;
            $lastStageChangeTime = $change->stage_change_time;
        }

        if (empty($timeDifferences)) {
            return [
                'average_time' => 0,
                'formatted_time' => '00:00',
            ];
        }

        $averageTimeInSeconds = array_sum($timeDifferences) / count($timeDifferences);

        return [
            'average_time' => $averageTimeInSeconds,
            'formatted_time' => $this->formatTime($averageTimeInSeconds),
        ];
    }

    private function formatTime(int $seconds): string
    {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);

        return sprintf('%02d:%02d', $hours, $minutes);
    }
}
