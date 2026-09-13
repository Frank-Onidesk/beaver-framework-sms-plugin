<?php

/**
 * View: sms::form
 *
 * Formulário de teste para envio de um SMS.
 * Só para dev — usar /sms/send-list para a versão de produção.
 *
 * Variáveis:
 * @var string $title
 * @var string $send
 * @var string $hint
 */
?>
<!DOCTYPE html>
<html lang="pt">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($title) ?></title>
    <style>
        body { font-family: system-ui, -apple-system, sans-serif;
               max-width: 520px; margin: 3rem auto; padding: 0 1.25rem;
               color: #222; line-height: 1.5; }
        h1 { margin: 0 0 1.5rem; font-size: 1.5rem; }
        label { display: block; margin-top: 1rem; font-weight: 600;
                font-size: .95rem; }
        input, textarea { width: 100%; padding: .55rem .75rem;
                          font: inherit; border: 1px solid #ccc;
                          border-radius: 6px; box-sizing: border-box; }
        input:focus, textarea:focus { outline: 2px solid #005cbf;
                                       outline-offset: 1px;
                                       border-color: #005cbf; }
        textarea { resize: vertical; min-height: 5rem; }
        .hint { color: #666; font-size: .85rem; margin: .25rem 0 0; }
        button { margin-top: 1.25rem; padding: .65rem 1.4rem;
                 font: inherit; font-weight: 600;
                 background: #005cbf; color: #fff; border: 0;
                 border-radius: 6px; cursor: pointer; }
        button:hover { background: #004799; }
        button:active { transform: translateY(1px); }
    </style>
</head>
<body>

    <h1><?= htmlspecialchars($title) ?></h1>

    <form method="post" action="/sms/test">
        <label for="phone">Número</label>
        <input type="text" id="phone" name="phone"
               placeholder="+351912345678" autofocus required>
        <p class="hint">Com ou sem +351. O serviço normaliza.</p>

        <label for="sms">Mensagem</label>
        <textarea id="sms" name="sms" rows="4"
                  placeholder="Mensagem de teste do sistema."
                  required></textarea>
        <p class="hint"><?= htmlspecialchars($hint) ?></p>

        <button type="submit"><?= htmlspecialchars($send) ?></button>
    </form>

</body>
</html>