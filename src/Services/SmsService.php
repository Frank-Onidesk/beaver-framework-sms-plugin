<?php

/**
 * Envio de SMS via EZ4U.
 *
 * Adaptado do meu SmsService do EspoCRM para o plugin "sms" do Beaver.
 *
 * Notas rápidas:
 *  - Aqui só ENVIO. Não gravo nada na BD, não falo com BD, não sei o que é HTTP.
 *    Quem grava o estado na BD é o controller.
 *  - Os parâmetros da EZ4U chegam todos pelo construtor. Vêm do
 *    SmsPlugin::registerServices(), que os vai buscar à config do plugin
 *    ou ao .env. Assim este serviço é puro e testável.
 *  - O licensekey NÃO é a password de acesso à plataforma EZ4U!
 *    É a que está no menu "APIs" do backoffice. Já perdi tempo com isto
 *    uma vez, por isso deixo aqui o aviso.
 */

namespace Beaver\Plugins\Sms\Services;

class SmsService
{
    // ---- Parâmetros da API EZ4U ----
    protected string $account;      // Nome da conta (ex: Onidesk)
    protected string $endpoint;     // URL completa da API
    protected string $licensekey;   // Password da API (menu "APIs" do EZ4U)

    // ---- Parâmetros opcionais com defaults seguros ----
    protected string $alfaSender = 'Onidesk';  // Remetente, max 11 chars, SEM acentos
    protected int $envio24 = 1;                 // 1 = permite envio 24h, 0 = bloqueia noturno
    protected int $ttl = 24;                    // Horas de validade (1-48)

    // ---- Dados do SMS em curso ----
    protected ?string $startDate = null;
    protected ?string $phone = null;
    protected ?string $sms = null;

    public function __construct(
        string $endpoint,
        string $account,
        string $licensekey,
        string $alfaSender = 'Onidesk',
        int $envio24 = 1,
        int $ttl = 24,
    ) {
        $this->endpoint   = $endpoint;
        $this->account    = $account;
        $this->licensekey = $licensekey;
        $this->alfaSender = $alfaSender;
        $this->envio24    = $envio24;
        $this->ttl        = $ttl;
    }

    /**
     * Envia um SMS.
     *
     * Espera um array com 'phone' e 'sms'. O 'id' (opcional) é ignorado
     * aqui de propósito — quem trata do CSMS é o controller.
     *
     * Lança InvalidArgumentException se o input for inválido, e deixa
     * o RuntimeException vir do cURL se houver falha de rede. O controller
     * apanha e traduz para HTTP.
     */
    public function send(array $data): array
    {
        // 1. Campos obrigatórios
        $this->phone = (string) ($data['phone'] ?? '');
        $this->sms   = (string) ($data['sms']   ?? '');

        if ($this->phone === '') {
            throw new \InvalidArgumentException('Invalid phone number format');
        }
        if ($this->sms === '' || strlen($this->sms) > 999) {
            throw new \InvalidArgumentException(
                'Message text must be between 1 and 999 characters'
            );
        }

        // 2. Normalizar o telefone (tirei isto do Espo, funcionava bem)
        $this->phone = $this->normalizePhone($this->phone);

        // 3. Parâmetros opcionais — valido sempre, porque um valor errado
        //    aqui faz a EZ4U rejeitar o SMS com um erro nada óbvio.
        if (strlen($this->alfaSender) > 11) {
            $this->alfaSender = substr($this->alfaSender, 0, 11);
            error_log('[SMS] alfaSender truncated to: ' . $this->alfaSender);
        }

        if ($this->ttl < 1 || $this->ttl > 48) {
            $this->ttl = 24;
            error_log('[SMS] TTL adjusted to default 24h');
        }

        if (!in_array($this->envio24, [0, 1], true)) {
            $this->envio24 = 1;
            error_log('[SMS] envio24 adjusted to default 1');
        }

        // 4. Data/hora de envio. A timezone já foi definida no Application,
        //    por isso não volto a mexer nela aqui (o Espo fazia-o, mas era
        //    um side-effect chato).
        $this->startDate = date('Y-m-d H:i:s');

        // 5. Enviar
        $raw      = $this->cUrl();
        $response = json_decode((string) $raw, true);

        error_log('[SMS] API Response: ' . $raw);

        return [
            'data'  => is_array($response) ? $response : [],
            'debug' => [
                'account'    => $this->account,
                'endpoint'   => $this->endpoint,
                'licenseKey' => substr($this->licensekey, 0, 4) . '****',  // só mostro os primeiros 4
                'startDate'  => $this->startDate,
                'phone'      => $this->phone,
                'sms'        => $this->sms,
                'alfaSender' => $this->alfaSender,
                'envio24'    => $this->envio24,
                'TTL'        => $this->ttl,
            ],
        ];
    }

    /**
     * Normaliza o número para o formato que a EZ4U espera.
     *
     * Regras que mantive do Espo (e que estão a funcionar):
     *  - tiro o "+" e tudo o que não seja dígito
     *  - se for um telemóvel PT (9 dígitos a começar por 9), prefixo 351
     *  - se já não começar por 351, prefixo 351 também
     *  - no fim, valido com 9 a 15 dígitos
     *
     * Extraí isto para método próprio porque quero testá-lo isolado.
     */
    protected function normalizePhone(string $phone): string
    {
        $phone = ltrim($phone, '+');
        $phone = preg_replace('/[^0-9]/', '', $phone) ?? '';

        if ($phone === '') {
            throw new \InvalidArgumentException('Invalid phone number format');
        }

        if (strlen($phone) === 9 && $phone[0] === '9') {
            $phone = '351' . $phone;
        } elseif (!str_starts_with($phone, '351')) {
            $phone = '351' . $phone;
        }

        if (!preg_match('/^[0-9]{9,15}$/', $phone)) {
            throw new \InvalidArgumentException('Invalid phone number format');
        }

        error_log('[SMS] normalised phone: ' . $phone);

        return $phone;
    }

    /**
     * POST à EZ4U em application/x-www-form-urlencoded.
     *
     * Diferente do Espo:
     *  - em vez de devolver false em erro, devolvo um JSON com Result=NOT OK
     *    e o erro lá dentro. Assim o controller só tem um formato de resposta
     *    para tratar — seja sucesso, erro EZ4U, ou erro cURL.
     *  - escondo sempre o licensekey no log (preg_replace).
     *  - timeout 30s, SSL verificado. Em dev, se precisares, pões
     *    VERIFYPEER a false — mas em prod deixa estar a true.
     */
    protected function cUrl(): string
    {
        $postFields = http_build_query([
            'account'     => $this->account,
            'licensekey'  => $this->licensekey,
            'phoneNumber' => $this->phone,
            'messageText' => $this->sms,
            'startDate'   => $this->startDate,
            'alfaSender'  => $this->alfaSender,
            'TTL'         => $this->ttl,
            'envio24'     => $this->envio24,
        ]);

        // Log dos parâmetros com a chave escondida. Já me aconteceu mandar
        // isto para um ticket sem reparar, por isso agora faço-o sempre.
        $debug = preg_replace('/licensekey=[^&]+/', 'licensekey=HIDDEN', $postFields);
        error_log('[SMS] cURL params: ' . $debug);

        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL            => $this->endpoint,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING       => '',
            CURLOPT_MAXREDIRS      => 10,
            CURLOPT_TIMEOUT        => 30,     // no Espo tínhamos 0, o que é má ideia
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST  => 'POST',
            CURLOPT_POSTFIELDS     => $postFields,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/x-www-form-urlencoded',
            ],
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_VERBOSE        => false,
        ]);

        $raw = curl_exec($ch);

        if (curl_errno($ch)) {
            $err = curl_error($ch);
            $no  = curl_errno($ch);
            curl_close($ch);

            error_log("[SMS] cURL error $no: $err");

            // Devolvo erro estruturado em vez de rebentar — o controller
            // trata isto como "Not Held" sem precisar de try/catch extra.
            return json_encode([
                'Result'    => 'NOT OK',
                'ErrorCode' => -100,
                'ErrorDesc' => "cURL Error: $err",
            ]) ?: '{}';
        }

        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        error_log('[SMS] HTTP response code: ' . $httpCode);

        return (string) $raw;
    }
}
