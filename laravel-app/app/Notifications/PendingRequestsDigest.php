<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PendingRequestsDigest extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public int $count)
    {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return config('app.mail_notifications_enabled') ? ['database', 'mail'] : ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return ['type' => 'pending_digest', 'count' => $this->count, 'action_url' => route('manager.dashboard', ['status' => 'pending'])];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)->subject('Solicitações de home office pendentes')->greeting('Olá, '.$notifiable->name.'.')->line("Há {$this->count} solicitação(ões) aguardando análise perto do fechamento do ciclo.")->action('Analisar agora', route('manager.dashboard', ['status' => 'pending']));
    }
}
