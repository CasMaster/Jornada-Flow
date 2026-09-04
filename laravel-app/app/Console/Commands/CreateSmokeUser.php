<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\AuditService;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class CreateSmokeUser extends Command
{
    protected $signature = 'hibrido:create-smoke-user {environment : homologacao ou producao}';

    protected $description = 'Cria uma conta sintética exclusiva para smoke test, sem sobrescrever usuários';

    public function handle(AuditService $audit): int
    {
        $environment = $this->argument('environment');
        $prefixes = ['homologacao' => 'homologacao', 'producao' => ''];
        if (! array_key_exists($environment, $prefixes)
            || trim((string) config('app.route_prefix'), '/') !== $prefixes[$environment]) {
            $this->error('Ambiente inválido ou incompatível com APP_ROUTE_PREFIX. Confira o container de destino.');

            return self::FAILURE;
        }

        if (! $this->input->isInteractive()) {
            $this->error('Execute em terminal interativo; a senha só pode ser informada em prompts ocultos.');

            return self::FAILURE;
        }

        $email = "smoke-{$environment}@mixhome.invalid";
        if (User::whereRaw('LOWER(email) = ?', [$email])->exists()) {
            $this->error('Conta já existente. Nenhum acesso foi alterado; solicite revisão ao Super Admin.');

            return self::FAILURE;
        }

        $this->line("Destino: {$environment}; conta sintética: {$email}; perfil: employee; sem equipe.");
        if (! $this->confirm('Confirma o container e o banco deste ambiente?')) {
            return self::FAILURE;
        }

        // Never fall back to visible input when the terminal cannot hide secrets.
        $password = $this->secret('Senha exclusiva do smoke test', false);
        $confirmation = $this->secret('Confirme a senha', false);
        $validator = Validator::make([
            'password' => $password, 'password_confirmation' => $confirmation,
        ], ['password' => ['required', 'string', 'confirmed', 'max:72', Password::min(20)->mixedCase()->numbers()->symbols()]]);
        if ($validator->fails() || strlen((string) $password) > 72) {
            $this->error('Senha inválida: use 20 a 72 caracteres (até 72 bytes), maiúsculas, minúsculas, números e símbolos; confirme exatamente.');

            return self::FAILURE;
        }

        try {
            DB::transaction(function () use ($environment, $email, $password, $audit): void {
                $user = User::create([
                    'name' => "Smoke Test Sintético - {$environment}",
                    'email' => $email, 'password' => Hash::make($password),
                    'role' => 'employee', 'team' => '', 'active' => true,
                ]);
                $audit->record('smoke_user.created', $user, [], ['environment' => $environment, 'role' => 'employee']);
            });
        } catch (QueryException) {
            // SQL exceptions can contain password hashes in their bindings.
            $this->error('Provisionamento não concluído. Confira disponibilidade do banco e possível conta concorrente; nenhum acesso existente foi alterado.');

            return self::FAILURE;
        }

        $this->info('Conta sintética criada. Cadastre os dois secrets somente no GitHub Environment correspondente.');

        return self::SUCCESS;
    }
}
