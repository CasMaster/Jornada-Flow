# Manual de usuário — MixHome

## 1. Acesso ao sistema

Abra o endereço fornecido pela empresa. A tela inicial possui duas opções:

- **Sou colaborador**: cadastro inicial, solicitações pessoais e histórico;
- **Sou gestor**: análise das equipes, filtros e exportação.

Contas de gestor e Super Admin também podem entrar pela opção **Sou colaborador** ou alternar de painel depois do login.

### Tema claro e escuro

Use o botão de tema no cabeçalho. A preferência fica salva no navegador utilizado.

## 2. Primeiro acesso do colaborador

1. Selecione **Sou colaborador**.
2. Abra **Primeiro acesso**.
3. Informe nome completo e e-mail corporativo.
4. Escolha a equipe correta.
5. Crie e confirme uma senha com pelo menos oito caracteres.
6. Clique em **Criar conta e continuar**.

Se o e-mail já estiver cadastrado, use **Já tenho cadastro**. Se a equipe não aparecer, solicite ao Super Admin que a ative ou cadastre.

## 3. Registrar home office

1. Entre no painel pessoal.
2. Navegue até o mês desejado pelas setas do calendário.
3. Clique em cada dia que deseja solicitar.
4. Confira o contador de dias selecionados.
5. Clique em **Enviar solicitação**.

Cada dia passa a ter o estado **Pendente**. Um dia enviado não pode ser alterado nem apagado pelo colaborador.

### Histórico

O histórico apresenta as solicitações com um dos estados:

- **Pendente**: ainda não analisada;
- **Aprovada**: aceita pelo gestor;
- **Recusada**: não aceita, mas mantida no histórico.

## 4. Gestor usando o painel pessoal

No cabeçalho, gestores encontram o seletor:

- **Meu home office**: solicitações e histórico próprios;
- **Gestão**: acompanhamento das equipes administradas.

Para registrar o próprio home office, a conta do gestor precisa ter uma **equipe própria** definida pelo Super Admin. Essa equipe é diferente das equipes que o gestor administra.

## 5. Painel do gestor

O painel exibe solicitações somente das equipes vinculadas ao gestor. O Super Admin possui visão global.

### Filtrar solicitações

É possível combinar:

- ciclo de apuração;
- uma ou várias equipes permitidas;
- estado;
- um ou vários colaboradores.
- ordenação e quantidade de resultados por página.

Nas seleções de equipes e colaboradores, clique nos nomes desejados. Cada escolha vira uma etiqueta. Use o **X** vermelho para removê-la; não é necessário usar `Ctrl` ou `Cmd`. Ao escolher equipes, a lista de colaboradores passa a mostrar somente pessoas dessas equipes.

Clique em **Aplicar filtros** para atualizar os resultados ou em **Limpar** para retornar ao período vigente sem filtros adicionais. Use **Salvar preferência** para guardar no navegador ciclo, equipes, estado, ordenação e quantidade por página. Nome pesquisado e colaboradores específicos não são armazenados.

### Ciclo 20–19

O período começa no dia 20 de um mês e termina no dia 19 do mês seguinte. Exemplo: 20/07 a 19/08.

### Aprovar ou recusar

1. Localize a solicitação.
2. Se desejar, escreva uma justificativa ou orientação para o colaborador.
3. Clique em **Aprovar** ou **Recusar**.
4. Confirme a recusa quando solicitado.

A recusa arquiva a solicitação; ela não apaga a data. A decisão pode ser atualizada posteriormente por um gestor autorizado. O colaborador vê a justificativa no próprio histórico. Nas ações em lote, a observação informada é aplicada a todas as solicitações selecionadas.

### Exportar Excel

1. Defina ciclo, equipe e colaboradores desejados.
2. Clique em **Aplicar**.
3. Clique em **Exportar dados**.

A planilha considera os filtros atuais e marca somente solicitações aprovadas. Os colaboradores aparecem nas linhas e as datas nas colunas.

## 6. Administração do Super Admin

### Equipes

- Cadastre uma equipe informando seu nome.
- Desative equipes que não devem aceitar novos cadastros.
- Reative quando necessário.

Desativar uma equipe não apaga usuários nem solicitações existentes.

### Criar usuário

Abra **Usuários** no seletor superior. O diretório possui busca por nome ou
e-mail, filtros de perfil, equipe e status, além de paginação.

Para criar uma conta, informe:

- nome e e-mail;
- perfil: Colaborador, Gestor ou Super Admin;
- equipe para as solicitações pessoais;
- equipes administradas, quando o perfil for Gestor;
- senha provisória com pelo menos oito caracteres; ou, quando a recuperação por
  e-mail estiver habilitada, deixe-a vazia para enviar um link de definição de
  senha.

### Editar usuário

1. Localize a pessoa usando a busca e os filtros.
2. Abra **Gerenciar**.
3. Atualize os campos necessários.
4. Deixe a senha vazia para mantê-la ou informe uma nova senha.
5. Clique em **Salvar alterações**.

### Desativar usuário

Use **Desativar** para impedir novos acessos. O histórico permanece preservado. O Super Admin não pode desativar a própria conta enquanto estiver conectado.

### Reenviar acesso

Quando o envio por e-mail estiver habilitado, use **Enviar acesso por e-mail**.
O usuário receberá um link individual, válido por 60 minutos e utilizável uma
única vez.

### Delegar equipes temporariamente

Na área **Delegação de gestores**, escolha o gestor de origem, o substituto e o período. Durante essas datas, o substituto poderá consultar e analisar as equipes administradas pelo gestor de origem. Somente o Super Admin cria ou encerra delegações, e todas as ações continuam registradas na auditoria.

## 7. Sair e trocar de usuário

- **Sair**, no cabeçalho, encerra a sessão.
- **Trocar usuário**, no painel pessoal, também encerra a sessão e retorna ao login.

Não compartilhe senhas e sempre encerre a sessão em computadores compartilhados.

## 8. Solução de problemas

### Não consigo entrar

- Confirme se escolheu o perfil correto na tela de login.
- Verifique e-mail e senha.
- Peça ao Super Admin para confirmar se a conta está ativa.
- Se **Esqueci minha senha** estiver disponível, solicite um link pelo próprio sistema.

### Recuperar senha

1. Na tela de login, clique em **Esqueci minha senha**.
2. Informe o e-mail corporativo.
3. Abra o link recebido por e-mail.
4. Defina e confirme a nova senha.

Por segurança, o sistema apresenta a mesma confirmação mesmo quando o e-mail
não está cadastrado. Ao redefinir a senha, as sessões anteriores são encerradas.

### Sou gestor, mas não vejo uma equipe

O Super Admin precisa vincular essa equipe em **Equipes administradas**.

### Sou gestor, mas minha solicitação pessoal não aparece corretamente

O Super Admin deve definir a **Equipe para o próprio home office** na sua conta.

### Uma data já foi enviada

O sistema não cria duplicidade para o mesmo usuário e data. Consulte o histórico.

### A aparência parece desatualizada

Recarregue a página. Os arquivos visuais são versionados automaticamente; se necessário, feche e abra novamente a aba.

### Preciso corrigir um dado ou redefinir a senha

Use a recuperação de senha ou procure o Super Admin. Colaboradores não podem editar solicitações já enviadas.

## 9. Calendário, notificações e histórico

Datas bloqueadas pela empresa aparecem marcadas no calendário e não podem ser selecionadas. Feriados nacionais e estaduais são atualizados automaticamente; datas municipais de Campinas ou eventos corporativos podem ser mantidos manualmente pelo Super Admin. A gestão exibe a data da última sincronização e alerta quando estiver desatualizada. O histórico informa envio, análise, gestor responsável e eventual justificativa. Aprovações e recusas aparecem também em “Notificações recentes”.

## 10. Pesquisa e análise em lote

O gestor pode pesquisar por nome ou e-mail e combinar ciclo, equipe, status e colaboradores. Os resultados podem ser ordenados e exibidos em grupos de 25, 50 ou 100. Para analisar vários, marque as caixas e use a barra “Ações em lote”. Todas as permissões são verificadas novamente no servidor.

O cartão “Aguardando sua ação” destaca pendências do filtro atual. Próximo ao fechamento, o sistema envia lembretes automáticos aos gestores.

## 11. Calendário corporativo e auditoria

O Super Admin pode cadastrar datas informativas ou bloquear solicitações. A tela “Auditoria” mostra aprovações, recusas e mudanças administrativas, com responsável, horário e IP.

## 12. Prioridades, visão executiva e exportação

O painel do gestor destaca as pendências mais antigas e resume a distribuição entre pendentes, aprovadas e recusadas por equipe. A exportação respeita os filtros aplicados: **Excel matricial** é indicado para a consolidação mensal e **CSV detalhado** para análises ou integrações. Escolha também o status antes de exportar.

## 13. Saúde operacional

O menu **Operação**, exclusivo do Super Admin, apresenta fila, falhas de processamento, pendências antigas, disponibilidade e tamanho do banco. Esses indicadores complementam o monitoramento externo e não substituem os alertas do servidor.

## 14. Planejamento de férias

Abra **Minhas férias**, informe início e término e envie o período. O sistema mostra
os dias corridos no histórico e impede sobreposição com outra solicitação de férias
ou com home office já registrado. Depois do envio, o colaborador apenas acompanha.

Gestores acessam **Gestão → Férias**, visualizam somente suas equipes, consultam os
próximos períodos aprovados e aprovam ou recusam pedidos. A exportação CSV respeita
os filtros. Férias aprovadas impedem novos pedidos de home office nas mesmas datas.

O Super Admin pode corrigir datas ou cancelar um registro mediante justificativa
obrigatória. O registro permanece no histórico e todas as ações ficam na auditoria.
