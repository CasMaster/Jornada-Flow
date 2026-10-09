<?php

namespace App\Services;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class KeycloakAdminService
{
    private ?string $accessToken = null;

    public function configured(): bool
    {
        return filled(config('oidc.issuer'))
            && filled(config('oidc.migration.client_id'))
            && filled(config('oidc.migration.client_secret'));
    }

    /** @return list<array<string, mixed>> */
    public function usersByEmail(string $email): array
    {
        $response = $this->request()->get($this->adminUrl('/users'), [
            'email' => $email,
            'exact' => 'true',
            'max' => 20,
        ])->throw();

        return array_values(array_filter($response->json(), fn ($user): bool =>
            is_array($user) && strcasecmp((string) ($user['email'] ?? ''), $email) === 0
        ));
    }

    /** @param list<string> $roles */
    public function createUser(string $name, string $email, array $roles): string
    {
        $parts = preg_split('/\s+/', trim($name), 2);
        $response = $this->request()->post($this->adminUrl('/users'), [
            'username' => $email,
            'email' => $email,
            'firstName' => $parts[0] ?? $name,
            'lastName' => $parts[1] ?? '',
            'enabled' => true,
            'emailVerified' => false,
            'requiredActions' => ['VERIFY_EMAIL', 'UPDATE_PASSWORD'],
        ])->throw();

        $location = $response->header('Location');
        $subject = $location ? basename(parse_url($location, PHP_URL_PATH)) : null;
        if (! $subject) {
            throw new RuntimeException('O Keycloak não retornou o identificador do usuário criado.');
        }

        $this->assignClientRoles($subject, $roles);
        $this->sendRequiredActionsEmail($subject);

        return $subject;
    }

    private function sendRequiredActionsEmail(string $subject): void
    {
        $this->request()->put(
            $this->adminUrl('/users/'.rawurlencode($subject).'/execute-actions-email').'?'.http_build_query([
                'client_id' => config('oidc.client_id'),
                'lifespan' => 43200,
            ]),
            ['VERIFY_EMAIL', 'UPDATE_PASSWORD']
        )->throw();
    }

    /** @param list<string> $roleNames */
    private function assignClientRoles(string $subject, array $roleNames): void
    {
        $clientId = (string) config('oidc.client_id');
        $clients = $this->request()->get($this->adminUrl('/clients'), [
            'clientId' => $clientId,
        ])->throw()->json();
        $client = collect($clients)->firstWhere('clientId', $clientId);
        if (! is_array($client) || empty($client['id'])) {
            throw new RuntimeException("Cliente Keycloak {$clientId} não encontrado.");
        }

        $available = $this->request()
            ->get($this->adminUrl('/clients/'.rawurlencode((string) $client['id']).'/roles'))
            ->throw()->json();
        $roles = collect($available)->whereIn('name', $roleNames)->values()->all();
        if (count($roles) !== count(array_unique($roleNames))) {
            throw new RuntimeException('Um ou mais papéis do MixHome não existem no Keycloak.');
        }

        $this->request()->post(
            $this->adminUrl('/users/'.rawurlencode($subject).'/role-mappings/clients/'.rawurlencode((string) $client['id'])),
            $roles
        )->throw();
    }

    private function request(): PendingRequest
    {
        if (! $this->configured()) {
            throw new RuntimeException('Configure o cliente administrativo de migração do Keycloak.');
        }

        return Http::acceptJson()
            ->asJson()
            ->withToken($this->token())
            ->timeout(15);
    }

    private function token(): string
    {
        if ($this->accessToken) {
            return $this->accessToken;
        }

        $response = Http::asForm()->acceptJson()->timeout(15)->post(
            rtrim((string) config('oidc.issuer'), '/').'/protocol/openid-connect/token',
            [
                'grant_type' => 'client_credentials',
                'client_id' => config('oidc.migration.client_id'),
                'client_secret' => config('oidc.migration.client_secret'),
            ]
        )->throw();

        $token = (string) $response->json('access_token');
        if ($token === '') {
            throw new RuntimeException('O Keycloak não retornou um token para o cliente de migração.');
        }

        return $this->accessToken = $token;
    }

    private function adminUrl(string $path): string
    {
        $issuer = (string) config('oidc.issuer');
        $realm = rawurlencode((string) basename(parse_url($issuer, PHP_URL_PATH)));
        $base = preg_replace('#/realms/[^/]+$#', '', rtrim($issuer, '/'));

        return $base.'/admin/realms/'.$realm.$path;
    }
}
