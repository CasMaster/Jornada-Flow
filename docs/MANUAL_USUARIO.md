# Manual de usuário — HÍBRIDO

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
- equipe;
- estado;
- um ou vários colaboradores.

Na seleção múltipla, clique nos nomes desejados. Cada escolha vira uma etiqueta. Use o **X** vermelho para removê-la; não é necessário usar `Ctrl` ou `Cmd`.

Clique em **Aplicar** para atualizar os resultados ou em **Ciclo atual** para limpar os filtros e retornar ao período vigente.

### Ciclo 20–19

O período começa no dia 20 de um mês e termina no dia 19 do mês seguinte. Exemplo: 20/07 a 19/08.

### Aprovar ou recusar

1. Localize a solicitação.
2. Clique em **Aprovar** ou **Recusar**.
3. Confirme a recusa quando solicitado.

A recusa arquiva a solicitação; ela não apaga a data. A decisão pode ser atualizada posteriormente por um gestor autorizado.

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
