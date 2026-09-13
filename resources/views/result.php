<?php
/**
 * View: sms::result
 *
 * Mostra o resultado de um envio de teste.
 *
 * Variáveis:
 * @var string      $title
 * @var string      $phone
 * @var string      $sms
 * @var array|null  $result   (retorno de SmsService::send)
 * @var string|null $error
 */
$data   = $result['data']  ?? [];
$debug  = $result['debug'] ?? [];
$ok     = ($data['Result'] ?? null) === 'OK';
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($title) ?> — resultado</title>
    <style>
        body { font-family: system-ui, -apple-system, sans-serif;
               max-width: 720px; margin: 3rem auto; padding: 0 1.25rem;
               color: #222; line-height: 1.5; }
        h1 { margin: 0 0 1.5rem; font-size: 1.5rem; }
        .card { border: 1px solid #ddd; border-radius: 8px;
                padding: 1rem 1.25rem; margin-top: 1rem;
                background: #fafafa; }
        .ok   { border-color: #2e7d32; background: #e8f5e9; }
        .fail { border-color: #c62828; background: #ffebee; }
        dl    { margin: 0; display: grid;
                grid-template-columns: 140px 1fr; gap: .25rem 1rem; }
        dt    { font-weight: 600; }
        dd    { margin: 0; word-break: break-all; }
        a     { display: inline-block; margin-top: 1.5rem;
                color: #005cbf; text-decoration: none; font-weight: 600; }
        a:hover { text-decoration: underline; }
        .section-title { margin: 1rem 0 .5rem; font-weight: 600;
                         font-size: 1rem; }
    </style>
</head>
<body>

    <h1><?= htmlspecialchars($title) ?> — resultado</h1>

    <?php if ($error) : ?>
        <div class="card fail">
            <strong>Erro:</strong> <?= htmlspecialchars($error) ?>
        </div>
    <?php else : ?>
        <div class="card <?= $ok ? 'ok' : 'fail' ?>">
            <strong><?= $ok ? 'Enviado com sucesso' : 'Envio falhou' ?></strong>
            <?php if (!empty($data['ErrorDesc'])) : ?>
                — <?= htmlspecialchars((string) $data['ErrorDesc']) ?>
            <?php endif; ?>
        </div>

        <div class="card">
            <div class="section-title">Pedido</div>
            <dl>
                <dt>Para</dt>
                <dd><?= htmlspecialchars($phone) ?></dd>

                <dt>Mensagem</dt>
                <dd><?= htmlspecialchars($sms) ?></dd>
            </dl>
        </div>

        <div class="card">
            <div class="section-title">Resposta EZ4U</div>
            <dl>
                <dt>Result</dt>
                <dd><?= htmlspecialchars((string) ($data['Result'] ?? '—')) ?></dd>

                <dt>LastSMSID</dt>
                <dd><?= htmlspecialchars((string) ($data['LastSMSID'] ?? '—')) ?></dd>

                <dt>ErrorDesc</dt>
                <dd><?= htmlspecialchars((string) ($data['ErrorDesc'] ?? '—')) ?></dd>
            </dl>
        </div>

        <?php if ($debug) : ?>
            <div class="card">
                <div class="section-title">Debug</div>
                <dl>
                    <?php foreach ($debug as $k => $v) : ?>
                        <dt><?= htmlspecialchars((string) $k) ?></dt>
                        <dd><?= htmlspecialchars(is_scalar($v) ? (string) $v : json_encode($v)) ?></dd>
                    <?php endforeach; ?>
                </dl>
            </div>
        <?php endif; ?>
    <?php endif; ?>

    <a href="/sms/test">← enviar outro</a>

</body>
</html>