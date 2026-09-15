# Arquitetura existente

## Visão geral

```text
Navegador
  → Caddy (HTTPS/proxy reverso no host)
    → Apache + Laravel (web)
      → PostgreSQL 16
    → Laravel queue worker
    → Laravel scheduler
```

O `compose.yaml` define quatro serviços: `postgres`, `hibrido_laravel`, `queue_worker` e `scheduler`. Web, worker e scheduler usam a mesma imagem. PostgreSQL, sessão, cache, filas, notificações, auditoria e dados de negócio compartilham o banco oficial.

## Organização

- `app/Http/Controllers`: autenticação, painel pessoal, gestão, administração, diretório, senha e saúde.
- `app/Http/Requests`: validação/autorização de criação e edição de usuários.
- `app/Http/Middleware/EnsureRole.php`: restrição por perfil.
- `app/Models`: modelos Eloquent de usuários, equipes, solicitações, feriados e auditoria.
- `app/Services`: regras de solicitações e gravação de auditoria.
- `app/Services/VacationRequestService.php`: conflitos e ciclo de vida das solicitações de férias.
- `app/Policies/WorkRequestPolicy.php`: escopo de análise por equipe.
- `app/Support/ReportingCycle.php`: limites e opções do ciclo 20–19.
- `app/Notifications`: mudança de status, resumo de pendências e falha operacional de backup.
- `app/Console/Commands`: criação de Super Admin e importação legada.
- `resources/views`: páginas Blade server-rendered.
- `public/assets`: identidade visual e interações sem pipeline de build frontend.
- `database/migrations`: schema incremental.
- `tests/Feature`: cobertura dos fluxos principais.

## Fluxos importantes

### Solicitação

1. Usuário autenticado seleciona datas no painel.
2. `EmployeeController` valida a entrada.
3. `WorkRequestService` verifica bloqueios, remove duplicatas e grava em transação.
4. Uma restrição única em `(user_id, work_date)` reforça a regra no banco.
5. `AuditService` registra a criação.

### Análise

1. Gestor consulta registros filtrados pelo ciclo e pelas equipes permitidas.
2. `WorkRequestPolicy` autoriza cada solicitação.
3. `WorkRequestService` atualiza status, revisor e horário e registra auditoria.
4. Uma notificação é enviada ao usuário; em produção a fila usa banco.
5. A análise em lote limita 100 itens e verifica autorização antes das alterações.

### Férias

1. Super Admin informa a data de contratação; o serviço gera os períodos anuais completos ao salvar e pela sincronização diária.
2. Colaborador escolhe um saldo e solicita um intervalo futuro imutável, contado em dias corridos inclusivos.
3. O serviço rejeita sobreposição, conflito com home office e insuficiência de saldo; pendências reservam dias.
4. Gestor autorizado aprova ou recusa; aprovação consome e recusa libera o saldo.
5. Férias aprovadas aparecem na visão da equipe e bloqueiam home office no intervalo.
6. Super Admin ajusta exceções de saldo, corrige ou cancela mediante justificativa, preservando auditoria.

### Usuários e permissões

- `users.team` representa a equipe própria do usuário.
- `manager_team` representa equipes administradas; são conceitos distintos.
- `manager_delegations` concede temporariamente ao substituto o escopo de equipes do gestor de origem, entre datas inclusivas.
- Rotas usam `auth` e `role:*`; ações sensíveis também usam Form Requests ou Policy.
- Super Admin acessa todas as equipes; gestor fica restrito aos vínculos.

### Ciclo e exportação

`ReportingCycle` fornece limites 20–19 usados pelo painel e exportação. `ManagerController` aplica pesquisa, equipe, status e seleção de colaboradores diretamente no banco. A planilha é produzida com PhpSpreadsheet.

### Recuperação de senha

As rotas existem, mas `PASSWORD_RECOVERY_ENABLED` controla a disponibilidade. Tokens usam a tabela Laravel `password_reset_tokens`; e-mail depende das configurações `MAIL_*`.

## Interfaces

Não existe API pública versionada. As interfaces são rotas web em `routes/web.php`, majoritariamente HTML/redirect; o envio do painel pessoal aceita resposta JSON. `GET /health/ready`, com eventual `APP_ROUTE_PREFIX`, retorna prontidão do banco e contagem de jobs.

## Inicialização e proxy

O entrypoint aguarda o banco e executa `php artisan migrate --force`, exceto quando `SKIP_MIGRATIONS=true`; depois cria o cache de configuração. A aplicação confia nos cabeçalhos do proxy configurados em `bootstrap/app.php`. Caddy é restrito ao servidor e não é versionado neste repositório. `mixhome.app.br` atende produção e `/homologacao` atende homologação.

## Pontos de atenção

- Não duplique regras de domínio em controllers ou JavaScript.
- Preserve autorização no servidor em qualquer novo filtro ou ação.
- Web, worker e scheduler devem usar código/configuração compatíveis.
- `MeasureRequest` adiciona `Server-Timing` e registra requisições lentas; `AppServiceProvider` registra consultas lentas sem valores dos parâmetros.
- Alterações em rotas devem considerar `APP_ROUTE_PREFIX` da homologação.
- Assets usam query string baseada em `filemtime`; preserve esse mecanismo ou documente sua substituição.
- O deploy automatizado usa `deploy.sh` para reconstruir a imagem e recriar web, worker e scheduler sem remover o PostgreSQL.
