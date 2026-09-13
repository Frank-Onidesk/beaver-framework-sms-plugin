<?php

/** @var \Beaver\Http\Router $router */
/** @var \Beaver\Plugins\Sms\SmsPlugin $plugin */

use Beaver\Http\Response;
use Beaver\Plugins\Sms\Controllers\SmsController;

// rota de diagnóstico
$router->get('/sms', function () use ($plugin) {
    return Response::json([
        'plugin'  => $plugin->name(),
        'version' => $plugin->version(),
        'slug'    => $plugin->slug(),
        'path'    => $plugin->path,
        'source'  => 'dev',
    ]);
});

// envio em massa — equivalente ao SimpleSms::postActionSendList
$router->post('/sms/send-list', function () use ($plugin) {
    return (new SmsController($plugin))->sendList();
});

/**
 * Se preferirmos usar [SmsController::class, 'sendList'] em vez de closure,
 * mantém-se o mesmo efeito. A closure dá-nos acesso ao $plugin,
 *  o que é útil para ler config, injetar o SmsService, etc.
 * $router->post('/sms/send-list', [SmsController::class, 'sendList']);
 */



// formulário de teste (dev only)
$router->get('/sms/test', function () use ($plugin) {
    return (new SmsController($plugin))->form();
});

$router->post('/sms/test', function () use ($plugin) {
    return (new SmsController($plugin))->send();
});
