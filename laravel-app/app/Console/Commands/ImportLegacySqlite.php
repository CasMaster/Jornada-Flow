<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RuntimeException;

class ImportLegacySqlite extends Command
{
    protected $signature = 'hibrido:import-sqlite {--path= : Caminho do banco SQLite legado}';

    protected $description = 'Importa usuários, equipes, vínculos e solicitações do SQLite para o banco principal vazio';

    public function handle(): int
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->error('A conexão principal precisa ser PostgreSQL.');
            return self::FAILURE;
        }

        $path = $this->option('path') ?: env('LEGACY_SQLITE_PATH');
        if (! is_string($path) || ! is_file($path)) {
            $this->error('Banco SQLite legado não encontrado.');
            return self::FAILURE;
        }

        foreach (['users', 'teams', 'manager_team', 'work_requests'] as $table) {
            if (! Schema::hasTable($table)) {
                $this->error("Tabela de destino ausente: {$table}");
                return self::FAILURE;
            }
        }

        if (DB::table('users')->exists() || DB::table('teams')->exists() || DB::table('work_requests')->exists()) {
            $this->error('Importação cancelada: o PostgreSQL já contém dados do sistema.');
            return self::FAILURE;
        }

        config(['database.connections.legacy_sqlite' => [
            'driver' => 'sqlite',
            'database' => $path,
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]]);
        DB::purge('legacy_sqlite');

        $source = DB::connection('legacy_sqlite');
        foreach (['users', 'teams', 'manager_team', 'work_requests'] as $table) {
            if (! $source->getSchemaBuilder()->hasTable($table)) {
                throw new RuntimeException("Tabela ausente no SQLite: {$table}");
            }
        }

        DB::transaction(function () use ($source): void {
            foreach (['users', 'teams', 'manager_team', 'work_requests'] as $table) {
                $source->table($table)->orderBy($this->orderColumn($table))->chunk(500, function ($rows) use ($table): void {
                    $payload = $rows->map(fn ($row) => (array) $row)->all();
                    if ($payload !== []) {
                        DB::table($table)->insert($payload);
                    }
                });
            }

            foreach (['users', 'teams', 'work_requests'] as $table) {
                DB::statement("SELECT setval(pg_get_serial_sequence('{$table}', 'id'), COALESCE((SELECT MAX(id) FROM {$table}), 1), (SELECT COUNT(*) > 0 FROM {$table}))");
            }
        });

        foreach (['users', 'teams', 'manager_team', 'work_requests'] as $table) {
            $this->line(sprintf('%s: %d registros', $table, DB::table($table)->count()));
        }
        $this->info('Importação concluída sem modificar o SQLite de origem.');

        return self::SUCCESS;
    }

    private function orderColumn(string $table): string
    {
        return $table === 'manager_team' ? 'manager_id' : 'id';
    }
}
