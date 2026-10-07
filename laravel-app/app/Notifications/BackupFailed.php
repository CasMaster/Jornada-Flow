<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class BackupFailed extends Notification
{
    public function __construct(
        public int $exitCode,
        public string $failedAt,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'backup_failed',
            'exit_code' => $this->exitCode,
            'failed_at' => $this->failedAt,
        ];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $name = config('brand.name');

        return (new MailMessage)
            ->subject("[{$name}] Falha no backup de produção")
            ->greeting('Olá, '.$notifiable->name.'.')
            ->line("O backup diário do {$name} não foi concluído.")
            ->line("Horário da falha: {$this->failedAt}.")
            ->line("Código de saída: {$this->exitCode}.")
            ->line('Consulte /opt/backups/hibrido-home-office/backup.log no servidor e execute novamente o backup após corrigir a causa.');
    }
}
