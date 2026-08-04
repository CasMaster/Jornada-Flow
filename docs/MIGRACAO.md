# Migração para outro servidor

Este procedimento considera Podman, `podman-compose`, Git, Caddy e PostgreSQL 16.

## 1. Preparar o pacote

No servidor atual:

```bash
mkdir -p ~/hibrido-migracao
podman exec hibrido-home-office-postgres-prod \
  pg_dump -U hibrido -d hibrido -Fc -f /tmp/hibrido.dump
podman cp hibrido-home-office-postgres-prod:/tmp/hibrido.dump \
  ~/hibrido-migracao/hibrido.dump
sha256sum ~/hibrido-migracao/hibrido.dump > ~/hibrido-migracao/SHA256SUMS
```

Copie também o `.env` por um canal seguro, separadamente do repositório e do dump. É preferível gerar novas credenciais no destino.

## 2. Preparar o destino

```bash
sudo mkdir -p /opt/hibrido-home-office
sudo chown "$USER":"$USER" /opt/hibrido-home-office
git clone https://github.com/CasMaster/hibrido-home-office.git /tmp/hibrido-repo
cp -a /tmp/hibrido-repo/laravel-app/. /opt/hibrido-home-office/
cd /opt/hibrido-home-office
cp .env.example .env
chmod 600 .env
```

Configure `APP_KEY`, URLs, nomes dos containers, porta e credenciais PostgreSQL.

## 3. Subir os containers

```bash
podman-compose up -d --build
podman ps --filter name=hibrido-home-office
```

Aguarde o PostgreSQL ficar saudável e a aplicação concluir as migrations.

## 4. Restaurar os dados

```bash
sha256sum -c ~/hibrido-migracao/SHA256SUMS
podman cp ~/hibrido-migracao/hibrido.dump \
  hibrido-home-office-postgres-prod:/tmp/hibrido.dump
podman exec hibrido-home-office-postgres-prod \
  pg_restore -U hibrido -d hibrido --clean --if-exists /tmp/hibrido.dump
podman restart hibrido-home-office-prod
```

Adapte os nomes dos containers quando forem diferentes dos exemplos.

## 5. Configurar o proxy

Exemplo sem DNS:

```caddyfile
http://IP_DO_SERVIDOR {
    reverse_proxy 127.0.0.1:8082
}
```

Valide e recarregue:

```bash
sudo caddy validate --config /etc/caddy/Caddyfile
sudo systemctl reload caddy
```

## 6. Conferência

1. Acesse a página de login.
2. Entre como Super Admin.
3. Compare as quantidades de usuários, equipes e solicitações.
4. Verifique os vínculos de gestores.
5. Entre como colaborador e confira o histórico.
6. Teste aprovação e recusa com uma solicitação controlada.
7. Exporte um ciclo e abra o arquivo Excel.
8. Gere um novo dump no servidor de destino.

## Migração excepcional de SQLite

Os comandos abaixo existem apenas para bancos históricos:

```bash
php artisan hibrido:import-legacy /caminho/sistema-php-antigo.sqlite
php artisan hibrido:import-sqlite --path=/caminho/laravel-antigo.sqlite
```

Use uma cópia do arquivo, execute primeiro em homologação e compare as contagens. Os importadores não substituem o processo normal de backup PostgreSQL.
