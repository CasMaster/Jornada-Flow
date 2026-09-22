# Operação do MixHome

## Ambientes atuais

| Ambiente | Porta interna | Containers padrão |
|---|---:|---|
| Produção | 8082 | `hibrido-home-office-prod`, `hibrido-home-office-postgres-prod` |
| Homologação | 8081 | `hibrido-home-office-laravel`, `hibrido-home-office-postgres` |

O proxy reverso publica a produção na raiz do endereço e mantém a homologação sob `/homologacao`.

## Variáveis obrigatórias

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:CHAVE_PRIVADA
APP_URL=https://mixhome.app.br
ASSET_URL=https://mixhome.app.br
APP_TIMEZONE=America/Sao_Paulo
APP_PORT=8082
APP_BIND_IP=127.0.0.1
APP_CONTAINER_NAME=hibrido-home-office-prod
POSTGRES_CONTAINER_NAME=hibrido-home-office-postgres-prod
POSTGRES_HOST_PORT=15432
DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=hibrido
DB_USERNAME=hibrido
DB_PASSWORD=SENHA_PRIVADA
PASSWORD_RECOVERY_ENABLED=true
MAIL_MAILER=smtp
MAIL_HOST=SERVIDOR_SMTP
MAIL_PORT=587
MAIL_USERNAME=USUARIO_SMTP
MAIL_PASSWORD=SENHA_SMTP
MAIL_FROM_ADDRESS=hibrido@DOMINIO
MAIL_FROM_NAME="MixHome | Mix Fiscal"
AUTOMATED_NOTIFICATION_EXCLUDED_EMAILS=gestor@mixfiscal.com.br
```

O `.env` deve ter permissão `600` e nunca pode entrar no Git.

O container Laravel deve publicar sua porta apenas em `127.0.0.1`. O Caddy é a
única entrada HTTP pública e encaminha as requisições para a porta local do
ambiente. Não use `0.0.0.0` em `APP_BIND_IP` em produção.

## Recuperação de senha e SMTP

SMTP e recuperação de senha estão habilitados e validados em homologação e produção. Mantenha `PASSWORD_RECOVERY_ENABLED=true`, `APP_URL` público em HTTPS e as variáveis `MAIL_*` corretas nos três processos da aplicação.

O cadastro público não existe. O Super Admin cria a conta no diretório e, quando não define uma senha provisória, o sistema envia um link individual de definição de senha. O token expira em 60 minutos, é de uso único e a redefinição encerra as sessões anteriores. Após alterar SMTP ou recuperação, recrie web, worker e scheduler e valide o fluxo completo em homologação antes de promover para produção.

O login aceita no máximo cinco tentativas por minuto para a mesma combinação de e-mail normalizado e IP. Investigue respostas HTTP 429 recorrentes antes de alterar esse limite.

## Desempenho e monitoramento

Toda resposta web inclui o cabeçalho `Server-Timing` com o tempo interno do Laravel. Requisições acima de `SLOW_REQUEST_MS` (750 ms por padrão) e consultas acima de `SLOW_QUERY_MS` (250 ms) são registradas como `slow_request` e `slow_query`. Consultas são gravadas sem os valores dos parâmetros, evitando dados pessoais nos logs.

Consulte os eventos com `podman logs hibrido-home-office-prod` e os processos worker/scheduler correspondentes. O workflow `Smoke test` valida produção a cada 15 minutos; falhas aparecem no GitHub Actions. `scripts/monitor-production.sh` também mede o endpoint de prontidão e falha quando ultrapassa `MAX_RESPONSE_SECONDS` (2 segundos por padrão).

## Deploy seguro e rollback

`deploy.sh` cria um dump PostgreSQL com checksum e permissão restrita na pasta `backups/` do próprio ambiente (ou em `BACKUP_DIR`, quando definido) antes de reconstruir os serviços. Essa pasta é excluída da imagem. Depois da recriação, executa repetidamente o healthcheck da imagem e sincroniza os saldos de férias das pessoas com data de admissão, incluindo cadastros anteriores à implantação dessa funcionalidade. Se a aplicação não ficar saudável, volta a apontar os serviços para a imagem anterior e encerra com erro. O smoke HTTPS externo ainda é obrigatório depois desse processo.

O retorno da imagem não desfaz migrations. Toda migration de produção deve ser progressiva e compatível com a versão anterior; se uma mudança de schema impedir o retorno, interrompa a operação e use o backup pré-deploy somente mediante autorização explícita do Super Admin.

## Acesso administrativo pelo DBeaver

O PostgreSQL é publicado somente no loopback do servidor em
`127.0.0.1:${POSTGRES_HOST_PORT}`. Ele não deve ser liberado no firewall público.

No DBeaver, crie uma conexão PostgreSQL com:

- host do banco: `127.0.0.1`;
- porta do banco: `15432` (ou o valor de `POSTGRES_HOST_PORT`);
- banco e usuário: valores de `DB_DATABASE` e `DB_USERNAME`;
- túnel SSH: servidor de produção, porta `22`, usuário `admin`.

A senha do banco é o valor de `DB_PASSWORD` no `.env` de produção.

## Publicação segura

1. Gere um dump do PostgreSQL.
2. Valide os testes no código que será publicado.
3. Atualize os arquivos no diretório do ambiente.
4. Construa a nova imagem.
5. Remova e recrie somente o container da aplicação.
6. Preserve o volume PostgreSQL.
7. Verifique login, assets, contagens e saúde do banco.

Exemplo de reconstrução da aplicação:

```bash
cd /opt/hibrido-home-office-prod
podman build -t hibrido-home-office-prod_hibrido_laravel -f Dockerfile .
podman rm -f hibrido-home-office-prod
podman-compose up -d
```

Não execute `podman-compose down -v`: a opção `-v` remove volumes e pode apagar o banco.

### Smoke test automatizado

O workflow manual de deploy executa `scripts/smoke-environment.sh` depois da
publicação, tanto em homologação quanto em produção. Sem credenciais, ele
valida pela entrada HTTPS pública:

- resposta pronta do banco em `/health/ready`;
- formulário de login e token CSRF;
- referências e entrega dos assets sob `/homologacao` na homologação;
- atributos `Secure` e `HttpOnly` no cookie de sessão;

Quando `SMOKE_EMAIL` e `SMOKE_PASSWORD` estão configurados juntos, o teste
também valida autenticação real, renovação segura da sessão e acesso ao painel.

Cadastre `SMOKE_EMAIL` e `SMOKE_PASSWORD` como **Environment secrets**, separados
em `homologacao` e `producao`, seguindo o procedimento abaixo. Não use secrets
compartilhados de repositório/organização com esses nomes como fallback.
`SMOKE_BASE_URL` é uma variável opcional do Environment; na ausência dela, o
script usa `https://mixhome.app.br` (sem `/homologacao`, acrescentado pelo workflow).

#### Provisionamento inicial da conta sintética

Pré-requisitos: código contendo `hibrido:create-smoke-user` já disponível na
imagem do ambiente, acesso SSH operacional autorizado e acesso para administrar
os secrets do repositório. A publicação desse código em produção exige autorização
explícita do Super Admin; este procedimento não executa deploy nem migrations.

1. No cofre de senhas aprovado, crie duas entradas separadas, uma para cada ambiente.
   Gere uma senha aleatória **diferente** em cada entrada, preferencialmente com
   32 caracteres ASCII, contendo maiúsculas, minúsculas, números e símbolos.
   O comando exige 20–72 caracteres e no máximo 72 bytes, por compatibilidade com bcrypt.
   Não use senhas de pessoas, e-mails pessoais ou contas existentes.
2. Em uma sessão SSH privada, sem gravação de terminal, confira o container e seu
   banco de destino. Não imprima `.env` ou `podman inspect` completo. Confirme com
   o responsável a separação dos bancos; o comando verifica o prefixo de rotas,
   mas não consegue detectar dois containers apontando indevidamente para o mesmo banco.
3. Execute **somente o comando do ambiente em provisionamento**:

| Ambiente | Comando no servidor | `SMOKE_EMAIL` sintético |
|---|---|---|
| Homologação | `podman exec -it hibrido-home-office-laravel php artisan hibrido:create-smoke-user homologacao` | `smoke-homologacao@mixhome.invalid` |
| Produção | `podman exec -it hibrido-home-office-prod php artisan hibrido:create-smoke-user producao` | `smoke-producao@mixhome.invalid` |

4. Confira o destino exibido, confirme a operação e cole a senha do cofre nos
   dois prompts ocultos. Não há opção `--password`, leitura de senha do `.env` ou senha
   gerada/impressa pelo comando. Não use pipe, `--no-interaction`, argumentos com
   secrets, `set -x`, transcrições ou captura de tela. Sem suporte a entrada oculta,
   interrompa e use um terminal compatível; não troque por entrada visível.
5. Aguarde a mensagem `Conta sintética criada`. O cadastro tem nome fixo
   `Smoke Test Sintético - <ambiente>`, perfil `employee`, ativo, equipe vazia e
   nenhum vínculo gerencial, solicitação ou notificação. O domínio `.invalid`
   identifica um endereço sem caixa postal real; não é necessário configurar DNS/SMTP.
   A senha é armazenada como hash; a auditoria `smoke_user.created` contém apenas
   referência à conta, ambiente e perfil, sem senha/hash nos valores auditados.

O comando é exclusivo de criação: uma segunda execução falha sem alterar senha,
perfil ou estado ativo, inclusive se encontrar uma conta desativada ou privilegiada.
Não use `hibrido:create-admin`, SQL manual ou recuperação de senha para contornar
uma colisão. Pare e peça revisão ao Super Admin. A criação e a auditoria são atômicas.

#### Cadastrar os secrets em homologação

1. Abra o repositório no GitHub → **Settings → Environments → homologacao**.
   Se o ambiente não existir, crie-o com esse nome exato e configure as restrições
   operacionais aprovadas. Não selecione **Secrets and variables → Actions** para
   criar secrets globais do repositório.
2. Em **Environment secrets → Add environment secret**, nome `SMOKE_EMAIL`,
   valor `smoke-homologacao@mixhome.invalid`; salve.
3. Adicione outro Environment secret, nome `SMOKE_PASSWORD`, com a senha da
   entrada de **homologação** do cofre, exatamente como informada ao comando; salve.
4. Confira apenas a presença dos dois nomes no environment `homologacao`.
   Evite rodar smoke/deploy entre os dois cadastros: configuração parcial falha.
5. Em **Actions → Smoke test → Run workflow**, selecione a referência aprovada
   que contém o workflow (normalmente `main`) e `environment=homologacao`.
   Aguarde sucesso com `Smoke test passed for https://mixhome.app.br/homologacao`.
   A mensagem com `authenticated checks skipped` **não** valida os secrets.

#### Cadastrar os secrets em produção

Somente após validar homologação e obter autorização operacional para produção:

1. Abra **Settings → Environments → producao** no mesmo repositório, com esse
   nome exato (sem acento). Preserve as proteções existentes; não as desabilite.
2. Em **Environment secrets → Add environment secret**, nome `SMOKE_EMAIL`,
   valor `smoke-producao@mixhome.invalid`; salve.
3. Adicione `SMOKE_PASSWORD` com a senha exclusiva da entrada de **produção** do
   cofre, informada ao comando no container de produção. Não copie a senha de homologação.
4. Confira a presença dos dois nomes em `producao`; execute **Actions → Smoke test
   → Run workflow**, referência aprovada, `environment=producao`.
5. Aguarde `Smoke test passed for https://mixhome.app.br`, sem indicação de checks
   autenticados ignorados. Esse workflow verifica o ambiente sem fazer deploy.

Os nomes sintéticos acima são identificadores públicos, não credenciais reais.
As senhas ficam somente no cofre e no respectivo Environment secret (e como hash
no banco), nunca em arquivos versionados, `.env`, issues, logs ou mensagens.
Os workflows existentes já injetam os dois secrets no smoke test; não é necessário
adicioná-los ao Compose ou aos processos Laravel.

#### Limites e falhas

Use a conta somente para login e leitura do próprio painel. Ela mantém as permissões
normais de `employee`: não é um perfil especial somente leitura. Não crie solicitações,
não associe equipe real e não promova seu perfil. O script não envia solicitações
nem notificações, mas o login grava sessão e token de lembrança normalmente.

Se houver erro de autenticação, confira ambiente, prefixo e par de secrets sem
exibir seus valores; atualize um secret incorreto a partir do cofre. O comando
não implementa rotação ou recuperação de senha. Se a senha original for perdida
ou comprometida, interrompa os smokes autenticados e solicite ao Super Admin um
procedimento controlado de revogação/rotação, incluindo sessões existentes;
não exclua conta, sessões ou auditoria para reprovisionar. Preserve a retenção de dois anos.
Não habilite recuperação de senha como solução. Registre somente ambiente,
resultado e identificador da execução, sem credenciais ou conteúdo de sessão.

Para executar manualmente sem registrar credenciais no histórico do shell,
exporte-as por um mecanismo seguro e rode:

```bash
# Produção
SMOKE_EMAIL="$SMOKE_EMAIL" SMOKE_PASSWORD="$SMOKE_PASSWORD" \
  sh scripts/smoke-environment.sh

# Homologação
SMOKE_ROUTE_PREFIX=/homologacao \
  SMOKE_EMAIL="$SMOKE_EMAIL" SMOKE_PASSWORD="$SMOKE_PASSWORD" \
  sh scripts/smoke-environment.sh
```

Os dois secrets são opcionais, mas precisam ser configurados em conjunto. Sem
eles, o deploy continua protegido pelas verificações públicas de prontidão,
HTTPS, formulário, assets e cookies.

O teste não cria usuários, solicitações ou outros registros de negócio. Uma
falha encerra o job de deploy com uma mensagem que identifica apenas a etapa,
sem imprimir credenciais, cookies ou conteúdo pessoal.

O workflow manual `Smoke test` executa a mesma validação sem publicar arquivos.
Use-o para verificar um ambiente existente ou para separar falhas do acesso SSH
de falhas da aplicação:

```bash
gh workflow run "Smoke test" --ref main -f environment=homologacao
gh workflow run "Smoke test" --ref main -f environment=producao
```

## Backup

### Cópia externa no Backblaze B2

O script aceita `BACKUP_REMOTE`, apontando para um remoto do `rclone` configurado
exclusivamente no servidor. O destino oficial é um bucket privado no Backblaze B2.
Ele envia o dump e o checksum e executa uma conferência do arquivo remoto antes de
concluir. `ONEDRIVE_REMOTE` permanece aceito apenas para compatibilidade:

```bash
rclone config
BACKUP_REMOTE=b2-mixhome:mixhome-backups/producao \
  /opt/hibrido-home-office-prod/scripts/backup-postgres.sh
```

Em produção, execute diariamente às `05:15 UTC`, equivalente a `02:15` em
`America/Sao_Paulo`. Registre a saída em
`/opt/backups/hibrido-home-office/backup.log`. Mantenha somente este agendamento:

```cron
15 5 * * * BACKUP_REMOTE=b2-mixhome:mixhome-backups/producao /opt/hibrido-home-office-prod/scripts/backup-postgres.sh >> /opt/backups/hibrido-home-office/backup.log 2>&1 || { status=$?; podman exec hibrido-home-office-prod php artisan hibrido:notify-backup-failure --exit-code="$status" --exclude-email=gestor@mixfiscal.com.br >> /opt/backups/hibrido-home-office/backup.log 2>&1; }
```

Em caso de falha, o comando envia imediatamente e-mail e notificação interna a
todos os Super Admins ativos, exceto contas técnicas explicitamente excluídas. Uma
falha de destinatário não impede a tentativa de entrega aos demais. O alerta depende do PostgreSQL, da aplicação e do
SMTP; monitore também o arquivo de log por um mecanismo externo quando disponível.

Use uma Application Key restrita ao bucket, nunca a chave principal da conta. Não
coloque `keyID` ou `applicationKey` no `.env` do projeto. Use o arquivo protegido do
`rclone` ou o cofre operacional. O bucket deve ser privado e usar Object Lock com a
retenção aprovada. Monitore a saída do cron e faça restauração trimestral em banco
descartável.

Teste manual do alerta, sem provocar falha no backup:

```bash
podman exec hibrido-home-office-prod \
  php artisan hibrido:notify-backup-failure --exit-code=1
```

Crie o dump dentro do PostgreSQL e copie-o para fora do container:

```bash
stamp=$(date +%Y%m%d-%H%M%S)
podman exec hibrido-home-office-postgres-prod \
  pg_dump -U hibrido -d hibrido -Fc -f /tmp/hibrido.dump
podman cp hibrido-home-office-postgres-prod:/tmp/hibrido.dump \
  "/opt/hibrido-home-office-backups/hibrido-${stamp}.dump"
chmod 600 "/opt/hibrido-home-office-backups/hibrido-${stamp}.dump"
sha256sum "/opt/hibrido-home-office-backups/hibrido-${stamp}.dump"
```

Política recomendada:

- diário: 7 cópias;
- semanal: 4 cópias;
- mensal: 12 cópias;
- pelo menos uma cópia fora do servidor;
- teste trimestral de restauração.

A cópia externa definida para este projeto é o Backblaze B2. A ferramenta de sincronização e suas credenciais devem ser configuradas somente no servidor ou em um cofre de secrets, nunca no repositório. O backup só deve ser considerado concluído após validar checksum no destino externo.

Somente o Super Admin pode autorizar deploy em produção, migrations de schema e restauração de dados. Configure proteção equivalente nos ambientes do GitHub e nos acessos ao servidor.

### Controle de deploy no servidor

Como o plano atual do GitHub não oferece revisores obrigatórios para este
repositório privado, a publicação usa uma chave SSH **exclusiva da automação**.
Sua entrada em `authorized_keys` deve conter somente a chave pública nova, com
este comando forçado (a chave pessoal do administrador fica em outra entrada):

```text
restrict,command="sudo -n --preserve-env=SSH_ORIGINAL_COMMAND /usr/local/sbin/mixhome-ci-gate" ssh-ed25519 CHAVE_PUBLICA_DA_AUTOMACAO
```

Instale `scripts/mixhome-ci-gate.sh` e `scripts/mixhome-approve.sh` como
`root:root`, modo `755`, em `/usr/local/sbin/mixhome-ci-gate` e
`/usr/local/sbin/mixhome-approve`. O diretório
`/var/lib/mixhome-ci/approved` deve ser `root:root`, modo `700`.
O secret `DEPLOY_SSH_KEY` dos **dois** ambientes usa o arquivo privado da chave
exclusiva da automação desde 2026-09-21. Nunca versione essa chave.

O comando forçado aceita apenas `backup-status`, `deploy-homologacao` e
`deploy-producao`. O workflow envia o pacote pelo canal SSH; o servidor imprime
o SHA-256 e aguarda por até 15 minutos. Depois de conferir o ambiente, commit e
digest nos logs, o Super Admin aprova em uma sessão SSH pessoal:

```bash
sudo /usr/local/sbin/mixhome-approve producao SHA256_EXIBIDO_NO_JOB
```

Para homologação, troque `producao` por `homologacao`. A aprovação vale para um
único pacote, expira após 15 minutos e é consumida antes da extração/deploy.
Sem aprovação, o pacote não é publicado. O monitor de backup usa somente
`backup-status`, sem permissão para executar shell arbitrário.

#### Aprovação web (ativação opcional)

A área **Gestão → Operação → Aprovação de deploy** permite que um Super Admin
ativo aprove o pacote pendente sem abrir SSH a cada publicação. O formulário exige
novamente a senha, tem proteção CSRF e limite de tentativas. O servidor continua
sendo a autoridade: confere o SHA-256 do pacote, a assinatura HMAC por ambiente,
o prazo de até cinco minutos e consome a autorização uma única vez. A aprovação
SSH existente permanece como contingência. Não habilite a interface antes de
instalar e testar a verificação no servidor.

Na preparação de **cada** ambiente, crie os diretórios
`/var/lib/mixhome-ci/pending` (root, modo 755) e
`/var/lib/mixhome-ci/web-approved/homologacao` e
`/var/lib/mixhome-ci/web-approved/producao` (cada um com UID/GID do Apache no
container, modo 700; o diretório pai permanece restrito ao root).
Instale `scripts/mixhome-verify-web-approval.py` como root, modo 755, em
`/usr/local/sbin/mixhome-verify-web-approval`, e atualize
`/usr/local/sbin/mixhome-ci-gate` a partir do script versionado. O host precisa
ter Python 3. Guarde uma chave aleatória **diferente por ambiente**, com no mínimo
32 caracteres, em `/etc/mixhome-ci/homologacao.approval-key` e
`/etc/mixhome-ci/producao.approval-key` (root, modo 600). Não registre as chaves
em logs, no GitHub ou neste documento.

No `.env` privado de cada ambiente, configure `DEPLOY_APPROVAL_ENV` com
`homologacao` ou `producao`, `DEPLOY_APPROVAL_KEY` com a respectiva chave e
`DEPLOY_PENDING_HOST_DIR=/var/lib/mixhome-ci/pending` e
`DEPLOY_APPROVED_HOST_DIR=/var/lib/mixhome-ci/web-approved/<ambiente>` (substitua
`<ambiente>` pelo nome correspondente). O Compose monta o
diretório de pendências como somente leitura e o de autorizações como escrita
somente no serviço web; worker e scheduler não recebem esses mounts ou a chave.
Confira UID/GID efetivos no Podman antes de ajustar a propriedade do diretório.
O arquivo `.env` precisa permanecer com permissão restrita.

Ative primeiro em homologação, execute um deploy controlado e confira: usuário
sem perfil Super Admin recebe 403; senha incorreta, digest ausente/expirado e
assinatura inválida não publicam; aprovação correta publica apenas o pacote
exibido e os healthcheck/smoke passam. Só depois, mediante autorização do Super
Admin, repita em produção. A primeira publicação que instala a interface ainda
usa a aprovação SSH anterior; publicações seguintes podem usar a interface.

Essa separação não altera a chave pessoal, mas ela já esteve em um secret do
GitHub: substituí-la no secret impede uso futuro pela automação, **não** revoga
eventuais cópias históricas. A garantia de exclusividade total depende de uma
rotação posterior da chave pessoal e da revisão de outras chaves administrativas.
O gate protege a chave da automação e o workflow oficial; pessoas com shell e
`sudo` no servidor ainda podem executar migrations, restaurações ou deploys
diretamente. Revise esses acessos separadamente e mantenha a exigência de
autorização operacional do Super Admin.
Após qualquer rotação futura, confirme o fingerprint usado pelo GitHub nos logs
SSH do servidor, o monitor B2 e a rejeição de um comando fora da lista. A nova
chave e o monitor foram validados em 2026-09-21; o primeiro deploy controlado
em homologação ainda deve ser acompanhado pelo Super Admin.

Logs de auditoria, sessões, notificações, solicitações recusadas e contas desativadas têm retenção definida de dois anos. Até existir rotina segura de expurgo/anonimização, não faça exclusões manuais dessas categorias.

### Retenção de dois anos

O comando é somente simulação por padrão:

```bash
podman exec hibrido-home-office-prod php artisan hibrido:apply-retention
```

Após backup, conferência da simulação e autorização do Super Admin, defina
`DATA_RETENTION_ENABLED=true`, recrie web/worker/scheduler e execute com
`--execute`. A rotina remove auditorias, notificações, sessões e recusas elegíveis,
e anonimiza contas inativas. Com a flag habilitada, roda mensalmente no dia 5.

### Retorno automático após reboot

Para Podman rootless, além de `restart: unless-stopped`, habilite o serviço do usuário:

```bash
sudo loginctl enable-linger admin
PODMAN_USER=admin /opt/hibrido-home-office-prod/scripts/configure-podman-autostart.sh
```

Valide em janela de manutenção e confirme aplicação, PostgreSQL, worker e scheduler.

## Restauração

Faça a restauração em janela de manutenção e confirme o arquivo antes:

```bash
sha256sum -c SHA256SUMS
podman cp hibrido.dump hibrido-home-office-postgres-prod:/tmp/hibrido.dump
podman exec hibrido-home-office-postgres-prod \
  pg_restore -U hibrido -d hibrido --clean --if-exists /tmp/hibrido.dump
podman restart hibrido-home-office-prod
```

Depois confira usuários, equipes, vínculos, solicitações e uma exportação Excel.

## Diagnóstico

```bash
podman ps -a --filter name=hibrido-home-office
podman logs --tail 100 hibrido-home-office-prod
podman logs --tail 100 hibrido-home-office-postgres-prod
podman inspect --format '{{.State.Health.Status}}' hibrido-home-office-postgres-prod
curl -I http://127.0.0.1:8082/login
sudo caddy validate --config /etc/caddy/Caddyfile
```

## Retorno de versão

Uma reversão de código deve usar a imagem anterior e preservar o PostgreSQL. Uma reversão de dados só deve ocorrer com autorização explícita, pois elimina alterações posteriores ao dump restaurado.

## Continuidade, filas e monitoramento

O `compose.yaml` mantém quatro processos: PostgreSQL, aplicação web, worker de filas e agendador. E-mails e notificações são processados pelo worker. O agendador dispara às 08:00 lembretes de pendências quando faltarem até três dias para fechar o ciclo.

Use `GET /health/ready` para confirmar aplicação e PostgreSQL e consultar jobs pendentes ou com falha. O script `scripts/monitor-production.sh` pode ser chamado a cada cinco minutos. Configure alertas para indisponibilidade, reinícios, `failed_jobs`, disco acima de 80% e ausência de backup nas últimas 26 horas.

O script `scripts/backup-postgres.sh` cria dump em formato custom, SHA-256 e retenção configurável. O agendamento oficial é o descrito na seção **Cópia externa no Backblaze B2**, às 05:15 UTC, com um único cron e alerta de falha. Não crie um segundo agendamento local. O workflow `Backup externo` consulta diariamente às 08:00 UTC o B2 por SSH e falha se não houver dump recente acompanhado de arquivo de checksum. Ele é independente da aplicação, banco e SMTP, mas depende do GitHub Actions, SSH, servidor e B2; a notificação de falha deve estar habilitada no GitHub.

Mantenha a cópia externa no bucket privado e repita trimestralmente o teste de `pg_restore` em banco descartável. O deploy manual do GitHub exige `DEPLOY_SSH_KEY`, `DEPLOY_HOST`, `DEPLOY_USER` e `TARGET_DIR`, sempre promovendo homologação antes de produção.
