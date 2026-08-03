# Migração para outro servidor

## Ambiente Laravel com PostgreSQL

A homologação Laravel utiliza dois containers: aplicação e PostgreSQL 16. A porta do banco não é publicada; somente a aplicação consegue acessá-lo pela rede interna do Podman. Os dados ficam no volume `hibrido_postgres_data`.

Antes da primeira inicialização, configure no `.env`:

```dotenv
DB_CONNECTION=pgsql
DB_HOST=postgres
DB_PORT=5432
DB_DATABASE=hibrido
DB_USERNAME=hibrido
DB_PASSWORD=UMA_SENHA_FORTE_E_EXCLUSIVA
```

O SQLite anterior permanece no volume `hibrido_laravel_data`, montado como somente leitura. Após subir os containers, importe-o uma vez:

```bash
podman-compose up -d --build
podman exec hibrido-home-office-laravel php artisan hibrido:import-sqlite
```

Confira as contagens apresentadas pelo comando e valide usuários, equipes, vínculos e solicitações no painel. A importação é cancelada automaticamente quando o PostgreSQL já contém dados.

### Backup do PostgreSQL

```bash
mkdir -p ~/hibrido-migracao
podman exec hibrido-home-office-postgres pg_dump -U hibrido -d hibrido -Fc -f /tmp/hibrido.dump
podman cp hibrido-home-office-postgres:/tmp/hibrido.dump ~/hibrido-migracao/hibrido.dump
sha256sum ~/hibrido-migracao/hibrido.dump > ~/hibrido-migracao/SHA256SUMS
sha256sum -c ~/hibrido-migracao/SHA256SUMS
```

O arquivo `.env` não deve ser incluído no backup nem versionado. Guarde a senha separadamente em um gerenciador de credenciais.

### Restauração em outro servidor

Suba os containers com um PostgreSQL novo, copie o dump e restaure antes de liberar acessos:

```bash
podman cp ~/hibrido-migracao/hibrido.dump hibrido-home-office-postgres:/tmp/hibrido.dump
podman exec hibrido-home-office-postgres pg_restore -U hibrido -d hibrido --clean --if-exists /tmp/hibrido.dump
podman restart hibrido-home-office-laravel
```

As instruções abaixo continuam válidas para a versão PHP/SQLite anterior.

Este guia considera Podman 4+, `podman-compose`, Git e um servidor Linux. O código está no repositório privado `CasMaster/hibrido-home-office`.

## 1. Gerar o backup no servidor atual

Crie uma cópia consistente do SQLite por dentro do container:

```bash
mkdir -p ~/hibrido-migracao
podman exec hibrido-home-office php /var/www/html/scripts/backup-data.php /tmp/home-office.sqlite
podman cp hibrido-home-office:/tmp/home-office.sqlite ~/hibrido-migracao/home-office.sqlite
sha256sum ~/hibrido-migracao/home-office.sqlite > ~/hibrido-migracao/SHA256SUMS
```

Valide o backup:

```bash
sha256sum -c ~/hibrido-migracao/SHA256SUMS
podman cp ~/hibrido-migracao/home-office.sqlite hibrido-home-office:/tmp/backup-verificacao.sqlite
podman exec hibrido-home-office php /var/www/html/scripts/verify-data.php /tmp/backup-verificacao.sqlite
```

O arquivo preserva usuários, gestores, equipes, solicitações, decisões e históricos. O `.env` não deve ser incluído no pacote; configure novas variáveis no destino.

## 2. Preparar o novo servidor

Instale Podman, `podman-compose` e Git. Clone o repositório privado:

```bash
sudo mkdir -p /opt/hibrido-home-office
sudo chown "$USER":"$USER" /opt/hibrido-home-office
git clone https://github.com/CasMaster/hibrido-home-office.git /opt/hibrido-home-office
cd /opt/hibrido-home-office
cp .env.example .env
chmod 600 .env
```

Edite `.env` e defina um novo `HIBRIDO_MANAGER_PIN`. Em bancos restaurados, esse valor não altera as senhas existentes; ele serve apenas para criar o primeiro gestor de um banco vazio.

## 3. Criar o container e restaurar

Crie o container e o volume, pare a aplicação e copie o banco:

```bash
cd /opt/hibrido-home-office
podman-compose up -d --build
podman stop hibrido-home-office
podman cp ~/hibrido-migracao/home-office.sqlite hibrido-home-office:/var/www/html/data/home-office.sqlite
podman start hibrido-home-office
```

O Apache ajustará o acesso ao volume. Se o container não conseguir escrever, execute:

```bash
podman exec --user root hibrido-home-office chown www-data:www-data /var/www/html/data/home-office.sqlite
podman exec --user root hibrido-home-office chmod 640 /var/www/html/data/home-office.sqlite
```

## 4. Publicar sem DNS

O serviço responde em `http://IP_DO_SERVIDOR:8080`. Se a porta 8080 não estiver liberada, use o Caddy já instalado no host:

```caddyfile
http://IP_DO_SERVIDOR {
    reverse_proxy 127.0.0.1:8080
}
```

Valide e recarregue:

```bash
sudo caddy validate --config /etc/caddy/Caddyfile
sudo systemctl reload caddy
```

## 5. Conferência após a migração

1. Abra a página de login.
2. Entre com uma conta de gestor já existente.
3. Confira usuários, equipes e a quantidade de solicitações do ciclo.
4. Exporte uma planilha Excel e confirme as datas aprovadas.
5. Envie uma solicitação de teste, aprove ou recuse e confira o histórico do colaborador.

## 6. Retorno ao servidor anterior

Não desligue o servidor anterior antes da conferência. Caso seja necessário voltar, direcione o acesso novamente ao endereço antigo; o backup e a restauração não modificam o banco de origem.
