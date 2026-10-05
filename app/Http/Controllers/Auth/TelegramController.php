<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\TelegramIdentity;
use App\Models\User;
use App\Modules\Scheduling\Services\WorkspaceService;
use App\Services\Auth\TelegramTokenVerifier;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class TelegramController extends Controller
{
    public function nonce(Request $r)
    {
        abort_unless(config('fitspot.telegram_client_id'), 503, 'Telegram пока не настроен.');
        $mode = $r->validate(['mode' => 'required|in:login,link,reauth'])['mode'];
        if ($mode !== 'login') {
            abort_unless($r->user()?->hasVerifiedEmail(), 403);
        }
        $nonce = Str::random(64);
        $r->session()->put('telegram.attempt', ['nonce' => $nonce, 'mode' => $mode, 'expires' => time() + 600, 'user_id' => $r->user()?->id]);

        return response()->json(['nonce' => $nonce]);
    }

    public function verify(Request $r, TelegramTokenVerifier $verifier)
    {
        $r->validate(['id_token' => 'required|string|max:10000']);
        $attempt = $r->session()->pull('telegram.attempt');
        if (! $attempt || $attempt['expires'] < time() || $attempt['user_id'] !== $r->user()?->id) {
            throw ValidationException::withMessages(['telegram' => 'Запрос входа истёк. Попробуйте ещё раз.']);
        }
        $identity = $verifier->verify($r->input('id_token'), $attempt['nonce']);
        $existing = TelegramIdentity::where('subject', $identity['subject'])->first();
        if ($attempt['mode'] === 'reauth') {
            if (! $existing || $existing->user_id !== $r->user()->id) {
                throw ValidationException::withMessages(['telegram' => 'Подтвердите вход своим Telegram-аккаунтом.']);
            }
            $r->session()->put('access_confirmed_at', time());

            return redirect('/app/access')->with('success', 'Личность подтверждена на 5 минут.');
        }
        if ($attempt['mode'] === 'link') {
            if ($existing && $existing->user_id !== $r->user()->id) {
                throw ValidationException::withMessages(['telegram' => 'Этот Telegram уже связан с другим аккаунтом.']);
            }
            DB::transaction(function () use ($r, $identity) {
                User::whereKey($r->user()->id)->lockForUpdate()->firstOrFail();
                if ($r->user()->telegram()->exists()) {
                    throw ValidationException::withMessages(['telegram' => 'Сначала отключите текущий Telegram.']);
                }
                TelegramIdentity::create(['user_id' => $r->user()->id, 'subject' => $identity['subject']]);
            });

            return back()->with('success', 'Telegram подключён.');
        }
        if ($existing) {
            Auth::login($existing->user);
            $r->session()->regenerate();

            return redirect('/app');
        }
        $r->session()->put('telegram.signup', [...$identity, 'expires' => time() + 600]);

        return redirect('/auth/telegram/complete');
    }

    public function complete(Request $r)
    {
        $this->pending($r);

        return Inertia::render('Auth', ['mode' => 'telegram-email']);
    }

    public function signup(Request $r, WorkspaceService $workspaces)
    {
        $pending = $this->pending($r);
        $data = $r->validate(['email' => 'required|email|max:255|unique:users', 'name' => 'required|string|max:100']);
        $user = DB::transaction(function () use ($pending, $data, $workspaces) {
            if (TelegramIdentity::where('subject', $pending['subject'])->exists()) {
                throw ValidationException::withMessages(['telegram' => 'Аккаунт уже создан. Выполните вход.']);
            }
            $user = User::create($data);
            $workspaces->create($user);
            TelegramIdentity::create(['user_id' => $user->id, 'subject' => $pending['subject']]);

            return $user;
        });
        $r->session()->forget('telegram.signup');
        event(new Registered($user));
        Auth::login($user);
        $r->session()->regenerate();

        return redirect('/email/verify');
    }

    private function pending(Request $r): array
    {
        $pending = $r->session()->get('telegram.signup');
        abort_unless($pending && $pending['expires'] >= time(), 403, 'Регистрация истекла. Начните вход заново.');

        return $pending;
    }
}
