# Handoff

## Objetivo atual

Integrar autenticação centralizada do Jornada Flow ao Keycloak do realm `mixapps`, preservando os dados de negócio e o acesso local de contingência durante a migração.

## Estado atual

Em 2026-10-08, a integração OIDC foi publicada nos commits `584d381` e `7b4fe52` e implantada em homologação pelo deploy final `37836787612`. O fluxo usa Authorization Code, PKCE `S256`, discovery, troca confidential, validação de assinatura/JWKS, algoritmo RS256, issuer, audience, expiração, nonce e `state`. Usuários são vinculados pelo `sub` em `users.keycloak_subject`; o e-mail verificado só participa do primeiro vínculo. `mixhome-user` habilita o acesso comum e `mixhome-admin` protege administração também por middleware, sem substituir equipes e escopos gerenciais locais. Logout local/federado, expiração de sessão, erros seguros, tela de login corporativo e aprovação de deploy por autenticação OIDC recente foram adicionados; login local e senhas permanecem como contingência. O cliente `mixhome-web`, papéis, mapper, audience, JWKS, backup da configuração e secret externo `0600` foram preparados no Keycloak pelo Super Admin. O deploy carrega esse arquivo sem versioná-lo e aplica as URLs públicas conforme o ambiente. Os CIs `37835848534` e `37836545896` aprovaram suíte, PostgreSQL 16 e build. A validação externa confirmou botão corporativo, issuer, client ID, callback de homologação, PKCE, `state`, `nonce` e healthcheck HTTP 200. Falta apenas o Super Admin concluir uma autenticação interativa real para confirmar o retorno autenticado e o papel administrativo. `composer audit` apontou advisories preexistentes em Laravel, CommonMark e Flysystem, registrados no TODO; `firebase/php-jwt` 7.2.1 não apresentou alerta.

Em 2026-10-05, o painel de solicitações da gestão foi simplificado no commit `0b1a2a0`, publicado em homologação pelo deploy `37358507254` e promovido para produção pelo deploy `37359484833`. O título foi reduzido, a exportação virou uma ação recolhível, filtros avançados permanecem fechados até serem solicitados ou possuírem parâmetros ativos, as três métricas foram condensadas em uma faixa e os cards de prioridade/distribuição passaram para um resumo opcional do ciclo. Nenhuma consulta ou função foi removida. Pint, compilação Blade, `git diff --check` e a suíte completa passaram, com 98 testes e 532 assertions; os CIs `37358247777` e `37358885964` confirmaram PostgreSQL 16 e build. Os deploys concluíram healthcheck e smoke autenticado, e as verificações públicas adicionais retornaram HTTP 200.

Em 2026-10-05, o login foi simplificado no commit `ed18999` para um único formulário compartilhado por colaboradores, gestores e Super Admins e publicado em homologação pelo deploy `37355739514`. A escolha manual de perfil e a opção textual de primeiro acesso foram removidas; após autenticar, o servidor encaminha colaboradores ao painel pessoal e perfis gerenciais à gestão, mantendo o acesso posterior ao painel pessoal pelo menu principal. O link de recuperação de senha permanece disponível. Pint e a suíte completa passaram, com 98 testes e 529 assertions; o CI `37355478983` confirmou PostgreSQL 16 e build. O deploy concluiu healthcheck e smoke autenticado, e a verificação pública confirmou o novo formulário sem o menu antigo nem a opção removida.

Em 2026-10-05, as correções dos três achados da auditoria foram publicadas no commit `908b9f7`, implantadas em homologação pelo deploy `37328399858` e promovidas para produção pelo deploy `37329595946`, após validação do CI e de homologação. Contas inativas e sessões com versão anterior são recusadas por middleware; a desativação incrementa `auth_version` e gira o token persistente, preservando os registros de sessão. Solicitações foram limitadas a 31 datas e dez envios por minuto, com defesa no controller e no serviço. Deploy e monitor de backup agora exigem `DEPLOY_SSH_KNOWN_HOSTS` fixo e `StrictHostKeyChecking=yes`. A chave pública Ed25519 foi conferida diretamente no servidor pelo canal SSH já confiável e cadastrada nos environments `homologacao` e `producao`, sem alterar a chave pessoal. Localmente, seis testes focados e a suíte completa passaram, com 98 testes e 526 assertions; Pint, `git diff --check`, sintaxe PHP e build da imagem também passaram. Os CIs `37328100022` e `37328856017` confirmaram a suíte, as migrations no PostgreSQL 16 e o build. Os deploys concluíram backup, migração, healthcheck e smoke autenticado; a verificação pública adicional de produção retornou HTTP 200.

Em 2026-09-25, foram implementados o abono pecuniário e as validações da CLT no módulo de férias. A solicitação oferece período integral, descanso com conversão de 1/3 em abono, período personalizado com abono opcional e solicitação exclusiva de abono sem datas de descanso. O servidor valida titularidade, saldo, prazo do abono, antecedência mínima de 30 dias, início compatível com feriados/repouso e fracionamento em até três períodos (um de 14 dias e os demais de 5). Descanso e abono reservam e consomem o mesmo saldo. Histórico, gestão, notificação, auditoria e CSV identificam o abono; solicitações exclusivas não bloqueiam dias de trabalho.

Em 2026-09-25, o indicador foi implementado localmente no painel de gestão. A consulta reutiliza `work_requests`, considera somente registros aprovados no mês/ano selecionado, respeita equipes próprias e delegadas do gestor e retorna os colaboradores em ordem alfabética. O card atualiza os dados de forma assíncrona, possui carregamento, estado vazio, tooltip acessível e rolagem horizontal no mobile, sem nova biblioteca ou alteração de banco. A suíte local passou com 84 testes e 442 assertions; envio e publicação ainda não foram realizados nesta tarefa.

Em 2026-09-23, a modalidade de trabalho foi adicionada localmente a `work_requests`, preservando os registros anteriores como `home_office`. O colaborador escolhe home office ou presencial no calendário; ambos nascem pendentes, respeitam bloqueios e férias, impedem modalidades ativas conflitantes e seguem pela mesma aprovação, auditoria, notificação, filtros e exportação. A migration e a publicação ainda não foram executadas fora dos testes locais.

Em 2026-09-23, a PWA foi implementada com manifesto dinâmico, escopo compatível com a raiz de produção e `/homologacao`, ícones, registro de Service Worker, comando de instalação e tela offline. O Service Worker armazena somente assets públicos e a tela offline; navegações e dados de negócio usam rede e não são persistidos. A homologação foi publicada pelo deploy `35870636198`; smoke autenticado, manifesto, escopo, ícones, Service Worker, tela offline e controle de instalação foram validados. Produção permanece sem essa entrega.

Em 2026-09-22, a implementação local da aprovação web foi preparada, mas ainda não foi enviada ao GitHub nem instalada no servidor. O Super Admin vê os pacotes pendentes do próprio ambiente, confirma a senha e gera autorização curta vinculada ao digest. O gate do host verifica assinatura e prazo antes de publicar; a aprovação SSH continua disponível. Os testes Laravel locais passaram (70 testes, 383 assertions), assim como o teste do verificador Python, a sintaxe shell, o Compose e o build local da imagem. A ativação exige preparar diretórios, chave por ambiente e atualizar os scripts root-owned conforme `docs/OPERACAO.md`, começando por homologação.

Produção recebeu em 2026-09-18 o commit `065f225` pelo deploy `35366275409`; publicação e smoke autenticado passaram. As correções de segurança do commit `03fd193` estão incluídas: cadastro público removido, login limitado por e-mail/IP e proteção de CSV/XLSX contra fórmulas. O smoke de produção de 2026-09-21 (`35591770253`) também passou. Recuperação de senha por SMTP está habilitada e é o fluxo oficial de primeiro acesso após o Super Admin criar a conta.

Em 2026-09-21, a revisão operacional confirmou que o ambiente GitHub `producao` ainda não possui regras de proteção. O Super Admin identificou sua conta como `CasMaster`, mas a API do GitHub recusou `required_reviewers` com HTTP 422 porque o plano não oferece essa regra para o repositório privado. O ambiente vazio criado durante a tentativa foi removido; o deploy não recebeu um gate ilusório. A simulação de retenção com dados reais de homologação ainda não foi executada. Um monitor diário independente do aplicativo para verificar a presença de dump e checksum recentes no B2 foi adicionado em `.github/workflows/backup-monitor.yml`; a primeira execução manual `35629353820` passou. Uma automação trimestral na tarefa Codex foi criada para lembrar a conferência da restauração e solicitar autorização do Super Admin; ela não executa restauração automaticamente.

Ainda em 2026-09-21, foi confirmado acesso SSH por um IP já presente em `known_hosts`. A chave pessoal `admin` também era usada pelo secret de deploy do GitHub, conforme fingerprints nos logs; ela não foi alterada. Um par novo e exclusivo para a automação foi criado fora do repositório. Os scripts `mixhome-ci-gate` e `mixhome-approve` foram instalados como root no servidor, e a entrada da chave nova em `authorized_keys` tem comando forçado. Os workflows foram publicados no commit `a5ab9b4` e `DEPLOY_SSH_KEY` foi substituído em homologação e produção. O monitor `35632343103` passou usando o fingerprint da nova chave; execução arbitrária foi negada e a chave pessoal continuou funcionando. O primeiro deploy controlado em homologação (`35634509195`) recebeu aprovação manual e passou pelo healthcheck e smoke autenticado; produção não foi recriada e seu healthcheck retornou 200. A chave pessoal esteve no GitHub e eventuais cópias históricas não são revogadas pela troca do secret.

Em 2026-09-14, o destino externo foi alterado para Backblaze B2. O remoto `b2-mixhome` está configurado exclusivamente no servidor, o bucket privado `mixhome-backups` recebeu o primeiro dump com checksum válido e a execução diária foi agendada para 05:15 UTC (02:15 em Campinas).

O primeiro teste de restauração a partir do B2 foi aprovado em um PostgreSQL 16 descartável: checksum válido, oito migrations e 18 usuários recuperados. O container e os arquivos temporários foram removidos ao final, sem alteração do banco de produção.

Falhas do backup disparam `hibrido:notify-backup-failure`, que envia e-mail e notificação interna diretamente aos Super Admins ativos. A conta técnica excluída no cron não recebe alertas, e falhas individuais não bloqueiam outros destinatários. Produção deve manter um único cron às 05:15 UTC; o alerta depende da aplicação, PostgreSQL e SMTP.

## Alterações atuais

- Em 2026-10-09, o realm `mixapps` recebeu um tema Keycloak próprio e persistente no projeto `/home/admin/MixIdentity`: identidade visual Mix Fiscal/Mix Apps, textos em português do Brasil, seletor de idioma, contraste e foco acessíveis, layout responsivo e tema de e-mail em português. O tema está ativo no login central compartilhado; nenhum segredo foi incluído. O estado anterior do realm e os arquivos substituídos foram preservados em `MixIdentity/backups/`.

- Em 2026-10-09, a migração foi validada em homologação. João permaneceu vinculado e confirmou o fluxo; Felipe, Victor e Marcio foram criados, receberam `mixhome-user`, receberam o convite e foram vinculados localmente. `gestor@local`, a conta de smoke e `homolog@mixfiscal.com.br` são contas técnicas sem migração; a identidade Keycloak criada durante o teste para `homolog@mixfiscal.com.br` foi desativada, sem excluir a conta local. O SMTP do realm foi configurado e validado, com backups anteriores preservados em `MixIdentity/backups/keycloak/`.

- Migração controlada de contas locais para o Keycloak implantada em homologação: cliente de serviço dedicado, simulação obrigatória por padrão, vínculo apenas por e-mail remoto único e confirmado, criação opcional sem copiar senha, atribuição de papéis e envio das ações de verificação/definição de senha. O diretório mostra vinculados e pendentes.

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
- Solicitações de férias oferecem período integral, descanso com abono de 1/3, período personalizado com abono opcional e pedido exclusivo de abono; o abono integra o saldo real, a aprovação, a auditoria, as notificações e as exportações. O domínio valida antecedência mínima de 30 dias, início compatível com feriados/repouso semanal e fracionamento legal.

## Validação

- Tema Keycloak `mixapps`: container saudável, login público HTTP 200, CSS e logotipo públicos HTTP 200, idioma `pt-BR` ativo e conferência visual aprovada após invalidar o cache do asset versionado `mixapps-v2.css`.
- Férias, abono e regras CLT: Laravel Pint, sintaxe JavaScript e suíte completa aprovados em 2026-09-25, com 92 testes e 496 assertions, incluindo abono em período personalizado, solicitação exclusiva sem datas, gestão e CSV.
- Modalidade presencial: suíte completa aprovada em 2026-09-23, com 81 testes e 431 assertions; inclui criação pendente, aprovação gerencial, conflito entre modalidades e filtro da gestão.
- Modalidade presencial: Laravel Pint, sintaxe JavaScript, `git diff --check` e build local da imagem aprovados.
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
- Homologação `35634509195`: primeiro deploy pelo gate, aprovação manual do digest, backup prévio, healthcheck e smoke autenticado aprovados.
- Produção `33905491385`: deploy, backup, healthcheck e smoke aprovados.
- Cinco medições externas de produção retornaram HTTP 200 entre 63 ms e 223 ms; `Server-Timing` observado em 9,48 ms.
- Contas sintéticas e secrets separados provisionados nos dois ambientes; smokes autenticados `33907539221` (homologação) e `33907612711` (produção) aprovados.
- A credencial inicialmente usada na criação de homologação foi imediatamente rotacionada por ter sido ecoada pelo terminal; sessões foram revogadas, a rotação foi auditada e somente a substituta não exibida permanece válida.

## Próximo passo

Acompanhar os próximos monitores de produção. Por orientação do Super Admin, alterações validadas devem seguir por padrão para homologação; produção continua dependendo de autorização explícita. A simulação de retenção em homologação e a revisão da chave pessoal antiga continuam pendentes. A revisão trimestral da restauração já está agendada como lembrete, sem execução automática.

## Limites

- O rollback automático restaura a imagem, não desfaz migrations; migrations devem permanecer compatíveis com a versão anterior.
- Alertas do smoke dependem das notificações configuradas no GitHub.
- Datas municipais continuam sob manutenção manual enquanto o endpoint contratado não as fornecer.
