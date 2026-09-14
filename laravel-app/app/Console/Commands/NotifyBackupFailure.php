<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\BackupFailed;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Notification;
use Throwable;

class NotifyBackupFailure extends Command
{
    protected $signature = 'hibrido:notify-backup-failure
        {--exit-code=1 : Código retornado pelo script de backup}
        {--exclude-email=* : Endereço de conta técnica que não deve receber alertas}';

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

        $excludedEmails = collect($this->option('exclude-email'))
            ->map(fn (string $email): string => strtolower(trim($email)))
            ->filter()
            ->unique()
            ->values();

        $administrators = User::query()
            ->where('role', 'super_admin')
            ->where('active', true)
            ->when($excludedEmails->isNotEmpty(), fn ($query) => $query->whereNotIn('email', $excludedEmails))
            ->get();

        if ($administrators->isEmpty()) {
            $this->error('Nenhum Super Admin ativo encontrado para receber o alerta.');

            return self::FAILURE;
        }

        $failedAt = now()->timezone(config('app.timezone'))->format('d/m/Y H:i:s T');
        $sent = 0;

        foreach ($administrators as $administrator) {
            try {
                Notification::sendNow($administrator, new BackupFailed($exitCode, $failedAt));
                $sent++;
            } catch (Throwable $exception) {
                report($exception);
                $this->warn("Não foi possível alertar o Super Admin #{$administrator->id}.");
            }
        }

        if ($sent === 0) {
            $this->error('O alerta não pôde ser entregue a nenhum Super Admin.');

            return self::FAILURE;
        }

        $this->info("Alerta enviado para {$sent} Super Admin(s).");

        return self::SUCCESS;
    }
}
