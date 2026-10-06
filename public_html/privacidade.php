<?php
/** Página de Política de Privacidade (texto editável em Admin → Configurações). */
require __DIR__ . '/app/bootstrap.php';

security_headers(false);
header('Content-Type: text/html; charset=utf-8');

$external = setting('privacy_url');
if ($external !== '') {
    redirect($external, 302);
}

$siteName = setting('site_name', 'Propriedade');
$text = setting('privacy_text');
if ($text === '') {
    $text = <<<TXT
Esta página descreve como tratamos os dados pessoais enviados por meio do formulário de interesse deste site, em conformidade com a Lei Geral de Proteção de Dados (Lei nº 13.709/2018 — LGPD).

## Controlador dos dados
[A DEFINIR — nome / razão social, CNPJ ou CPF e endereço do responsável pelos dados.]

## Quais dados coletamos
Nome, WhatsApp, e-mail, nome da empresa, site ou Instagram da empresa e o texto informado sobre o seu interesse. Também registramos dados técnicos do envio (data e hora, página de origem, parâmetros de campanha como utm_source, endereço IP e navegador), usados para segurança e para entender por qual canal você nos encontrou.

## Para que usamos
Exclusivamente para analisar o seu interesse e entrar em contato a respeito desta oportunidade imobiliária. Não vendemos nem compartilhamos seus dados para fins de marketing de terceiros.

## Base legal
Consentimento do titular, manifestado ao enviar o formulário, e legítimo interesse para a segurança do site.

## Por quanto tempo guardamos
Pelo tempo necessário à negociação desta oportunidade ou até que você solicite a exclusão. [A DEFINIR — prazo de retenção, se houver.]

## Seus direitos
Você pode solicitar a qualquer momento a confirmação, o acesso, a correção ou a exclusão dos seus dados, bem como revogar o consentimento.

## Contato
[A DEFINIR — e-mail ou canal para solicitações relacionadas a dados pessoais.]
TXT;
}

$html = '';
foreach (preg_split("/\n\s*\n/", trim($text)) as $block) {
    $block = trim($block);
    if (strpos($block, '## ') === 0) {
        $lines = explode("\n", $block, 2);
        $html .= '<h2>' . e(substr($lines[0], 3)) . '</h2>';
        if (isset($lines[1]) && trim($lines[1]) !== '') {
            $html .= '<p>' . nl2br(e(trim($lines[1])), false) . '</p>';
        }
    } else {
        $html .= '<p>' . nl2br(e($block), false) . '</p>';
    }
}
$favicon = setting('favicon');
?><!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Política de Privacidade · <?= e($siteName) ?></title>
<meta name="robots" content="noindex, follow">
<?php if ($favicon): ?><link rel="icon" type="image/png" href="<?= e(media_url($favicon)) ?>"><?php else: ?><link rel="icon" type="image/svg+xml" href="<?= e(asset('assets/img/favicon.svg')) ?>"><?php endif; ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;500&family=Inter:wght@300;400;500&display=swap">
<link rel="stylesheet" href="<?= e(asset('assets/css/site.css')) ?>">
</head>
<body class="page-legal">
<header class="site-header is-solid">
    <div class="container header-inner">
        <a class="brand" href="<?= e(url('')) ?>"><?= e($siteName) ?></a>
        <nav class="nav"><a href="<?= e(url('')) ?>" class="nav-cta">Voltar ao site</a></nav>
    </div>
</header>
<main class="section legal">
    <div class="container legal-inner">
        <p class="section-label">Privacidade</p>
        <h1 class="section-title">Política de Privacidade</h1>
        <div class="prose"><?= $html ?></div>
    </div>
</main>
<footer class="site-footer">
    <div class="container footer-inner">
        <p class="footer-brand"><?= e($siteName) ?></p>
        <div class="footer-links"><span>© <?= date('Y') ?></span></div>
    </div>
</footer>
</body>
</html>
