<?php

namespace App\Services;

use App\Models\Holiday;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class HolidaySyncService
{
    public function sync(int $year): array
    {
        $token = (string) config('services.holidays.token');
        if ($token === '') {
            throw new RuntimeException('Configure HOLIDAYS_API_TOKEN antes de sincronizar.');
        }

        $response = Http::baseUrl((string) config('services.holidays.url'))
            ->withToken($token)
            ->acceptJson()
            ->retry(3, 500)
            ->timeout(15)
            ->get('/api/v1/feriados/estado/'.config('services.holidays.state'), ['ano' => $year])
            ->throw()
            ->json();

        $items = $response['feriados'] ?? null;
        if (! is_array($items)) {
            throw new RuntimeException('A API de feriados retornou um formato inesperado.');
        }

        $result = ['created' => 0, 'updated' => 0, 'skipped' => 0];
        foreach ($items as $item) {
            $date = $this->date($item['data'] ?? null);
            $name = trim((string) ($item['nome'] ?? ''));
            if ($date === null || $name === '') {
                throw new RuntimeException('A API de feriados retornou um registro inválido.');
            }

            $holiday = Holiday::whereDate('date', $date)->first();
            if ($holiday !== null && $holiday->source === 'manual') {
                $result['skipped']++;

                continue;
            }

            $values = [
                'name' => $name,
                'blocks_requests' => strtoupper((string) ($item['tipo'] ?? '')) !== 'FACULTATIVO',
                'source' => 'feriados_api',
                'scope' => strtolower((string) ($item['tipo'] ?? '')),
                'external_id' => isset($item['id']) ? (string) $item['id'] : null,
                'last_synced_at' => now(),
                'created_by' => null,
            ];

            if ($holiday === null) {
                Holiday::create(['date' => $date, ...$values]);
                $result['created']++;
            } else {
                $holiday->update($values);
                $result['updated']++;
            }
        }

        return $result;
    }

    private function date(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        foreach (['d/m/Y', 'Y-m-d'] as $format) {
            try {
                return CarbonImmutable::createFromFormat('!'.$format, $value)->format('Y-m-d');
            } catch (\Throwable) {
                // Tenta o próximo formato conhecido.
            }
        }

        return null;
    }
}
