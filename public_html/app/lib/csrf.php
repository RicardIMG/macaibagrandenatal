<?php
defined('APP') or exit;

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

/** Interrompe a requisição se o token CSRF for inválido. */
function csrf_verify(): void
{
    $sent = $_POST['csrf_token'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    if (!is_string($sent) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $sent)) {
        http_response_code(403);
        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><meta charset="utf-8"><title>Sessão expirada</title>'
           . '<p style="font-family:sans-serif;padding:2rem">Sua sessão expirou ou a requisição é inválida. '
           . '<a href="' . e(url('admin/')) . '">Voltar ao painel</a>.</p>';
        exit;
    }
}
