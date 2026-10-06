<?php
defined('APP') or exit;

/** Escapa texto para saída HTML. */
function e($value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Caminho base onde o site está instalado (ex.: "" na raiz, "/site" em subpasta). */
function base_path(): string
{
    static $base = null;
    if ($base !== null) {
        return $base;
    }
    $configured = trim((string) config('base_url', ''));
    if ($configured !== '') {
        $path = (string) parse_url($configured, PHP_URL_PATH);
        return $base = rtrim($path, '/');
    }
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    $file   = realpath($_SERVER['SCRIPT_FILENAME'] ?? '') ?: '';
    $root   = realpath(APP_ROOT) ?: APP_ROOT;
    if ($file !== '' && strpos($file, $root) === 0) {
        $rel = str_replace('\\', '/', substr($file, strlen($root))); // ex.: /admin/index.php
        if ($rel !== '' && substr($script, -strlen($rel)) === $rel) {
            $base = rtrim(substr($script, 0, -strlen($rel)), '/');
            // Repositório inteiro no servidor: o .htaccess da raiz reescreve
            // internamente para /public_html, que não aparece na URL visitada.
            $reqPath = (string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
            if ($base !== '' && $reqPath !== $base && strpos($reqPath, $base . '/') !== 0) {
                $base = (string) preg_replace('~/public_html$~', '', $base);
            }
            return $base;
        }
    }
    return $base = '';
}

/** URL relativa à raiz do site. */
function url(string $path = ''): string
{
    return base_path() . '/' . ltrim($path, '/');
}

/** URL de um arquivo estático com "cache busting" pela data de modificação. */
function asset(string $path): string
{
    $file = APP_ROOT . '/' . ltrim($path, '/');
    $v = is_file($file) ? (string) filemtime($file) : '1';
    return url($path) . '?v=' . $v;
}

/** URL de arquivo enviado (caminho salvo no banco, ex.: "uploads/abc.jpg"). */
function media_url(?string $path): string
{
    if (!$path) {
        return '';
    }
    return url($path);
}

function is_https(): bool
{
    if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
        return true;
    }
    if (($_SERVER['SERVER_PORT'] ?? '') === '443') {
        return true;
    }
    $proto = strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? ''));
    return $proto === 'https';
}

/** URL absoluta do site (para canonical, Open Graph etc.). */
function site_url(string $path = ''): string
{
    $configured = rtrim(trim((string) config('base_url', '')), '/');
    if ($configured !== '') {
        return $configured . '/' . ltrim($path, '/');
    }
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $host = preg_replace('/[^A-Za-z0-9.\-:\[\]]/', '', $host);
    return (is_https() ? 'https' : 'http') . '://' . $host . url($path);
}

function redirect(string $to, int $code = 303): void
{
    header('Location: ' . $to, true, $code);
    exit;
}

function client_ip(): string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : '0.0.0.0';
}

function now(): string
{
    return date('Y-m-d H:i:s');
}

function request_is_post(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

/** Lê string de um array (POST/GET), removendo caracteres de controle e espaços extras. */
function input_str(array $src, string $key, int $max = 255, bool $multiline = false): string
{
    $v = $src[$key] ?? '';
    if (!is_string($v)) {
        return '';
    }
    $v = str_replace(["\r\n", "\r"], "\n", $v);
    $v = $multiline
        ? preg_replace('/[^\P{C}\n\t]/u', '', $v)
        : preg_replace('/[\p{C}]/u', ' ', $v);
    $v = trim((string) $v);
    if (!$multiline) {
        $v = preg_replace('/\s{2,}/u', ' ', $v);
    }
    return mb_substr((string) $v, 0, $max);
}

function json_response(array $data, int $code = 200): void
{
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/** Converte texto com quebras de linha em parágrafos HTML seguros. */
function paragraphs(?string $text): string
{
    $text = trim((string) $text);
    if ($text === '') {
        return '';
    }
    $blocks = preg_split("/\n\s*\n/", $text);
    $html = '';
    foreach ($blocks as $b) {
        $html .= '<p>' . nl2br(e(trim($b)), false) . '</p>';
    }
    return $html;
}

/** Converte texto "um item por linha" em array (ignorando linhas vazias). */
function lines(?string $text): array
{
    $out = [];
    foreach (preg_split('/\n/', (string) $text) as $line) {
        $line = trim(ltrim(trim($line), "-•*·"));
        if ($line !== '') {
            $out[] = $line;
        }
    }
    return $out;
}

function filled(?string $v): bool
{
    return trim((string) $v) !== '';
}

/** Mensagens "flash" (exibidas uma única vez após redirecionamento). */
function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function take_flashes(): array
{
    $f = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $f;
}

/** Status possíveis de um lead. */
function lead_statuses(): array
{
    return [
        'novo'              => 'Novo',
        'contato_realizado' => 'Contato realizado',
        'qualificado'       => 'Qualificado',
        'em_negociacao'     => 'Em negociação',
        'sem_interesse'     => 'Sem interesse',
        'venda_concluida'   => 'Venda concluída',
    ];
}

function status_label(string $key): string
{
    return lead_statuses()[$key] ?? $key;
}

/**
 * Número apenas com dígitos no formato internacional para o link do WhatsApp.
 * Números sem "+" e com 10 ou 11 dígitos são considerados brasileiros (DDD + número).
 */
function whatsapp_digits(string $phone): string
{
    $phone = trim($phone);
    $hasPlus = strpos($phone, '+') === 0 || strpos($phone, '00') === 0;
    $digits = preg_replace('/\D+/', '', $phone);
    if (strpos($phone, '00') === 0) {
        $digits = substr($digits, 2);
    }
    if (!$hasPlus) {
        $digits = ltrim($digits, '0');
        if (strlen($digits) === 10 || strlen($digits) === 11) {
            $digits = '55' . $digits;
        }
    }
    return $digits;
}

function whatsapp_link(string $phone): string
{
    return 'https://wa.me/' . whatsapp_digits($phone);
}

/** Link clicável para o site/Instagram informado pelo lead (ou '' se não for possível). */
function website_link(string $value): string
{
    $v = trim($value);
    if ($v === '') {
        return '';
    }
    if (preg_match('/^@([A-Za-z0-9._]{1,30})$/', $v, $m)) {
        return 'https://instagram.com/' . $m[1];
    }
    if (preg_match('~^https?://~i', $v)) {
        return filter_var($v, FILTER_VALIDATE_URL) ? $v : '';
    }
    if (preg_match('/^(instagram\.com|www\.instagram\.com)\//i', $v)) {
        return 'https://' . $v;
    }
    if (preg_match('/^[A-Za-z0-9\-]+(\.[A-Za-z0-9\-]+)+(\/\S*)?$/', $v)) {
        return 'https://' . $v;
    }
    return '';
}

function format_datetime(?string $dt, string $format = 'd/m/Y H:i'): string
{
    if (!$dt) {
        return '';
    }
    $ts = strtotime($dt);
    return $ts ? date($format, $ts) : '';
}

/** Cabeçalhos de segurança comuns. */
function security_headers(bool $admin = false): void
{
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
    if ($admin) {
        header('X-Frame-Options: DENY');
        header('X-Robots-Tag: noindex, nofollow, noarchive');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header("Content-Security-Policy: default-src 'self'; img-src 'self' data: blob:; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; script-src 'self'; frame-ancestors 'none'; form-action 'self'; base-uri 'self'");
    } else {
        header('X-Frame-Options: SAMEORIGIN');
        header("Content-Security-Policy: default-src 'self'; img-src 'self' data: https:; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; script-src 'self'; connect-src 'self'; frame-ancestors 'self'; form-action 'self'; base-uri 'self'");
    }
}
