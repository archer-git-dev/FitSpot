<?php

namespace App\Providers;

use App\Actions\Fortify\CreateNewUser;
use App\Actions\Fortify\ResetUserPassword;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Inertia\Inertia;
use Laravel\Fortify\Contracts\FailedPasswordResetLinkRequestResponse;
use Laravel\Fortify\Contracts\SuccessfulPasswordResetLinkRequestResponse;
use Laravel\Fortify\Fortify;

class FortifyServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(FailedPasswordResetLinkRequestResponse::class, fn () => new class implements FailedPasswordResetLinkRequestResponse
        {
            public function toResponse($request)
            {
                return $request->wantsJson() ? response()->json(['message' => 'Если аккаунт существует, письмо отправлено.']) : back()->with('status', 'Если аккаунт существует, письмо отправлено.');
            }
        });
    }

    public function boot(): void
    {
        $this->app->singleton(SuccessfulPasswordResetLinkRequestResponse::class, fn () => new class implements SuccessfulPasswordResetLinkRequestResponse
        {
            public function toResponse($request)
            {
                return $request->wantsJson() ? response()->json(['message' => 'Если аккаунт существует, письмо отправлено.']) : back()->with('status', 'Если аккаунт существует, письмо отправлено.');
            }
        });
        Fortify::createUsersUsing(CreateNewUser::class);
        Fortify::resetUserPasswordsUsing(ResetUserPassword::class);
        Fortify::loginView(fn () => Inertia::render('Auth', ['mode' => 'login']));
        Fortify::registerView(fn () => Inertia::render('Auth', ['mode' => 'register']));
        Fortify::requestPasswordResetLinkView(fn () => Inertia::render('Auth', ['mode' => 'forgot']));
        Fortify::resetPasswordView(fn (Request $r) => Inertia::render('Auth', ['mode' => 'reset', 'token' => $r->route('token'), 'email' => $r->input('email')]));
        Fortify::verifyEmailView(fn () => Inertia::render('Auth', ['mode' => 'verify']));
        RateLimiter::for('login', fn (Request $r) => Limit::perMinute(5)->by(mb_strtolower($r->input('email', '')).'|'.$r->ip()));
        RateLimiter::for('registration', fn (Request $r) => Limit::perMinute(5)->by($r->ip()));
        RateLimiter::for('mail', fn (Request $r) => Limit::perMinute(1)->by(($r->user()?->id ?? $r->ip()).'|'.$r->path()));
        RateLimiter::for('telegram', fn (Request $r) => Limit::perMinute(10)->by($r->ip()));
    }
}
