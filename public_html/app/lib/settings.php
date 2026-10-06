<?php
defined('APP') or exit;

/** Todas as configurações (tabela settings), com cache por requisição. */
function settings(bool $refresh = false): array
{
    static $cache = null;
    if ($cache === null || $refresh) {
        $cache = [];
        foreach (db_all('SELECT setting_key, setting_value FROM settings') as $row) {
            $cache[$row['setting_key']] = (string) $row['setting_value'];
        }
    }
    return $cache;
}

function setting(string $key, string $default = ''): string
{
    $all = settings();
    return isset($all[$key]) && $all[$key] !== '' ? $all[$key] : $default;
}

function set_setting(string $key, string $value): void
{
    db_exec(
        'INSERT INTO settings (setting_key, setting_value) VALUES (?, ?)
         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)',
        [$key, $value]
    );
    settings(true);
}

/** Chave secreta da aplicação (gerada automaticamente e guardada no banco). */
function app_secret(): string
{
    $secret = setting('app_secret');
    if (strlen($secret) < 32) {
        $secret = bin2hex(random_bytes(32));
        set_setting('app_secret', $secret);
    }
    return $secret;
}
