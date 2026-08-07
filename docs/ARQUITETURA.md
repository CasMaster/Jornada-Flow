# Arquitetura e regras do HÍBRIDO

## Componentes

```text
Navegador
   │ HTTP/HTTPS
   ▼
Proxy reverso (Caddy)
   │ rede local do servidor
   ▼
Laravel + Apache
   │ rede interna do Podman
   ▼
PostgreSQL 16
```

O banco não publica a porta 5432. A persistência fica em volume próprio do Podman. A aplicação executa migrations automaticamente na inicialização e aguarda o banco ficar disponível.

O Laravel também não publica portas em interfaces externas: produção e
homologação são vinculadas ao loopback do host. O Caddy é a única camada HTTP
pública e atua como proxy reverso para essas portas locais.

## Perfis e permissões

| Perfil | Painel pessoal | Painel de gestão | Administração |
|---|---:|---:|---:|
| Colaborador | Sim | Não | Não |
| Gestor | Sim | Equipes vinculadas | Não |
| Super Admin | Sim | Todas as equipes | Sim |

Um gestor precisa ter uma **equipe própria** para que suas solicitações pessoais apareçam corretamente nos filtros. As **equipes administradas** são vínculos separados e podem ser múltiplas.

## Solicitações

- Cada usuário pode ter no máximo uma solicitação por data.
- Toda nova solicitação começa como `pending`.
- A decisão altera o estado para `approved` ou `rejected`.
- Solicitações enviadas não podem ser alteradas pelo solicitante.
- Recusas permanecem no banco para auditoria.
- A análise registra gestor e horário.

## Ciclo de apuração

O ciclo começa no dia 20 e termina no dia 19 do mês seguinte. O filtro do gestor e a exportação utilizam os mesmos limites inclusivos.

## Dados principais

- `users`: identidade, senha, perfil, equipe própria e status;
- `teams`: equipes e disponibilidade para novos cadastros;
- `manager_team`: vínculo de gestores a múltiplas equipes;
- `work_requests`: data, estado, solicitante e análise;
- `sessions`, `cache` e tabelas de filas: infraestrutura do Laravel.

## Decisões técnicas

- PostgreSQL foi escolhido para suportar gravações simultâneas, integridade e crescimento.
- CSS e JavaScript do produto são servidos diretamente de `public/assets`; não há etapa Node/Vite.
- As URLs dos assets recebem versão baseada no arquivo para evitar cache antigo após atualizações.
- A autorização é conferida no servidor; esconder botões na interface não substitui a validação de perfil.
- Os importadores SQLite permanecem apenas para recuperação e migração de instalações antigas.
