# HÍBRIDO — Controle de Home Office

Aplicação em PHP 8.3 com SQLite para solicitação, aprovação e consolidação de dias de home office.

## Funcionalidades

- Login unificado para colaboradores e gestores.
- Primeiro acesso de colaboradores com equipes predefinidas.
- Super Admin com visão global e administração de acessos.
- Múltiplos gestores com contas individuais e vínculo a várias equipes.
- Cadastro de equipes e gerenciamento de usuários.
- Solicitações com estados pendente, aprovada e recusada.
- Histórico imutável para o colaborador; recusas permanecem arquivadas.
- Ciclos de apuração entre o dia 20 e o dia 19 do mês seguinte.
- Exportação Excel em formato matricial, com colaboradores nas linhas e datas nas colunas.
- Filtro de múltiplos colaboradores limitado às equipes permitidas para o gestor.
- Fuso horário configurável, com padrão `America/Sao_Paulo`.

## Arquitetura

- PHP 8.3 e Apache em um único container.
- Banco SQLite persistido no volume `hibrido_data`.
- Dependências PHP instaladas pelo Composer durante o build.
- Porta interna publicada em `8080`; em produção, recomenda-se Caddy ou outro proxy reverso na porta 80/443.

## Execução local com Podman

1. Copie `.env.example` para `.env`.
2. Defina um valor seguro em `HIBRIDO_MANAGER_PIN`.
3. Execute:

   ```bash
   podman-compose up -d --build
   ```

4. Acesse `http://127.0.0.1:8080`.

Também é possível usar `docker compose up -d --build` em ambientes Docker.

## Primeiro gestor

Quando o banco ainda não possui gestores, o sistema cria:

- E-mail: `gestor@local`
- Senha inicial: valor de `HIBRIDO_MANAGER_PIN`

Essa conta é promovida automaticamente a Super Admin. Depois disso, novos gestores, suas equipes e redefinições de senha são administrados pelo painel. Gestores comuns só consultam e analisam solicitações das equipes vinculadas. Em uma restauração de banco, contas, vínculos e senhas existentes são preservados.

## Dados persistentes

O arquivo principal é `/var/www/html/data/home-office.sqlite`, armazenado no volume `hibrido_data`. Não copie o arquivo diretamente enquanto houver gravações; use o script de backup consistente:

```bash
podman exec hibrido-home-office php /var/www/html/scripts/backup-data.php /tmp/home-office.sqlite
podman cp hibrido-home-office:/tmp/home-office.sqlite ./home-office.sqlite
```

## Migração

O procedimento completo de backup, transporte, restauração e validação está em [MIGRACAO.md](MIGRACAO.md).

## Segurança

- Nunca versione `.env`, backups ou arquivos SQLite.
- O backup contém dados pessoais e hashes de senha; armazene-o em local privado.
- Publique o sistema atrás de HTTPS ao configurar um domínio.
- Troque credenciais iniciais antes de liberar o ambiente a usuários.
