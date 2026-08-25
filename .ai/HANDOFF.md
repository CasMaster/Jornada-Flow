# Handoff

## Objetivo atual

Consolidar o repositório oficial, automatizar a validação pós-deploy e manter produção e homologação verificáveis.

## Estado atual

A aplicação Laravel oficial foi promovida para a raiz do workspace local. O legado foi movido para uma pasta recuperável fora do projeto. Produção e homologação respondem pelo Caddy, e o smoke test público foi validado nos dois ambientes.

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
- Smoke test pós-deploy com prontidão, HTTPS, assets, CSRF e cookies seguros; autenticação real é ativada quando os dois secrets da conta técnica existirem.
- Environments `homologacao` e `producao` criados no GitHub com secrets separados de SSH e diretório de destino.
- Homologação confirmada em `/homologacao`, com aplicação, PostgreSQL, worker e scheduler ativos.

## Em andamento

- Nenhum trabalho em andamento.

## Pendente

- Cadastrar conta técnica sintética e os secrets `SMOKE_EMAIL` e `SMOKE_PASSWORD` para habilitar a etapa autenticada do smoke test.
- O plano atual do GitHub não permite reviewer obrigatório em environment de repositório privado; deploy de produção permanece manual, mas sem aprovação técnica obrigatória pela plataforma.

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

Publicar as alterações na `main`, acompanhar o CI e executar o deploy manual da homologação para validar o fluxo completo do GitHub Actions.

## Atenção

Não incluir `.env`, credenciais, dumps, dados pessoais ou configuração privada do servidor. Não alterar aplicação, banco ou produção como parte deste handoff.
