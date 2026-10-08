<?php

namespace App\Services;

use Firebase\JWT\JWK;
use Firebase\JWT\JWT;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class OidcService
{
    /** @return array{url:string,state:string,nonce:string,code_verifier:string} */
    public function authorizationRequest(): array
    {
        $this->assertConfigured();
        $metadata = $this->metadata();
        $state = $this->randomValue();
        $nonce = $this->randomValue();
        $verifier = $this->randomValue(64);
        $challenge = rtrim(strtr(base64_encode(hash('sha256', $verifier, true)), '+/', '-_'), '=');

        $query = http_build_query([
            'response_type' => 'code',
            'client_id' => config('oidc.client_id'),
            'redirect_uri' => $this->redirectUri(),
            'scope' => config('oidc.scopes'),
            'state' => $state,
            'nonce' => $nonce,
            'code_challenge' => $challenge,
            'code_challenge_method' => 'S256',
        ], '', '&', PHP_QUERY_RFC3986);

        return [
            'url' => $metadata['authorization_endpoint'].'?'.$query,
            'state' => $state,
            'nonce' => $nonce,
            'code_verifier' => $verifier,
        ];
    }

    /** @return array<string, mixed> */
    public function exchangeCode(string $code, string $codeVerifier): array
    {
        $metadata = $this->metadata();
        $request = Http::asForm()->acceptJson()->timeout(10);
        $secret = (string) config('oidc.client_secret');

        if ($secret !== '') {
            $request = $request->withBasicAuth((string) config('oidc.client_id'), $secret);
        }

        $payload = [
            'grant_type' => 'authorization_code',
            'code' => $code,
            'redirect_uri' => $this->redirectUri(),
            'code_verifier' => $codeVerifier,
        ];
        if ($secret === '') {
            $payload['client_id'] = config('oidc.client_id');
        }

        $response = $request->post($metadata['token_endpoint'], $payload);
        if (! $response->successful()) {
            throw new RuntimeException('OIDC token exchange failed.');
        }

        $tokens = $response->json();
        if (! is_array($tokens) || ! is_string($tokens['id_token'] ?? null)) {
            throw new RuntimeException('OIDC response did not contain an ID token.');
        }

        return $tokens;
    }

    /** @return array<string, mixed> */
    public function validateIdToken(string $idToken, string $expectedNonce): array
    {
        $metadata = $this->metadata();
        $jwks = Cache::remember(
            'oidc.jwks.'.hash('sha256', $metadata['jwks_uri']),
            max(60, (int) config('oidc.jwks_cache_seconds')),
            fn (): array => $this->getJson($metadata['jwks_uri'])
        );

        $headers = new \stdClass;
        $algorithm = (string) config('oidc.signing_algorithm');
        $decoded = JWT::decode($idToken, JWK::parseKeySet($jwks, $algorithm), $headers);
        if (($headers->alg ?? null) !== $algorithm) {
            throw new RuntimeException('Invalid ID token signing algorithm.');
        }
        $claims = json_decode(json_encode($decoded, JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);

        if (($claims['iss'] ?? null) !== config('oidc.issuer')) {
            throw new RuntimeException('Invalid ID token issuer.');
        }
        $audience = Arr::wrap($claims['aud'] ?? []);
        if (! in_array(config('oidc.client_id'), $audience, true)) {
            throw new RuntimeException('Invalid ID token audience.');
        }
        if (count($audience) > 1 && ($claims['azp'] ?? null) !== config('oidc.client_id')) {
            throw new RuntimeException('Invalid ID token authorized party.');
        }
        if (! is_string($claims['nonce'] ?? null) || ! hash_equals($expectedNonce, $claims['nonce'])) {
            throw new RuntimeException('Invalid ID token nonce.');
        }
        if (! is_string($claims['sub'] ?? null) || $claims['sub'] === '') {
            throw new RuntimeException('ID token subject is missing.');
        }
        if (! is_int($claims['exp'] ?? null) && ! is_float($claims['exp'] ?? null)) {
            throw new RuntimeException('ID token expiration is missing.');
        }

        return $claims;
    }

    /** @param array<string, mixed> $claims @return list<string> */
    public function applicationRoles(array $claims): array
    {
        $roles = data_get($claims, 'resource_access.'.config('oidc.client_id').'.roles', []);

        return is_array($roles)
            ? array_values(array_unique(array_filter($roles, 'is_string')))
            : [];
    }

    public function logoutUrl(?string $idToken): ?string
    {
        $endpoint = $this->metadata()['end_session_endpoint'] ?? null;
        if (! is_string($endpoint) || $endpoint === '') {
            return null;
        }

        $query = [
            'client_id' => config('oidc.client_id'),
            'post_logout_redirect_uri' => $this->logoutRedirectUri(),
        ];
        if ($idToken) {
            $query['id_token_hint'] = $idToken;
        }

        return $endpoint.'?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }

    /** @return array<string, mixed> */
    public function metadata(): array
    {
        $this->assertConfigured();
        $issuer = (string) config('oidc.issuer');

        return Cache::remember(
            'oidc.discovery.'.hash('sha256', $issuer),
            max(60, (int) config('oidc.discovery_cache_seconds')),
            function () use ($issuer): array {
                $metadata = $this->getJson($issuer.'/.well-known/openid-configuration');
                if (($metadata['issuer'] ?? null) !== $issuer) {
                    throw new RuntimeException('OIDC discovery issuer mismatch.');
                }
                foreach (['authorization_endpoint', 'token_endpoint', 'jwks_uri'] as $field) {
                    $this->assertTrustedEndpoint($metadata[$field] ?? null);
                }
                if (isset($metadata['end_session_endpoint'])) {
                    $this->assertTrustedEndpoint($metadata['end_session_endpoint']);
                }

                return $metadata;
            }
        );
    }

    public function redirectUri(): string
    {
        return (string) (config('oidc.redirect_uri') ?: route('oidc.callback'));
    }

    public function logoutRedirectUri(): string
    {
        return (string) (config('oidc.logout_redirect_uri') ?: route('home'));
    }

    /** @return array<string, mixed> */
    private function getJson(string $url): array
    {
        $response = Http::acceptJson()->timeout(10)->get($url);
        if (! $response->successful() || ! is_array($response->json())) {
            throw new RuntimeException('OIDC provider metadata is unavailable.');
        }

        return $response->json();
    }

    private function assertConfigured(): void
    {
        if (! config('oidc.enabled') || ! config('oidc.issuer') || ! config('oidc.client_id') || ! config('oidc.client_secret')) {
            throw new RuntimeException('OIDC authentication is not configured.');
        }
    }

    private function assertTrustedEndpoint(mixed $endpoint): void
    {
        if (! is_string($endpoint) || ! filter_var($endpoint, FILTER_VALIDATE_URL)) {
            throw new RuntimeException('OIDC discovery returned an invalid endpoint.');
        }
        $issuerHost = parse_url((string) config('oidc.issuer'), PHP_URL_HOST);
        $scheme = parse_url($endpoint, PHP_URL_SCHEME);
        $host = parse_url($endpoint, PHP_URL_HOST);
        if ($scheme !== 'https' || ! is_string($host) || ! hash_equals((string) $issuerHost, $host)) {
            throw new RuntimeException('OIDC discovery returned an untrusted endpoint.');
        }
    }

    private function randomValue(int $bytes = 32): string
    {
        return rtrim(strtr(base64_encode(random_bytes($bytes)), '+/', '-_'), '=');
    }
}
