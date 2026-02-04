<?php

return [
    [
        'key'        => 'doctor',
        'name'       => 'Doctor',
        'route'      => 'admin.doctor.index',
        'sort'       => 5,
        'icon-class' => 'icon-user',
    ],
    [
        'key'        => 'doctor.doctors',
        'name'       => 'Doctores',
        'route'      => 'admin.doctor.index',
        'sort'       => 1,
        'icon-class' => '',
    ],
    [
        'key'        => 'doctor.specialties',
        'name'       => 'Especialidades',
        'route'      => 'admin.specialties.index',
        'sort'       => 2,
        'icon-class' => '',
    ],
    [
        'key'        => 'doctor.schedules',
        'name'       => 'Horarios',
        'route'      => 'admin.schedules.index',
        'sort'       => 3,
        'icon-class' => '',
    ],
];
