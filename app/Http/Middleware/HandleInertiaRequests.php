<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function share(Request $request): array
    {
        return [...parent::share($request), 'auth' => ['user' => $request->user()?->only(['id', 'name', 'email', 'email_verified_at'])], 'flash' => ['success' => fn () => $request->session()->get('success'), 'status' => fn () => $request->session()->get('status') === 'verification-link-sent' ? 'Письмо подтверждения отправлено.' : $request->session()->get('status')], 'telegram' => ['enabled' => filled(config('fitspot.telegram_client_id')), 'clientId' => config('fitspot.telegram_client_id')]];
    }
}
