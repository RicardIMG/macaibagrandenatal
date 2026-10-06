<?php
define('ADMIN_PUBLIC_PAGE', true);
require __DIR__ . '/../app/admin.php';

if (current_admin()) {
    redirect(url('admin/'));
}

$error = '';
$email = '';

if (request_is_post()) {
    csrf_verify();
    $email = input_str($_POST, 'email', 190);
    $password = (string) ($_POST['password'] ?? '');
    if ($email === '' || $password === '') {
        $error = 'Informe e-mail e senha.';
    } elseif (strlen($password) > 1024) {
        $error = 'E-mail ou senha inválidos.';
    } else {
        $result = attempt_login($email, $password);
        if ($result['ok']) {
            redirect(url('admin/'));
        }
        $error = $result['error'];
    }
}

$noAdmin = admin_count() === 0;
$flashes = take_flashes();
?><!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow, noarchive">
<title>Acesso · Painel da Propriedade</title>
<link rel="icon" type="image/svg+xml" href="<?= e(asset('assets/img/favicon.svg')) ?>">
<link rel="stylesheet" href="<?= e(asset('assets/css/admin.css')) ?>">
</head>
<body class="login-page">
<main class="login-card">
    <p class="sidebar-kicker">Painel da</p>
    <h1>Propriedade</h1>

    <?php if ($noAdmin): ?>
        <div class="flash flash-info">Nenhum administrador cadastrado ainda. Siga o passo “Criar o primeiro administrador” do INSTALL.md.</div>
    <?php endif; ?>
    <?php if ($error): ?><div class="flash flash-error" role="alert"><?= e($error) ?></div><?php endif; ?>

    <form method="post" action="<?= e(url('admin/login.php')) ?>" class="stack" autocomplete="on">
        <?= csrf_field() ?>
        <label class="field">
            <span>E-mail</span>
            <input type="email" name="email" value="<?= e($email) ?>" required autocomplete="username" autofocus>
        </label>
        <label class="field">
            <span>Senha</span>
            <input type="password" name="password" required autocomplete="current-password">
        </label>
        <button type="submit" class="btn btn-primary btn-block">Entrar</button>
    </form>
    <p class="login-back"><a href="<?= e(url('')) ?>">← Voltar ao site</a></p>
</main>
</body>
</html>
