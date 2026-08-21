# Ambiente

## Dependências

- Git.
- Podman 4+ e `podman-compose` para o ambiente recomendado.
- PHP 8.3 e Composer 2 para execução direta de comandos/testes.
- Extensões PHP usadas pela imagem: GD, mbstring, PDO PostgreSQL, PDO SQLite e ZIP.
- Caddy no host de produção/homologação, fora deste repositório.
- SMTP não será configurado neste momento; recuperação de senha deve permanecer desabilitada.

Não há pipeline Node/Vite ativo para os assets do produto.

## Variáveis

Nomes relevantes, sem valores privados:

```text
APP_NAME
APP_ENV
APP_KEY
APP_DEBUG
APP_URL
ASSET_URL
APP_TIMEZONE
APP_ROUTE_PREFIX
APP_PORT
APP_BIND_IP
APP_CONTAINER_NAME
POSTGRES_CONTAINER_NAME
POSTGRES_HOST_PORT
DB_CONNECTION
DB_HOST
DB_PORT
DB_DATABASE
DB_USERNAME
DB_PASSWORD
SESSION_DRIVER
SESSION_SECURE_COOKIE
CACHE_STORE
QUEUE_CONNECTION
PASSWORD_RECOVERY_ENABLED
LOG_CHANNEL
LOG_LEVEL
MAIL_MAILER
MAIL_SCHEME
MAIL_HOST
MAIL_PORT
MAIL_USERNAME
MAIL_PASSWORD
MAIL_FROM_ADDRESS
MAIL_FROM_NAME
LEGACY_SQLITE_PATH
```

Use `.env.example` como referência. `.env` nunca deve entrar no Git.

## Iniciar e parar

```bash
cd laravel-app
cp .env.example .env
podman-compose up -d --build
podman-compose ps
```

Crie a chave e o primeiro administrador por meios que não exponham secrets no histórico. O comando administrativo disponível é:

```bash
podman exec -it hibrido-home-office-laravel php artisan hibrido:create-admin
```

Parar sem excluir volumes:

```bash
podman-compose stop
```

`podman-compose down` remove containers/rede; não acrescente `-v`.

## Portas e ambientes documentados

| Uso | Bind padrão | Porta |
|---|---|---:|
| Aplicação local/homologação | `127.0.0.1` | 8081 |
| Aplicação produção | `127.0.0.1` | 8082 |
| PostgreSQL produção para túnel | `127.0.0.1` | 15432 |
| PostgreSQL homologação observado na operação | `127.0.0.1` | 15433 |

Caddy é a entrada pública, restrita ao servidor. `mixhome.app.br` é produção e `/homologacao` é homologação. O Caddyfile não faz parte deste repositório.

## Comandos úteis

```bash
cd laravel-app
php artisan route:list
php artisan migrate:status
php artisan queue:failed
php artisan config:clear
vendor/bin/pint --test
php artisan test
```

Saúde e operação:

```bash
curl --fail http://127.0.0.1:8082/health/ready
scripts/monitor-production.sh
scripts/backup-postgres.sh
```

Em homologação com prefixo, use `/homologacao/health/ready` através do roteamento correspondente.

## Testes e CI

`phpunit.xml` usa SQLite `:memory:`, fila síncrona, sessão/cache em memória e mailer de teste. O GitHub Actions instala dependências, gera `APP_KEY`, executa Pint, PHPUnit e build Docker.

```bash
cd laravel-app
composer install
cp .env.example .env
php artisan key:generate
vendor/bin/pint --test
php artisan test
podman build -t hibrido-home-office:local .
```

Os secrets do GitHub Actions permanecem externos ao repositório. SMTP não está no escopo atual.
