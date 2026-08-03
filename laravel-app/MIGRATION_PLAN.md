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

## Estratégia

1. Modelar usuáios, equipes, vínculos de gestores e solicitações com migrations e models.
2. Implementar autenticação, autorização por policies e fluxo de primeiro acesso.
3. Recriar os painéis e a exportação preservando a experiência visual.
4. Criar importador idempotente para uma cópia do SQLite atual.
5. Validar com testes automatizados e subir um container de homologação separado.

Nenhuma troca de produção deve ocorrer antes da conferência dos dados importados e da aprovação funcional.
