<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\KeycloakAdminService;
use Illuminate\Console\Command;
use Throwable;

class MigrateUsersToKeycloak extends Command
{
    protected $signature = 'hibrido:keycloak-migrate-users
        {--apply : Efetiva vínculos e criações}
        {--create-missing : Cria no Keycloak as contas locais ausentes}';

    protected $description = 'Simula ou executa a migração segura das contas locais para o Keycloak';

    public function handle(KeycloakAdminService $keycloak): int
    {
        if (! $keycloak->configured()) {
            $this->error('Cliente administrativo não configurado. Consulte docs/KEYCLOAK.md.');

            return self::FAILURE;
        }

        $apply = (bool) $this->option('apply');
        $createMissing = (bool) $this->option('create-missing');
        $summary = ['linked' => 0, 'link' => 0, 'create' => 0, 'pending' => 0, 'excluded' => 0, 'conflict' => 0, 'error' => 0];
        $this->info($apply ? 'MODO APLICAÇÃO' : 'MODO SIMULAÇÃO — nenhuma alteração será feita');

        User::query()->where('active', true)->orderBy('id')->each(function (User $user) use ($keycloak, $apply, $createMissing, &$summary): void {
            if ($this->isTechnicalAccount($user->email)) {
                $summary['excluded']++;
                $this->line("IGNORADO    {$user->email} (conta técnica)");

                return;
            }

            if ($user->keycloak_subject) {
                $summary['linked']++;
                $this->line("VINCULADO  {$user->email}");

                return;
            }

            try {
                $matches = $keycloak->usersByEmail($user->email);
                if (count($matches) > 1) {
                    $summary['conflict']++;
                    $this->warn("CONFLITO   {$user->email} (mais de uma conta no Keycloak)");

                    return;
                }

                if (count($matches) === 1) {
                    $remote = $matches[0];
                    if (! ($remote['emailVerified'] ?? false) || empty($remote['id'])) {
                        $summary['conflict']++;
                        $this->warn("CONFLITO   {$user->email} (e-mail remoto não confirmado)");

                        return;
                    }
                    $summary['link']++;
                    $this->line(($apply ? 'VINCULANDO ' : 'VINCULARIA ')." {$user->email}");
                    if ($apply) {
                        $user->forceFill(['keycloak_subject' => $remote['id']])->save();
                    }

                    return;
                }

                if (! $createMissing) {
                    $summary['pending']++;
                    $this->line("PENDENTE   {$user->email} (não existe no Keycloak)");

                    return;
                }

                $summary['create']++;
                $this->line(($apply ? 'CRIANDO   ' : 'CRIARIA   ')." {$user->email}");
                if ($apply) {
                    $roles = $user->role === 'super_admin'
                        ? [config('oidc.user_role'), config('oidc.admin_role')]
                        : [config('oidc.user_role')];
                    $subject = $keycloak->createUser($user->name, $user->email, $roles);
                    $user->forceFill(['keycloak_subject' => $subject])->save();
                }
            } catch (Throwable $exception) {
                $summary['error']++;
                report($exception);
                $this->error("ERRO       {$user->email}: não foi possível processar a conta");
            }
        });

        $this->newLine();
        $this->table(['Já vinculadas', 'Vínculos', 'Criações', 'Pendentes', 'Ignoradas', 'Conflitos', 'Erros'], [[
            $summary['linked'], $summary['link'], $summary['create'], $summary['pending'], $summary['excluded'], $summary['conflict'], $summary['error'],
        ]]);
        if (! $apply) {
            $this->comment('Revise o resultado e repita com --apply. Para criar ausentes, acrescente --create-missing.');
        }

        return ($summary['conflict'] + $summary['error']) > 0 ? self::FAILURE : self::SUCCESS;
    }

    private function isTechnicalAccount(string $email): bool
    {
        $normalized = strtolower(trim($email));
        $excluded = array_map('strtolower', config('oidc.migration.excluded_emails', []));

        return filter_var($normalized, FILTER_VALIDATE_EMAIL) === false
            || str_ends_with($normalized, '.invalid')
            || in_array($normalized, $excluded, true);
    }
}
