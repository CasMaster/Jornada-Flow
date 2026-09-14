<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\BackupFailed;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;

class NotifyBackupFailure extends Command
{
    protected $signature = 'hibrido:notify-backup-failure {--exit-code=1 : Código retornado pelo script de backup}';

    protected $description = 'Notifica os Super Admins ativos sobre uma falha no backup de produção';

    public function handle(): int
    {
        if (! config('app.mail_notifications_enabled')) {
            $this->error('Notificações por e-mail não estão habilitadas.');

            return self::FAILURE;
        }

        $exitCode = filter_var($this->option('exit-code'), FILTER_VALIDATE_INT);
        if ($exitCode === false || $exitCode < 1 || $exitCode > 255) {
            $this->error('Código de saída inválido.');

            return self::INVALID;
        }

        $administrators = User::query()
            ->where('role', 'super_admin')
            ->where('active', true)
            ->get();

        if ($administrators->isEmpty()) {
            $this->error('Nenhum Super Admin ativo encontrado para receber o alerta.');

            return self::FAILURE;
        }

        $failedAt = now()->timezone(config('app.timezone'))->format('d/m/Y H:i:s T');
        Notification::sendNow($administrators, new BackupFailed($exitCode, $failedAt));

        $this->info("Alerta enviado para {$administrators->count()} Super Admin(s).");

        return self::SUCCESS;
    }
}
