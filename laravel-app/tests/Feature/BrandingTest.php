<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BrandingTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_uses_mixhome_brand_and_existing_favicon(): void
    {
        $favicon = public_path('assets/favicon.svg');

        $this->assertFileExists($favicon);
        $this->assertStringContainsString('data:image/png;base64,', file_get_contents($favicon));

        $this->get('/login')
            ->assertOk()
            ->assertSee('<title>Entrar — MixHome</title>', false)
            ->assertSee('rel="icon" type="image/svg+xml"', false)
            ->assertSee(asset('assets/favicon.svg').'?v='.filemtime($favicon), false)
            ->assertDontSee('HÍBRIDO')
            ->assertDontSee('Híbrido');
    }
}
