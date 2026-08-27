# Handoff

## Objetivo atual

Adicionar favicon e substituir o nome público Híbrido por MixHome.

## Estado atual

Implementação local concluída em 2026-08-27. Publicação autorizada pelo usuário; CI e deploy em homologação e produção em andamento.

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

## Próximo passo

Concluir CI e deploy, verificando favicon e nome em produção e em /homologacao. Nenhuma migration de banco é necessária.

## Pendências operacionais preexistentes

- Conta técnica sintética e secrets SMOKE_EMAIL/SMOKE_PASSWORD para smoke test autenticado.
- Plano atual do GitHub sem reviewer obrigatório para environment privado; deploy de produção permanece manual.
- Integração OneDrive não configurada.
- Expurgo/anonimização após dois anos ainda não implementado.
- SMTP/recuperação de senha permanecem adiados.

## Atenção

Não versionar .env, credenciais, dumps, dados pessoais ou configuração privada do Caddy. Publicação depende de autorização explícita.
