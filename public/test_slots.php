<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$kernel->handle(Illuminate\Http\Request::capture());

use Webkul\Admin\Services\ShareMeDataService;
use Carbon\Carbon;

$service = app(ShareMeDataService::class);

$doctorExternalId = '69bd9a9b7549b10008e0acfa'; // ID de ejemplo
$specialty = 'Odontopediatría';
$subsidiary = 'Santa Cruz';
$date = '2026-04-04';

echo "Consultando disponibilidad para $doctorExternalId en $date...\n";

$slots = $service->checkAvailability(
    $doctorExternalId,
    $specialty,
    $subsidiary,
    "$date 00:00:00",
    "$date 23:59:59"
);

echo json_encode($slots, JSON_PRETTY_PRINT) . "\n";
