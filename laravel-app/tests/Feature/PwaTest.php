<?php

namespace Tests\Feature;

use App\Http\Controllers\PwaController;
use Tests\TestCase;

class PwaTest extends TestCase
{
    public function test_manifest_is_installable_and_uses_the_configured_scope(): void
    {
        config(['app.route_prefix' => '']);

        $this->get('/manifest.webmanifest')
            ->assertOk()
            ->assertHeader('Content-Type', 'application/manifest+json')
            ->assertJsonPath('name', 'MixHome')
            ->assertJsonPath('display', 'standalone')
            ->assertJsonPath('scope', '/')
            ->assertJsonCount(3, 'icons');
    }

    public function test_service_worker_does_not_cache_authenticated_pages(): void
    {
        $response = $this->get('/service-worker.js')->assertOk();
        $script = $response->getContent();

        $this->assertStringContainsString("request.mode === 'navigate'", $script);
        $this->assertStringContainsString('fetch(request).catch(() => caches.match(OFFLINE_URL))', $script);
        $this->assertStringContainsString("url.pathname.includes('/assets/')", $script);
        $this->assertLessThan(
            strpos($script, "url.pathname.includes('/assets/')"),
            strpos($script, "request.mode === 'navigate'")
        );
    }

    public function test_manifest_respects_the_homologation_prefix(): void
    {
        config(['app.route_prefix' => 'homologacao']);

        $manifest = app(PwaController::class)->manifest()->getData(true);

        $this->assertSame('/homologacao/', $manifest['id']);
        $this->assertSame('/homologacao/', $manifest['start_url']);
        $this->assertSame('/homologacao/', $manifest['scope']);
    }

    public function test_offline_page_explains_that_business_data_is_not_stored(): void
    {
        $this->get('/offline')
            ->assertOk()
            ->assertSee('Você está sem conexão')
            ->assertSee('não guarda solicitações ou dados da sua conta');
    }

    public function test_login_layout_exposes_the_manifest_and_install_control(): void
    {
        $this->get('/login')
            ->assertOk()
            ->assertSee('rel="manifest"', false)
            ->assertSee('data-service-worker=', false)
            ->assertSee('data-pwa-install', false);
    }

    public function test_pwa_script_includes_ios_installation_guidance(): void
    {
        $script = file_get_contents(public_path('assets/pwa.js'));

        $this->assertStringContainsString('/iphone|ipad|ipod/i', $script);
        $this->assertStringContainsString('Como instalar', $script);
        $this->assertStringContainsString('Adicionar à Tela de Início', $script);
        $this->assertStringContainsString('showModal()', $script);
    }
}
