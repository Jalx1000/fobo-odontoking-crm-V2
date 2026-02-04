<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\Validator;

$emails = [
    'valid' => [
        'simple@example.com',
        'very.common@example.com',
        'disposable.style.email.with+symbol@example.com',
        'other.email-with-hyphen@example.com',
        'fully-qualified-domain@example.com',
        'user.name+tag+sorting@example.com',
        'x@example.com',
        'example-indeed@strange-example.com',
        'test/test@test.com',
        'admin@mailserver1',
        'example@s.example',
        'mailhost!username@example.org',
        'user%example.com@example.org',
    ],
    'invalid' => [
        'Abc.example.com',
        'A@b@c@example.com',
        'a"b(c)d,e:f;g<h>i[j\k]l@example.com',
        'just"not"right@example.com',
        'this is"not\allowed@example.com',
        'this\ still\"not\\allowed@example.com',
    ]
];

echo "Iniciando pruebas de validación de email (Backend Laravel)...\n\n";

foreach ($emails['valid'] as $email) {
    $validator = Validator::make(['email' => $email], ['email' => 'email']);
    if ($validator->fails()) {
        echo "[FALLÓ] El email válido '$email' fue rechazado.\n";
    } else {
        echo "[PASÓ] El email válido '$email' fue aceptado.\n";
    }
}

echo "\nPruebas de emails inválidos:\n";
foreach ($emails['invalid'] as $email) {
    $validator = Validator::make(['email' => $email], ['email' => 'email']);
    if ($validator->fails()) {
        echo "[PASÓ] El email inválido '$email' fue rechazado correctamente.\n";
    } else {
        echo "[FALLÓ] El email inválido '$email' fue aceptado incorrectamente.\n";
    }
}

echo "\nPruebas completadas.\n";
