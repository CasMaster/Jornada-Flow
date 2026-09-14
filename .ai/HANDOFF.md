# Handoff

## Objetivo atual

Validar o pacote de continuidade operacional, painéis gerenciais, exportação, calendário acessível, retenção e observabilidade antes de publicar.

## Estado atual

Implementação publicada na `main` e implantada em homologação e produção em 2026-09-04. Os trabalhos foram preservados em `d97df74` (conta sintética), `bdf0b36` (apresentação de feriados), `f9cbef0` (fluxos e confiabilidade) e `e7b8b33` (destino seguro do backup). A migration progressiva foi aplicada pelos deploys. Recuperação de senha por SMTP está habilitada e foi validada pelo responsável.

Em 2026-09-14, o destino externo foi alterado para Backblaze B2. O remoto `b2-mixhome` está configurado exclusivamente no servidor, o bucket privado `mixhome-backups` recebeu o primeiro dump com checksum válido e a execução diária foi agendada para 05:15 UTC (02:15 em Campinas).

O primeiro teste de restauração a partir do B2 foi aprovado em um PostgreSQL 16 descartável: checksum válido, oito migrations e 18 usuários recuperados. O container e os arquivos temporários foram removidos ao final, sem alteração do banco de produção.

Falhas do backup disparam `hibrido:notify-backup-failure`, que envia e-mail e notificação interna diretamente aos Super Admins ativos. Produção deve manter um único cron às 05:15 UTC; o alerta depende da aplicação, PostgreSQL e SMTP.

## Alterações atuais

- Delegação temporária entre gestores, administrada exclusivamente pelo Super Admin, com autorização aplicada na Policy.
- Justificativa opcional individual ou em lote, visível no histórico do colaborador e registrada na auditoria.
- Ordenação, 25/50/100 registros por página e preferência não pessoal de filtros salva no navegador.
- Indicador de última sincronização de feriados e manutenção manual para datas municipais/corporativas.
- Cabeçalho `Server-Timing`, logs de requisições e SQL lentos com limites configuráveis e sem valores dos parâmetros.
- CI adicional em PostgreSQL 16 e smoke externo de produção a cada 15 minutos.
- Deploy com dump/checksum prévio, verificação de saúde e tentativa de retorno à imagem anterior.
- `.dockerignore` evita copiar cache local de descoberta de pacotes para a imagem de produção.

## Validação

- Laravel Pint: aprovado.
- PHPUnit/SQLite: 35 testes, 233 assertions, todos aprovados.
- Migration completa executada com sucesso em PostgreSQL 16 temporário.
- Build da imagem de produção: aprovado.
- Sintaxe JavaScript e scripts shell: aprovada.
- `git diff --check`: aprovado.
- CI `33905163885`: aprovado, incluindo PostgreSQL 16 e build.
- Homologação `33905331399`: deploy, backup, healthcheck e smoke aprovados.
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
