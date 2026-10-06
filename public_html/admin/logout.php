<?php
define('ADMIN_PUBLIC_PAGE', true);
require __DIR__ . '/../app/admin.php';

if (request_is_post()) {
    csrf_verify();
    logout();
    start_secure_session();
    flash('success', 'Você saiu do painel.');
}
redirect(url('admin/login.php'));
