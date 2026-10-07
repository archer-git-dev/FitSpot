<?php

use App\Actions\Fortify\CreateNewUser;
use App\Models\User;
use App\Modules\Scheduling\Services\ScheduleService;
use App\Modules\Scheduling\Services\TrainingServiceService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('fitspot:demo {--email=demo@fitspot.test}', function () {
    if (! app()->environment(['local', 'testing'])) {
        $this->error('Демо разрешено только в local/testing.');

        return 1;
    }
    $email = strtolower($this->option('email'));
    if (User::where('email', $email)->exists()) {
        $this->info('Демо-аккаунт уже существует; данные не изменены.');

        return 0;
    }
    DB::transaction(function () use ($email) {
        $user = app(CreateNewUser::class)->create(['name' => 'Анна Смирнова', 'email' => $email, 'password' => 'FitSpotDemo123!', 'password_confirmation' => 'FitSpotDemo123!']);
        $user->forceFill(['email_verified_at' => now()])->save();
        $workspace = $user->workspace;
        $workspace->update(['description' => 'Персональный тренер. Силовые тренировки и забота о движении.', 'location_name' => 'Студия движения']);
        app(ScheduleService::class)->replace($workspace, [['day' => 1, 'start' => 540, 'end' => 780], ['day' => 1, 'start' => 900, 'end' => 1200], ['day' => 3, 'start' => 600, 'end' => 1080]]);
        app(TrainingServiceService::class)->save($workspace, ['name' => 'Персональная тренировка', 'type' => 'personal', 'format' => 'in_person', 'duration' => 60, 'price_kopecks' => 250000, 'capacity' => 1, 'active' => true]);
    });
    $this->info('Создан демо-аккаунт '.$email.'; пароль: FitSpotDemo123!');
})->purpose('Создать демонстрационного тренера без изменения существующих аккаунтов');
