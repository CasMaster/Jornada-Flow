# Decisões técnicas

Somente decisões comprovadas pelo código ou pela documentação existente são registradas aqui.

## 2026-09-14 — Alerta de falha no backup

Produção mantém um único backup B2 diário às 02:15 de Campinas. Quando o script retorna erro, o cron executa `hibrido:notify-backup-failure`, que envia imediatamente e-mail e notificação interna aos Super Admins ativos, excluindo contas técnicas declaradas no agendamento. A falha de um destinatário não bloqueia os demais. O mecanismo não inclui conteúdo do log na mensagem e não substitui um monitor externo, pois depende da aplicação, PostgreSQL e SMTP.

## 2026-09-14 — Backblaze B2 como cópia externa de backup

Backblaze B2 substitui o OneDrive como destino externo oficial dos dumps PostgreSQL. O bucket deve ser privado, usar Object Lock e receber os arquivos por `rclone` configurado exclusivamente no servidor com uma Application Key restrita ao bucket. O script usa `BACKUP_REMOTE` e preserva `ONEDRIVE_REMOTE` apenas como compatibilidade temporária.

## 2026-09-10 — Retenção protegida e backup externo

A rotina de dois anos opera em simulação por padrão e exige simultaneamente `--execute` e `DATA_RETENTION_ENABLED=true`. Ela remove dados operacionais vencidos e anonimiza contas inativas, preservando os relacionamentos restantes. O backup externo usa `rclone` configurado fora do Git e valida a cópia antes de concluir.

## 2026-09-04 — Delegação temporária e justificativa de análise

Somente o Super Admin programa ou encerra delegações entre gestores ativos. Durante o intervalo inclusivo, o substituto recebe o escopo das equipes do gestor de origem; a Policy continua sendo a autoridade no servidor. Aprovações e recusas aceitam observação de até 1.000 caracteres, preservada na solicitação e na auditoria.

## 2026-09-04 — Observabilidade e validação operacional

Respostas web incluem `Server-Timing`; requisições e consultas acima dos limites configuráveis são registradas sem parâmetros SQL. O CI passa a executar a suíte também em PostgreSQL 16. O deploy cria dump e checksum antes da recriação, valida o healthcheck e tenta restaurar a imagem anterior se a nova não ficar saudável. O smoke externo de produção roda a cada 15 minutos.

## 2026-08-28 — Provisionamento explícito de conta sintética para smoke test

`hibrido:create-smoke-user` cria uma identidade fixa por ambiente em `mixhome.invalid`,
com perfil `employee`, sem equipe ou dados de negócio. A execução é interativa,
valida `APP_ROUTE_PREFIX` e usa senha oculta confirmada, sem argumento de senha.
Não sobrescreve usuários existentes. Cadastro e auditoria sem senha/hash são
transacionais; nenhuma migration, seed automático, SMTP ou recuperação é necessário.

Os secrets `SMOKE_EMAIL` e `SMOKE_PASSWORD` ficam separados nos GitHub Environments
`homologacao` e `producao`, com senhas distintas. O comando é apenas de criação,
não de rotação. A conta mantém permissões normais de colaborador; a restrição de
uso somente para smoke test é operacional. Procedimento em `docs/OPERACAO.md`.

## 2026-08-27 — Marca MixHome

O nome público do produto passa a ser MixHome, com favicon baseado na marca existente da Mix Fiscal. Títulos, cabeçalho, rodapé, configuração de nome e documentação usam a nova marca.

Identificadores técnicos `hibrido` (banco, volumes, containers, comandos e código) permanecem inalterados. O Compose preserva o nome anterior do cookie de sessão por padrão e permite sobrescrevê-lo por `SESSION_COOKIE`; não há migração de dados nesta mudança.

## 2026-08-03 — Laravel como aplicação oficial

### Contexto

O projeto precisava concentrar autenticação, permissões, equipes, solicitações e operação em uma aplicação mantível.

### Decisão

Usar PHP 8.3, Laravel 12 e Apache na implementação oficial em `laravel-app/`.

### Motivo

A estrutura atual usa controllers, middleware, policies, Eloquent, migrations, filas e testes do Laravel.

### Consequências

Implementações PHP/SQLite anteriores são legadas; alterações funcionais devem ocorrer na aplicação Laravel.

## 2026-08-03 — PostgreSQL como banco oficial

### Contexto

O sistema exige integridade, gravações concorrentes, relacionamentos e margem de crescimento.

### Decisão

Usar PostgreSQL 16 em container separado, com Eloquent/Query Builder.

### Motivo

O compose, migrations e documentação operacional adotam PostgreSQL; SQLite fica restrito a testes e importação histórica.

### Consequências

Schema deve evoluir por migrations e produção exige backup/restauração PostgreSQL. A suíte SQLite não substitui testes PostgreSQL para recursos específicos.

## 2026-08-03 — Ciclo operacional 20–19

### Contexto

Relatórios e análises precisam seguir o período administrativo da empresa.

### Decisão

O ciclo começa no dia 20 e termina no dia 19 seguinte, inclusive.

### Motivo

A regra está centralizada em `ReportingCycle` e é usada em filtros, métricas e exportação.

### Consequências

Novos relatórios não devem recalcular o ciclo de forma independente.

## 2026-08-03 — Solicitações imutáveis e recusas preservadas

### Contexto

Era necessário manter rastreabilidade após o envio e substituir exclusão por decisão gerencial.

### Decisão

O colaborador não edita/apaga solicitações enviadas; o gestor altera o status para aprovado ou recusado, mantendo o registro.

### Motivo

O histórico e a auditoria dependem da permanência dos registros.

### Consequências

Qualquer fluxo de correção/cancelamento futuro deve ser modelado explicitamente, sem exclusão silenciosa.

## 2026-08-11 — Serviços de domínio, Policy e auditoria

### Contexto

Criação/análise de solicitações e autorização por equipe precisavam ser consistentes entre ações individuais e em lote.

### Decisão

Centralizar regras em `WorkRequestService`, auditoria em `AuditService` e autorização em `WorkRequestPolicy`.

### Motivo

Evita duplicação nos controllers e mantém a validação no servidor.

### Consequências

Novos pontos de entrada devem reutilizar esses componentes e registrar eventos relevantes.

## 2026-08-11 — Processos web, worker e scheduler separados

### Contexto

Notificações e lembretes não devem depender do ciclo da requisição HTTP.

### Decisão

Executar web, worker de fila e scheduler como serviços separados usando a mesma imagem, com filas no banco.

### Motivo

Permite processamento assíncrono e agendamento sem adicionar outro datastore.

### Consequências

Deploys e mudanças de configuração devem manter os três processos alinhados; saúde da fila precisa ser monitorada.

## 2026-08-11 — Caddy como única entrada HTTP pública

### Contexto

Produção e homologação precisam de HTTPS e isolamento das portas internas.

### Decisão

Vincular Laravel e PostgreSQL ao loopback e usar Caddy como proxy reverso público; Laravel confia nos cabeçalhos encaminhados.

### Motivo

Centraliza HTTPS e evita exposição direta dos containers.

### Consequências

URLs, cookies seguros, `X-Forwarded-*` e `APP_ROUTE_PREFIX` precisam estar coerentes com o ambiente. A configuração do Caddy vive fora do repositório.

## 2026-08-11 — Assets sem pipeline Node

### Contexto

A interface atual usa CSS e JavaScript próprios e precisa de publicação simples.

### Decisão

Servir assets diretamente de `public/assets`, usando versão por `filemtime` nas views.

### Motivo

É o mecanismo implementado atualmente e evita etapa adicional de build frontend.

### Consequências

Mudanças visuais são aplicadas diretamente nesses arquivos; introduzir bundler exige decisão explícita e atualização da operação.

## 2026-08-20 — Caddy restrito ao servidor e rotas dos ambientes

### Contexto

Era necessário definir onde manter a configuração do proxy e os endereços oficiais.

### Decisão

Manter Caddy exclusivamente no servidor. `mixhome.app.br` atende produção e `/homologacao` atende homologação.

### Motivo

Essa é a organização operacional definida pelo responsável do projeto.

### Consequências

O repositório documenta o contrato de proxy, mas não contém o Caddyfile. Mudanças de domínio/prefixo exigem coordenação com o servidor.

## 2026-08-20 — OneDrive como cópia externa de backup (substituída)

### Contexto

Os dumps locais precisam de uma cópia fora do servidor.

### Decisão

Usar OneDrive como destino externo dos backups PostgreSQL. Esta decisão foi substituída em 2026-09-14 pela adoção do Backblaze B2.

### Motivo

Destino escolhido pelo responsável do projeto.

### Consequências

Credenciais e configuração de sincronização ficam fora do Git. A integração ainda precisa ser instalada, testada e monitorada.

## 2026-08-20 — Super Admin como autoridade operacional

### Contexto

Deploy, migrations e restaurações precisam de um responsável definido.

### Decisão

Somente o Super Admin pode autorizar deploy em produção, mudanças de schema e restauração de backup.

### Motivo

Centralizar responsabilidade por mudanças operacionais sensíveis.

### Consequências

Automação externa deve aplicar aprovação protegida equivalente; o perfil da aplicação, sozinho, não controla GitHub ou acesso SSH.

## 2026-08-20 — Retenção de dados por dois anos

### Contexto

Era necessário definir retenção para auditoria, sessões, notificações, solicitações recusadas e contas desativadas.

### Decisão

Reter essas categorias por dois anos.

### Motivo

Prazo definido pelo responsável do projeto.

### Consequências

O expurgo deve ser automatizado e respeitar integridade, auditoria e eventual anonimização. A implementação desse processo ainda está pendente.
