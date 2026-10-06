<?php
/**
 * Inicialização das páginas do painel administrativo.
 * Uso: require __DIR__ . '/../app/admin.php';  → $admin fica disponível.
 */
require __DIR__ . '/bootstrap.php';

start_secure_session();
security_headers(true);
header('Content-Type: text/html; charset=utf-8');

if (!defined('ADMIN_PUBLIC_PAGE')) {
    $admin = require_admin();
}

/** Renderiza o topo do layout do painel. */
function admin_header(string $title, string $active = ''): void
{
    $admin = current_admin();
    require APP_DIR . '/views/admin/header.php';
}

function admin_footer(): void
{
    require APP_DIR . '/views/admin/footer.php';
}
