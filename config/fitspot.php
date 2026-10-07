<?php

return [
    'public_url' => env('PUBLIC_BASE_URL', env('APP_URL', 'http://localhost:8080')),
    'telegram_client_id' => env('TELEGRAM_CLIENT_ID'),
    'telegram_client_secret' => env('TELEGRAM_CLIENT_SECRET'),
    'telegram_jwks_url' => 'https://oauth.telegram.org/.well-known/jwks.json',
    'mail_queue' => 'mail',
    'reserved_slugs' => ['admin', 'app', 'api', 'login', 'logout', 'register', 'password', 'email', 'auth', 'p', 'storage', 'up'],
];
