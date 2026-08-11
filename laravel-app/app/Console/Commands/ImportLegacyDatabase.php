<?php

namespace App\Console\Commands;

use App\Models\Team;
use App\Models\User;
use App\Models\WorkRequest;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use PDO;

class ImportLegacyDatabase extends Command
{
    protected $signature = 'hibrido:import-legacy {path : Caminho para uma cópia do SQLite antigo} {--force : Importar mesmo com usuários existentes}';

    protected $description = 'Importa dados do sistema PHP sem alterar o arquivo de origem';

    public function handle(): int
    {
        $path = realpath($this->argument('path'));
        if (! $path || ! is_file($path)) {
            $this->error('Arquivo não encontrado.');

            return self::FAILURE;
        }
        if (User::exists() && ! $this->option('force')) {
            $this->error('Destino não está vazio. Use --force apenas após conferir o banco.');

            return self::FAILURE;
        }
        $legacy = new PDO('sqlite:'.$path);
        $legacy->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $tables = $legacy->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);
        foreach (['teams', 'users', 'records'] as $required) {
            if (! in_array($required, $tables, true)) {
                $this->error("Tabela obrigatória ausente: $required");

                return self::FAILURE;
            }
        }
        $counts = DB::transaction(function () use ($legacy, $tables) {
            $teamIds = [];
            foreach ($legacy->query('SELECT * FROM teams') as $row) {
                $team = Team::updateOrCreate(['name' => $row['name']], ['active' => (bool) $row['active']]);
                $teamIds[(int) $row['id']] = $team->id;
            }
            $users = [];
            $userIds = [];
            foreach ($legacy->query('SELECT * FROM users') as $row) {
                $u = User::updateOrCreate(['email' => strtolower($row['email'])], ['name' => $row['name'], 'team' => $row['team'] ?? '', 'password' => $row['password_hash'], 'role' => $row['role'] ?? 'employee', 'active' => (bool) ($row['active'] ?? 1), 'created_at' => $row['created_at'] ?? now()]);
                $users[strtolower($row['email'])] = $u;
                $userIds[(int) $row['id']] = $u->id;
            }
            if (in_array('manager_teams', $tables, true)) {
                foreach ($legacy->query('SELECT * FROM manager_teams') as $row) {
                    $manager = User::find($userIds[(int) $row['manager_id']] ?? 0);
                    $teamId = $teamIds[(int) $row['team_id']] ?? null;
                    if ($manager && $teamId) {
                        $manager->managedTeams()->syncWithoutDetaching([$teamId]);
                    }
                }
            } else {
                foreach ($userIds as $userId) {
                    $manager = User::find($userId);
                    if ($manager?->role === 'manager' && $manager->team) {
                        $team = Team::where('name', $manager->team)->first();
                        if ($team) {
                            $manager->managedTeams()->syncWithoutDetaching([$team->id]);
                        }
                    }
                }
            }
            foreach ($legacy->query('SELECT * FROM records') as $row) {
                $u = $users[strtolower($row['email'])] ?? null;
                if (! $u) {
                    continue;
                } WorkRequest::updateOrCreate(['user_id' => $u->id, 'work_date' => $row['work_date']], ['status' => $row['status'] ?? 'approved', 'reviewed_by' => isset($row['reviewed_by']) ? ($userIds[(int) $row['reviewed_by']] ?? null) : null, 'reviewed_at' => $row['reviewed_at'] ?? null, 'created_at' => $row['created_at'] ?? now()]);
            }

            return [User::count(), Team::count(), WorkRequest::count()];
        });
        $this->info("Importação concluída: {$counts[0]} usuários, {$counts[1]} equipes e {$counts[2]} solicitações.");

        return self::SUCCESS;
    }
}
