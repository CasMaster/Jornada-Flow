<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class CreateAdmin extends Command
{
    protected $signature = 'hibrido:create-admin {--name=} {--email=} {--password=}';

    protected $description = 'Cria ou atualiza o acesso inicial de Super Admin';

    public function handle(): int
    {
        $data = ['name' => $this->option('name') ?: $this->ask('Nome'), 'email' => $this->option('email') ?: $this->ask('E-mail'), 'password' => $this->option('password') ?: $this->secret('Senha')];
        $validator = Validator::make($data, ['name' => ['required', 'string'], 'email' => ['required', 'email'], 'password' => ['required', Password::min(8)]]);
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }
        User::updateOrCreate(['email' => strtolower($data['email'])], ['name' => $data['name'], 'password' => $data['password'], 'role' => 'super_admin', 'team' => '', 'active' => true]);
        $this->info('Super Admin salvo.');

        return self::SUCCESS;
    }
}
