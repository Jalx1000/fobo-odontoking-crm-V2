<?php

namespace Webkul\Doctor\Repositories;

use Illuminate\Container\Container;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Webkul\Core\Eloquent\Repository;
use Webkul\Doctor\Contracts\Doctor as DoctorContract;

class DoctorRepository extends Repository
{
    protected $fieldSearchable = [
        'number',
        'name',
        'title',
        'specialties.name',
    ];

    public function __construct(
        protected SpecialtyRepository $specialtyRepository,
        Container $container
    ) {
        parent::__construct($container);
    }

    public function model()
    {
        return DoctorContract::class;
    }

    public function create(array $data)
    {
        $data = $this->sanitize($data);

        $specialtyIds = $this->resolveSpecialties($data);

        $doctor = parent::create(Arr::except($data, ['specialties', 'specialty_names']));

        if (! empty($specialtyIds)) {
            $doctor->specialties()->sync($specialtyIds);
        }

        return $doctor;
    }

    public function update(array $data, $id)
    {
        $data = $this->sanitize($data);

        $specialtyIds = $this->resolveSpecialties($data);

        $doctor = parent::update(Arr::except($data, ['specialties', 'specialty_names']), $id);

        if (! is_null($specialtyIds)) {
            $doctor->specialties()->sync($specialtyIds);
        }

        return $doctor;
    }

    protected function sanitize(array $data): array
    {
        $data['number'] = trim((string) ($data['number'] ?? ''));
        $data['name'] = trim((string) ($data['name'] ?? ''));
        $data['title'] = trim((string) ($data['title'] ?? ''));
        $data['unique_id'] = implode('|', array_filter([$data['number'] ?? null, $data['name'] ?? null]));
        $data['is_active'] = array_key_exists('is_active', $data) ? (bool) $data['is_active'] : true;

        return $data;
    }

    protected function resolveSpecialties(array $data): ?array
    {
        $ids = [];

        $specialties = $data['specialties'] ?? [];
        if (! is_array($specialties)) {
            if (is_string($specialties) && $specialties !== '') {
                $specialties = explode(',', $specialties);
            } else {
                $specialties = [$specialties];
            }
        }

        foreach ($specialties as $id) {
            if ($id) {
                $ids[] = (int) $id;
            }
        }

        foreach (($data['specialty_names'] ?? []) as $name) {
            $name = trim((string) $name);

            if ($name !== '') {
                $ids[] = $this->specialtyRepository->fetchOrCreateByName($name)->id;
            }
        }

        $ids = array_values(array_unique($ids));

        return $ids;
    }
}
