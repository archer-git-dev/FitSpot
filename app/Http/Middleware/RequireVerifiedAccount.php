<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class RequireVerifiedAccount
{
    public function handle(Request $r, Closure $next)
    {
        if ($r->user() && ! $r->user()->hasVerifiedEmail() && ! $r->is('email/verify', 'email/verify/*', 'email/verification-notification', 'email/correct', 'logout')) {
            return redirect('/email/verify');
        }

        return $next($r);
    }
}
