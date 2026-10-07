# Integração de feriados

O Jornada Flow sincroniza os feriados nacionais e estaduais aplicáveis ao estado de São Paulo por meio da Feriados API. O endpoint estadual inclui também os feriados nacionais e está disponível no plano gratuito do provedor.

## Configuração

Configure somente no `.env` de cada ambiente:

```text
HOLIDAYS_API_URL=https://feriadosapi.com
HOLIDAYS_API_TOKEN=token-fornecido-pelo-provedor
HOLIDAYS_STATE=SP
```

O token é obrigatório e nunca deve ser versionado. Após alterar o `.env`, recrie `hibrido_laravel`, `queue_worker` e `scheduler` para renovar o cache de configuração.

## Execução

Por padrão, o comando sincroniza o ano atual e o seguinte:

```bash
php artisan hibrido:sync-holidays
```

Para anos determinados:

```bash
php artisan hibrido:sync-holidays 2026 2027
```

O scheduler executa a sincronização no primeiro dia de cada mês, às 03:00, no fuso da aplicação. Feriados e datas corporativas criados manualmente são preservados quando existe conflito de data. Pontos facultativos são importados como informativos; os demais feriados bloqueiam solicitações.

Se a API falhar ou retornar dados inválidos, o comando encerra com erro e conserva os registros já armazenados. A abertura do calendário não consulta a API externa.
