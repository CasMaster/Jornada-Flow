# Aplicação Laravel do Jornada Flow

Este diretório contém a aplicação oficial do sistema de controle de home office.

## Comandos principais

```bash
podman-compose up -d --build
podman-compose down
php artisan test
php artisan route:list
```

## Docker sem Caddy

O `compose.yaml` inicia a aplicação web, PostgreSQL, worker de fila e scheduler; Caddy não é necessário para desenvolvimento local ou para uma rede privada. Copie o arquivo de exemplo e defina uma chave própria antes de subir os containers:

```bash
cp .env.example .env
php artisan key:generate
docker compose up -d --build
```

Também é compatível com Podman, substituindo `docker compose` por `podman-compose`. Com os valores padrão, a aplicação fica disponível somente em `http://127.0.0.1:8081`, e o PostgreSQL em `127.0.0.1:15432`. Acesse `http://127.0.0.1:8081` diretamente, sem proxy reverso.

Para disponibilizar a aplicação apenas em uma rede privada, ajuste `APP_BIND_IP=0.0.0.0`, `APP_URL` e `ASSET_URL` para o endereço interno do servidor, mantenha `SESSION_SECURE_COOKIE=false` enquanto usar HTTP e restrinja a porta no firewall. Não exponha essa configuração diretamente à internet: para produção pública, use HTTPS por Caddy ou outro proxy/TLS confiável e mantenha `SESSION_SECURE_COOKIE=true`.

Comandos úteis:

```bash
docker compose ps
docker compose logs -f hibrido_laravel
docker compose stop
```

Não use `docker compose down -v` nem `podman-compose down -v`, pois a opção `-v` remove o volume persistente do banco.

Comandos próprios do sistema:

```bash
php artisan hibrido:create-admin
php artisan hibrido:create-smoke-user homologacao
php artisan hibrido:sync-holidays
php artisan hibrido:sync-vacation-entitlements
php artisan hibrido:notify-backup-failure --exit-code=1
php artisan hibrido:import-legacy /caminho/banco-antigo.sqlite
php artisan hibrido:import-sqlite --path=/caminho/database.sqlite
```

Os dois importadores devem ser usados apenas durante migrações controladas. O PostgreSQL é o banco oficial.

O cadastro público está desabilitado. O Super Admin cria usuários no diretório e o primeiro acesso é concluído pelo link de recuperação enviado por SMTP. O login possui limitação por e-mail e IP. Backups externos usam Backblaze B2 por configuração `rclone` mantida fora do repositório.

## Marca de um fork

Um fork pode usar sua própria identidade sem alterar regras de negócio. Configure no `.env` as variáveis `BRAND_*` listadas no `.env.example`: nome, empresa, sigla, texto institucional, cores e caminhos dos logos, favicon e ícones PWA.

Os arquivos de imagem devem ficar em `public/assets/` no fork. Antes de publicar uma nova marca, use um banco PostgreSQL vazio e configure domínio, cookie de sessão, SMTP, backups e credenciais de deploy próprios. Não copie `.env`, dados corporativos, secrets ou configuração operacional da instalação original.

Consulte a documentação principal:

- [Visão geral](../README.md)
- [Manual de usuário](../docs/MANUAL_USUARIO.md)
- [Operação](../docs/OPERACAO.md)
- [Migração](../docs/MIGRACAO.md)
- [Arquitetura](../docs/ARQUITETURA.md)
