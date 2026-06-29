<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Webkul\User\Models\User;

uses(DatabaseTransactions::class);

/**
 * El layout del tablero cambia según el rol: un Administrador ve el card "ventas",
 * mientras que el resto ve "tiempo-por-vendedor". Antes la detección comparaba
 * === 'admin' (nunca coincidía con "Administrator") y el card ventas quedaba muerto.
 */
it('un administrador ve el card de ventas', function () {
    $admin = User::find(1); // rol Administrator (id 1)

    $this->actingAs($admin, 'user')
        ->get(route('admin.dashboard.index'))
        ->assertStatus(200)
        ->assertSee('v-dashboard-ventas', false);
});

it('un recepcionista ve tiempo-por-vendedor y no ve ventas', function () {
    $staff = User::create([
        'name'     => 'Recepcion '.uniqid(),
        'email'    => 'staff'.uniqid().'@test.com',
        'password' => bcrypt('secret'),
        'role_id'  => 2, // Recepcionista
        'status'   => 1,
    ]);

    $this->actingAs($staff, 'user')
        ->get(route('admin.dashboard.index'))
        ->assertStatus(200)
        ->assertSee('v-dashboard-tiempo-por-vendedor', false)
        ->assertDontSee('<v-dashboard-ventas', false);
});
