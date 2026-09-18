# MixHome — Controle de Home Office

Sistema da Mix Fiscal para solicitação, aprovação, acompanhamento e exportação de dias de home office.

Inclui ciclos 20–19, múltiplas equipes, gestores que também atuam como colaboradores, aprovação individual ou em lote, férias, calendário corporativo, notificações assíncronas, auditoria, recuperação de senha e monitoramento de prontidão.

## Estado atual

A aplicação oficial está em [`laravel-app`](laravel-app) e utiliza:

- PHP 8.3, Laravel 12 e Apache;
- PostgreSQL 16 em container separado;
- Podman e `podman-compose`;
- fuso horário `America/Sao_Paulo`;
- temas claro e escuro com preferência salva no navegador.

A antiga implementação PHP/SQLite foi retirada da árvore ativa após a migração para produção. Ela continua recuperável pelo histórico do Git, e os backups de migração permanecem armazenados no servidor.

## Funcionalidades

- Login unificado para colaboradores, gestores e Super Admin;
- primeiro acesso controlado por conta criada pelo Super Admin e link enviado ao e-mail corporativo;
- gestores vinculados a múltiplas equipes;
- gestores também podem usar o sistema como colaboradores;
- solicitações imutáveis com estados pendente, aprovada e recusada;
- recusas arquivadas, sem exclusão do histórico;
- ciclo padrão de apuração do dia 20 ao dia 19;
- filtros por ciclo, equipe, status e múltiplos colaboradores;
- exportações Excel e CSV com neutralização de fórmulas em campos textuais;
- gerenciamento de equipes, usuários, perfis e acessos;
- planejamento de férias por período aquisitivo, saldo e aprovação;
- PostgreSQL restrito ao loopback e backups externos no Backblaze B2.

## Estrutura

```text
.
├── docs/
│   ├── ARQUITETURA.md
│   ├── MANUAL_USUARIO.md
│   ├── MIGRACAO.md
│   └── OPERACAO.md
└── laravel-app/
    ├── app/                 Regras, controllers, models e comandos
    ├── database/            Migrations, factories e seeders
    ├── public/assets/       CSS, JavaScript e identidade visual
    ├── resources/views/     Telas Blade
    ├── routes/              Rotas web e console
    ├── tests/               Testes automatizados do fluxo do sistema
    ├── compose.yaml         Aplicação e PostgreSQL
    └── Dockerfile           Imagem PHP/Apache
```

## Execução local

Pré-requisitos: Podman 4+, `podman-compose`, Git e portas locais disponíveis.

```bash
cd laravel-app
cp .env.example .env
```

Edite `.env` e configure obrigatoriamente:

```dotenv
APP_KEY=base64:CHAVE_GERADA_COM_32_BYTES
APP_URL=http://127.0.0.1:8081
ASSET_URL=http://127.0.0.1:8081
DB_PASSWORD=SENHA_FORTE_E_EXCLUSIVA
```

Inicie o ambiente:

```bash
podman-compose up -d --build
```

Crie o primeiro Super Admin pelo modo interativo, para não registrar a senha no histórico do terminal:

```bash
podman exec -it hibrido-home-office-laravel php artisan hibrido:create-admin
```

Acesse `http://127.0.0.1:8081`.

## Testes

Com as dependências instaladas:

```bash
cd laravel-app
php artisan test
```

## Documentação

- [Manual de usuário](docs/MANUAL_USUARIO.md)
- [Operação, publicação e backup](docs/OPERACAO.md)
- [Migração para outro servidor](docs/MIGRACAO.md)
- [Arquitetura e regras do sistema](docs/ARQUITETURA.md)

## Segurança

- Nunca versione `.env`, dumps, arquivos SQLite ou credenciais.
- Não publique a porta 5432 do PostgreSQL.
- Mantenha HTTPS, cookies seguros e SMTP configurados nos ambientes publicados.
- Mantenha os dumps no bucket privado do Backblaze B2 e teste trimestralmente a restauração.
- Novas contas devem ser criadas pelo Super Admin; o cadastro público permanece desabilitado.
- Desative imediatamente contas de pessoas que perderem o acesso autorizado.
