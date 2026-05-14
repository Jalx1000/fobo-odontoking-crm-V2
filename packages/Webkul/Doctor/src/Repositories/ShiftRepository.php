<?php

namespace Webkul\Doctor\Repositories;

use Illuminate\Container\Container;
use Webkul\Core\Eloquent\Repository;
use Webkul\Doctor\Contracts\Shift as ShiftContract;

class ShiftRepository extends Repository
{
    protected $fieldSearchable = [
        'doctor_id',
        'date',
    ];

    public function __construct(Container $container)
    {
        parent::__construct($container);
    }

    public function model()
    {
        return ShiftContract::class;
    }

    /**
     * Determina si existe un turno solapado para el doctor en la misma fecha y rango horario.
     * Excluye el ID dado (útil en updates).
     */
    public function hasConflict(int $doctorId, string $date, string $startTime, string $endTime, ?int $excludeId = null): bool
    {
        $query = $this->getModel()->newQuery()
            ->where('doctor_id', $doctorId)
            ->where('date', $date)
            ->where('start_time', '<', $endTime)
            ->where('end_time', '>', $startTime);

        if ($excludeId !== null) {
            $query->where('id', '!=', $excludeId);
        }

        return $query->exists();
    }
}
