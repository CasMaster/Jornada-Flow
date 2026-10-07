# Aplicação Laravel do MixHome

Este diretório contém a aplicação oficial do sistema de controle de home office.

## Comandos principais

```bash
podman-compose up -d --build
podman-compose down
php artisan test
php artisan route:list
```

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
