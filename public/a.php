<?php
header('Content-Type: text/plain');

// USAMOS LA IP DIRECTA PARA EVITAR EL "RESOLVING TIMED OUT"
$url = 'http://144.217.242.23/api/calendar/schedule/createEvent';
$apiKey = '$2a$08$ZnoEdG50NB2n4VfimSpVjOkqgv1VqHVqRRL9Z6pohthsOvTliEWi2';

$data = [
    'summary' => 'Prueba con IP Directa Carlos',
    'physician' => ['_id' => '69bd9a9b7549b10008e0acfa'],
    'patient' => [
        'name' => 'Test', 
        'lastName' => 'IP Directa', 
        'phone' => '59170000000'
    ],
    'slot' => [
        'start' => '2026-03-31T15:00:00-04:00', 
        'end' => '2026-03-31T15:15:00-04:00'
    ]
];

echo "Iniciando prueba de conexión DIRECTA a IP: $url\n";
echo "-------------------------------------------------------\n";

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

// CONFIGURACIÓN CRÍTICA
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Accept: application/json',
    'apiKey: ' . $apiKey,
    'Host: gamma.sharemedata.com' // LE DECIMOS AL SERVIDOR QUIÉNES SOMOS REALMENTE
]);

curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);

$response = curl_exec($ch);
$info = curl_getinfo($ch);
$error = curl_error($ch);
curl_close($ch);

if ($error) {
    echo "❌ ERROR SIGUE PERSISTIENDO: " . $error . "\n";
    echo "Si sale 'Failed to connect', el puerto 3000 está bloqueado en el Firewall del Panel.\n";
} else {
    echo "✅ ¡CONEXIÓN EXITOSA CON LA IP!\n";
    echo "HTTP CODE: " . $info['http_code'] . "\n";
    echo "RESPUESTA: " . $response . "\n";
}