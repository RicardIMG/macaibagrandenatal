<?php
/**
 * Inicialização comum a todas as páginas e endpoints.
 */
declare(strict_types=1);

define('APP', true);
define('APP_ROOT', dirname(__DIR__));          // pasta pública (public_html/...)
define('APP_DIR', __DIR__);
define('UPLOAD_DIR', APP_ROOT . '/uploads');

$configFile = APP_ROOT . '/config/config.php';
if (!is_file($configFile)) {
    http_response_code(503);
    header('Content-Type: text/html; charset=utf-8');
    echo '<!doctype html><meta charset="utf-8"><title>Configuração pendente</title>'
       . '<p style="font-family:sans-serif;padding:2rem">Configuração pendente: crie o arquivo '
       . '<code>config/config.php</code> a partir de <code>config/config.sample.php</code> (veja INSTALL.md).</p>';
    exit;
}

$GLOBALS['config'] = require $configFile;

date_default_timezone_set(config('timezone', 'America/Sao_Paulo'));
mb_internal_encoding('UTF-8');

if (config('debug', false)) {
    ini_set('display_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    error_reporting(E_ALL);
}

require APP_DIR . '/lib/helpers.php';
require APP_DIR . '/lib/db.php';
require APP_DIR . '/lib/settings.php';
require APP_DIR . '/lib/session.php';
require APP_DIR . '/lib/csrf.php';
require APP_DIR . '/lib/auth.php';
require APP_DIR . '/lib/property.php';
require APP_DIR . '/lib/leads.php';
require APP_DIR . '/lib/images.php';

set_exception_handler(function (Throwable $e) {
    error_log('[app] ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
    if (!headers_sent()) {
        http_response_code(500);
    }
    if (config('debug', false)) {
        echo '<pre>' . htmlspecialchars((string) $e, ENT_QUOTES, 'UTF-8') . '</pre>';
    } else {
        echo 'Ocorreu um erro inesperado. Tente novamente em instantes.';
    }
});

function config(string $key, $default = null)
{
    $value = $GLOBALS['config'] ?? [];
    foreach (explode('.', $key) as $part) {
        if (!is_array($value) || !array_key_exists($part, $value)) {
            return $default;
        }
        $value = $value[$part];
    }
    return $value;
}
