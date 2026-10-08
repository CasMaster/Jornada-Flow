# Autenticação centralizada com Keycloak

O Jornada Flow usa OpenID Connect Authorization Code com PKCE `S256` para autenticar no realm `mixapps`. O servidor descobre os endpoints pelo issuer, troca o código usando o segredo do cliente e valida o ID token com o JWKS publicado pelo Keycloak. Assinatura, algoritmo, issuer, audience, expiração e nonce são obrigatórios.

## Configuração do cliente no Keycloak

Crie ou ajuste o cliente com estas propriedades:

- Client ID: `mixhome-web`.
- Client authentication: habilitada (cliente confidential).
- Standard Flow: habilitado.
- PKCE method: `S256`.
- Direct Access Grants e Implicit Flow: desabilitados, salvo necessidade externa documentada.
- Root/Home URL de produção: `https://mixhome.app.br`.

Cadastre exatamente estas Valid Redirect URIs:

```text
https://mixhome.app.br/auth/callback
https://mixhome.app.br/homologacao/auth/callback
```

Cadastre o Web Origin:

```text
https://mixhome.app.br
```

Cadastre estas Valid Post Logout Redirect URIs:

```text
https://mixhome.app.br
https://mixhome.app.br/homologacao
```

Crie os client roles `mixhome-admin` e `mixhome-user` no cliente `mixhome-web`. Atribua-os diretamente ou por grupos. Confirme com um Client Scope/mapper que os papéis aparecem tanto no ID token quanto no access token neste caminho:

```text
resource_access.mixhome-web.roles
```

O ID token também precisa conter `sub`, `email`, `email_verified`, `name`, `aud`, `iss`, `exp` e `nonce`. Confirme que `mixhome-web` consta em `aud`; se necessário, adicione um Audience mapper dedicado ao cliente.

## Variáveis por ambiente

O segredo é obtido em Credentials do cliente Keycloak e deve ser configurado diretamente no ambiente protegido do servidor. Ele nunca deve ser gravado no Git, em documentação ou nos arquivos Compose versionados.

O deploy procura por padrão `/home/admin/.config/mixhome-web/oidc.env`, exige arquivo regular com permissão `0600`, exporta seu conteúdo somente para o processo de implantação e repassa as variáveis OIDC aos containers. Um caminho diferente pode ser definido por `OIDC_ENV_FILE` no ambiente do deploy. Antes de futuramente habilitar produção, use um arquivo próprio ou confira que as URIs nele configuradas correspondem ao ambiente de produção.

Produção:

```ini
OIDC_ENABLED=true
OIDC_ISSUER=https://auth.mixhome.app.br/realms/mixapps
OIDC_CLIENT_ID=mixhome-web
OIDC_CLIENT_SECRET=<fornecido externamente>
OIDC_REDIRECT_URI=https://mixhome.app.br/auth/callback
OIDC_LOGOUT_REDIRECT_URI=https://mixhome.app.br
OIDC_SCOPES="openid profile email"
OIDC_SIGNING_ALGORITHM=RS256
```

Homologação:

```ini
OIDC_ENABLED=true
OIDC_ISSUER=https://auth.mixhome.app.br/realms/mixapps
OIDC_CLIENT_ID=mixhome-web
OIDC_CLIENT_SECRET=<fornecido externamente>
OIDC_REDIRECT_URI=https://mixhome.app.br/homologacao/auth/callback
OIDC_LOGOUT_REDIRECT_URI=https://mixhome.app.br/homologacao
OIDC_SCOPES="openid profile email"
OIDC_SIGNING_ALGORITHM=RS256
```

Após alterar o ambiente, limpe o cache de configuração e reinicie os serviços web/worker sem excluir volumes. A inicialização executará a migration que adiciona `users.keycloak_subject`.

## Vínculo e autorização

O identificador permanente é o `sub` do Keycloak, salvo em `users.keycloak_subject`, único e inicialmente nullable. No primeiro acesso, uma conta local ainda não vinculada pode ser localizada uma única vez pelo e-mail verificado; depois disso, todos os acessos usam exclusivamente o `sub`. Se não houver conta local, uma é criada com senha aleatória não conhecida pelo usuário.

- `mixhome-user`: permite o acesso comum. Uma nova conta recebe o perfil local `employee`; contas já existentes preservam perfil, equipe e relacionamentos.
- `mixhome-admin`: permite áreas administrativas e associa o perfil local `super_admin`.
- Sem um desses papéis: o callback responde com acesso negado e não cria sessão nem usuário.

O middleware administrativo confere `mixhome-admin` na sessão OIDC, além do perfil local. Assim, um papel administrativo antigo salvo localmente não contorna o Keycloak. A sessão termina quando o ID token expira e um novo login é exigido.

O login local e a recuperação de senha permanecem disponíveis como contingência temporária. Não remova senhas nem desative esse caminho antes de validar todos os usuários, gestores, Super Admins, integrações de smoke e o procedimento de recuperação operacional.

## Implantação e validação

1. Faça backup e publique primeiro em homologação.
2. Cadastre o cliente, URLs, roles, mapper e segredo no Keycloak.
3. Configure as variáveis de homologação sem exibir o segredo no terminal ou logs.
4. Execute as migrations e limpe o cache de configuração.
5. Valide um usuário de cada cenário: `mixhome-admin`, `mixhome-user` e sem papel.
6. Confirme login, retorno ao painel correto, bloqueio administrativo, logout no Keycloak e novo login após a expiração.
7. Só então repita a configuração em produção, com backup e autorização operacional.

Configuração manual restante: criar/configurar o cliente no Keycloak, fornecer o `OIDC_CLIENT_SECRET` nos dois ambientes, atribuir os client roles aos usuários/grupos e validar o mapper/audience. Essas ações não podem ser realizadas pelo código da aplicação.
