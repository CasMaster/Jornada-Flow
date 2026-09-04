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
APP_URL=http://IP_OU_DOMINIO
ASSET_URL=http://IP_OU_DOMINIO
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
PASSWORD_RECOVERY_ENABLED=false
MAIL_MAILER=smtp
MAIL_HOST=SERVIDOR_SMTP
MAIL_PORT=587
MAIL_USERNAME=USUARIO_SMTP
MAIL_PASSWORD=SENHA_SMTP
MAIL_FROM_ADDRESS=hibrido@DOMINIO
MAIL_FROM_NAME="MixHome | Mix Fiscal"
```

O `.env` deve ter permissão `600` e nunca pode entrar no Git.

O container Laravel deve publicar sua porta apenas em `127.0.0.1`. O Caddy é a
única entrada HTTP pública e encaminha as requisições para a porta local do
ambiente. Não use `0.0.0.0` em `APP_BIND_IP` em produção.

## Recuperação de senha e SMTP

Mantenha `PASSWORD_RECOVERY_ENABLED=false` em homologação e produção.
SMTP e recuperação de senha estão deliberadamente adiados: HTTPS, conta de
smoke test ou secrets cadastrados não autorizam habilitá-los. O provisionamento
abaixo não envia e-mail, convite ou token de recuperação e não altera configuração.

## Desempenho e monitoramento

Toda resposta web inclui o cabeçalho `Server-Timing` com o tempo interno do Laravel. Requisições acima de `SLOW_REQUEST_MS` (750 ms por padrão) e consultas acima de `SLOW_QUERY_MS` (250 ms) são registradas como `slow_request` e `slow_query`. Consultas são gravadas sem os valores dos parâmetros, evitando dados pessoais nos logs.

Consulte os eventos com `podman logs hibrido-home-office-prod` e os processos worker/scheduler correspondentes. O workflow `Smoke test` valida produção a cada 15 minutos; falhas aparecem no GitHub Actions. `scripts/monitor-production.sh` também mede o endpoint de prontidão e falha quando ultrapassa `MAX_RESPONSE_SECONDS` (2 segundos por padrão).

## Deploy seguro e rollback

`deploy.sh` cria um dump PostgreSQL com checksum e permissão restrita antes de reconstruir os serviços. Depois da recriação, executa repetidamente o healthcheck da imagem. Se a aplicação não ficar saudável, volta a apontar os serviços para a imagem anterior e encerra com erro. O smoke HTTPS externo ainda é obrigatório depois desse processo.

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

A cópia externa definida para este projeto é o OneDrive. A ferramenta de sincronização e suas credenciais devem ser configuradas somente no servidor ou em um cofre de secrets, nunca no repositório. O backup só deve ser considerado concluído após validar checksum no destino externo.

Somente o Super Admin pode autorizar deploy em produção, migrations de schema e restauração de dados. Configure proteção equivalente nos ambientes do GitHub e nos acessos ao servidor.

Logs de auditoria, sessões, notificações, solicitações recusadas e contas desativadas têm retenção definida de dois anos. Até existir rotina segura de expurgo/anonimização, não faça exclusões manuais dessas categorias.

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

O script `scripts/backup-postgres.sh` cria dump em formato custom, SHA-256 e retenção configurável. Exemplo de cron diário:

```cron
15 2 * * * BACKUP_DIR=/opt/backups/hibrido-home-office RETENTION_DAYS=30 /opt/hibrido-home-office/scripts/backup-postgres.sh >> /var/log/hibrido-backup.log 2>&1
```

Mantenha uma cópia fora do servidor e teste mensalmente `pg_restore` em um banco descartável. O deploy manual do GitHub exige `DEPLOY_SSH_KEY`, `DEPLOY_HOST`, `DEPLOY_USER` e `TARGET_DIR`, sempre promovendo homologação antes de produção.
