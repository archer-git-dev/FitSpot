<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\LimitAuthRequests;
use App\Http\Middleware\NormalizeEmail;
use App\Http\Middleware\RequireVerifiedAccount;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            NormalizeEmail::class,
            LimitAuthRequests::class,
            HandleInertiaRequests::class,
            RequireVerifiedAccount::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->dontFlash(['id_token']);
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
        $exceptions->render(function (QueryException $e) {
            if (($e->errorInfo[0] ?? null) !== '23505') {
                return null;
            }
            $message = $e->getMessage();
            $field = str_contains($message, 'slug') ? 'slug' : (str_contains($message, 'telegram_identities') ? 'telegram' : 'email');
            $errors = [$field => 'Это значение уже используется. Обновите данные и повторите попытку.'];

            return request()->expectsJson()
                ? response()->json(['message' => 'Значение уже занято.', 'errors' => $errors], 422)
                : redirect()->back()->withErrors($errors)->withInput(request()->except(['password', 'password_confirmation', 'current_password', 'id_token']));
        });
    })->create();
