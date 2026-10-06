<?php
defined('APP') or exit;

/** Hash de senha seguro (bcrypt/argon conforme o padrão do PHP). */
function hash_password(string $password): string
{
    return password_hash($password, PASSWORD_DEFAULT);
}

/** Regras mínimas de senha administrativa. Retorna mensagem de erro ou ''. */
function password_problem(string $password): string
{
    if (mb_strlen($password) < 10) {
        return 'A senha deve ter pelo menos 10 caracteres.';
    }
    if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
        return 'A senha deve conter letras e números.';
    }
    return '';
}

function admin_count(): int
{
    return (int) db_value('SELECT COUNT(*) FROM admins');
}

/** Minutos restantes de bloqueio para este e-mail/IP (0 = liberado). */
function login_lock_remaining(string $email): int
{
    $window   = (int) config('login_lock_minutes', 15);
    $maxEmail = (int) config('login_max_attempts', 5);
    $maxIp    = (int) config('login_max_ip_attempts', 15);
    $since    = date('Y-m-d H:i:s', time() - $window * 60);

    $byEmail = db_one(
        'SELECT COUNT(*) AS n, MAX(created_at) AS last FROM login_attempts
         WHERE email = ? AND success = 0 AND created_at > ?',
        [mb_strtolower($email), $since]
    );
    $byIp = db_one(
        'SELECT COUNT(*) AS n, MAX(created_at) AS last FROM login_attempts
         WHERE ip_address = ? AND success = 0 AND created_at > ?',
        [client_ip(), $since]
    );

    $last = null;
    if ((int) $byEmail['n'] >= $maxEmail) {
        $last = $byEmail['last'];
    }
    if ((int) $byIp['n'] >= $maxIp) {
        $last = max((string) $last, (string) $byIp['last']);
    }
    if (!$last) {
        return 0;
    }
    $remaining = (strtotime($last) + $window * 60) - time();
    return $remaining > 0 ? (int) ceil($remaining / 60) : 0;
}

function record_login_attempt(string $email, bool $success): void
{
    db_exec(
        'INSERT INTO login_attempts (ip_address, email, success, created_at) VALUES (?, ?, ?, ?)',
        [client_ip(), mb_substr(mb_strtolower($email), 0, 190), $success ? 1 : 0, now()]
    );
    // Limpeza ocasional de registros antigos
    if (random_int(1, 50) === 1) {
        db_exec('DELETE FROM login_attempts WHERE created_at < ?', [date('Y-m-d H:i:s', time() - 86400 * 30)]);
    }
}

/**
 * Tenta autenticar. Retorna ['ok' => bool, 'error' => string].
 * Toda a validação acontece no servidor.
 */
function attempt_login(string $email, string $password): array
{
    $email = mb_strtolower(trim($email));
    $lock = login_lock_remaining($email);
    if ($lock > 0) {
        return ['ok' => false, 'error' => "Muitas tentativas. Tente novamente em {$lock} minuto(s)."];
    }

    $admin = db_one('SELECT id, email, password_hash FROM admins WHERE email = ?', [$email]);

    // Mesmo sem usuário, executa verificação para manter tempo de resposta semelhante.
    $hash = $admin['password_hash'] ?? '$2y$10$QOrDnIkJnu8ZeNBXlQ/3c.jFax4a23arYoPG9nb0F2dAq6otGbBky';
    $valid = password_verify($password, $hash) && $admin !== null;

    record_login_attempt($email, $valid);

    if (!$valid) {
        usleep(random_int(200000, 500000));
        return ['ok' => false, 'error' => 'E-mail ou senha inválidos.'];
    }

    if (password_needs_rehash($admin['password_hash'], PASSWORD_DEFAULT)) {
        db_exec('UPDATE admins SET password_hash = ? WHERE id = ?', [hash_password($password), $admin['id']]);
    }
    db_exec('UPDATE admins SET last_login_at = ? WHERE id = ?', [now(), $admin['id']]);

    session_regenerate_id(true);
    $_SESSION['admin_id']    = (int) $admin['id'];
    $_SESSION['admin_email'] = $admin['email'];
    $_SESSION['last_seen']   = time();
    $_SESSION['ua']          = hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? '');
    unset($_SESSION['csrf_token']);

    return ['ok' => true, 'error' => ''];
}

function logout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires'  => time() - 42000,
            'path'     => $p['path'],
            'secure'   => $p['secure'],
            'httponly' => true,
            'samesite' => $p['samesite'] ?? 'Lax',
        ]);
    }
    session_destroy();
}

/** Admin logado (ou null). Verifica expiração por inatividade e o navegador. */
function current_admin(): ?array
{
    if (empty($_SESSION['admin_id'])) {
        return null;
    }
    $idle = (int) config('session_idle_minutes', 120) * 60;
    if (time() - (int) ($_SESSION['last_seen'] ?? 0) > $idle
        || !hash_equals((string) ($_SESSION['ua'] ?? ''), hash('sha256', $_SERVER['HTTP_USER_AGENT'] ?? ''))) {
        logout();
        start_secure_session();
        return null;
    }
    $admin = db_one('SELECT id, email, last_login_at, created_at FROM admins WHERE id = ?', [(int) $_SESSION['admin_id']]);
    if (!$admin) {
        logout();
        start_secure_session();
        return null;
    }
    $_SESSION['last_seen'] = time();
    return $admin;
}

/** Exige login: redireciona para a tela de login se não autenticado. */
function require_admin(): array
{
    $admin = current_admin();
    if (!$admin) {
        flash('info', 'Faça login para continuar.');
        redirect(url('admin/login.php'));
    }
    return $admin;
}
