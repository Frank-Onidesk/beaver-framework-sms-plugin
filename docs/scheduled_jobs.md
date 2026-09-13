# scheduled_jobs — Declaração de jobs agendados

O plugin SMS declara os seus jobs agendados no `plugin.json`, na
secção `scheduled_jobs`. É uma **declaração**, não uma implementação —
o plugin diz "quero isto agendado", e o Beaver (quando o sistema de
scheduling estiver implementado) trata de o criar e correr.

## Estado

> ⚠️ **Ainda não implementado.**
>
> O Beaver **não lê** esta secção atualmente. É uma declaração
> para o futuro. Quando o framework tiver o sistema de scheduling,
> vai ler o `plugin.json` de cada plugin, registar os jobs na
> tabela `scheduled_jobs`, e sincronizá-los com o cron do sistema
> operativo.

## Formato

    {
        "scheduled_jobs": [
            {
                "name": "sms:send_scheduled",
                "description": "Envia SMS agendados que estão due",
                "handler": "Beaver\\Plugins\\Sms\\Jobs\\SendScheduledSmsJob",
                "expression": "*/5 * * * *",
                "enabled": false
            }
        ]
    }

## Campos

| Campo | Obrigatório? | Tipo | O que é |
|---|---|---|---|
| `name` | SIM | string | Identificador único. Formato: `<slug>:<ação>` |
| `description` | NAO | string | Descrição legível (aparece no admin) |
| `handler` | SIM | string | FQCN da classe a invocar. Tem de ter `handle(array $payload)` |
| `expression` | SIM | string | Expressão cron de 5 campos |
| `enabled` | NAO (default: `true`) | boolean | Se o job pode ser ativado pelo admin |

## Expressão cron

Formato de 5 campos:

    ┌───────────── minuto (0-59)
    │ ┌───────────── hora (0-23)
    │ │ ┌───────────── dia do mês (1-31)
    │ │ │ ┌───────────── mês (1-12)
    │ │ │ │ ┌───────────── dia da semana (0-7, 0 e 7 = domingo)
    * * * * *

### Exemplos

| Expressão | Quando corre |
|---|---|
| `*/5 * * * *` | A cada 5 minutos |
| `0 * * * *` | A cada hora |
| `0 9 * * *` | Todos os dias às 9:00 |
| `0 9 * * 1` | Todas as segundas às 9:00 |
| `0 0 1 * *` | No primeiro dia de cada mês à meia-noite |
| `0 9-18 * * 1-5` | Dias úteis, das 9h às 18h, a cada hora |

## O handler

O `handler` é o FQCN da classe a instanciar quando o job corre.

    namespace Beaver\Plugins\Sms\Jobs;

    use Beaver\Plugins\Sms\Services\SmsService;

    class SendScheduledSmsJob
    {
        public function __construct(
            private SmsService $sms,
        ) {}

        public function handle(array $payload = []): void
        {
            // lógica do job
        }
    }

O Beaver:

1. Instancia a classe via container (`Application::make($handler)`).
2. Injeta as dependências (ex: `SmsService`).
3. Chama `->handle($payload)`.

## `enabled` vs `is_active`

Dois estados diferentes:

| Estado | Onde vive | Quem controla |
|---|---|---|
| `enabled` | `plugin.json` | O developer do plugin |
| `is_active` | Tabela `scheduled_jobs` | O admin do sistema |

**Regra:**

- Se o job não existe na tabela → é criado com `is_active = enabled`.
- Se já existe → o framework **não mexe** no `is_active`.
  Só atualiza `handler` e `expression`.

## Fluxo completo (quando implementado)

    1. Plugin declara: scheduled_jobs → sms:send_scheduled (*/5 * * * *)
    2. Framework lê o plugin.json
    3. Cria/atualiza linha em scheduled_jobs
    4. Admin corre: php beaver job:sync
    5. CronManager escreve no cron do SO (Linux/Windows/macOS)
    6. A cada 5 min, o SO corre: php beaver job:run sms:send_scheduled
    7. O comando instancia SendScheduledSmsJob via container
    8. O job corre com o SmsService injetado

## Ver também

- `plugin.json` — declaração
- `src/SmsPlugin.php` — o plugin
- `src/Services/SmsService.php` — o serviço que o job usa
