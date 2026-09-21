# Handoff

## Objetivo atual

Manter o MixHome seguro e operacional, promovendo para produção somente mudanças já validadas em homologação.

## Estado atual

Produção recebeu em 2026-09-18 o commit `065f225` pelo deploy `35366275409`; publicação e smoke autenticado passaram. As correções de segurança do commit `03fd193` estão incluídas: cadastro público removido, login limitado por e-mail/IP e proteção de CSV/XLSX contra fórmulas. O smoke de produção de 2026-09-21 (`35591770253`) também passou. Recuperação de senha por SMTP está habilitada e é o fluxo oficial de primeiro acesso após o Super Admin criar a conta.

Em 2026-09-21, a revisão operacional confirmou que o ambiente GitHub `producao` ainda não possui regras de proteção. O Super Admin identificou sua conta como `CasMaster`, mas a API do GitHub recusou `required_reviewers` com HTTP 422 porque o plano não oferece essa regra para o repositório privado. O ambiente vazio criado durante a tentativa foi removido; o deploy não recebeu um gate ilusório. A simulação de retenção com dados reais de homologação ainda não foi executada. Um monitor diário independente do aplicativo para verificar a presença de dump e checksum recentes no B2 foi adicionado em `.github/workflows/backup-monitor.yml`; a primeira execução manual `35629353820` passou. Uma automação trimestral na tarefa Codex foi criada para lembrar a conferência da restauração e solicitar autorização do Super Admin; ela não executa restauração automaticamente.

Ainda em 2026-09-21, foi confirmado acesso SSH por um IP já presente em `known_hosts`. A chave pessoal `admin` também era usada pelo secret de deploy do GitHub, conforme fingerprints nos logs; ela não foi alterada. Um par novo e exclusivo para a automação foi criado fora do repositório. Os scripts `mixhome-ci-gate` e `mixhome-approve` foram instalados como root no servidor, e a entrada da chave nova em `authorized_keys` tem comando forçado. Os workflows foram publicados no commit `a5ab9b4` e `DEPLOY_SSH_KEY` foi substituído em homologação e produção. O monitor `35632343103` passou usando o fingerprint da nova chave; execução arbitrária foi negada e a chave pessoal continuou funcionando. Ainda falta acompanhar o primeiro deploy controlado em homologação. A chave pessoal esteve no GitHub e eventuais cópias históricas não são revogadas pela troca do secret.

Em 2026-09-14, o destino externo foi alterado para Backblaze B2. O remoto `b2-mixhome` está configurado exclusivamente no servidor, o bucket privado `mixhome-backups` recebeu o primeiro dump com checksum válido e a execução diária foi agendada para 05:15 UTC (02:15 em Campinas).

O primeiro teste de restauração a partir do B2 foi aprovado em um PostgreSQL 16 descartável: checksum válido, oito migrations e 18 usuários recuperados. O container e os arquivos temporários foram removidos ao final, sem alteração do banco de produção.

Falhas do backup disparam `hibrido:notify-backup-failure`, que envia e-mail e notificação interna diretamente aos Super Admins ativos. A conta placeholder `gestor@mixfiscal.com.br` é excluída no cron, e falhas individuais não bloqueiam outros destinatários. Produção deve manter um único cron às 05:15 UTC; o alerta depende da aplicação, PostgreSQL e SMTP.

## Alterações atuais

- Cadastro público removido; criação de contas permanece exclusiva do Super Admin.
- Primeiro acesso e redefinição usam token individual por e-mail, com expiração e revogação das sessões anteriores.
- Login limitado a cinco tentativas por minuto para a combinação de e-mail normalizado e IP.
- CSVs neutralizam prefixos de fórmula e o XLSX força campos de nome como texto.
- O painel mostra o período anual em formação e permite planejar férias antecipadamente, mas restringe o início à data de liberação e o fim ao prazo de utilização; o servidor reforça os mesmos limites.
- A solicitação usa um calendário de intervalo próprio do MixHome, responsivo e acessível, com datas fora da janela do saldo desabilitadas.
- O deploy sincroniza de forma idempotente os saldos de férias após o healthcheck, cobrindo datas de admissão cadastradas antes da implantação do cálculo automático.
- Módulo de férias com geração automática dos períodos pela data de contratação, contagem inclusiva, saldo, reserva/consumo, fracionamento, análise por equipe, bloqueio cruzado com home office, correção/cancelamento auditado e CSV.
- Delegação temporária entre gestores, administrada exclusivamente pelo Super Admin, com autorização aplicada na Policy.
- Justificativa opcional individual ou em lote, visível no histórico do colaborador e registrada na auditoria.
- Ordenação, 25/50/100 registros por página e preferência não pessoal de filtros salva no navegador.
- Indicador de última sincronização de feriados e manutenção manual para datas municipais/corporativas.
- Cabeçalho `Server-Timing`, logs de requisições e SQL lentos com limites configuráveis e sem valores dos parâmetros.
- CI adicional em PostgreSQL 16 e smoke externo de produção a cada 15 minutos.
- Deploy com dump/checksum prévio, verificação de saúde e tentativa de retorno à imagem anterior.
- `.dockerignore` evita copiar cache local de descoberta de pacotes para a imagem de produção.

## Validação

- Laravel Pint: aprovado após as correções de segurança.
- PHPUnit/SQLite: 67 testes, 360 assertions, todos aprovados.
- Migration completa executada com sucesso em PostgreSQL 16 temporário.
- Build da imagem de produção: aprovado.
- Sintaxe JavaScript e scripts shell: aprovada.
- `git diff --check`: aprovado.
- Monitor externo B2 `35629353820`: execução manual aprovada, dump recente e checksum localizados.
- CI `35629335190`: suíte SQLite/PostgreSQL e build aprovados após publicação do monitor.
- CI `33905163885`: aprovado, incluindo PostgreSQL 16 e build.
- Homologação `33905331399`: deploy, backup, healthcheck e smoke aprovados.
- Homologação `35347548481`: correções de segurança, healthcheck e smoke autenticado aprovados.
- Produção `33905491385`: deploy, backup, healthcheck e smoke aprovados.
- Cinco medições externas de produção retornaram HTTP 200 entre 63 ms e 223 ms; `Server-Timing` observado em 9,48 ms.
- Contas sintéticas e secrets separados provisionados nos dois ambientes; smokes autenticados `33907539221` (homologação) e `33907612711` (produção) aprovados.
- A credencial inicialmente usada na criação de homologação foi imediatamente rotacionada por ter sido ecoada pelo terminal; sessões foram revogadas, a rotação foi auditada e somente a substituta não exibida permanece válida.

## Próximo passo

Acompanhar o primeiro deploy controlado em homologação antes de usar o novo fluxo em produção. A simulação de retenção em homologação e a revisão da chave pessoal antiga continuam pendentes. A revisão trimestral da restauração já está agendada como lembrete, sem execução automática.

## Limites

- O rollback automático restaura a imagem, não desfaz migrations; migrations devem permanecer compatíveis com a versão anterior.
- Alertas do smoke dependem das notificações configuradas no GitHub.
- Datas municipais continuam sob manutenção manual enquanto o endpoint contratado não as fornecer.
