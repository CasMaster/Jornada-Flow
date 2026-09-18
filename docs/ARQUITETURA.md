# Arquitetura e regras do MixHome

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

- `users`: identidade, senha, perfil, equipe própria e status, com índices para o diretório;
- `teams`: equipes e disponibilidade operacional;
- `manager_team`: vínculo de gestores a múltiplas equipes;
- `work_requests`: data, estado, solicitante e análise;
- `vacation_entitlements`: concessões e ajustes auditáveis por período aquisitivo;
- `vacation_requests`: intervalo, período aquisitivo, estado, análise, correção e cancelamento de férias;
- `password_reset_tokens`: tokens de uso único e expiração de 60 minutos;
- `sessions`, `cache` e tabelas de filas: infraestrutura do Laravel.

## Decisões técnicas

- PostgreSQL foi escolhido para suportar gravações simultâneas, integridade e crescimento.
- CSS e JavaScript do produto são servidos diretamente de `public/assets`; não há etapa Node/Vite.
- As URLs dos assets recebem versão baseada no arquivo para evitar cache antigo após atualizações.
- A autorização é conferida no servidor; esconder botões na interface não substitui a validação de perfil.
- O diretório de usuários usa busca no banco e paginação de 20 registros, evitando carregar todas as contas em memória.
- SMTP e recuperação de senha estão habilitados nos ambientes publicados; o primeiro acesso usa uma conta criada pelo Super Admin e um token de uso único.
- O cadastro público permanece desabilitado, e o login é limitado pela combinação de e-mail normalizado e IP.
- Exportações CSV neutralizam prefixos interpretáveis como fórmulas; o XLSX grava nomes como texto explícito.
- Os importadores SQLite permanecem apenas para recuperação e migração de instalações antigas.

## Serviços de domínio e processamento assíncrono

`WorkRequestService` concentra criação, bloqueios do calendário e análise; `VacationRequestService` mantém conflitos, reserva/consumo de saldo, decisões, correções e cancelamentos das férias; `AuditService` registra rastreabilidade. As Policies limitam cada gestor às equipes permitidas. Controllers coordenam HTTP e não devem duplicar essas regras.

O fluxo é: colaborador envia datas → serviço valida calendário e imutabilidade → solicitação e auditoria são gravadas → gestor analisa individualmente ou em lote → nova auditoria é gravada → notificação é enfileirada → worker persiste a notificação e envia e-mail.

PostgreSQL armazena negócio, sessões, cache, filas, notificações e auditoria. Web, worker e scheduler compartilham a imagem, mas possuem ciclos de vida independentes. O painel pagina 25 solicitações e aplica pesquisa e filtros diretamente no banco.
