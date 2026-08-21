# Handoff

## Objetivo atual

Preparar o repositório como fonte de verdade compartilhada e corrigir os débitos técnicos autorizados.

## Estado atual

A estrutura de contexto foi criada e recebeu as definições operacionais do responsável. Healthcheck, deploy, permissões de backup e separação de testes foram corrigidos localmente.

## Concluído

- Identificação da aplicação oficial em `laravel-app/`.
- Levantamento de arquitetura, banco, containers, rotas, migrations, testes, CI, scripts e convenções.
- Criação das instruções comuns e documentos de projeto, arquitetura, banco, ambiente, decisões e pendências.
- Registro explícito de informações que dependem do ambiente como **A confirmar**.
- Registro de Caddy restrito ao servidor, endereços oficiais, OneDrive, autoridade do Super Admin e retenção de dois anos.
- Correção do healthcheck com suporte a `APP_ROUTE_PREFIX`.
- Deploy idempotente com recriação dos serviços de aplicação.
- Backups locais com permissões restritas.
- Testes unitários do ciclo 20–19.
- Sintaxe PHP e shell validada; smoke test direto de `ReportingCycle` e `git diff --check` aprovados.

## Em andamento

- Nenhum trabalho em andamento.

## Pendente

- Aprovação do usuário para commit, push ou Pull Request; nenhuma dessas ações está autorizada nesta tarefa.
- Executar a suíte PHPUnit completa em ambiente com `mbstring`; o PHP local não possui a extensão.

## Arquivos alterados

- `AGENTS.md`
- `.ai/PROJECT.md`
- `.ai/ARCHITECTURE.md`
- `.ai/DATABASE.md`
- `.ai/ENVIRONMENT.md`
- `.ai/DECISIONS.md`
- `.ai/TODO.md`
- `.ai/HANDOFF.md`
- `laravel-app/Dockerfile`
- `laravel-app/docker-healthcheck.php`
- `laravel-app/deploy.sh`
- `laravel-app/tests/Unit/ReportingCycleTest.php`
- `.github/workflows/deploy.yml`
- `scripts/backup-postgres.sh`

## Decisões tomadas

- Manter `AGENTS.md` e `.ai/*.md` como contexto principal independente de ferramenta.
- Documentar somente fatos verificáveis; detalhes externos ao repositório foram marcados como **A confirmar**.

## Problemas encontrados

- Integração OneDrive ainda não configurada.
- Expurgo/anonimização após dois anos ainda não implementado.

## Próximo passo recomendado

Executar a suíte e o build em ambiente completo. Depois da aprovação explícita, criar commit e integrar pelo fluxo Git escolhido pelo responsável.

## Atenção

Não incluir `.env`, credenciais, dumps, dados pessoais ou configuração privada do servidor. Não alterar aplicação, banco ou produção como parte deste handoff.
