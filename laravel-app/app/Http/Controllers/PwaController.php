<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class PwaController extends Controller
{
    public function manifest(): JsonResponse
    {
        $scope = $this->scope();

        return response()->json([
            'id' => $scope,
            'name' => 'MixHome',
            'short_name' => 'MixHome',
            'description' => 'Solicitações e acompanhamento de home office e férias.',
            'lang' => 'pt-BR',
            'dir' => 'ltr',
            'start_url' => $scope,
            'scope' => $scope,
            'display' => 'standalone',
            'orientation' => 'portrait-primary',
            'background_color' => '#071424',
            'theme_color' => '#071424',
            'icons' => [
                ['src' => asset('assets/icons/mixhome-192.png'), 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => asset('assets/icons/mixhome-512.png'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => asset('assets/icons/mixhome-maskable-512.png'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
            ],
        ])->header('Content-Type', 'application/manifest+json')
            ->header('Cache-Control', 'public, max-age=3600');
    }

    public function serviceWorker(): Response
    {
        $files = [
            'assets/style.css',
            'assets/manager.css',
            'assets/login.css',
            'assets/employee.css',
            'assets/brand-theme.css',
            'assets/workflow.css',
            'assets/deploy-approval.css',
            'assets/theme.js',
            'assets/notifications.js',
            'assets/mix-fiscal-mark.png',
            'assets/mix-fiscal-logo.svg',
            'assets/icons/mixhome-192.png',
            'assets/icons/mixhome-512.png',
        ];
        $version = max(array_map(fn (string $file): int => filemtime(public_path($file)), $files));

        return response()->view('pwa.service-worker', [
            'assets' => array_map(fn (string $file): string => asset($file).'?v='.filemtime(public_path($file)), $files),
            'cacheName' => 'mixhome-shell-'.$version,
            'offlineUrl' => route('pwa.offline'),
        ])->header('Content-Type', 'application/javascript; charset=UTF-8')
            ->header('Service-Worker-Allowed', $this->scope())
            ->header('Cache-Control', 'no-cache, no-store, must-revalidate');
    }

    public function offline(): Response
    {
        return response()->view('pwa.offline')->header('Cache-Control', 'public, max-age=3600');
    }

    private function scope(): string
    {
        $prefix = trim((string) config('app.route_prefix'), '/');

        return $prefix === '' ? '/' : '/'.$prefix.'/';
    }
}
