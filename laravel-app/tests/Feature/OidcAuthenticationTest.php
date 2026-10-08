<?php

namespace Tests\Feature;

use App\Models\User;
use Firebase\JWT\JWT;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OidcAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    private string $issuer = 'https://auth.mixhome.app.br/realms/mixapps';

    /** @var resource|\OpenSSLAsymmetricKey */
    private $privateKey;

    /** @var array<string, mixed> */
    private array $jwk;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::clear();
        config()->set([
            'oidc.enabled' => true,
            'oidc.issuer' => $this->issuer,
            'oidc.client_id' => 'mixhome-web',
            'oidc.client_secret' => 'test-only-secret',
            'oidc.redirect_uri' => 'https://mixhome.app.br/auth/callback',
            'oidc.logout_redirect_uri' => 'https://mixhome.app.br',
        ]);

        $keyOptions = ['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA];
        $windowsOpenSslConfig = 'C:\\Program Files\\Git\\mingw64\\etc\\ssl\\openssl.cnf';
        if (is_file($windowsOpenSslConfig)) {
            $keyOptions['config'] = $windowsOpenSslConfig;
        }
        $this->privateKey = openssl_pkey_new($keyOptions);
        $this->assertNotFalse($this->privateKey, 'Não foi possível gerar a chave RSA temporária do teste.');
        $details = openssl_pkey_get_details($this->privateKey);
        $this->jwk = [
            'kty' => 'RSA',
            'kid' => 'test-key',
            'alg' => 'RS256',
            'use' => 'sig',
            'n' => $this->base64Url($details['rsa']['n']),
            'e' => $this->base64Url($details['rsa']['e']),
        ];
    }

    public function test_login_redirect_uses_discovery_state_nonce_and_pkce(): void
    {
        $this->fakeProvider();

        $response = $this->get(route('oidc.login'));

        $response->assertRedirect();
        $query = [];
        parse_str((string) parse_url($response->headers->get('Location'), PHP_URL_QUERY), $query);
        $this->assertSame('code', $query['response_type']);
        $this->assertSame('mixhome-web', $query['client_id']);
        $this->assertSame('S256', $query['code_challenge_method']);
        $this->assertNotEmpty($query['state']);
        $this->assertNotEmpty($query['nonce']);
        $this->assertSame($query['state'], session('oidc_transaction.state'));
        $this->assertSame($query['nonce'], session('oidc_transaction.nonce'));
        $this->assertNotEmpty(session('oidc_transaction.code_verifier'));
    }

    public function test_admin_token_links_user_and_allows_administration(): void
    {
        $user = User::factory()->create(['email' => 'admin@mixhome.app.br', 'role' => 'employee']);
        $this->fakeProvider($this->token('subject-admin', 'admin@mixhome.app.br', ['mixhome-admin'], 'expected-nonce'));

        $response = $this->withSession($this->transaction('expected-nonce'))
            ->get(route('oidc.callback', ['code' => 'valid-code', 'state' => 'expected-state']));

        $response->assertRedirect(route('manager.dashboard'));
        $this->assertAuthenticatedAs($user->fresh());
        $this->assertSame('subject-admin', $user->fresh()->keycloak_subject);
        $this->assertSame('super_admin', $user->fresh()->role);
        $this->get(route('admin.users.index'))->assertOk();
    }

    public function test_user_token_allows_common_area_but_denies_administration(): void
    {
        $this->fakeProvider($this->token('subject-user', 'user@mixhome.app.br', ['mixhome-user'], 'expected-nonce'));

        $response = $this->withSession($this->transaction('expected-nonce'))
            ->get(route('oidc.callback', ['code' => 'valid-code', 'state' => 'expected-state']));

        $response->assertRedirect(route('employee.dashboard'));
        $this->assertAuthenticated();
        $this->assertSame('employee', auth()->user()->role);
        $this->get(route('employee.dashboard'))->assertOk();
        $this->get(route('admin.users.index'))->assertForbidden();
    }

    public function test_token_without_application_role_is_safely_denied(): void
    {
        $this->fakeProvider($this->token('subject-denied', 'denied@mixhome.app.br', [], 'expected-nonce'));

        $response = $this->withSession($this->transaction('expected-nonce'))
            ->get(route('oidc.callback', ['code' => 'valid-code', 'state' => 'expected-state']));

        $response->assertForbidden()->assertSee('não possui acesso');
        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['keycloak_subject' => 'subject-denied']);
    }

    public function test_invalid_state_is_rejected_before_token_exchange(): void
    {
        $this->fakeProvider();

        $this->withSession($this->transaction('expected-nonce'))
            ->get(route('oidc.callback', ['code' => 'valid-code', 'state' => 'wrong-state']))
            ->assertRedirect(route('login'))
            ->assertSessionHasErrors('oidc');

        $this->assertGuest();
        Http::assertNotSent(fn ($request): bool => $request->url() === $this->issuer.'/protocol/openid-connect/token');
    }

    public function test_logout_clears_local_session_and_redirects_to_keycloak(): void
    {
        $user = User::factory()->create();
        $this->fakeProvider();

        $response = $this->actingAs($user)->withSession([
            'authenticated_version' => $user->auth_version,
            'auth_provider' => 'oidc',
            'oidc_roles' => ['mixhome-user'],
            'oidc_expires_at' => now()->addMinutes(5)->timestamp,
            'oidc_id_token' => 'opaque-test-id-token',
        ])->post(route('logout'));

        $response->assertRedirectContains($this->issuer.'/protocol/openid-connect/logout');
        $this->assertGuest();
    }

    /** @param list<string> $roles */
    private function token(string $subject, string $email, array $roles, string $nonce): string
    {
        return JWT::encode([
            'iss' => $this->issuer,
            'aud' => 'mixhome-web',
            'sub' => $subject,
            'exp' => time() + 300,
            'iat' => time(),
            'nonce' => $nonce,
            'name' => 'Usuário OIDC',
            'email' => $email,
            'email_verified' => true,
            'resource_access' => ['mixhome-web' => ['roles' => $roles]],
        ], $this->privateKey, 'RS256', 'test-key');
    }

    private function fakeProvider(?string $idToken = null): void
    {
        Http::fake([
            $this->issuer.'/.well-known/openid-configuration' => Http::response([
                'issuer' => $this->issuer,
                'authorization_endpoint' => $this->issuer.'/protocol/openid-connect/auth',
                'token_endpoint' => $this->issuer.'/protocol/openid-connect/token',
                'jwks_uri' => $this->issuer.'/protocol/openid-connect/certs',
                'end_session_endpoint' => $this->issuer.'/protocol/openid-connect/logout',
            ]),
            $this->issuer.'/protocol/openid-connect/token' => Http::response(['id_token' => $idToken ?? 'unused']),
            $this->issuer.'/protocol/openid-connect/certs' => Http::response(['keys' => [$this->jwk]]),
        ]);
    }

    /** @return array<string, array<string, int|string>> */
    private function transaction(string $nonce): array
    {
        return ['oidc_transaction' => [
            'state' => 'expected-state',
            'nonce' => $nonce,
            'code_verifier' => str_repeat('v', 64),
            'created_at' => now()->timestamp,
        ]];
    }

    private function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
