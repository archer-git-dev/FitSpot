<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Notifications\PendingEmailVerification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AccessController extends Controller
{
    private function confirm(Request $r): void
    {
        $u = $r->user();
        if ($u->password && Hash::check((string) $r->input('current_password'), $u->password)) {
            return;
        }
        if ($r->session()->get('access_confirmed_at', 0) > time() - 300 && $u->telegram()->exists()) {
            return;
        }
        throw ValidationException::withMessages(['current_password' => 'Введите текущий пароль или подтвердите свой Telegram.']);
    }

    public function password(Request $r)
    {
        $this->confirm($r);
        $data = $r->validate(['password' => ['required', 'confirmed', Password::min(12)]]);
        $r->user()->forceFill(['password' => $data['password']])->save();
        $r->session()->forget('access_confirmed_at');

        return back()->with('success', 'Пароль сохранён.');
    }

    public function email(Request $r)
    {
        $this->confirm($r);
        $data = $r->validate(['email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($r->user()->id)]]);
        if ($data['email'] === $r->user()->email) {
            return back();
        }
        $r->user()->forceFill(['pending_email' => $data['email']])->save();
        Notification::route('mail', $data['email'])->notify(new PendingEmailVerification($data['email'], $r->user()->id));

        return back()->with('success', 'Письмо отправлено на новый адрес. До подтверждения действует прежний email.');
    }

    public function confirmEmail(Request $r, User $user)
    {
        abort_unless($r->hasValidSignature(), 403);
        DB::transaction(function () use ($r, $user) {
            $user = User::whereKey($user->id)->lockForUpdate()->firstOrFail();
            abort_unless($user->pending_email && hash_equals(hash('sha256', $user->pending_email), (string) $r->query('hash')), 403);
            if (User::where('email', $user->pending_email)->where('id', '!=', $user->id)->exists()) {
                throw ValidationException::withMessages(['email' => 'Этот email уже занят.']);
            }
            $user->forceFill(['email' => $user->pending_email, 'pending_email' => null, 'email_verified_at' => now()])->save();
        });

        return redirect('/app/access')->with('success', 'Новый email подтверждён.');
    }

    public function correctEmail(Request $r)
    {
        abort_if($r->user()->hasVerifiedEmail(), 403);
        $data = $r->validate(['email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($r->user()->id)]]);
        $r->user()->update(['email' => $data['email']]);
        $r->user()->sendEmailVerificationNotification();

        return back()->with('success', 'Адрес исправлен, письмо отправлено.');
    }

    public function unlinkTelegram(Request $r)
    {
        $this->confirm($r);
        DB::transaction(function () use ($r) {
            $u = User::whereKey($r->user()->id)->lockForUpdate()->firstOrFail();
            if (! $u->hasVerifiedEmail() || ! $u->password) {
                throw ValidationException::withMessages(['telegram' => 'Сначала установите пароль для входа по email.']);
            }
            $u->telegram()->delete();
        });
        $r->session()->forget('access_confirmed_at');

        return back()->with('success', 'Telegram отключён.');
    }
}
