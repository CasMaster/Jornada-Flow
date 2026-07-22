# HÍBRIDO — versão PHP

Sistema autocontido em PHP 8.1+ com banco SQLite. Não requer Node.js, processo de build ou serviço de banco separado.

## Publicação

1. Envie todo o conteúdo desta pasta para a pasta pública do host (`public_html`, `www` ou equivalente).
2. Confirme que as extensões PHP `pdo` e `pdo_sqlite` estão habilitadas.
3. Garanta permissão de escrita do PHP na pasta `data`.
4. Configure a variável de ambiente `HIBRIDO_MANAGER_PIN` com um PIN seguro.
5. Opcionalmente, configure `HIBRIDO_TIMEZONE` (o padrão é `America/Sao_Paulo`).
6. Acesse o domínio. O banco será criado automaticamente no primeiro uso.

O PIN padrão `1234` serve apenas para teste local e deve ser substituído em produção.

## Execução com contêiner

1. Copie `.env.example` para `.env` e defina um PIN seguro.
2. Execute `docker compose up -d --build`.
3. Acesse `http://IP_DO_SERVIDOR:8080`.

O banco SQLite fica no volume persistente `hibrido_data`. Nenhum DNS é necessário; o acesso inicial é feito pelo IP do servidor e porta 8080.

## Requisitos do host

- PHP 8.1 ou superior
- PDO SQLite
- HTTPS
- Apache com `.htaccess` ou regra equivalente para impedir acesso à pasta `data`
