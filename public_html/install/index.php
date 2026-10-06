<?php
/**
 * Assistente de instalação — cria o PRIMEIRO administrador.
 *
 * Segurança:
 *  - só funciona enquanto NÃO existir nenhum administrador no banco;
 *  - exige o "setup_token" definido em config/config.php (fora do Git);
 *  - a senha é gravada somente como hash (password_hash);
 *  - após concluir, EXCLUA a pasta /install do servidor.
 */
define('ADMIN_PUBLIC_PAGE', true);
require __DIR__ . '/../app/admin.php';

$checks = [];
$checks[] = ['PHP 8.0 ou superior', version_compare(PHP_VERSION, '8.0.0', '>='), 'Versão atual: ' . PHP_VERSION];
$checks[] = ['Extensão PDO MySQL', extension_loaded('pdo_mysql'), ''];
$checks[] = ['Extensão GD (processamento de imagens)', extension_loaded('gd'), ''];
$checks[] = ['Extensão Fileinfo (validação de uploads)', extension_loaded('fileinfo'), ''];
$checks[] = ['Pasta uploads/ com permissão de escrita', is_dir(UPLOAD_DIR) && is_writable(UPLOAD_DIR), 'Ajuste para 755 no Gerenciador de Arquivos'];

$dbOk = false;
$tablesOk = false;
$dbError = '';
try {
    db();
    $dbOk = true;
    $missing = [];
    foreach (['admins', 'leads', 'property', 'gallery', 'settings', 'login_attempts'] as $t) {
        if (!db_value('SHOW TABLES LIKE ' . db()->quote($t))) {
            $missing[] = $t;
        }
    }
    $tablesOk = !$missing;
    if ($missing) {
        $dbError = 'Tabelas ausentes: ' . implode(', ', $missing) . '. Importe o arquivo database.sql no phpMyAdmin.';
    }
} catch (Throwable $e) {
    $dbError = 'Não foi possível conectar ao MySQL. Confira host, nome do banco, usuário e senha em config/config.php.';
}
$checks[] = ['Conexão com o banco de dados', $dbOk, $dbOk ? '' : $dbError];
$checks[] = ['Tabelas do banco importadas', $tablesOk, $tablesOk ? '' : $dbError];

$token = (string) config('setup_token', '');
$tokenOk = strlen($token) >= 24;
$checks[] = ['setup_token definido no config.php (mín. 24 caracteres)', $tokenOk, ''];

$hasAdmin = $tablesOk && admin_count() > 0;
$ready = !in_array(false, array_column($checks, 1), true);

$errors = [];
$done = false;
$email = '';

if ($ready && !$hasAdmin && request_is_post()) {
    csrf_verify();
    $email = mb_strtolower(input_str($_POST, 'email', 190));
    $password = (string) ($_POST['password'] ?? '');
    $confirm = (string) ($_POST['password_confirm'] ?? '');
    $sentToken = (string) ($_POST['setup_token'] ?? '');

    if (login_lock_remaining('install') > 0) {
        $errors[] = 'Muitas tentativas inválidas. Aguarde alguns minutos.';
    } elseif (!hash_equals($token, $sentToken)) {
        record_login_attempt('install', false);
        $errors[] = 'Token de instalação incorreto.';
    } else {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Informe um e-mail válido.';
        }
        if ($problem = password_problem($password)) {
            $errors[] = $problem;
        }
        if ($password !== $confirm) {
            $errors[] = 'A confirmação não confere com a senha.';
        }
        if (!$errors) {
            // Revalida dentro da transação para evitar dois cadastros simultâneos.
            $pdo = db();
            $pdo->beginTransaction();
            if ((int) db_value('SELECT COUNT(*) FROM admins FOR UPDATE') === 0) {
                db_exec('INSERT INTO admins (email, password_hash, created_at, updated_at) VALUES (?, ?, ?, ?)', [$email, hash_password($password), now(), now()]);
                $pdo->commit();
                app_secret();
                $done = true;
                $hasAdmin = true;
            } else {
                $pdo->rollBack();
                $hasAdmin = true;
            }
        }
    }
}
?><!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Instalação · Painel da Propriedade</title>
<link rel="stylesheet" href="<?= e(asset('assets/css/admin.css')) ?>">
</head>
<body class="login-page">
<main class="login-card install-card">
    <p class="sidebar-kicker">Instalação</p>
    <h1>Primeiro administrador</h1>

    <?php if ($done): ?>
        <div class="flash flash-success">Administrador criado com sucesso.</div>
        <p><strong>Importante:</strong> exclua agora a pasta <code>install/</code> do servidor (Gerenciador de Arquivos da Hostinger)
           e, se quiser, apague o valor de <code>setup_token</code> no <code>config/config.php</code>.</p>
        <p><a class="btn btn-primary btn-block" href="<?= e(url('admin/login.php')) ?>">Ir para o login</a></p>
    <?php elseif ($hasAdmin): ?>
        <div class="flash flash-info">Já existe um administrador cadastrado. Este assistente está desativado.</div>
        <p>Por segurança, exclua a pasta <code>install/</code> do servidor.</p>
        <p><a class="btn btn-primary btn-block" href="<?= e(url('admin/login.php')) ?>">Ir para o login</a></p>
    <?php else: ?>
        <ul class="checklist">
            <?php foreach ($checks as [$label, $ok, $hint]): ?>
                <li class="<?= $ok ? 'ok' : 'fail' ?>">
                    <span><?= $ok ? '✓' : '✕' ?></span>
                    <div><?= e($label) ?><?php if (!$ok && $hint): ?><br><small><?= e($hint) ?></small><?php endif; ?></div>
                </li>
            <?php endforeach; ?>
        </ul>

        <?php if (!$ready): ?>
            <div class="flash flash-error">Corrija os itens acima e recarregue a página.</div>
        <?php else: ?>
            <?php foreach ($errors as $err): ?><div class="flash flash-error"><?= e($err) ?></div><?php endforeach; ?>
            <form method="post" class="stack" autocomplete="off">
                <?= csrf_field() ?>
                <label class="field"><span>Token de instalação (o mesmo do config.php)</span>
                    <input type="password" name="setup_token" required autocomplete="off"></label>
                <label class="field"><span>E-mail do administrador</span>
                    <input type="email" name="email" value="<?= e($email) ?>" required autocomplete="username"></label>
                <label class="field"><span>Senha (mín. 10 caracteres, letras e números)</span>
                    <input type="password" name="password" required minlength="10" autocomplete="new-password"></label>
                <label class="field"><span>Confirmar senha</span>
                    <input type="password" name="password_confirm" required minlength="10" autocomplete="new-password"></label>
                <button type="submit" class="btn btn-primary btn-block">Criar administrador</button>
            </form>
        <?php endif; ?>
    <?php endif; ?>
</main>
</body>
</html>
