<?php

namespace Tests\Feature;

use App\Models\User;
use App\Notifications\BackupFailed;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class BackupFailureNotificationTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_notifies_only_active_super_admins_immediately(): void
    {
        Notification::fake();
        config(['app.mail_notifications_enabled' => true]);

        $administrator = User::factory()->create(['role' => 'super_admin', 'active' => true]);
        $inactiveAdministrator = User::factory()->create(['role' => 'super_admin', 'active' => false]);
        $manager = User::factory()->create(['role' => 'manager', 'active' => true]);
        $placeholder = User::factory()->create(['role' => 'super_admin', 'active' => true, 'email' => 'gestor@mixfiscal.com.br']);

        $this->artisan('hibrido:notify-backup-failure', [
            '--exit-code' => 23,
            '--exclude-email' => ['gestor@mixfiscal.com.br'],
        ])
            ->expectsOutput('Alerta enviado para 1 Super Admin(s).')
            ->assertSuccessful();

        Notification::assertSentTo(
            $administrator,
            BackupFailed::class,
            fn (BackupFailed $notification): bool => $notification->exitCode === 23
                && $notification->via($administrator) === ['database', 'mail'],
        );
        Notification::assertNotSentTo($inactiveAdministrator, BackupFailed::class);
        Notification::assertNotSentTo($manager, BackupFailed::class);
        Notification::assertNotSentTo($placeholder, BackupFailed::class);
    }

    public function test_it_fails_safely_when_mail_notifications_are_disabled(): void
    {
        Notification::fake();
        config(['app.mail_notifications_enabled' => false]);
        User::factory()->create(['role' => 'super_admin', 'active' => true]);

        $this->artisan('hibrido:notify-backup-failure')
            ->expectsOutput('Notificações por e-mail não estão habilitadas.')
            ->assertFailed();

        Notification::assertNothingSent();
    }
}
