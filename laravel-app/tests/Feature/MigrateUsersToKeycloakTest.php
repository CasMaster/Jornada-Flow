<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MigrateUsersToKeycloakTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set([
            'oidc.issuer' => 'https://auth.example.test/realms/mixapps',
            'oidc.client_id' => 'mixhome-web',
            'oidc.migration.client_id' => 'mixhome-user-migrator',
            'oidc.migration.client_secret' => 'test-secret',
        ]);
    }

    public function test_simulation_reports_link_without_changing_local_user(): void
    {
        $user = User::factory()->create(['email' => 'user@example.test', 'keycloak_subject' => null]);
        $this->fakeTokenAndUsers([['id' => 'remote-sub', 'email' => 'user@example.test', 'emailVerified' => true]]);

        $this->artisan('hibrido:keycloak-migrate-users')
            ->expectsOutputToContain('MODO SIMULAÇÃO')
            ->expectsOutputToContain('VINCULARIA')
            ->assertSuccessful();

        $this->assertNull($user->fresh()->keycloak_subject);
    }

    public function test_apply_links_only_unique_verified_email(): void
    {
        $user = User::factory()->create(['email' => 'user@example.test', 'keycloak_subject' => null]);
        $this->fakeTokenAndUsers([['id' => 'remote-sub', 'email' => 'user@example.test', 'emailVerified' => true]]);

        $this->artisan('hibrido:keycloak-migrate-users', ['--apply' => true])->assertSuccessful();

        $this->assertSame('remote-sub', $user->fresh()->keycloak_subject);
    }

    public function test_unverified_remote_email_is_a_conflict(): void
    {
        $user = User::factory()->create(['email' => 'user@example.test', 'keycloak_subject' => null]);
        $this->fakeTokenAndUsers([['id' => 'remote-sub', 'email' => 'user@example.test', 'emailVerified' => false]]);

        $this->artisan('hibrido:keycloak-migrate-users', ['--apply' => true])
            ->expectsOutputToContain('CONFLITO')
            ->assertFailed();

        $this->assertNull($user->fresh()->keycloak_subject);
    }

    public function test_apply_can_create_user_without_copying_password_and_assign_user_role(): void
    {
        $user = User::factory()->create([
            'name' => 'Maria Teste',
            'email' => 'maria@example.test',
            'role' => 'employee',
            'keycloak_subject' => null,
        ]);
        Http::fake(function ($request) {
            if (str_ends_with($request->url(), '/protocol/openid-connect/token')) {
                return Http::response(['access_token' => 'service-token']);
            }
            if (str_contains($request->url(), '/users?')) {
                return Http::response([]);
            }
            if ($request->method() === 'POST' && str_ends_with($request->url(), '/users')) {
                return Http::response([], 201, ['Location' => 'https://auth.example.test/admin/realms/mixapps/users/new-sub']);
            }
            if (str_contains($request->url(), '/clients?')) {
                return Http::response([['id' => 'client-uuid', 'clientId' => 'mixhome-web']]);
            }
            if (str_ends_with($request->url(), '/clients/client-uuid/roles')) {
                return Http::response([
                    ['id' => 'role-user', 'name' => 'mixhome-user'],
                    ['id' => 'role-admin', 'name' => 'mixhome-admin'],
                ]);
            }

            return Http::response([], 204);
        });

        $this->artisan('hibrido:keycloak-migrate-users', ['--apply' => true, '--create-missing' => true])
            ->assertSuccessful();

        $this->assertSame('new-sub', $user->fresh()->keycloak_subject);
        Http::assertSent(function ($request): bool {
            if ($request->method() !== 'POST' || ! str_ends_with($request->url(), '/users')) {
                return false;
            }
            $payload = $request->data();

            return ! array_key_exists('credentials', $payload)
                && $payload['requiredActions'] === ['VERIFY_EMAIL', 'UPDATE_PASSWORD'];
        });
        Http::assertSent(fn ($request): bool =>
            $request->method() === 'PUT'
            && str_contains($request->url(), '/users/new-sub/execute-actions-email?')
            && $request->data() === ['VERIFY_EMAIL', 'UPDATE_PASSWORD']
        );
    }

    public function test_technical_accounts_are_excluded_from_migration(): void
    {
        User::factory()->create(['email' => 'gestor@local', 'keycloak_subject' => null]);
        User::factory()->create(['email' => 'smoke-homologacao@mixhome.invalid', 'keycloak_subject' => null]);
        Http::fake();

        $this->artisan('hibrido:keycloak-migrate-users', ['--apply' => true, '--create-missing' => true])
            ->expectsOutputToContain('IGNORADO')
            ->assertSuccessful();

        Http::assertNothingSent();
    }

    private function fakeTokenAndUsers(array $users): void
    {
        Http::fake([
            'https://auth.example.test/realms/mixapps/protocol/openid-connect/token' => Http::response(['access_token' => 'service-token']),
            'https://auth.example.test/admin/realms/mixapps/users*' => Http::response($users),
        ]);
    }
}
