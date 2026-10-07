<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

class PendingEmailVerification extends Notification implements ShouldQueue
{
    use Queueable;

    public $tries = 3;

    public $backoff = [10, 60, 300];

    public function __construct(public string $email, public int $userId)
    {
        $this->onQueue('mail')->afterCommit();
    }

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)->subject('Подтвердите новый email FitSpot')->line('Подтвердите изменение email аккаунта.')->action('Подтвердить email', URL::temporarySignedRoute('access.email.confirm', now()->addMinutes(60), ['user' => $this->userId, 'hash' => hash('sha256', $this->email)]))->line('Если вы не меняли email, проигнорируйте письмо.');
    }
}
