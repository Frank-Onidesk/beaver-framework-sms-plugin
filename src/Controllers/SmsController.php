<?php

namespace Beaver\Plugins\Sms\Controllers;

use Beaver\Foundation\Application;
use Beaver\Http\Request;
use Beaver\Http\Response;
use Beaver\Plugins\Sms\Repositories\CsmsRepository;
use Beaver\Plugins\Sms\Services\SmsService;
use Beaver\Plugin\PluginBase;

class SmsController
{
    public function __construct(private PluginBase $plugin)
    {
    }

    /**
     * POST /sms/send-list
     *
     * Equivalent of EspoCRM's SimpleSms::postActionSendList().
     * Sends the same text to every phone number and records each attempt.
     *
     * Two modes:
     *  - bulk (default): creates a new CSMS per phone number.
     *  - single-record + csmsId: updates one existing CSMS instead
     *    of creating new ones.
     */
    public function sendList(): Response
    {
        $app  = Application::getInstance();
        $req  = Request::capture();
        $data = $req->all();

        try {
            // ---- 1. input ----
            $phones         = (array) ($data['phones'] ?? []);
            $smsText        = trim((string) ($data['smsText'] ?? ''));
            $templateId     = $data['templateId'] ?? null;
            $parentType     = $data['parentType'] ?? null;
            $parentName     = $data['parentName'] ?? null;
            $isSingleRecord = (bool) ($data['isSingleRecord'] ?? false);
            $csmsId         = $data['csmsId'] ?? null;

            if ($phones === []) {
                return Response::json(['error' => __('sms::error.no_phones')], 400);
            }
            if ($smsText === '') {
                return Response::json(['error' => __('sms::error.no_text')], 400);
            }

            // ---- 2. dependências ----
            /** @var SmsService $sms */
            $sms = $app->make(SmsService::class);

            /** @var CsmsRepository $csmsRepo */
            $csmsRepo = $app->make(CsmsRepository::class);

            // ---- 3. single-record + csmsId → atualizar em vez de criar ----
            $singleCsms = null;
            if ($isSingleRecord && $csmsId) {
                $singleCsms = $csmsRepo->find((string) $csmsId);
                if ($singleCsms === null) {
                    return Response::json([
                        'error' => "CSMS record not found: $csmsId",
                    ], 400);
                }
                $csmsRepo->update($singleCsms, [
                    'phoneNumberCustom' => $phones[0] ?? null,
                    'smsText'           => $smsText,
                    'parentType'        => $parentType,
                    'parentName'        => $parentName,
                    'smsTemplateId'     => $templateId,
                    'status'            => 'Planned',
                    'dateStart'         => date('Y-m-d H:i:s'),
                ]);
            }

            // ---- 4. ciclo de envio ----
            $results   = [];
            $okCount   = 0;
            $failCount = 0;
            $csmsIds   = [];

            foreach ($phones as $index => $rawPhone) {
                $phone = trim((string) $rawPhone);

                // cria ou reutiliza CSMS
                if ($isSingleRecord && $singleCsms !== null) {
                    $csms = $singleCsms;
                } else {
                    $csms = $csmsRepo->create([
                        'phoneNumberCustom' => $phone,
                        'smsText'           => $smsText,
                        'smsTemplateId'     => $templateId,
                        'parentType'        => $parentType,
                        'parentName'        => $parentName,
                        'status'            => 'Planned',
                        'dateStart'         => date('Y-m-d H:i:s'),
                        'type'              => 'Bulk',
                    ]);
                }

                try {
                    // Passa o número original. O SmsService::normalizePhone()
                    // trata do '+', do '351' e da validação — não duplicar aqui.
                    $result = $sms->send([
                        'phone' => $phone,
                        'sms'   => $smsText,
                        'id'    => $csms['id'] ?? null,
                    ]);

                    $ok = ($result['data']['Result'] ?? null) === 'OK';

                    if ($ok) {
                        $okCount++;
                        $status  = 'Held';
                        $message = __('sms::sent.success', ['number' => $phone]);
                        $smsId   = $result['data']['LastSMSID'] ?? null;
                    } else {
                        $failCount++;
                        $status  = 'Not Held';
                        // ErrorDesc primeiro — é mais informativo que Result
                        // (que só diz "NOT OK").
                        $message = $result['data']['ErrorDesc']
                            ?? $result['data']['Result']
                            ?? __('sms::error.unknown');
                        $smsId   = null;
                    }

                    $csmsRepo->update($csms, [
                        'status'      => $status,
                        'dateSent'    => date('Y-m-d H:i:s'),
                        // O SmsService devolve 'data' (a resposta da EZ4U)
                        // e 'debug'. Aqui quero o 'data', não o 'debug'.
                        'description' => json_encode($result['data'] ?? null),
                    ]);

                    $csmsIds[] = $csms['id'] ?? null;

                    $results[] = array_filter([
                        'phone'      => $phone,
                        'status'     => $status,
                        'message'    => $message,
                        'smsId'      => $smsId,
                        'csmsId'     => $csms['id'] ?? null,
                        'recordId'   => $data['ids'][$index] ?? null,
                        'recordName' => $parentName,
                        'parentName' => $parentName,
                    ], fn ($v) => $v !== null);
                } catch (\Throwable $e) {
                    $failCount++;
                    error_log("[SMS] {$phone}: " . $e->getMessage());

                    $results[] = [
                        'phone'      => $phone,
                        'status'     => 'Not Held',
                        'message'    => $e->getMessage(),
                        'parentName' => $parentName,
                    ];
                }
            }

            // ---- 5. resposta ----
            $summary = __('sms::bulk.summary', [
                'total'   => count($phones),
                'success' => $okCount,
                'failed'  => $failCount,
            ]);

            return Response::json([
                'success' => true,
                'message' => $summary,
                'data'    => [
                    'total'   => count($phones),
                    'success' => $okCount,
                    'failed'  => $failCount,
                    'csmsIds' => $csmsIds,
                    'results' => $results,
                ],
            ]);
        } catch (\Throwable $e) {
            error_log('[SMS] BULK ERROR: ' . $e->getMessage());
            return Response::json([
                'error' => __('sms::error.processing') . ': ' . $e->getMessage(),
            ], 500);
        }
    }



    /**
 * GET /sms/test
 *
 * Mostra o formulário de teste. Só para dev.
 */
    public function form(): Response
    {
        $view = Application::getInstance()->make(\Beaver\View\View::class);

        $html = $view->render('sms::form', [
        'title' => __('sms::title'),
        'send'  => __('sms::send'),
        'hint'  => __('sms::form.hint'),
        ]);

        return new Response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

/**
 * POST /sms/test
 *
 * Envia UM SMS (o do formulário) e mostra o resultado em HTML.
 * Reutiliza o SmsService e o CsmsRepository — não duplica lógica
 * do sendList(), só simplifica (1 número, sem template/parent).
 */
    public function send(): Response
    {
        $app  = Application::getInstance();
        $req  = Request::capture();
        $data = $req->all();

        $phone  = trim((string) ($data['phone'] ?? ''));
        $sms    = trim((string) ($data['sms']   ?? ''));
        $result = null;
        $error  = null;

        if ($phone === '' || $sms === '') {
            $error = __('sms::error.no_text');
        } else {
            try {
                /** @var SmsService $smsService */
                $smsService = $app->make(SmsService::class);

                /** @var CsmsRepository $csmsRepo */
                $csmsRepo = $app->make(CsmsRepository::class);

                // Cria o CSMS antes do envio (igual ao sendList)
                $csms = $csmsRepo->create([
                'phoneNumberCustom' => $phone,
                'smsText'           => $sms,
                'status'            => 'Planned',
                'dateStart'         => date('Y-m-d H:i:s'),
                'type'              => 'Test',
                ]);

                // Envia
                $result = $smsService->send([
                    'phone' => $phone,
                    'sms'   => $sms,
                    'id'    => $csms['id'] ?? null,
                ]);

                $ok = ($result['data']['Result'] ?? null) === 'OK';

                // Atualiza o CSMS com o resultado
                $csmsRepo->update($csms, [
                    'status'      => $ok ? 'Held' : 'Not Held',
                    'dateSent'    => date('Y-m-d H:i:s'),
                    'description' => json_encode($result['data'] ?? null),
                ]);
            } catch (\Throwable $e) {
                error_log('[SMS] TEST ERROR: ' . $e->getMessage());
                $error = $e->getMessage();
            }
        }

        $view = $app->make(\Beaver\View\View::class);

        $html = $view->render('sms::result', [
        'title'  => __('sms::title'),
        'phone'  => $phone,
        'sms'    => $sms,
        'result' => $result,
        'error'  => $error,
        ]);

        return new Response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }
}
