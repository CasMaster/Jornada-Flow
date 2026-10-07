<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;

class PwaController extends Controller
{
    public function manifest(): JsonResponse
    {
        $scope = $this->scope();
        $brand = config('brand');

        return response()->json([
            'id' => $scope,
            'name' => $brand['name'],
            'short_name' => $brand['name'],
            'description' => $brand['description'],
            'lang' => 'pt-BR',
            'dir' => 'ltr',
            'start_url' => $scope,
            'scope' => $scope,
            'display' => 'standalone',
            'orientation' => 'portrait-primary',
            'background_color' => $brand['theme_color'],
            'theme_color' => $brand['theme_color'],
            'icons' => [
                ['src' => asset($brand['icon_192']), 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => asset($brand['icon_512']), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
                ['src' => asset($brand['maskable_icon_512']), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
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
            config('brand.mark'),
            config('brand.logo'),
            config('brand.icon_192'),
            config('brand.icon_512'),
        ];
        $version = max(array_map(fn (string $file): int => filemtime(public_path($file)), $files));

        return response()->view('pwa.service-worker', [
            'assets' => array_map(fn (string $file): string => asset($file).'?v='.filemtime(public_path($file)), $files),
            'cacheName' => 'brand-shell-'.$version,
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
