<?php

/**
 * SMS plugin translations (English).
 *
 * Keys are resolved as: __('sms::<key>')
 * Placeholders use the ":name" syntax, e.g. ':number', ':reason'.
 * Plural forms use "singular|plural", picked by Translator::choice() / _n().
 */

return [
    // general
    'title'          => 'SMS',
    'send'           => 'Send SMS',
    'sending'        => 'Sending…',

    // status
    'status.sent'    => 'Sent',
    'status.failed'  => 'Failed',
    'status.pending' => 'Pending',

    // messages
    'sent.success'   => 'SMS sent to :number.',
    'sent.failed'    => 'Failed to send SMS: :reason',
    'invalid.number' => 'Invalid number: :number',

    // counts
    'recipients.count' => 'You have :count recipient|You have :count recipients',

    // errors
    'error.no_phones'  => 'No phone numbers provided.',
    'error.no_text'    => 'No SMS text provided.',
    'error.unknown'    => 'Unknown error from the SMS service.',
    'error.processing' => 'Error processing the request',

    // bulk
    'bulk.summary' => 'SMS sent to :total numbers. Success: :success, Failed: :failed',

     // formulário de teste
    'form.hint' => 'Up to 999 characters.',
];
