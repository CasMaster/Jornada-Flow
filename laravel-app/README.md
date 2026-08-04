# Aplicação Laravel do HÍBRIDO

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
php artisan hibrido:import-legacy /caminho/banco-antigo.sqlite
php artisan hibrido:import-sqlite --path=/caminho/database.sqlite
```

Os dois importadores devem ser usados apenas durante migrações controladas. O PostgreSQL é o banco oficial.

Consulte a documentação principal:

- [Visão geral](../README.md)
- [Manual de usuário](../docs/MANUAL_USUARIO.md)
- [Operação](../docs/OPERACAO.md)
- [Migração](../docs/MIGRACAO.md)
- [Arquitetura](../docs/ARQUITETURA.md)
