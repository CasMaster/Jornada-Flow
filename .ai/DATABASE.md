# Banco de dados

## Tecnologia e acesso

- Banco oficial: PostgreSQL 16 (`postgres:16-alpine`).
- ORM/camada de acesso: Eloquent ORM e Query Builder do Laravel.
- Testes: SQLite em memória, conforme `phpunit.xml`.
- Importação SQLite: somente para migrações históricas controladas.

O serviço web acessa o host interno `postgres:5432`. O compose publica PostgreSQL apenas no loopback do host para acesso administrativo por túnel SSH. Nunca documente ou versione valores de credenciais.

## Estrutura geral

- `users`: identidade, hash de senha, perfil, equipe própria, data de contratação, estado ativo e versão de autenticação usada para revogar sessões anteriores.
- `teams`: catálogo de equipes e estado ativo.
- `manager_team`: relação muitos-para-muitos entre gestores e equipes.
- `work_requests`: solicitante, data, modalidade (`home_office` ou `onsite`), status, revisor e data da análise.
- `vacation_requests`: solicitante, tipo (`vacation` ou `cash_allowance`), intervalo, dias convertidos em abono pecuniário, status, análise, correção e cancelamento preservado. Solicitações exclusivas de abono não têm datas de descanso no domínio; usam datas técnicas posteriores ao prazo concessivo apenas para manter compatibilidade de rollback com a versão anterior.
- `vacation_entitlements`: concessão e ajuste de dias por colaborador e período aquisitivo; consumo e reserva são derivados das solicitações vinculadas.
- `holidays`: datas corporativas e indicador de bloqueio.
- `audit_logs`: ator, evento, alvo, valores anterior/novo e metadados da requisição.
- `notifications`: notificações persistidas.
- `password_reset_tokens`: tokens de redefinição.
- `sessions`, `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`: infraestrutura Laravel.

Restrições relevantes:

- e-mail de usuário único;
- nome de equipe único;
- vínculo gestor–equipe único pela chave composta;
- solicitação única por `(user_id, work_date, work_mode)`; modalidades diferentes na mesma data só podem coexistir quando a anterior foi recusada;
- data corporativa única.

## Migrations

As migrations estão em `laravel-app/database/migrations/` e criam, nesta ordem lógica, infraestrutura Laravel, entidades do domínio, índices do diretório e recursos operacionais/auditoria.

Criar e conferir:

```bash
cd laravel-app
php artisan make:migration nome_descritivo
php artisan migrate:status
php artisan migrate
```

Rollback suportado pelo Laravel:

```bash
php artisan migrate:rollback --step=1
```

Use rollback somente em ambiente descartável ou com autorização e plano de dados. Em produção prefira migrations corretivas progressivas. O entrypoint executa migrations automaticamente com `--force` no serviço web.

## Regras para mudanças de schema

- Não altere migrations que já possam ter sido aplicadas; crie uma nova.
- Escreva `down()` coerente, mas não presuma que rollback com perda de dados é aceitável.
- Teste com PostgreSQL quando usar comportamento específico do banco; a suíte rápida usa SQLite.
- Para índices/restrições em tabelas grandes, avalie bloqueio e tempo de execução.
- Faça dump verificado antes de aplicar schema em produção.
- Preserve o volume `hibrido_postgres_data`; nunca use `podman-compose down -v`.
- Compare contagens e execute `/health/ready` após migrations.

## Backup e restauração

Use `scripts/backup-postgres.sh` e as instruções em `docs/OPERACAO.md`. O destino externo definido é Backblaze B2, sempre por integração autenticada fora do Git. Restauração altera dados e exige autorização do Super Admin, janela de manutenção e conferência de checksum.

A política definida é reter por dois anos logs de auditoria, sessões, notificações, solicitações recusadas e contas desativadas. A automação de expurgo/anonimização ainda precisa ser projetada com preservação de integridade referencial e auditoria; não exclua esses dados manualmente.
