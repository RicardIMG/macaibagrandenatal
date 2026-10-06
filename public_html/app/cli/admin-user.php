<?php
/**
 * Utilitário de linha de comando (SSH) para criar administrador ou redefinir senha.
 * Uso (na pasta public_html):
 *   php app/cli/admin-user.php email@dominio.com.br
 * A senha é solicitada de forma interativa (não fica no histórico do terminal).
 */
if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit;
}
require __DIR__ . '/../bootstrap.php';

$email = mb_strtolower(trim($argv[1] ?? ''));
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "Uso: php app/cli/admin-user.php email@dominio.com.br\n");
    exit(1);
}

function prompt_hidden(string $label): string
{
    fwrite(STDOUT, $label);
    if (DIRECTORY_SEPARATOR === '/') {
        system('stty -echo');
        $v = trim((string) fgets(STDIN));
        system('stty echo');
        fwrite(STDOUT, "\n");
        return $v;
    }
    return trim((string) fgets(STDIN));
}

$password = getenv('ADMIN_PASSWORD') ?: prompt_hidden('Nova senha: ');
$confirm  = getenv('ADMIN_PASSWORD') ?: prompt_hidden('Confirme a senha: ');
if ($password !== $confirm) {
    fwrite(STDERR, "As senhas não conferem.\n");
    exit(1);
}
if ($problem = password_problem($password)) {
    fwrite(STDERR, $problem . "\n");
    exit(1);
}

$existing = db_value('SELECT id FROM admins WHERE email = ?', [$email]);
if ($existing) {
    db_exec('UPDATE admins SET password_hash = ? WHERE id = ?', [hash_password($password), $existing]);
    db_exec('DELETE FROM login_attempts WHERE email = ?', [$email]);
    echo "Senha redefinida para {$email}.\n";
} else {
    db_exec('INSERT INTO admins (email, password_hash, created_at, updated_at) VALUES (?, ?, ?, ?)', [$email, hash_password($password), now(), now()]);
    echo "Administrador {$email} criado.\n";
}
