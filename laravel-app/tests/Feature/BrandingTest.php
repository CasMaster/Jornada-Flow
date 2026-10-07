<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BrandingTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_uses_the_configured_brand_and_favicon(): void
    {
        $favicon = public_path('assets/jornada-flow-favicon.png');

        $this->assertFileExists($favicon);

        $this->get('/login')
            ->assertOk()
            ->assertSee('<title>Entrar — Jornada Flow</title>', false)
            ->assertSee('rel="icon" type="image/png"', false)
            ->assertSee(asset('assets/jornada-flow-favicon.png').'?v='.filemtime($favicon), false)
            ->assertDontSee('HÍBRIDO')
            ->assertDontSee('Híbrido');
    }

    public function test_login_uses_brand_values_configured_for_a_fork(): void
    {
        config([
            'brand.name' => 'Nova Jornada',
            'brand.company' => 'Empresa Exemplo',
            'brand.initials' => 'NE',
        ]);

        $this->get('/login')
            ->assertOk()
            ->assertSee('<title>Entrar — Nova Jornada</title>', false)
            ->assertSee('Nova Jornada direciona você automaticamente')
            ->assertSee('Empresa Exemplo')
            ->assertSee('>NE<', false);
    }
}
