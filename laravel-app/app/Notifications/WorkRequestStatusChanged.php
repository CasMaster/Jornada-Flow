<?php

namespace App\Notifications;

use App\Models\WorkRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WorkRequestStatusChanged extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public WorkRequest $record)
    {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return config('app.mail_notifications_enabled') ? ['database', 'mail'] : ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return ['work_request_id' => $this->record->id, 'date' => $this->record->work_date->format('Y-m-d'), 'status' => $this->record->status];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $status = $this->record->status === 'approved' ? 'aprovada' : 'recusada';

        return (new MailMessage)->subject('Atualização da solicitação de home office')->greeting('Olá, '.$notifiable->name.'.')->line('Sua solicitação de '.$this->record->work_date->format('d/m/Y').' foi '.$status.'.')->action('Ver histórico', route('employee.dashboard'));
    }
}
