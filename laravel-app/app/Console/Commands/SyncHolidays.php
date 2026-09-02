<?php

namespace App\Console\Commands;

use App\Services\HolidaySyncService;
use Illuminate\Console\Command;
use Throwable;

class SyncHolidays extends Command
{
    protected $signature = 'hibrido:sync-holidays {years?* : Anos que serão sincronizados}';

    protected $description = 'Sincroniza feriados nacionais e estaduais de São Paulo';

    public function handle(HolidaySyncService $service): int
    {
        $years = $this->argument('years') ?: [now()->year, now()->addYear()->year];

        foreach ($years as $year) {
            if (! ctype_digit((string) $year) || (int) $year < 2000 || (int) $year > 2100) {
                $this->error("Ano inválido: {$year}");

                return self::INVALID;
            }

            try {
                $result = $service->sync((int) $year);
            } catch (Throwable $exception) {
                report($exception);
                $this->error("Não foi possível sincronizar {$year}: {$exception->getMessage()}");

                return self::FAILURE;
            }

            $this->info("{$year}: {$result['created']} criados, {$result['updated']} atualizados, {$result['skipped']} manuais preservados.");
        }

        return self::SUCCESS;
    }
}
