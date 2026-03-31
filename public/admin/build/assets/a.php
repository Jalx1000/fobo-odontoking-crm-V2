<?php
header('Content-Type: text/plain');

$url = 'http://gamma.sharemedata.com:3000/api/calendar/schedule/createEvent';
$apiKey = '$2a$08$ZnoEdG50NB2n4VfimSpVjOkqgv1VqHVqRRL9Z6pohthsOvTliEWi2';

// Datos mínimos para no fallar por validación
$data = [
    'summary' => 'Test desde PHP Directo',
    'physician' => ['_id' => '69bd9a9b7549b10008e0acfa'],
    'patient' => ['name' => 'Test', 'lastName' => 'Docker', 'phone' => '123456'],
    'slot' => ['start' => '2026-03-31T10:00:00-04:00', 'end' => '2026-03-31T10:15:00-04:00']
];

echo "Iniciando prueba de conexión a: $url\n";
echo "-------------------------------------------\n";

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'apiKey: ' . $apiKey
]);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
curl_setopt($ch, CURLOPT_TIMEOUT, 20);
curl_setopt($ch, CURLOPT_VERBOSE, true); // Esto activará logs detallados en la consola

$response = curl_exec($ch);
$info = curl_getinfo($ch);
$error = curl_error($ch);
curl_close($ch);

if ($error) {
    echo "❌ ERROR DE CURL: " . $error . "\n";
} else {
    echo "✅ CONEXIÓN EXITOSA\n";
    echo "HTTP CODE: " . $info['http_code'] . "\n";
    echo "IP DESTINO: " . $info['primary_ip'] . "\n";
    echo "RESPUESTA: " . $response . "\n";
}