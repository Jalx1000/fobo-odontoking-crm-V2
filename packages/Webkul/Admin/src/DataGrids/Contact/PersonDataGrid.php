<?php

namespace Webkul\Admin\DataGrids\Contact;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Webkul\Contact\Repositories\OrganizationRepository;
use Webkul\DataGrid\DataGrid;

class PersonDataGrid extends DataGrid
{
    /**
     * Create a new class instance.
     *
     * @return void
     */
    public function __construct(protected OrganizationRepository $organizationRepository) {}

    /**
     * Resolve the id of the "cliente_ciudad" custom attribute for persons.
     *
     * Looked up by code (not hardcoded) so the filter keeps working across
     * environments where the attribute may have a different id.
     */
    protected function getCityAttributeId(): ?int
    {
        static $attributeId;

        if ($attributeId === null) {
            $attributeId = DB::table('attributes')
                ->where('code', 'cliente_ciudad')
                ->where('entity_type', 'persons')
                ->value('id') ?? false;
        }

        return $attributeId ?: null;
    }

    /**
     * Resolve the id of the "Sin ciudad" pipeline, used as the bucket for
     * prospects that have no "cliente_ciudad" value.
     *
     * Looked up by name (not hardcoded) so it stays correct across environments.
     */
    protected function getNoCityPipelineId(): ?int
    {
        static $pipelineId;

        if ($pipelineId === null) {
            $pipelineId = DB::table('lead_pipelines')
                ->where('name', 'Sin ciudad')
                ->value('id') ?? false;
        }

        return $pipelineId ?: null;
    }

    /**
     * Prepare query builder.
     */
    public function prepareQueryBuilder(): Builder
    {
        $queryBuilder = DB::table('persons')
            ->addSelect(
                'persons.id',
                'persons.name as person_name',
                'persons.emails',
                'persons.contact_numbers',
                'persons.created_at',
                'organizations.name as organization',
                'organizations.id as organization_id'
            )
            ->leftJoin('organizations', 'persons.organization_id', '=', 'organizations.id');

        if ($userIds = bouncer()->getAuthorizedUserIds()) {
            $queryBuilder->whereIn('persons.user_id', $userIds);
        }

        /**
         * Global city filter (shared with the dashboard/Pedidos via the
         * "pipeline_id" query param + "global_pipeline_id" cookie).
         *
         * A person's city is stored as the custom "cliente_ciudad" select
         * attribute, a lookup to lead_pipelines whose selected pipeline id
         * lives in attribute_values.integer_value. So filtering by a city is a
         * direct match of that value against the requested pipeline_id.
         *
         * A prospect with no "cliente_ciudad" value is treated as "Sin ciudad",
         * so filtering by that city also returns the ones without any city (a
         * left join lets those NULL rows through).
         */
        $pipelineId = request()->query('pipeline_id');

        if (is_numeric($pipelineId) && ($cityAttributeId = $this->getCityAttributeId())) {
            $pipelineId = (int) $pipelineId;

            $queryBuilder->leftJoin('attribute_values as city_av', function ($join) use ($cityAttributeId) {
                $join->on('city_av.entity_id', '=', 'persons.id')
                    ->where('city_av.entity_type', 'persons')
                    ->where('city_av.attribute_id', $cityAttributeId);
            });

            if ($pipelineId === $this->getNoCityPipelineId()) {
                $queryBuilder->where(function ($query) use ($pipelineId) {
                    $query->where('city_av.integer_value', $pipelineId)
                        ->orWhereNull('city_av.integer_value');
                });
            } else {
                $queryBuilder->where('city_av.integer_value', $pipelineId);
            }
        }

        /**
         * Global date filter (shared with the dashboard/Pedidos via the
         * "start"/"end" query params + "global_date_range" cookie): restrict by
         * the prospect's creation date.
         */
        $start = request()->query('start');

        $end = request()->query('end');

        if ($start && $end) {
            try {
                $startDate = Carbon::parse($start)->startOfDay();

                $endDate = Carbon::parse($end)->endOfDay();

                if ($startDate->lte($endDate)) {
                    $queryBuilder->whereBetween('persons.created_at', [$startDate, $endDate]);
                }
            } catch (\Exception $e) {
                // Ignore an invalid range and fall back to the unfiltered list.
            }
        }

        $this->addFilter('id', 'persons.id');
        $this->addFilter('person_name', 'persons.name');
        $this->addFilter('organization', 'organizations.name');

        return $queryBuilder;
    }

    /**
     * Add columns.
     */
    public function prepareColumns(): void
    {
        $this->addColumn([
            'index'      => 'id',
            'label'      => trans('admin::app.contacts.persons.index.datagrid.id'),
            'type'       => 'integer',
            'filterable' => true,
            'sortable'   => true,
            'searchable' => true,
        ]);

        $this->addColumn([
            'index'      => 'person_name',
            'label'      => trans('admin::app.contacts.persons.index.datagrid.name'),
            'type'       => 'string',
            'sortable'   => true,
            'filterable' => true,
            'searchable' => true,
        ]);

        $this->addColumn([
            'index'      => 'emails',
            'label'      => trans('admin::app.contacts.persons.index.datagrid.emails'),
            'type'       => 'string',
            'sortable'   => false,
            'filterable' => true,
            'searchable' => true,
            'closure'    => fn ($row) => collect(json_decode($row->emails, true) ?? [])->pluck('value')->join(', '),
        ]);

        $this->addColumn([
            'index'      => 'contact_numbers',
            'label'      => trans('admin::app.contacts.persons.index.datagrid.contact-numbers'),
            'type'       => 'string',
            'sortable'   => true,
            'filterable' => true,
            'searchable' => true,
            'closure'    => fn ($row) => collect(json_decode($row->contact_numbers, true) ?? [])->pluck('value')->join(', '),
        ]);

        $this->addColumn([
            'index'              => 'organization',
            'label'              => trans('admin::app.contacts.persons.index.datagrid.organization-name'),
            'type'               => 'string',
            'searchable'         => true,
            'filterable'         => true,
            'sortable'           => true,
            'filterable_type'    => 'searchable_dropdown',
            'filterable_options' => [
                'repository' => OrganizationRepository::class,
                'column'     => [
                    'label' => 'name',
                    'value' => 'name',
                ],
            ],
        ]);
    }

    /**
     * Prepare actions.
     */
    public function prepareActions(): void
    {
        if (bouncer()->hasPermission('contacts.persons.view')) {
            $this->addAction([
                'icon'   => 'icon-eye',
                'title'  => trans('admin::app.contacts.persons.index.datagrid.view'),
                'method' => 'GET',
                'url'    => function ($row) {
                    return route('admin.contacts.persons.view', $row->id);
                },
            ]);
        }

        if (bouncer()->hasPermission('contacts.persons.edit')) {
            $this->addAction([
                'icon'   => 'icon-edit',
                'title'  => trans('admin::app.contacts.persons.index.datagrid.edit'),
                'method' => 'GET',
                'url'    => function ($row) {
                    return route('admin.contacts.persons.edit', $row->id);
                },
            ]);
        }

        if (bouncer()->hasPermission('contacts.persons.delete')) {
            $this->addAction([
                'icon'   => 'icon-delete',
                'title'  => trans('admin::app.contacts.persons.index.datagrid.delete'),
                'method' => 'DELETE',
                'url'    => function ($row) {
                    return route('admin.contacts.persons.delete', $row->id);
                },
            ]);
        }
    }

    /**
     * Prepare mass actions.
     */
    public function prepareMassActions(): void
    {
        if (bouncer()->hasPermission('contacts.persons.delete')) {
            $this->addMassAction([
                'icon'   => 'icon-delete',
                'title'  => trans('admin::app.contacts.persons.index.datagrid.delete'),
                'method' => 'POST',
                'url'    => route('admin.contacts.persons.mass_delete'),
            ]);
        }
    }
}
