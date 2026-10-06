<?php
/**
 * Endpoint público: recebe o formulário de interesse e grava o lead no MySQL.
 * Aceita requisição via fetch (resposta JSON) ou envio tradicional (redireciona).
 */
require __DIR__ . '/../app/bootstrap.php';

security_headers(false);
header('X-Robots-Tag: noindex');

$wantsJson = stripos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false
    || !empty($_SERVER['HTTP_X_REQUESTED_WITH']);

function lead_reply(bool $wantsJson, array $data, int $code = 200): void
{
    if ($wantsJson) {
        json_response($data, $code);
    }
    if (!empty($data['ok'])) {
        redirect(url('?enviado=1#contato'));
    }
    http_response_code($code);
    header('Content-Type: text/html; charset=utf-8');
    $msg = $data['message'] ?? 'Não foi possível enviar.';
    $list = '';
    foreach (($data['errors'] ?? []) as $err) {
        $list .= '<li>' . e($err) . '</li>';
    }
    echo '<!doctype html><html lang="pt-BR"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
       . '<title>Verifique seus dados</title><body style="font-family:sans-serif;max-width:560px;margin:10vh auto;padding:0 20px;line-height:1.6">'
       . '<h1 style="font-weight:400">Verifique seus dados</h1><p>' . e($msg) . '</p><ul>' . $list . '</ul>'
       . '<p><a href="' . e(url('#contato')) . '">Voltar ao formulário</a></p></body></html>';
    exit;
}

if (!request_is_post()) {
    header('Allow: POST');
    lead_reply($wantsJson, ['ok' => false, 'message' => 'Método não permitido.'], 405);
}

// Robôs: campo invisível preenchido → responde "ok" sem gravar.
if (!empty($_POST['company_fax'])) {
    lead_reply($wantsJson, ['ok' => true]);
}

$tokenProblem = lead_form_token_problem((string) ($_POST['form_token'] ?? ''));
if ($tokenProblem === 'too_fast') {
    lead_reply($wantsJson, ['ok' => false, 'message' => 'Envio muito rápido. Aguarde alguns segundos e tente novamente.'], 429);
}
if ($tokenProblem !== '') {
    lead_reply($wantsJson, [
        'ok' => false,
        'message' => 'Sua sessão nesta página expirou. Clique em enviar novamente.',
        'token' => lead_form_token(),
    ], 400);
}

[$data, $errors] = validate_lead($_POST);
if ($errors) {
    lead_reply($wantsJson, ['ok' => false, 'message' => 'Verifique os campos destacados.', 'errors' => $errors], 422);
}

if (leads_from_ip_last_hour() >= 10) {
    lead_reply($wantsJson, ['ok' => false, 'message' => 'Recebemos muitos envios a partir da sua conexão. Tente novamente mais tarde.'], 429);
}

try {
    $result = create_lead($data);
} catch (Throwable $e) {
    error_log('[lead] ' . $e->getMessage());
    lead_reply($wantsJson, ['ok' => false, 'message' => 'Não foi possível registrar seu interesse agora. Tente novamente em instantes.'], 500);
}

lead_reply($wantsJson, ['ok' => true, 'duplicate' => $result['duplicate']]);
