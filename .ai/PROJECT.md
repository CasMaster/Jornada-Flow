# Projeto

## Objetivo

O MixHome é um sistema interno da Mix Fiscal para controlar dias de home office. Ele substitui controles dispersos por um fluxo rastreável de solicitação, análise gerencial, histórico e exportação.

## Problema resolvido

- Colaboradores solicitam datas de home office ou trabalho presencial em um painel pessoal.
- Gestores analisam solicitações das equipes sob sua responsabilidade.
- Super Admins mantêm usuários, equipes, calendário e auditoria.
- A gestão consolida o período operacional em planilha Excel.

## Domínio e regras principais

- O ciclo de apuração vai do dia 20 ao dia 19 seguinte, com limites inclusivos.
- Uma pessoa pode ter no máximo uma solicitação por data.
- Toda solicitação nasce `pending` e pode se tornar `approved` ou `rejected`.
- O colaborador visualiza o histórico, mas não altera uma solicitação enviada.
- Recusa arquiva o estado; não exclui o registro.
- Gestores podem administrar múltiplas equipes e também usar o painel de colaborador.
- Super Admins têm acesso global e administrativo.
- Datas corporativas podem bloquear novas solicitações ou ser apenas informativas.

## Funcionalidades atuais

- Login unificado e primeiro acesso por conta previamente criada pelo Super Admin.
- Perfis `employee`, `manager` e `super_admin`.
- Painel pessoal, histórico e notificações.
- Escolha de modalidade home office ou presencial, ambas submetidas à aprovação gerencial e impedidas durante férias aprovadas.
- Painel gerencial com filtros, paginação, análise individual/em lote e métricas.
- Indicador gerencial mensal de modalidade, com dias aprovados de home office e presencial agrupados alfabeticamente por colaborador.
- Exportação XLSX matricial.
- Diretório paginado de usuários e vínculos gestor–equipe.
- Recuperação de senha habilitada por SMTP/HTTPS, usada também no primeiro acesso.
- Limitação de tentativas de login por e-mail e IP e exportações protegidas contra fórmulas.
- Auditoria administrativa, calendário corporativo, filas e lembretes agendados.
- Delegação temporária entre gestores, justificativas de análise e preferências locais de filtros.
- Telemetria de requisições/consultas lentas e monitoramento externo agendado.
- Endpoint de prontidão e scripts de backup/monitoramento.
- Importadores SQLite apenas para instalações históricas.
- Planejamento de férias com geração de períodos pela data de contratação, dias corridos, saldo, fracionamento em solicitações, análise gerencial, calendário de equipe, auditoria e exportação.
- Solicitação guiada de férias integrais, personalizadas com abono opcional, com conversão de 1/3 em abono ou exclusivamente de abono sem datas de descanso, incorporada ao saldo e à aprovação, com antecedência, início e fracionamento validados conforme a CLT.
- Aplicativo web instalável (PWA) em Android, iPhone e desktop, com escopo compatível com produção e `/homologacao` e tela offline sem persistência de dados corporativos.

## Componentes

- Aplicação oficial: `laravel-app/`.
- Infraestrutura: `laravel-app/compose.yaml`, `laravel-app/Dockerfile` e `laravel-app/docker-entrypoint.sh`.
- Operação: `scripts/` e `docs/`.
- Automação: `.github/workflows/`.

## Estado atual

- Aplicação Laravel/PostgreSQL é a implementação oficial.
- Produção e homologação são descritas em `docs/OPERACAO.md`.
- Branch observada durante a criação deste contexto: `codex/laravel-migration`.
- O worktree estava limpo antes da criação destes arquivos.
- Trabalho funcional em andamento: nenhum identificado no repositório.
- Recuperação de senha/SMTP: habilitada e validada pelo responsável.
- `mixhome.app.br` é produção e `/homologacao` continuará sendo homologação.
- Caddy é administrado exclusivamente no servidor e não será versionado neste repositório.
- Backblaze B2 foi escolhido como destino externo de backups; integração e credenciais permanecem fora do Git.
