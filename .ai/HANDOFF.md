# Handoff

## Objetivo atual

Adicionar favicon e substituir o nome público Híbrido por MixHome.

## Estado atual

Publicação concluída em 2026-08-27, autorizada pelo usuário. Código efe6c2b publicado na main e implantado em homologação e produção.

## Alterações

- Favicon SVG autocontido com a marca existente da Mix Fiscal, referenciado pelo layout compartilhado com versionamento de cache.
- MixHome nos títulos, cabeçalho, rodapé, configurações de nome e documentação.
- Identificadores técnicos de banco, containers, volumes e comandos preservados.
- Compose mantém o cookie anterior como padrão e aceita SESSION_COOKIE explícito.
- Teste de regressão em laravel-app/tests/Feature/BrandingTest.php.

## Validação

- Laravel Pint: aprovado.
- PHPUnit: 22 testes, 102 assertions, todos aprovados.
- Favicon validado como XML.
- git diff --check: aprovado.
- CI 33113736323: aprovado, incluindo build da imagem.
- Deploy homologação 33113893253 e produção 33114013837: aprovados, incluindo smoke tests públicos.
- Login com título MixHome e favicon SVG retornando HTTP 200 verificados nos dois ambientes.
- Smoke autenticado permanece fora desta validação, sem conta técnica configurada.

## Próximo passo

Nenhuma etapa de publicação pendente para esta mudança. Nenhuma migration de banco foi adicionada.

## Pendências operacionais preexistentes

- Conta técnica sintética e secrets SMOKE_EMAIL/SMOKE_PASSWORD para smoke test autenticado.
- Plano atual do GitHub sem reviewer obrigatório para environment privado; deploy de produção permanece manual.
- Integração OneDrive não configurada.
- Expurgo/anonimização após dois anos ainda não implementado.
- SMTP/recuperação de senha permanecem adiados.

## Atenção

Não versionar .env, credenciais, dumps, dados pessoais ou configuração privada do Caddy. Publicação depende de autorização explícita.
