<?php

return [
    'enabled' => env('OIDC_ENABLED', false),
    'issuer' => rtrim((string) env('OIDC_ISSUER', ''), '/'),
    'client_id' => env('OIDC_CLIENT_ID', ''),
    'client_secret' => env('OIDC_CLIENT_SECRET'),
    'redirect_uri' => env('OIDC_REDIRECT_URI'),
    'logout_redirect_uri' => env('OIDC_LOGOUT_REDIRECT_URI'),
    'scopes' => env('OIDC_SCOPES', 'openid profile email'),
    'signing_algorithm' => env('OIDC_SIGNING_ALGORITHM', 'RS256'),
    'admin_role' => 'mixhome-admin',
    'user_role' => 'mixhome-user',
    'discovery_cache_seconds' => (int) env('OIDC_DISCOVERY_CACHE_SECONDS', 3600),
    'jwks_cache_seconds' => (int) env('OIDC_JWKS_CACHE_SECONDS', 3600),
    'migration' => [
        'client_id' => env('KEYCLOAK_MIGRATION_CLIENT_ID'),
        'client_secret' => env('KEYCLOAK_MIGRATION_CLIENT_SECRET'),
    ],
];
