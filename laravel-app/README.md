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

Consulte a documentação principal:

- [Visão geral](../README.md)
- [Manual de usuário](../docs/MANUAL_USUARIO.md)
- [Operação](../docs/OPERACAO.md)
- [Migração](../docs/MIGRACAO.md)
- [Arquitetura](../docs/ARQUITETURA.md)
