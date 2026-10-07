<?php

use App\Http\Controllers\Auth\AccessController;
use App\Http\Controllers\Auth\TelegramController;
use App\Modules\Scheduling\Http\Controllers\PanelController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => redirect('/login'));
Route::post('/auth/telegram/nonce', [TelegramController::class, 'nonce'])->middleware('throttle:telegram');
Route::post('/auth/telegram/verify', [TelegramController::class, 'verify'])->middleware('throttle:telegram');
Route::middleware(['guest', 'throttle:registration'])->group(function () {
    Route::get('/auth/telegram/complete', [TelegramController::class, 'complete']);
    Route::post('/auth/telegram/complete', [TelegramController::class, 'signup']);
});
Route::middleware('auth')->group(function () {
    Route::post('/email/correct', [AccessController::class, 'correctEmail'])->middleware('throttle:mail');
});
Route::get('/access/email/confirm/{user}', [AccessController::class, 'confirmEmail'])->name('access.email.confirm')->middleware('signed');
Route::middleware(['auth', 'verified'])->prefix('app')->group(function () {
    Route::get('/', [PanelController::class, 'show'])->name('dashboard');
    Route::get('/photo', [PanelController::class, 'photo'])->name('workspace.photo');
    Route::post('/profile', [PanelController::class, 'profile']);
    Route::get('/schedule/calendar', [PanelController::class, 'calendarRange']);
    Route::put('/schedule/dates', [PanelController::class, 'calendar']);
    Route::delete('/schedule/dates/{date}', [PanelController::class, 'resetDate']);
    Route::put('/schedule', [PanelController::class, 'schedule']);
    Route::put('/rules', [PanelController::class, 'rules']);
    Route::post('/services', [PanelController::class, 'saveService']);
    Route::put('/services/{id}', [PanelController::class, 'saveService'])->whereNumber('id');
    Route::patch('/services/{id}/status', [PanelController::class, 'serviceStatus'])->whereNumber('id');
    Route::post('/access/password', [AccessController::class, 'password']);
    Route::post('/access/email', [AccessController::class, 'email'])->middleware('throttle:mail');
    Route::delete('/access/telegram', [AccessController::class, 'unlinkTelegram']);
    Route::get('/{section}', [PanelController::class, 'show'])->where('section', 'overview|profile|schedule|services|rules|access');
});
