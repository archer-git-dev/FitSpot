<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

class LimitAuthRequests
{
    public function handle(Request $r, Closure $next)
    {
        if ($r->isMethod('POST') && in_array($r->path(), ['register', 'forgot-password', 'reset-password'])) {
            $mail = $r->path() === 'forgot-password';
            $key = 'auth:'.$r->path().':'.$r->ip();
            $max = $mail ? 1 : 5;
            if (RateLimiter::tooManyAttempts($key, $max)) {
                return back()->withErrors(['email' => 'Слишком много запросов. Повторите через минуту.']);
            }
            RateLimiter::hit($key, 60);
        }

        return $next($r);
    }
}
