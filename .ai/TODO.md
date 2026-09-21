# Pendências e oportunidades

## Confirmado

- Repetir trimestralmente o teste de restauração do backup B2 em banco descartável, mediante autorização do Super Admin.
- Validar em homologação a rotina conservadora de retenção e somente então autorizar `DATA_RETENTION_ENABLED=true` em produção.
- Configurar proteção de ambiente no GitHub/servidor para que somente o Super Admin autorize produção, schema e restauração. `CasMaster` é a conta indicada, mas o GitHub recusou `required_reviewers` (HTTP 422) no plano atual do repositório privado; avaliar plano compatível ou gate externo no servidor.
- Avaliar rotação posterior da chave pessoal: ela permanece intacta, mas já esteve em um secret do GitHub, e cópias históricas não são revogadas pela substituição do secret.

## Corrigido

- Healthcheck agora usa `/health/ready` e respeita `APP_ROUTE_PREFIX`.
- Deploy passou a reconstruir e recriar explicitamente web, worker e scheduler, preservando PostgreSQL.
- Backup agora usa `umask 077` e força permissão `600` no dump e checksum.
- Remoto `b2-mixhome`, bucket privado `mixhome-backups`, primeiro dump e agendamento diário às 02:15 de Campinas foram validados em produção.
- Primeiro teste de restauração B2 aprovado em PostgreSQL 16 descartável, com checksum, migrations e usuários conferidos sem alterar produção.
- Foram adicionados testes Unit separados para `ReportingCycle`.
- CI valida a suíte em PostgreSQL 16, além do SQLite rápido.
- Deploy cria backup prévio, verifica saúde e preserva referência para retorno à imagem anterior.
- Monitoramento externo de produção roda a cada 15 minutos e mede tempo de resposta.
- Monitor diário externo do backup em `.github/workflows/backup-monitor.yml` publicado e validado manualmente; depende de GitHub Actions, SSH, servidor e B2.
- Gate SSH no servidor com chave exclusiva restrita à automação; `DEPLOY_SSH_KEY` de homologação e produção foi substituído, e o monitor pelo GitHub passou usando a nova chave.
- Primeiro deploy controlado em homologação validado com aprovação manual do digest, backup prévio, healthcheck e smoke autenticado.
- Solicitações aceitam justificativa, gestores podem delegar equipes temporariamente e filtros suportam ordenação, tamanho de página e preferência local.
- O calendário distingue solicitações por estado; painel gerencial ganhou prioridades, visão executiva, CSV e área operacional.
- Retenção de dois anos possui simulação, trava explícita e agendamento; backup suporta destinos externos via `rclone`.

## Sugestão

- Adicionar teste PostgreSQL em CI para mudanças que dependam de comportamento específico do banco, mantendo SQLite para feedback rápido.
- Tornar o procedimento de deploy idempotente, com backup, recriação controlada, verificação de saúde e rollback de imagem.
- Adicionar verificação automatizada de documentação/contexto para links quebrados e possíveis secrets.

## A confirmar

- Se a retenção de dois anos termina em exclusão ou anonimização para cada categoria de dado.
