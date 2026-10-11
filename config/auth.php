<?php

use App\Models\Usuario;

return [
    'defaults' => ['guard' => 'web', 'passwords' => 'usuarios'],
    'guards' => [
        'web' => ['driver' => 'session', 'provider' => 'usuarios'],
    ],
    'providers' => [
        'usuarios' => ['driver' => 'eloquent', 'model' => Usuario::class],
    ],
    'passwords' => [],
    'password_timeout' => 10800,
];
