<?php

return [

    // Primeira conta de acesso (admin), criada por `make db-seed`. As demais chegam pelo cadastro público e um admin aprova.
    'admin' => [
        'name' => env('SEED_ADMIN_NAME'),
        'email' => env('SEED_ADMIN_EMAIL'),
        'password' => env('SEED_ADMIN_PASSWORD'),
    ],

    // Usuários de demonstração com visitas e "seguindo" (`DemoUsersSeeder`): só local, nunca em produção.
    'demo' => [
        'enabled' => (bool)env('SEED_DEMO_USERS', false),
        'password' => env('SEED_DEMO_PASSWORD'),
    ],

];
