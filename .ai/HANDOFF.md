# Handoff

## Objetivo atual

Manter o MixHome seguro e operacional, promovendo para produção somente mudanças já validadas em homologação.

## Estado atual

Produção permanece na versão anteriormente aprovada. A `main` e homologação receberam em 2026-09-18 o commit `03fd193`, que corrige os três achados médios da auditoria Codex Security: remove cadastro público, limita login por e-mail/IP e protege CSV/XLSX contra fórmulas. O deploy de homologação `35347548481` concluiu publicação, healthcheck e smoke autenticado com sucesso. Recuperação de senha por SMTP está habilitada e é o fluxo oficial de primeiro acesso após o Super Admin criar a conta.

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
- CI `33905163885`: aprovado, incluindo PostgreSQL 16 e build.
- Homologação `33905331399`: deploy, backup, healthcheck e smoke aprovados.
- Homologação `35347548481`: correções de segurança, healthcheck e smoke autenticado aprovados.
- Produção `33905491385`: deploy, backup, healthcheck e smoke aprovados.
- Cinco medições externas de produção retornaram HTTP 200 entre 63 ms e 223 ms; `Server-Timing` observado em 9,48 ms.
- Contas sintéticas e secrets separados provisionados nos dois ambientes; smokes autenticados `33907539221` (homologação) e `33907612711` (produção) aprovados.
- A credencial inicialmente usada na criação de homologação foi imediatamente rotacionada por ter sido ecoada pelo terminal; sessões foram revogadas, a rotação foi auditada e somente a substituta não exibida permanece válida.

## Próximo passo

Repetir trimestralmente a restauração em banco descartável, mediante autorização do Super Admin, e avaliar monitor externo redundante para falhas do backup; manter retenção desabilitada até uma simulação conferida pelo Super Admin.

## Limites

- O rollback automático restaura a imagem, não desfaz migrations; migrations devem permanecer compatíveis com a versão anterior.
- Alertas do smoke dependem das notificações configuradas no GitHub.
- Datas municipais continuam sob manutenção manual enquanto o endpoint contratado não as fornecer.
