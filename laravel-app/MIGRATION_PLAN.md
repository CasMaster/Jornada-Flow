# Migração para Laravel

Esta pasta é a implementação paralela do Híbrido em Laravel. O sistema PHP atual permanece na raiz do repositório e continua sendo a versão de produção durante a migração.

## Compatibilidade obrigatória

- SQLite e fuso `America/Sao_Paulo`.
- Login único para colaboradores, gestores e Super Admin.
- Gestores vinculados a várias equipes.
- Solicitações com estados pendente, aprovada e recusada, sem exclusão do histórico.
- Ciclos do dia 20 ao dia 19, inclusive nos filtros do gestor.
- Exportação Excel no formato adotado pelo projeto atual.
- Identidade visual Mix Fiscal e temas claro/escuro.
- Execução em Podman e migração segura dos dados existentes.

## Implementado nesta branch

- Migrations e models para usuários, equipes, vínculos e solicitações.
- Login unificado, primeiro acesso e autorização por perfil.
- Escopo de gestores limitado às equipes vinculadas.
- Painéis de colaborador, gestor e Super Admin com identidade Mix Fiscal.
- Ciclo de apuração do dia 20 ao dia 19 e filtros por equipe, status e colaboradores.
- Aprovação e recusa sem exclusão do histórico.
- Exportação `.xlsx` em matriz de colaboradores e datas.
- Importador idempotente compatível com bancos antigos e atuais.
- Container PHP 8.3/Apache para Podman e suíte automatizada.

## Homologação local

1. Copie `.env.example` para `.env` e defina uma `APP_KEY` válida.
2. Execute `podman-compose up -d --build` nesta pasta.
3. Acesse `http://127.0.0.1:8081`.
4. Em uma instalação vazia, crie o acesso inicial com `php artisan hibrido:create-admin` dentro do container.
5. Para importar uma cópia do banco anterior, monte o arquivo no container e execute `php artisan hibrido:import-legacy /caminho/copia.sqlite`.

Nenhuma troca de produção deve ocorrer antes da conferência dos dados importados e da aprovação funcional.
