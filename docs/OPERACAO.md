# Operação do HÍBRIDO

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
MAIL_FROM_NAME="Híbrido | Mix Fiscal"
```

O `.env` deve ter permissão `600` e nunca pode entrar no Git.

O container Laravel deve publicar sua porta apenas em `127.0.0.1`. O Caddy é a
única entrada HTTP pública e encaminha as requisições para a porta local do
ambiente. Não use `0.0.0.0` em `APP_BIND_IP` em produção.

## Recuperação de senha e SMTP

Mantenha `PASSWORD_RECOVERY_ENABLED=false` enquanto o endereço público não usar
HTTPS ou enquanto o SMTP não estiver validado. Depois de configurar domínio,
certificado e credenciais de e-mail:

1. ajuste `APP_URL` e `ASSET_URL` para a URL `https://`;
2. configure as variáveis `MAIL_*` no `.env` de produção;
3. envie um e-mail de teste;
4. altere `PASSWORD_RECOVERY_ENABLED=true`;
5. recrie apenas o container Laravel;
6. teste solicitação, recebimento, expiração e redefinição.

Tokens expiram em 60 minutos, são substituídos quando um novo link é solicitado
e nunca devem aparecer em logs, chamados ou capturas de tela.

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
