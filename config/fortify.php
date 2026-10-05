<?php

use Laravel\Fortify\Features;

return [
    'guard' => 'web', 'passwords' => 'users', 'username' => 'email', 'email' => 'email', 'lowercase_usernames' => true, 'home' => '/app',
    'prefix' => '', 'domain' => null, 'middleware' => ['web'], 'limiters' => ['login' => 'login', 'two-factor' => 'two-factor', 'verification' => 'mail'], 'views' => true,
    'features' => [Features::registration(), Features::resetPasswords(), Features::emailVerification()],
];
