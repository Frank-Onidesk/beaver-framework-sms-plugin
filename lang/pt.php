<?php

/**
 * Traduções do plugin SMS (Português).
 *
 * As chaves são resolvidas como: __('sms::<chave>')
 * Os placeholders usam a sintaxe ":nome", por exemplo ':number', ':reason'.
 * Formas plurais usam "singular|plural", escolhidas por Translator::choice() / _n().
 */

return [
    // geral
    'title'          => 'SMS',
    'send'           => 'Enviar SMS',
    'sending'        => 'A enviar…',

    // estados
    'status.sent'    => 'Enviado',
    'status.failed'  => 'Falhou',
    'status.pending' => 'Pendente',

    // mensagens
    'sent.success'   => 'SMS enviado para :number.',
    'sent.failed'    => 'Falha ao enviar SMS: :reason',
    'invalid.number' => 'Número inválido: :number',

    // contagens
    'recipients.count' => 'Tens :count destinatário|Tens :count destinatários',

    // erros
    'error.no_phones'  => 'Nenhum número de telefone fornecido.',
    'error.no_text'    => 'Nenhum texto de SMS fornecido.',
    'error.unknown'    => 'Erro desconhecido do serviço SMS.',
    'error.processing' => 'Erro ao processar o envio',

    // envio em lote
    'bulk.summary' => 'O SMS foi enviado a :total números. Sucesso: :success, Falhas: :failed',

    // formulário de teste
    'form.hint' => 'Até 999 caracteres.',
];
