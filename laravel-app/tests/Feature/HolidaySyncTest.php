<?php

namespace Tests\Feature;

use App\Models\Holiday;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class HolidaySyncTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_syncs_sao_paulo_holidays_and_preserves_manual_dates(): void
    {
        config([
            'services.holidays.url' => 'https://holidays.test',
            'services.holidays.token' => 'test-token',
            'services.holidays.state' => 'SP',
        ]);

        Holiday::create([
            'date' => '2026-11-20',
            'name' => 'Evento corporativo',
            'blocks_requests' => true,
        ]);

        Http::fake([
            'https://holidays.test/api/v1/feriados/estado/SP*' => Http::response([
                'feriados' => [
                    ['id' => 'a', 'data' => '09/07/2026', 'nome' => 'Revolução Constitucionalista', 'tipo' => 'ESTADUAL'],
                    ['id' => 'b', 'data' => '20/11/2026', 'nome' => 'Consciência Negra', 'tipo' => 'NACIONAL'],
                    ['id' => 'c', 'data' => '08/12/2026', 'nome' => 'Imaculada Conceição', 'tipo' => 'MUNICIPAL'],
                    ['id' => 'd', 'data' => '04/06/2026', 'nome' => 'Corpus Christi', 'tipo' => 'FACULTATIVO'],
                ],
            ]),
        ]);

        $this->artisan('hibrido:sync-holidays', ['years' => ['2026']])
            ->expectsOutput('2026: 3 criados, 0 atualizados, 1 manuais preservados.')
            ->assertSuccessful();

        $this->assertTrue(Holiday::whereDate('date', '2026-07-09')->where([
            'scope' => 'estadual', 'source' => 'feriados_api', 'blocks_requests' => true,
        ])->exists());
        $this->assertTrue(Holiday::whereDate('date', '2026-12-08')->where([
            'scope' => 'municipal', 'source' => 'feriados_api',
        ])->exists());
        $this->assertTrue(Holiday::whereDate('date', '2026-06-04')->where([
            'scope' => 'facultativo', 'blocks_requests' => false,
        ])->exists());
        $this->assertTrue(Holiday::whereDate('date', '2026-11-20')->where([
            'name' => 'Evento corporativo', 'source' => 'manual',
        ])->exists());

        Http::assertSent(fn ($request) => $request->url() === 'https://holidays.test/api/v1/feriados/estado/SP?ano=2026'
            && $request->hasHeader('Authorization', 'Bearer test-token'));
    }

    public function test_it_fails_safely_without_api_token(): void
    {
        config(['services.holidays.token' => null]);

        $this->artisan('hibrido:sync-holidays', ['years' => ['2026']])
            ->expectsOutput('Não foi possível sincronizar 2026: Configure HOLIDAYS_API_TOKEN antes de sincronizar.')
            ->assertFailed();

        Http::assertNothingSent();
    }
}
