# Instruções para agentes de IA

Este repositório contém o HÍBRIDO, sistema corporativo da Mix Fiscal para solicitação, aprovação, acompanhamento e exportação de dias de home office. A aplicação oficial está em `laravel-app/`. O contexto compartilhado e independente de ferramenta está neste arquivo e em `.ai/`.

## Leitura obrigatória antes de alterar código

Todo agente deve, nesta ordem:

1. Ler `AGENTS.md`.
2. Ler `.ai/PROJECT.md`.
3. Ler `.ai/ARCHITECTURE.md`.
4. Ler `.ai/DECISIONS.md`.
5. Ler `.ai/HANDOFF.md`.
6. Executar `git status` e, quando houver alterações, `git diff`.
7. Não reverter alterações existentes sem entender sua origem.
8. Preservar código e alterações feitos por outros agentes ou desenvolvedores.
9. Executar os testes relevantes depois das alterações.
10. Atualizar a documentação de contexto quando uma decisão importante for tomada.

Consulte também `.ai/DATABASE.md`, `.ai/ENVIRONMENT.md` e `.ai/TODO.md` conforme a tarefa.

## Limites do projeto

- Trate `laravel-app/` como a implementação oficial.
- Não restaure implementações PHP/SQLite antigas sem uma tarefa explícita. Os comandos de importação legada existem somente para migrações controladas.
- Não altere framework, banco ou arquitetura por preferência pessoal.
- Faça alterações pequenas e focadas no objetivo atual.
- Não grave secrets, dados pessoais, dumps ou conteúdo de `.env` em código, documentação, logs de tarefa ou commits.
- Não execute restauração de banco, rollback destrutivo, exclusão de volume ou publicação em produção sem autorização explícita do Super Admin, autoridade operacional definida para essas ações.
- Não use `podman-compose down -v`; o volume contém dados persistentes.
- Não faça commit, push, merge, deploy ou Pull Request sem autorização explícita do usuário.
- Caddy é restrito ao servidor: não crie uma cópia presumida do Caddyfile no repositório. Produção usa `mixhome.app.br` e homologação usa `/homologacao`.
- SMTP/recuperação de senha permanecem adiados. Backups externos devem ir para OneDrive por integração sem credenciais versionadas.
- Preserve por dois anos auditoria, sessões, notificações, solicitações recusadas e contas desativadas até existir rotina autorizada de expurgo/anonimização.

## Arquitetura e convenções

- Backend server-rendered em PHP 8.3 e Laravel 12, com Blade, CSS e JavaScript próprios.
- PostgreSQL 16 é o banco oficial; Eloquent é a camada de persistência.
- Regras de solicitações ficam em `app/Services/WorkRequestService.php`.
- Auditoria fica em `app/Services/AuditService.php`.
- Autorização de análise fica em `app/Policies/WorkRequestPolicy.php`; esconder controles na interface não substitui autorização no servidor.
- Cálculos do ciclo 20–19 ficam em `app/Support/ReportingCycle.php`.
- Validações reutilizáveis de usuários usam Form Requests. Controllers coordenam HTTP e não devem duplicar regras de domínio.
- Código PHP segue PSR-4 e o formato validado pelo Laravel Pint.
- Assets do produto ficam em `public/assets/`; não há etapa Node/Vite ativa para esses arquivos.
- Preserve o fuso `America/Sao_Paulo`, os três perfis (`employee`, `manager`, `super_admin`) e a imutabilidade das solicitações pelo colaborador.
- Mantenha migrations progressivas. Não edite uma migration já aplicada para mudar produção; crie outra migration.

## Estrutura essencial

```text
.
├── .ai/                  contexto compartilhado entre agentes
├── .github/workflows/    CI e deploy manual
├── docs/                 arquitetura, operação, migração e manual
├── scripts/              backup e monitoramento
└── laravel-app/
    ├── app/              domínio, HTTP, modelos, serviços e comandos
    ├── database/         migrations, factories e seeders
    ├── public/assets/    CSS, JavaScript e imagens
    ├── resources/views/  templates Blade
    ├── routes/           rotas web e agendamento
    └── tests/            testes PHPUnit
```

## Execução e validação

Ambiente com containers:

```bash
cd laravel-app
cp .env.example .env
podman-compose up -d --build
```

Configure as variáveis descritas em `.ai/ENVIRONMENT.md`; nunca copie valores de produção. Validação padrão:

```bash
cd laravel-app
vendor/bin/pint --test
php artisan test
podman build -t hibrido-home-office:local .
```

Para mudanças menores, execute no mínimo o teste diretamente relacionado. Antes de entregar, rode a suíte inteira quando as dependências estiverem disponíveis. O CI executa Pint, PHPUnit e build da imagem.

## Banco e migrations

Leia `.ai/DATABASE.md` antes de alterar schema. Com o ambiente configurado:

```bash
cd laravel-app
php artisan make:migration nome_descritivo
php artisan migrate
php artisan migrate:status
```

O entrypoint executa `php artisan migrate --force` ao iniciar o serviço web. Portanto, uma migration incompatível pode impedir a aplicação de subir. Teste migrations em banco descartável ou homologação e faça backup antes de produção.

## Colaboração entre agentes

- Nunca assuma exclusividade. Antes de uma alteração importante, execute `git status`, `git diff` e, quando necessário, `git log --oneline -10`.
- Nunca apague, sobrescreva ou reverta trabalho desconhecido.
- Não misture refatorações ou correções não solicitadas com a tarefa atual.
- Registre contexto durável no repositório, não apenas na conversa.
- Registre decisões arquiteturais comprovadas em `.ai/DECISIONS.md`.
- Se o trabalho ficar incompleto, atualize `.ai/HANDOFF.md` antes de encerrar.
- Se houver alterações concorrentes no mesmo arquivo, pare, compare os diffs e coordene a integração em vez de escolher uma versão silenciosamente.

Estratégia recomendada para trabalho simultâneo:

```text
main
├── ai/codex/<tarefa>
├── ai/claude/<tarefa>
├── ai/gemini/<tarefa>
└── ai/<agente>/<tarefa>
```

Use uma branch por agente/tarefa e integre por Pull Request quando houver trabalho simultâneo. Rebase/merge e resolução de conflitos devem preservar mudanças legítimas de todos os participantes.

## Documentação e handoff

- Atualize `.ai/DECISIONS.md` somente para decisões permanentes, com evidência no código ou documentação.
- Atualize `.ai/TODO.md` quando um débito confirmado surgir ou for resolvido; mantenha sugestões separadas.
- `.ai/HANDOFF.md` descreve apenas o estado corrente, não um histórico acumulado.
- Ao finalizar uma tarefa incompleta, informe objetivo, estado, arquivos alterados, validações executadas, bloqueios e próximo passo verificável.
- Arquivos específicos de uma ferramenta podem existir, mas devem apontar para `AGENTS.md` e `.ai/` em vez de duplicar regras.
