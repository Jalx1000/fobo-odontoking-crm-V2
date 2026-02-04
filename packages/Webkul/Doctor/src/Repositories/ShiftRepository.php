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
}
