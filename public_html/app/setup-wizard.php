<?php
/**
 * Assistente de primeira instalação.
 *
 * Exibido automaticamente enquanto config/config.php NÃO existir:
 *  1. testa a conexão com o banco MySQL informado;
 *  2. cria as tabelas (app/schema.sql) se ainda não existirem;
 *  3. cria o primeiro administrador (senha gravada só como hash);
 *  4. grava config/config.php — a partir daí este assistente deixa de existir.
 */
defined('APP') or exit;
require APP_DIR . '/lib/auth.php';

header('Content-Type: text/html; charset=utf-8');
header('X-Robots-Tag: noindex, nofollow');
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');

// Requisições de API (formulário público) não devem cair no assistente.
if (strpos($_SERVER['SCRIPT_NAME'] ?? '', '/api/') !== false) {
    http_response_code(503);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok' => false, 'message' => 'Site em configuração.']);
    exit;
}

session_name('PROPSETUP');
session_start();
if (empty($_SESSION['setup_csrf'])) {
    $_SESSION['setup_csrf'] = bin2hex(random_bytes(32));
}

$configFile = APP_ROOT . '/config/config.php';
$errors = [];
$done = false;
$adminExisted = false;
$v = [
    'db_host' => 'localhost', 'db_name' => '', 'db_user' => '', 'db_pass' => '',
    'email' => '', 'site_url' => '',
];

$checks = [
    ['PHP 8.0 ou superior (atual: ' . PHP_VERSION . ')', version_compare(PHP_VERSION, '8.0.0', '>=')],
    ['Extensão PDO MySQL', extension_loaded('pdo_mysql')],
    ['Extensão GD (imagens)', extension_loaded('gd')],
    ['Extensão Fileinfo (uploads)', extension_loaded('fileinfo')],
    ['Pasta config/ com permissão de escrita', is_writable(APP_ROOT . '/config')],
    ['Pasta uploads/ com permissão de escrita', is_dir(UPLOAD_DIR) && is_writable(UPLOAD_DIR)],
];
$envOk = !in_array(false, array_column($checks, 1), true);

/** Executa o schema.sql comando a comando. */
function setup_import_schema(PDO $pdo): void
{
    $sql = (string) file_get_contents(APP_DIR . '/schema.sql');
    $sql = preg_replace('/^\s*--.*$/m', '', $sql);
    foreach (preg_split('/;\s*(\r?\n|$)/', $sql) as $stmt) {
        if (trim($stmt) !== '') {
            $pdo->exec($stmt);
        }
    }
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['setup_wizard'])) {
    foreach (['db_host', 'db_name', 'db_user', 'email', 'site_url'] as $k) {
        $v[$k] = trim((string) ($_POST[$k] ?? ''));
    }
    $v['db_pass'] = (string) ($_POST['db_pass'] ?? '');
    $password = (string) ($_POST['password'] ?? '');
    $confirm = (string) ($_POST['password_confirm'] ?? '');

    if (!hash_equals($_SESSION['setup_csrf'], (string) ($_POST['csrf'] ?? ''))) {
        $errors[] = 'A página expirou. Recarregue e tente novamente.';
    } elseif (!$envOk) {
        $errors[] = 'Corrija os itens do servidor marcados com ✕ antes de continuar.';
    } elseif (is_file($configFile)) {
        $errors[] = 'O site já foi configurado.';
    }

    if (!$errors) {
        if ($v['db_name'] === '' || $v['db_user'] === '') {
            $errors[] = 'Informe o nome do banco e o usuário do banco.';
        }
        if (!filter_var($v['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Informe um e-mail válido para o administrador.';
        }
        if ($problem = password_problem($password)) {
            $errors[] = $problem;
        }
        if ($password !== $confirm) {
            $errors[] = 'A confirmação não confere com a senha do administrador.';
        }
        if ($v['site_url'] !== '' && !preg_match('~^https?://[^/\s]+$~i', rtrim($v['site_url'], '/'))) {
            $errors[] = 'Endereço do site inválido. Use o formato https://seudominio.com.br (ou deixe em branco).';
        }
    }

    $pdo = null;
    if (!$errors) {
        try {
            $pdo = new PDO(
                sprintf('mysql:host=%s;port=3306;dbname=%s;charset=utf8mb4', $v['db_host'], $v['db_name']),
                $v['db_user'],
                $v['db_pass'],
                [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES => false, PDO::ATTR_TIMEOUT => 8]
            );
        } catch (Throwable $e) {
            $code = $e instanceof PDOException ? (string) $e->getCode() : '';
            if ($code === '1045') {
                $errors[] = 'Usuário ou senha do banco incorretos. Confira em hPanel → Bancos de dados (o usuário tem um prefixo, ex.: u123456789_admin).';
            } elseif ($code === '1044' || $code === '1049') {
                $errors[] = 'Banco não encontrado ou sem permissão. Confira o nome completo (com prefixo, ex.: u123456789_propriedade) e se o usuário está vinculado a esse banco.';
            } else {
                $errors[] = 'Não foi possível conectar ao MySQL (' . mb_substr($e->getMessage(), 0, 160) . '). Na Hostinger o host normalmente é "localhost".';
            }
        }
    }

    if (!$errors && $pdo) {
        try {
            if (!$pdo->query("SHOW TABLES LIKE 'property'")->fetchColumn()) {
                setup_import_schema($pdo);
            }
            $email = mb_strtolower($v['email']);
            $count = (int) $pdo->query('SELECT COUNT(*) FROM admins')->fetchColumn();
            if ($count === 0) {
                $st = $pdo->prepare('INSERT INTO admins (email, password_hash, created_at, updated_at) VALUES (?, ?, NOW(), NOW())');
                $st->execute([$email, hash_password($password)]);
            } else {
                $adminExisted = true;
            }

            $config = [
                'db' => [
                    'host' => $v['db_host'], 'port' => 3306, 'name' => $v['db_name'],
                    'user' => $v['db_user'], 'password' => $v['db_pass'], 'charset' => 'utf8mb4',
                ],
                'base_url' => rtrim($v['site_url'], '/'),
                'setup_token' => '',
                'timezone' => 'America/Sao_Paulo',
                'upload_max_mb' => 15,
                'login_max_attempts' => 5,
                'login_max_ip_attempts' => 15,
                'login_lock_minutes' => 15,
                'session_idle_minutes' => 120,
                'debug' => false,
            ];
            $php = "<?php\n// Gerado pelo assistente de instalação em " . date('d/m/Y H:i') . ".\n"
                 . "// Contém a senha do banco: não compartilhe e não envie ao Git.\n"
                 . 'return ' . var_export($config, true) . ";\n";
            if (file_put_contents($configFile, $php, LOCK_EX) === false) {
                throw new RuntimeException('Não foi possível gravar config/config.php. Verifique a permissão da pasta config/ (755).');
            }
            @chmod($configFile, 0640);
            $done = true;
            unset($_SESSION['setup_csrf']);
        } catch (Throwable $e) {
            $errors[] = 'Erro ao preparar o banco: ' . mb_substr($e->getMessage(), 0, 200);
        }
    }
}

$base = base_path();
?><!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>Instalação · Painel da Propriedade</title>
<style>
:root { --ink:#141a17; --muted:#6f6a60; --line:#e2ddd3; --bg:#f4f2ee; --ok:#2f6b4a; --err:#a23b2a; --accent:#a88a5c; }
* { box-sizing:border-box; }
body { margin:0; padding:40px 16px; background:var(--ink); color:var(--ink); font:15px/1.55 system-ui,-apple-system,"Segoe UI",Roboto,Arial,sans-serif; }
.card { max-width:560px; margin:0 auto; background:#fff; border-radius:10px; padding:32px 28px; }
.kicker { font-size:11px; letter-spacing:.2em; text-transform:uppercase; color:var(--accent); margin:0; }
h1 { font-family:Georgia,serif; font-weight:400; font-size:30px; margin:4px 0 8px; }
h2 { font-size:13px; letter-spacing:.1em; text-transform:uppercase; color:var(--muted); margin:28px 0 12px; }
p.lead { color:var(--muted); margin:0 0 20px; }
ul.checks { list-style:none; padding:0; margin:0; font-size:14px; }
ul.checks li { padding:6px 0; border-bottom:1px solid var(--line); }
ul.checks .ok b { color:var(--ok); } ul.checks .fail b { color:var(--err); }
label { display:block; margin-bottom:14px; }
label span { display:block; font-size:13px; font-weight:600; color:#3d453f; margin-bottom:5px; }
label small { display:block; font-weight:400; color:var(--muted); margin-top:4px; font-size:12px; }
input { width:100%; font:inherit; font-size:16px; padding:11px 12px; border:1px solid #cfc9bd; border-radius:6px; }
input:focus { outline:none; border-color:var(--ink); box-shadow:0 0 0 3px rgba(20,26,23,.12); }
.row { display:grid; grid-template-columns:1fr 1fr; gap:0 12px; }
@media (max-width:520px) { .row { grid-template-columns:1fr; } }
button, .btn { display:block; width:100%; margin-top:8px; padding:14px; border:0; border-radius:6px; background:var(--ink); color:#fff; font:inherit; font-weight:600; cursor:pointer; text-align:center; text-decoration:none; }
.msg { padding:12px 14px; border-radius:6px; margin:0 0 16px; font-size:14px; border-left:3px solid; }
.msg.err { background:#fbeeeb; border-color:var(--err); color:#7c2a1c; }
.msg.ok { background:#eaf4ee; border-color:var(--ok); color:#234f37; }
.msg.info { background:#f4efe4; border-color:var(--accent); color:#5b4a30; }
code { background:#f1eee8; padding:1px 5px; border-radius:4px; }
</style>
</head>
<body>
<main class="card">
    <p class="kicker">Instalação</p>
    <h1>Configurar o site</h1>

<?php if ($done): ?>
    <div class="msg ok">Tudo pronto! Banco configurado<?= $adminExisted ? '' : ' e administrador criado' ?>.</div>
    <?php if ($adminExisted): ?>
        <div class="msg info">Este banco já tinha um administrador cadastrado — entre com o e-mail e a senha dele.</div>
    <?php endif; ?>
    <p>Próximos passos:</p>
    <ol>
        <li>Entre no painel e cadastre as imagens e informações da propriedade.</li>
        <li>Envie um teste pelo formulário do site e confira em <strong>Leads</strong>.</li>
    </ol>
    <a class="btn" href="<?= e($base . '/admin/login.php') ?>">Entrar no painel</a>
    <p style="text-align:center;margin-top:14px"><a href="<?= e($base . '/') ?>">Ver o site</a></p>
<?php else: ?>
    <p class="lead">Preencha os dados abaixo uma única vez. O sistema cria as tabelas, o seu acesso de administrador e o arquivo de configuração automaticamente.</p>

    <?php foreach ($errors as $err): ?><div class="msg err"><?= e($err) ?></div><?php endforeach; ?>

    <?php if (!$envOk): ?>
        <h2>Servidor</h2>
        <ul class="checks">
            <?php foreach ($checks as [$label, $ok]): ?>
                <li class="<?= $ok ? 'ok' : 'fail' ?>"><b><?= $ok ? '✓' : '✕' ?></b> <?= e($label) ?></li>
            <?php endforeach; ?>
        </ul>
        <div class="msg err" style="margin-top:16px">Corrija os itens marcados com ✕ (Gerenciador de Arquivos → permissões 755; extensões em hPanel → Avançado → Configuração do PHP) e recarregue a página.</div>
    <?php endif; ?>

    <form method="post" autocomplete="off">
        <input type="hidden" name="setup_wizard" value="1">
        <input type="hidden" name="csrf" value="<?= e($_SESSION['setup_csrf']) ?>">

        <h2>1. Banco de dados MySQL</h2>
        <p class="lead" style="margin-bottom:14px;font-size:14px">Crie em <strong>hPanel → Bancos de dados → Gerenciamento de banco de dados MySQL</strong> e copie os nomes completos (com o prefixo <code>u123456789_</code>).</p>
        <label><span>Host do banco</span>
            <input name="db_host" value="<?= e($v['db_host']) ?>" required>
            <small>Na Hostinger normalmente é <code>localhost</code>.</small></label>
        <label><span>Nome do banco</span>
            <input name="db_name" value="<?= e($v['db_name']) ?>" placeholder="u123456789_propriedade" required></label>
        <div class="row">
            <label><span>Usuário do banco</span>
                <input name="db_user" value="<?= e($v['db_user']) ?>" placeholder="u123456789_admin" required></label>
            <label><span>Senha do banco</span>
                <input name="db_pass" type="password" value="<?= e($v['db_pass']) ?>" autocomplete="new-password"></label>
        </div>

        <h2>2. Seu acesso ao painel</h2>
        <label><span>E-mail do administrador</span>
            <input name="email" type="email" value="<?= e($v['email']) ?>" required autocomplete="username"></label>
        <div class="row">
            <label><span>Senha do painel</span>
                <input name="password" type="password" required minlength="10" autocomplete="new-password">
                <small>Mín. 10 caracteres, com letras e números.</small></label>
            <label><span>Confirmar senha</span>
                <input name="password_confirm" type="password" required minlength="10" autocomplete="new-password"></label>
        </div>

        <h2>3. Endereço do site (opcional)</h2>
        <label><span>URL do site</span>
            <input name="site_url" value="<?= e($v['site_url']) ?>" placeholder="https://seudominio.com.br">
            <small>Pode deixar em branco: o endereço é detectado automaticamente.</small></label>

        <button type="submit"<?= $envOk ? '' : ' disabled' ?>>Instalar</button>
    </form>
<?php endif; ?>
</main>
</body>
</html>
