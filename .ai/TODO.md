# Pendências e oportunidades

## Confirmado

- Executar o provisionamento sintético e cadastrar `SMOKE_EMAIL`/`SMOKE_PASSWORD` separadamente nos environments após publicação autorizada do comando; validar o workflow `Smoke test` autenticado nos dois ambientes conforme `docs/OPERACAO.md`.
- Configurar a cópia externa dos dumps no OneDrive, sem versionar credenciais, e testar restauração a partir dessa cópia.
- Implementar expurgo/anonimização após dois anos para auditoria, sessões, notificações, solicitações recusadas e contas desativadas, com regras seguras para relacionamentos.
- Configurar proteção de ambiente no GitHub/servidor para que somente o Super Admin autorize produção, schema e restauração.

## Corrigido

- Healthcheck agora usa `/health/ready` e respeita `APP_ROUTE_PREFIX`.
- Deploy passou a reconstruir e recriar explicitamente web, worker e scheduler, preservando PostgreSQL.
- Backup agora usa `umask 077` e força permissão `600` no dump e checksum.
- Foram adicionados testes Unit separados para `ReportingCycle`.

## Sugestão

- Adicionar teste PostgreSQL em CI para mudanças que dependam de comportamento específico do banco, mantendo SQLite para feedback rápido.
- Tornar o procedimento de deploy idempotente, com backup, recriação controlada, verificação de saúde e rollback de imagem.
- Adicionar verificação automatizada de documentação/contexto para links quebrados e possíveis secrets.

## A confirmar

- Se recuperação de senha e SMTP estão habilitados e testados nos ambientes atuais.
- Qual ferramenta será usada para sincronizar com OneDrive e como será monitorada.
- Se a retenção de dois anos termina em exclusão ou anonimização para cada categoria de dado.
