<?php

namespace App\Providers;

use App\Modules\Scheduling\Models\TrainingService;
use App\Modules\Scheduling\Models\Workspace;
use App\Modules\Scheduling\Policies\TrainingServicePolicy;
use App\Modules\Scheduling\Policies\WorkspacePolicy;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        Gate::policy(Workspace::class, WorkspacePolicy::class);
        Gate::policy(TrainingService::class, TrainingServicePolicy::class);
        VerifyEmail::toMailUsing(fn ($u, $url) => (new MailMessage)->subject('Подтвердите email FitSpot')->line('Подтвердите email для доступа к панели тренера.')->action('Подтвердить email', $url)->line('Если вы не регистрировались, проигнорируйте письмо.'));
        ResetPassword::toMailUsing(fn ($u, $token) => (new MailMessage)->subject('Восстановление доступа FitSpot')->line('Ссылка действует 60 минут.')->action('Установить пароль', url('/reset-password/'.$token).'?email='.urlencode($u->email))->line('Если вы не запрашивали восстановление, проигнорируйте письмо.'));
    }
}
