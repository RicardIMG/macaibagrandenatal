<?php defined('APP') or exit;
$menu = [
    'dashboard' => ['Dashboard', 'admin/'],
    'leads'     => ['Leads', 'admin/leads.php'],
    'property'  => ['Propriedade', 'admin/property.php'],
    'images'    => ['Imagens', 'admin/images.php'],
    'settings'  => ['Configurações', 'admin/settings.php'],
];
$newLeads = (int) db_value("SELECT COUNT(*) FROM leads WHERE status = 'novo'");
?><!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow, noarchive">
<title><?= e($title) ?> · Painel da Propriedade</title>
<link rel="icon" type="image/svg+xml" href="<?= e(asset('assets/img/favicon.svg')) ?>">
<link rel="stylesheet" href="<?= e(asset('assets/css/admin.css')) ?>">
</head>
<body class="admin">
<div class="admin-shell">
    <aside class="sidebar" data-sidebar>
        <div class="sidebar-head">
            <span class="sidebar-kicker">Painel da</span>
            <strong>Propriedade</strong>
        </div>
        <nav class="sidebar-nav" aria-label="Menu do painel">
            <?php foreach ($menu as $key => [$label, $href]): ?>
                <a href="<?= e(url($href)) ?>" class="<?= $active === $key ? 'is-active' : '' ?>">
                    <?= e($label) ?>
                    <?php if ($key === 'leads' && $newLeads > 0): ?><span class="pill"><?= $newLeads ?></span><?php endif; ?>
                </a>
            <?php endforeach; ?>
            <form method="post" action="<?= e(url('admin/logout.php')) ?>" class="logout-form">
                <?= csrf_field() ?>
                <button type="submit">Sair</button>
            </form>
        </nav>
        <div class="sidebar-foot">
            <a href="<?= e(url('')) ?>" target="_blank" rel="noopener">Ver site ↗</a>
            <?php if ($admin): ?><span class="muted small"><?= e($admin['email']) ?></span><?php endif; ?>
        </div>
    </aside>
    <div class="main">
        <header class="topbar">
            <button type="button" class="menu-toggle" data-menu-toggle aria-label="Abrir menu">☰</button>
            <h1><?= e($title) ?></h1>
        </header>
        <div class="content">
            <?php foreach (take_flashes() as $f): ?>
                <div class="flash flash-<?= e($f['type']) ?>" role="status"><?= e($f['message']) ?></div>
            <?php endforeach; ?>
