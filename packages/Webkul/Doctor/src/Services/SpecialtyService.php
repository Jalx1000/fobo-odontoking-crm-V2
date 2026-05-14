<?php

namespace Webkul\Doctor\Services;

use Webkul\Doctor\Repositories\SpecialtyRepository;

class SpecialtyService
{
    public function __construct(
        protected SpecialtyRepository $specialtyRepository
    ) {}

    /**
     * Resuelve IDs de especialidades a partir de IDs existentes y/o nombres.
     * Crea las especialidades por nombre que no existan aún.
     *
     * @param  array  $specialtyIds  IDs de especialidades ya existentes
     * @param  array  $specialtyNames  Nombres a buscar o crear
     * @return array Lista única de IDs
     */
    public function resolveOrCreateMany(array $specialtyIds = [], array $specialtyNames = []): array
    {
        $ids = array_map('intval', array_filter($specialtyIds));

        foreach ($specialtyNames as $name) {
            $name = trim((string) $name);

            if ($name !== '') {
                $ids[] = $this->specialtyRepository->fetchOrCreateByName($name)->id;
            }
        }

        return array_values(array_unique($ids));
    }
}
