<?php

namespace App\Notifications;

use App\Models\VacationRequest;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class VacationStatusChanged extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public VacationRequest $vacation)
    {
        $this->afterCommit();
    }

    public function via(object $notifiable): array
    {
        return config('app.mail_notifications_enabled') ? ['database', 'mail'] : ['database'];
    }

    public function toArray(object $notifiable): array
    {
        return ['type' => 'vacation_status', 'vacation_request_id' => $this->vacation->id, 'request_type' => $this->vacation->request_type, 'status' => $this->vacation->status, 'starts_on' => $this->vacation->isAllowanceOnly() ? null : $this->vacation->starts_on->format('Y-m-d'), 'ends_on' => $this->vacation->isAllowanceOnly() ? null : $this->vacation->ends_on->format('Y-m-d'), 'cash_allowance_days' => $this->vacation->cash_allowance_days];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $label = ['approved' => 'aprovada', 'rejected' => 'recusada', 'cancelled' => 'cancelada'][$this->vacation->status] ?? 'atualizada';

        $description = $this->vacation->isAllowanceOnly()
            ? 'Sua solicitação de '.$this->vacation->cash_allowance_days.' dia(s) de abono foi '.$label.'.'
            : 'Sua solicitação de '.$this->vacation->starts_on->format('d/m/Y').' a '.$this->vacation->ends_on->format('d/m/Y').' foi '.$label.'.';

        return (new MailMessage)->subject('Atualização da solicitação de férias')->greeting('Olá, '.$notifiable->name.'.')->line($description)->action('Consultar férias', route('vacations.index'));
    }
}
