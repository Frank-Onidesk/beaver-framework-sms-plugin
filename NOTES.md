# Plugin SMS — notas de design

Escrevo isto sobretudo para mim. Quando voltar a este plugin daqui a uns
meses, quero saber **porque é que fiz as coisas assim** e não de outra
maneira qualquer. Se estiver a ler isto e algo parecer estranho, é
provavelmente porque tive uma razão na altura — e está aqui em baixo.

## Índice

1.  Porque vive em beaver-plugins/
2.  Porque se chama SmsPlugin e não SMSPlugin
3.  Porque o SmsService não grava CSMS
4.  Porque as credenciais vêm de config → env → default
5.  Porque o SmsService recebe params no construtor
6.  Porque a normalização do telefone está no serviço
7.  CsmsRepository é um stub em memória
8.  Controller sem DI no construtor
9.  Erros do cURL não lançam exceção
10. Prefixo sms:: nas traduções
11. .env do framework separado do .env do site
12. TODOs antigos (histórico)
13. Porque criar dentro duma pasta — /var/www/onidesk/beaver-framework
14. Como chegámos aqui — site → componentes → framework
15. Credenciais do fornecedor — só uma, a licensekey
16. O fornecedor de SMS é configurável
17. Renomeação EZ4U_* → SMS_PROVIDER_*
18. Primeiro envio real — confirmado
19. Receita rápida — smoke test do plugin
20. Permissions — verificação de rede fica em stand-by

---

## 1. Porque é que o plugin vive em `/var/www/onidesk/beaver-plugins/sms`

O framework (beaver-framework) e o site Onidesk (`/var/www/onidesk/index.php`)
são projetos **separados**. Coexistem na mesma pasta só porque é o docroot
do Apache, não porque tenham alguma coisa a ver um com o outro.

Decisão: os plugins ficam **fora** do framework, em `beaver-plugins/`.
Assim:

- posso mexer no plugin sem tocar no framework,
- posso ter o plugin em dev e um "publicado" no framework ao mesmo tempo,
- se o framework for atualizado, os plugins ficam intactos.

Isto é o modo `dev` do `PluginManager`. Em produção passa a `prod` e os
plugins vivem dentro do framework em `app/plugins/`.

---

## 2. Porque é que o SmsPlugin se chama `SmsPlugin` e não `SMSPlugin` ou `Sms`

PSR-4 gosta de StudlyCase. Se a classe fosse `SMSPlugin`, o Composer
precisava de saber o path exato. `SmsPlugin` é o que o Composer gera a
partir do namespace `Beaver\Plugins\Sms` + classe `SmsPlugin`.

O `plugin.json` mantém o `slug` em minúsculas (`sms`) porque é isso que
aparece nas URLs, nos namespaces de views (`sms::`) e de traduções.

---

## 3. Porque é que o SmsService NÃO grava o CSMS (a tabela na BD)

Este plugin foi adaptado dum que fiz no EspoCRM.

No Espo, o `SmsService::sendSms()` fazia tudo: enviava, atualizava a
entidade, registava o erro. Era prático, mas acoplava o serviço ao ORM.

No Beaver, decidi separar:

- **SmsService** — sabe falar com a EZ4U. Não sabe o que é uma entidade CSMS.
- **SmsController** — orquestra: pede ao service para enviar, grava o
  estado no repositório.

Porquê: quero poder testar o `SmsService` sem BD nenhuma, e quero poder
reutilizá-lo noutro sítio (comando CLI, job agendado) sem arrastar o
repositório atrás.

---

## 4. Porque é que as credenciais vêm de config → env → default

No `SmsPlugin::registerServices()` uso um `resolve()` que faz:

    config('sms_provider.key') ?: env('SMS_PROVIDER_KEY') ?: ''

Porquê os três níveis:

- **config do plugin** — útil em produção, para valores fixos daquele
  cliente.
- **.env** — útil em dev e staging, para cada máquina ter as suas
  credenciais sem tocar no código.
- **default** — para o plugin não rebentar se não houver nada configurado.
  Um endpoint hardcoded é melhor que um fatal error.

A ordem é esta porque **quem configura o plugin sabe mais** que o `.env`.
Se um dia quiser forçar a chave em produção, ponho-a no
`config/sms_provider.php` e ela ganha.

---

## 5. Porque é que o SmsService recebe os parâmetros no construtor

Podia fazer `$sms->setAccount(...)` antes de enviar, ou ir buscar tudo
dentro do `send()`. Optei pelo construtor porque:

- Se falta uma credencial, sei logo no `boot()` do plugin — não à terceira
  chamada de envio.
- O serviço fica imutável. Depois de construído, é sempre o mesmo.
- Testar é trivial: `new SmsService('url', 'acc', 'key')`.

No Espo isto era impossível porque as settings vinham da BD em cada
chamada. Ganhei previsibilidade, perdi a capacidade de mudar a conta
em runtime. Não preciso disso, por isso está bem.

---

## 6. Porque é que mantive a normalização do telefone dentro do serviço

A regra "prefixa 351 se for PT, remove tudo o que não é dígito" é
**específica da EZ4U e do contexto PT**. Podia estar no controller, mas
colocá-la no service tem uma vantagem: quem chama `send()` só precisa de
passar "o número que o utilizador escreveu". Não tem de saber as regras.

Contrapartida: se um dia precisar de enviar para Espanha sem prefixo 351,
tenho de mexer aqui. Aceitei o risco — hoje só envio para PT.

---

## 7. Porque é que o CsmsRepository é um stub em memória

O ORM do Beaver (Bloco 4) ainda não está pronto neste momento em que
estou a desenhar o plugin. Em vez de bloquear o plugin inteiro, fiz um
repositório que guarda tudo num array estático.

A assinatura é a mesma que a versão real vai ter:

    find(string $id): ?array
    create(array $attrs): array
    update(array &$record, array $attrs): void

Quando o ORM estiver pronto, troco o corpo destes métodos por chamadas
ao Model. O controller não muda uma linha.

⚠️ ISTO NÃO PERSISTE. Se alguém correr isto em produção, os CSMS
(CSMS = registo de um SMS enviado ou a enviar) desaparecem quando o
request termina. Está lá um `error_log()` no construtor a avisar. Não
remover esse aviso antes de trocar o stub pelo real.

---

## 8. Porque é que o controller não usa DI no construtor

O Router do Beaver, neste momento, não resolve controladores pelo
container. Ou o controller é chamado por closure (que tem acesso ao
`$plugin`), ou é instanciado com `new SmsController($plugin)`.

Optei por **closure em `routes/web.php`** porque:

- tenho o `$plugin` à mão para ler config,
- o controller pode continuar a fazer `Application::getInstance()->make(...)`
  para serviços (SmsService, CsmsRepository),
- quando o Router aprender a fazer DI no construtor, migrar é trivial.

Se o Router passar a suportar `[SmsController::class, 'sendList']`,
mudo a rota e o controller continua a funcionar sem alterações.

---

## 9. Porque é que os erros do cURL não lançam exceção

No `SmsService::cUrl()`, se o cURL falha, devolvo um JSON estruturado
com `Result: NOT OK`. Não lanço exceção.

Razão: o controller trata todos os resultados da mesma maneira —
seja OK, seja erro EZ4U, seja erro cURL. Ter um único formato
(um array com `Result` e `ErrorDesc`) simplifica imenso o código
do controller. Se lançasse exceção, teria de ter dois fluxos.

O `send()` só lança `InvalidArgumentException` para input do utilizador
(número inválido, texto vazio). Isso sim, é responsabilidade do
controller tratar como 400.

---

## 10. Porque é que as traduções têm o prefixo `sms::`

Foi para seguir o mesmo padrão das views (`view('sms::modal')`).
O `Translator::addNamespace('sms', ...)` e o `View::registerNamespace('sms', ...)`
são simétricos — isto é intencional.

Assim, quando num template do plugin escrevo `__('sms::title')` e
`view('sms::form')`, sei que ambos vêm do mesmo sítio. E se um dia
tiver dois plugins, cada um tem o seu prefixo, sem colisões.

---

## 11. Porque é que o .env do framework é separado do .env do site

O `.env` do framework (`/var/www/onidesk/beaver-framework/.env`) tem
as variáveis do framework e dos plugins (`SMS_PROVIDER_*`, `BEAVER_PLUGIN_MODE`).

O `.env` do site (`/var/www/onidesk/.env`) tem as variáveis da loja
(base de dados do site, PayPal, SIBS, etc.).

**Nunca** as misturar. Se um dia precisar de uma variável dos dois
lados, duplico-a conscientemente. É preferível ter duas cópias
do que ter uma var que muda de significado conforme o contexto.

---

## 12. O que ainda não está feito (histórico — ver #20 para a lista atual)

> Esta lista é de antes do plugin estar pronto. A lista viva está
> na nota #20.

- [x] SmsController (adaptação do SimpleSms do Espo)
- [x] CsmsRepository stub em memória
- [x] Traduções pt/en (namespace sms::)
- [x] Views form e result
- [x] Rotas de teste (`/sms`, `/sms/send-list`, `/sms/test`)
- [x] Primeiro envio real
- [ ] CsmsRepository real (quando o Bloco 4 do framework estiver pronto)
- [ ] Migração da tabela `csms`
- [ ] Templates dinâmicos (`templateId`)
- [ ] Lookup de pai (`recordIds` + Lead/Contact)
- [ ] Página de admin no Beaver para configurar o fornecedor
- [ ] Endpoint `GET /sms/status/{id}`
- [ ] Fila de jobs para envios grandes (hoje é tudo síncrono)

---

## 13. Porque criar dentro duma pasta — no meu caso `/var/www/onidesk/beaver-framework`

A maioria dos utilizadores instala as frameworks na directoria onde vão
trabalhar e não num nível acima.

Para mim fez sentido assim. Passei a explicar porquê, porque daqui a uns
meses vou olhar para isto e perguntar-me se foi distração.

### Contexto

O `/var/www/onidesk/` é o docroot do Apache. Já tinha lá o site
(loja, `index.php`, `cart.php`, `checkout.php`, etc.) e queria experimentar
o Beaver sem partir nada. Se tivesse criado `/var/www/beaver-framework/`,
tinha três problemas:

- **Apache** — tinha de configurar um VirtualHost ou um Alias novo só
  para chegar ao framework. Dentro de `onidesk` já funciona com o
  `.htaccess` que lá está.
- **Backup** — o meu script de backup (`gerar_onidesk.ps1`) copia
  `/var/www/onidesk/`. Se o framework estivesse fora, tinha de o
  acrescentar à lista. Assim está tudo debaixo do mesmo chapéu.
- **Dúvida** — o Beaver ainda é novo (v0.1). Quero poder apagá-lo
  inteiro (`rm -rf beaver-framework`) sem ter de mexer em nada fora
  deste diretório. Assim é só uma pasta.

### Mas convém não misturar

O site e o framework **não se conhecem**. Não têm autoloads partilhados,
não têm `.env` partilhado, não têm BD partilhada. Se algum dia parecer
que partilham alguma coisa, é sinal de que algo está mal.

Por isso as regras que estabeleci:

- `.env` do site → `/var/www/onidesk/.env`
- `.env` do framework → `/var/www/onidesk/beaver-framework/.env`
- `composer.json` e `vendor/` → cada um tem o seu
- Plugins → `/var/www/onidesk/beaver-plugins/` (fora do framework, mas
  também fora do site)

### Quando isto passa a fazer sentido

Enquanto for um projeto só (Onidesk), ter o framework dentro do docroot
é prático. Se um dia:

- tiver dois sites a usar o mesmo Beaver,
- tiver de fazer deploy do framework para um servidor separado,
- tiver um cliente que quer o Beaver sem o Onidesk,

aí tiro o framework de dentro e passo a fazer `composer require` num
sítio próprio. Hoje não compensa a complicação.

---

## 14. Como é que isto chegou aqui — site → componentes → framework

Comecei por fazer o site em HTML puro. Páginas estáticas, `<head>`
copiado à mão em cada ficheiro, mesmo menu, mesmo footer, mesmo bloco
de login em todos os sítios. Funcionava, mas cheguei a um ponto em que
cada alteração ao menu obrigava a mexer em dez ficheiros. Foi aí que
pensei:

> "Porque não poupar trabalho e criar os meus próprios componentes?"

Fiz. Comecei a extrair pedaços repetidos — header, footer, formulários,
cartões de produto — para pequenos ficheiros PHP que eu incluía. Deixou
de haver dez cópias do menu, passou a haver uma.

Depois o "componente" cresceu. Já não era só apresentação: precisei de
rotas (para ter `/produto/123` em vez de `produto.php?id=123`), precisei
de uma camada de acesso à BD, precisei de migrations para não estar
sempre a fazer `ALTER TABLE` à mão. Cada coisa destas era um
"porque não…" que ia somando. A framework apareceu assim, por
acumulação — não foi um plano desenhado de início.

### Porque é que o site ainda não usa a framework

Neste momento tenho duas coisas:

- **O site / loja** — feito em HTML+PHP puro, com includes manuais,
  a funcionar. Já vende, já tem clientes, já tem código lá dentro
  que sei que funciona.
- **A framework** — que tem views, routing, ORM, migrations, plugins,
  certificados, fila de jobs, CLI.

A pergunta natural é: **porque é que não aplico já a framework ao site?**

Resposta honesta: **ainda não tem tudo o que o site precisa**. Falta-me
sobretudo o sistema de views como quero (namespaces, layouts, partials,
o motor todo afinado), e falta-me testar o resto em condições antes
de reescrever o site inteiro.

Se eu migrasse agora, ficava a meio: o site partia, a framework
não estava pronta, e perdia vendas enquanto acabava. Não compensa.

### Como vamos caminhar daqui para a frente

A ideia é que, quando fizer sentido, o site / loja passe a correr
**dentro** da framework, em vez de ao lado dela.

Concretamente, o que a framework pede é:

- Os **controllers**, **models**, **services** e **views** vão para
  `app/` (é onde a framework procura — ver `config/app.php`).
- As **rotas** vão para `routes/web.php`.
- As **migrations** vão para `database/migrations/`.
- A **config** vai para `config/`.
- O **storage** (logs, cache, sessões, uploads privados) vai para
  `storage/`.

E a parte que o **Apache** precisa — o docroot — vai para uma pasta
`public/`, que é o único sítio que fica exposto ao browser:

- `public/index.php`      ← front controller
- `public/assets/`        ← CSS, JS, imagens
- `public/uploads/`       ← uploads públicos
- `public/.htaccess`
- `public/favicon.ico`, `robots.txt`, etc.

Ou seja: **`public/` não é uma pasta da framework — é a pasta que o
servidor web aponta**. A framework não impõe lá nada, não a conhece,
não a gere. Só vivem lá os ficheiros que o browser tem de poder pedir
diretamente. Tudo o resto fica **fora** do `public/`, como convém
em qualquer aplicação PHP bem estruturada. Se um dia me apetecer pôr
um controller dentro de `public/`, é sinal de que estou a fazer asneira.

### Como o servidor vai ficar

Antes:

    Apache docroot → /var/www/onidesk/
    ├── index.php            (site)
    ├── cart.php
    ├── checkout.php
    ├── assets/
    └── beaver-framework/    ← o framework existia aqui "ao lado"

Depois:

    Apache docroot → /var/www/onidesk/beaver-framework/public/
    └── beaver-framework/
        ├── app/             ← controllers, models, services, views
        ├── config/
        ├── database/
        ├── public/          ← docroot (index.php, assets, uploads)
        ├── routes/
        ├── storage/
        └── vendor/

A `/var/www/onidesk/` passa a ter apenas `beaver-framework/` e
`beaver-plugins/`. O site e a framework são a mesma coisa.

### O que aprendi com isto

Foi mais fácil fazer a framework **separada** do que a framework
**dentro** do site. Se tivesse começado ao contrário — a framework
a nascer dentro da loja — provavelmente nunca a teria extraído. O site
tinha-a engolido. Ao pô-la fora, obriguei-me a pensar nela como uma
coisa independente, com as suas próprias regras e o seu próprio
`composer.json`.

Isto é uma lição que vale a pena lembrar: **é mais fácil extrair do
que separar depois**.

---

## 15. Credenciais do fornecedor — só uma, a licensekey

No `.env` do framework tenho só uma credencial do fornecedor de SMS:

- **SMS_PROVIDER_KEY** — é a `licensekey` da API. Vai no POST do
  `SmsService`, no campo `licensekey`. Obtém-se no menu "APIs" do
  backoffice do fornecedor. **NÃO é a password de login na plataforma**
  — já me lixou uma vez, por isso deixei o aviso bem visível no
  `SmsService` do Espo e mantenho-o também no `SmsService` do Beaver.

Não guardo a password do dashboard no `.env`. Não é precisa para
nada — a API autentica só com a licensekey. Se um dia precisar de
entrar no dashboard, meto a password manualmente. Não a quero a
circular em ficheiros de configuração.

Se algum dia o fornecedor mudar e passar a pedir `password` no POST,
acrescento ao construtor do `SmsService` e ao `http_build_query`. É uma
alteração de duas linhas.

---

## 16. O fornecedor de SMS é configurável

O plugin nasceu com a EZ4U, mas **não está preso à EZ4U**.

Nada no código assume um cliente ou fornecedor específico. A conta,
a licensekey, o remetente, o endpoint, o TTL e o `envio24` vêm todos
de config ou `.env`.

O único default com "AutoReno" é o **alfaSender** (o nome que aparece
no SMS). É cosmético, e qualquer instalação o pode sobrepor com uma
linha no `.env`.

**Regra:** nada específico de um cliente vai para o código. Tudo o
que é específico de um cliente vai para o `.env`.

---

## 17. Renomeação EZ4U_* → SMS_PROVIDER_*

Originalmente as variáveis no `.env` eram `EZ4U_*`. Renomeei-as para
`SMS_PROVIDER_*` porque:

- o código não deve saber quem é o fornecedor,
- se um dia trocar para outro (Twilio, Vonage, etc.), não quero
  andar a renomear variáveis pelo projeto todo,
- um plugin que diz "EZ4U" está a mentir sobre o que faz.

O que continua a **parecer** específico:

- Alguns comentários referem "EZ4U" como referência histórica.
- O método `cUrl()` usa os nomes de campo da EZ4U (`account`,
  `licensekey`, `phoneNumber`, `messageText`, `startDate`,
  `alfaSender`, `TTL`, `envio24`). Se um dia trocar, adapto lá.
- O valor do `SMS_PROVIDER_URL` no `.env` aponta para o endpoint
  EZ4U. Isso é config, não código.

O que **não** é específico:

- O construtor do `SmsService`.
- Os parâmetros do `SmsController`.
- As rotas.
- As traduções.
- O `CsmsRepository`.

---

## 18. Primeiro envio real — confirmado

No dia 12 de setembro de 2026 fiz o primeiro envio real pelo Beaver,
através da conta **AutoReno** da EZ4U. O SMS chegou ao telemóvel
em segundos.

### O pedido

    curl -s -X POST http://localhost:9000/sms/send-list \
      -H "Content-Type: application/json" \
      -d '{
        "phones": ["+351938125635"],
        "smsText": "fix ao handling das credencias novas"
      }'

### A resposta

    {
      "success": true,
      "message": "O SMS foi enviado a 1 números. Sucesso: 1, Falhas: 0",
      "data": {
        "total": 1,
        "success": 1,
        "failed": 0,
        "csmsIds": ["cada6caaff2a286b"],
        "results": [{
          "phone": "+351938125635",
          "status": "Held",
          "message": "SMS enviado para +351938125635.",
          "smsId": "1f1aefd6-433e-6fce-82b9-b6c747da5fdf",
          "csmsId": "cada6caaff2a286b"
        }]
      }
    }

### O que ficou provado

- Rota `POST /sms/send-list` registada e funcional.
- `SmsController::sendList()` corre e devolve JSON.
- `SmsService` fala com a EZ4U.
- Credenciais `SMS_PROVIDER_*` no `.env` corretas.
- `CsmsRepository` (stub) cria e atualiza CSMS.
- Traduções carregadas, placeholders substituídos.
- **SMS entregue no telemóvel.**

Também testei pelo formulário em `/sms/test`, com o mesmo resultado.

### Detalhes interessantes

- A EZ4U mudou os IDs de SMS para UUIDs. Antes eram numéricos,
  agora é `1f1aefd6-433e-6fce-82b9-b6c747da5fdf`.
- O `csmsId` é gerado pelo stub (`bin2hex(random_bytes(8))`).
  Quando o ORM real entrar, será outro formato.

---

## 19. Receita rápida — smoke test do plugin

Para confirmar rapidamente que o plugin está vivo:

### 1. Plugin descoberto

    curl -s http://localhost:9000/sms

Esperado:

    {"plugin":"SMS","version":"0.1.0","slug":"sms","path":"...","source":"dev"}

### 2. Autoload do plugin

    php -r "
    require '/path/framework/vendor/autoload.php';
    require '/path/plugin/vendor/autoload.php';
    var_dump(class_exists('Beaver\\Plugin\\PluginBase'));
    var_dump(class_exists('Beaver\\Plugins\\Sms\\SmsPlugin'));
    var_dump(class_exists('Beaver\\Plugins\\Sms\\Services\\SmsService'));
    "

Esperado: três `bool(true)`.

### 3. i18n carregado

    php -r "
    require '/path/framework/vendor/autoload.php';
    \$app = new \Beaver\Foundation\Application('/path/framework');
    \$app->boot();
    echo __('sms::title'), PHP_EOL;
    echo __('sms::bulk.summary', ['total'=>3,'success'=>2,'failed'=>1]), PHP_EOL;
    "

Esperado:

    SMS
    O SMS foi enviado a 3 números. Sucesso: 2, Falhas: 1

### 4. Envio real

    curl -s -X POST http://localhost:9000/sms/send-list \
      -H "Content-Type: application/json" \
      -d '{"phones":["+3519XXXXXXXX"],"smsText":"teste"}'

Esperado: `success: true`, e SMS no telemóvel.

---

## 20. Permissions — verificação de rede fica em stand-by

O `plugin.json` declara isto:

    "permissions": {
        "network": {
            "outbound": [
                { "host": "dashboard.ez4uteam.com", "port": 443, "protocol": "https" }
            ]
        }
    }

**Estado atual:** esta secção é **decorativa**. O Beaver não a lê
(confirmado com `grep -rn "permissions\|network" src/` — sem resultado).
O plugin pode aceder a qualquer host, mesmo que o `plugin.json` diga o
contrário.

### O que está planeado (stand-by)

Quando o framework crescer (mais plugins, mais hosts, mais risco),
implementar uma classe `NetworkPolicy` em:

    beaver-framework/src/Plugin/Permissions/NetworkPolicy.php

Responsabilidades:

- Lê `manifest['permissions']['network']['outbound']`.
- Faz `parse_url()` do URL que o plugin quer chamar.
- Verifica `host`, `port`, `protocol` contra as regras.
- Suporta wildcard `*.ez4uteam.com` (não apanha o domínio raiz).
- Lança `PermissionDenied` se não houver match.

### Decisão importante: começar em "modo observação"

Não ligar o bloqueio logo. Começar com:

    BEAVER_ENFORCE_PERMISSIONS=false

Neste modo:

- A verificação **corre**.
- Os pedidos que seriam bloqueados são **registados em log**.
- Nada é bloqueado de facto.

Só depois de validar que as regras estão certas (via logs), passa-se a:

    BEAVER_ENFORCE_PERMISSIONS=true

### Porque não implementar agora

- Só há **um** plugin (SMS).
- O plugin fala com **um** host (`dashboard.ez4uteam.com`).
- Zero risco real neste momento.

Fica registado. Quando fizer sentido, faz-se.

### Lembrete para quando acontecer

- [ ] Não esquecer de atualizar o `NOTES.md` quando a `NetworkPolicy` existir.
- [ ] Confirmar o comportamento do wildcard (interpretação de `*`).
- [ ] Decidir política default para plugins **sem** `permissions` declaradas
      (recomendação: **permitir** — para não partir nada).
- [ ] Documentar no README do framework como se declara permissões num plugin.


## 21. Commits com mensagens desatualizadas

O commit `1923cb6` diz "Screenshots - sms reception on my mobile"
mas na verdade só alterou `.gitignore` e `NOTES.md.bak-*`. Os
screenshots só foram adicionados depois (commit seguinte).

**Lição:** `git add .` apanha tudo o que estiver no diretório — incluindo
coisas que não quero (backups, lixo de sistema). Antes de um `git add .`,
confiro sempre com:

    git status --short

E leio a lista antes de escrever a mensagem do commit. A mensagem
tem de descrever o que o commit faz, não o que eu *pensei* que ia fazer.