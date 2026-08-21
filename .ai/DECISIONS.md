# Decisões técnicas

Somente decisões comprovadas pelo código ou pela documentação existente são registradas aqui.

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

## 2026-08-20 — OneDrive como cópia externa de backup

### Contexto

Os dumps locais precisam de uma cópia fora do servidor.

### Decisão

Usar OneDrive como destino externo dos backups PostgreSQL.

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
