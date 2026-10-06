<?php
/**
 * Landing page pública da propriedade.
 * Todo o conteúdo vem do banco de dados e é editável em /admin.
 */
require __DIR__ . '/app/bootstrap.php';

security_headers(false);
header('Content-Type: text/html; charset=utf-8');

$p        = property();
$gallery  = gallery_items();
$facts    = property_facts($p);
$chars    = lines($p['characteristics']);
$diffs    = lines($p['differentials']);
$siteName = setting('site_name', 'Propriedade');

$seoTitle = setting('seo_title', $p['headline'] ?: $siteName);
$seoDesc  = setting('seo_description', $p['subheadline']);
$canonical = setting('canonical_url', site_url(''));
$ogImage  = setting('og_image') ?: ($p['hero_image'] ?: '');
$ogImageUrl = $ogImage ? site_url($ogImage) : '';
$favicon  = setting('favicon');
$privacyUrl = setting('privacy_url') ?: url('privacidade.php');
$noindex  = setting('noindex_site') === '1';
$leadSent = isset($_GET['enviado']);

$heroImg   = $p['hero_image'] ? media_url($p['hero_image']) : asset('assets/img/hero-placeholder.svg');
$heroThumb = $p['hero_image_thumb'] ? media_url($p['hero_image_thumb']) : '';
$topoImg   = $p['topographic_image'] ? media_url($p['topographic_image']) : asset('assets/img/topographic-placeholder.svg');
$topoFull  = $p['topographic_full'] ? media_url($p['topographic_full']) : $topoImg;
$hasTopo   = $p['topographic_image'] !== '';

$hasDetails = $facts || $chars || $diffs || filled($p['additional_info']);
$year = date('Y');

// Destaca em dourado a área (ex.: "260.000 m²") quando ela aparece na headline.
$headlineHtml = e($p['headline']);
if (filled($p['area']) && mb_stripos($p['headline'], $p['area']) !== false) {
    $headlineHtml = str_ireplace(e($p['area']), '<span class="hl">' . e($p['area']) . '</span>', $headlineHtml);
}
?><!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<title><?= e($seoTitle) ?></title>
<meta name="description" content="<?= e($seoDesc) ?>">
<?php if ($noindex): ?><meta name="robots" content="noindex, nofollow"><?php else: ?><meta name="robots" content="index, follow"><?php endif; ?>

<link rel="canonical" href="<?= e($canonical) ?>">
<meta property="og:type" content="website">
<meta property="og:locale" content="pt_BR">
<meta property="og:site_name" content="<?= e($siteName) ?>">
<meta property="og:title" content="<?= e($seoTitle) ?>">
<meta property="og:description" content="<?= e($seoDesc) ?>">
<meta property="og:url" content="<?= e($canonical) ?>">
<?php if ($ogImageUrl): ?>
<meta property="og:image" content="<?= e($ogImageUrl) ?>">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:image" content="<?= e($ogImageUrl) ?>">
<?php endif; ?>
<meta name="twitter:title" content="<?= e($seoTitle) ?>">
<meta name="twitter:description" content="<?= e($seoDesc) ?>">
<meta name="theme-color" content="#141a17">

<?php if ($favicon): ?>
<link rel="icon" type="image/png" href="<?= e(media_url($favicon)) ?>">
<link rel="apple-touch-icon" href="<?= e(media_url($favicon)) ?>">
<?php else: ?>
<link rel="icon" type="image/svg+xml" href="<?= e(asset('assets/img/favicon.svg')) ?>">
<?php endif; ?>

<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;500;600&family=Inter:wght@300;400;500;600&display=swap">
<link rel="preload" as="image" href="<?= e($heroImg) ?>"<?php if ($heroThumb): ?> imagesrcset="<?= e($heroThumb) ?> 1280w, <?= e($heroImg) ?> 2560w" imagesizes="100vw"<?php endif; ?>>
<link rel="stylesheet" href="<?= e(asset('assets/css/site.css')) ?>">

<script type="application/ld+json"><?= json_encode([
    '@context' => 'https://schema.org',
    '@type' => 'WebPage',
    'name' => $seoTitle,
    'description' => $seoDesc,
    'url' => $canonical,
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG) ?></script>
</head>
<body>
<a class="skip-link" href="#conteudo">Pular para o conteúdo</a>

<header class="site-header" data-header>
    <div class="container header-inner">
        <a class="brand" href="#topo"><?= e($siteName) ?></a>
        <nav class="nav" aria-label="Navegação principal">
            <a href="#propriedade">A propriedade</a>
            <a href="#conheca-a-area">A área</a>
            <?php if ($gallery): ?><a href="#galeria">Galeria</a><?php endif; ?>
            <a href="#contato" class="nav-cta" data-scroll-to-form>Contato</a>
        </nav>
    </div>
</header>

<main id="conteudo">
    <!-- HERO -->
    <section class="hero" id="topo" aria-label="Apresentação">
        <div class="hero-media">
            <img src="<?= e($heroImg) ?>"
                 <?php if ($heroThumb): ?>srcset="<?= e($heroThumb) ?> 1280w, <?= e($heroImg) ?> 2560w" sizes="100vw"<?php endif; ?>
                 alt="<?= e($p['hero_image_alt'] ?: 'Vista da propriedade') ?>" fetchpriority="high" decoding="async">
        </div>
        <div class="hero-shade"></div>
        <div class="container hero-content">
            <?php if (filled($p['hero_eyebrow'])): ?><p class="eyebrow reveal"><?= e($p['hero_eyebrow']) ?></p><?php endif; ?>
            <?php if (filled($p['headline'])): ?><h1 class="hero-title reveal"><?= $headlineHtml ?></h1><?php endif; ?>
            <?php if (filled($p['subheadline'])): ?><p class="hero-sub reveal"><?= e($p['subheadline']) ?></p><?php endif; ?>
            <?php if (filled($p['area'])): ?>
            <p class="hero-meta reveal">
                <?php if (filled($p['area_label'])): ?><span class="hero-meta-label"><?= e($p['area_label']) ?>:</span><?php endif; ?>
                <strong><?= e($p['area']) ?></strong>
            </p>
            <?php endif; ?>
            <?php if (filled($p['hero_cta_text'])): ?>
            <a href="#contato" class="btn btn-gold reveal" data-scroll-to-form><?= e($p['hero_cta_text']) ?></a>
            <?php endif; ?>
        </div>
        <a href="#propriedade" class="scroll-hint" aria-label="Rolar para a apresentação"><span></span></a>
    </section>

    <!-- A PROPRIEDADE -->
    <section class="section intro" id="propriedade" aria-labelledby="t-propriedade">
        <div class="container intro-grid">
            <div>
                <p class="section-label" id="t-propriedade">A propriedade</p>
                <?php if (filled($p['area'])): ?>
                <p class="stat"><span class="stat-number"><?= e($p['area']) ?></span><span class="stat-caption">de área<?= filled($p['area_label']) ? ' · ' . e(mb_strtolower($p['area_label'])) : '' ?></span></p>
                <?php endif; ?>
            </div>
            <?php if (filled($p['description'])): ?>
            <div class="intro-text prose"><?= paragraphs($p['description']) ?></div>
            <?php endif; ?>
        </div>
    </section>

    <!-- CONHEÇA A ÁREA / PLANTA DA PROPRIEDADE -->
    <section class="section topo" id="conheca-a-area" aria-labelledby="t-area">
        <div class="container">
            <div class="section-head">
                <p class="section-label">Planta da propriedade</p>
                <h2 class="section-title" id="t-area"><?= e($p['topographic_title'] ?: 'Conheça a área') ?></h2>
                <?php if (filled($p['topographic_text'])): ?><p class="section-text"><?= e($p['topographic_text']) ?></p><?php endif; ?>
            </div>
            <figure class="topo-figure">
                <button type="button" class="topo-button" data-lightbox="topo" data-full="<?= e($topoFull) ?>"
                        data-caption="<?= e($p['topographic_alt'] ?: 'Planta da propriedade') ?>"
                        aria-label="Ampliar a planta da propriedade">
                    <img src="<?= e($topoImg) ?>" alt="<?= e($p['topographic_alt'] ?: 'Planta da propriedade') ?>" loading="lazy" decoding="async">
                    <span class="zoom-badge" aria-hidden="true">
                        <svg viewBox="0 0 24 24" width="16" height="16"><path fill="none" stroke="currentColor" stroke-width="1.6" d="M10.5 4a6.5 6.5 0 1 1 0 13 6.5 6.5 0 0 1 0-13zM15.5 15.5 20 20M10.5 7.5v6M7.5 10.5h6"/></svg>
                        Ampliar
                    </span>
                </button>
                <figcaption>
                    <?php if ($hasTopo): ?>
                        Toque ou clique para ampliar. <a href="<?= e($topoFull) ?>" target="_blank" rel="noopener">Abrir em alta resolução</a>
                    <?php else: ?>
                        Imagem ilustrativa — a planta da área será disponibilizada em breve.
                    <?php endif; ?>
                </figcaption>
            </figure>
        </div>
    </section>

    <?php if ($hasDetails): ?>
    <!-- DETALHES -->
    <section class="section details" id="detalhes" aria-labelledby="t-detalhes">
        <div class="container">
            <div class="section-head">
                <p class="section-label">Informações</p>
                <h2 class="section-title" id="t-detalhes">Detalhes da propriedade</h2>
            </div>
            <?php if ($facts): ?>
            <dl class="facts">
                <?php foreach ($facts as $f): ?>
                <div class="fact">
                    <dt><?= e($f['label']) ?></dt>
                    <dd><?= nl2br(e($f['value']), false) ?></dd>
                </div>
                <?php endforeach; ?>
            </dl>
            <?php endif; ?>

            <?php if ($chars || $diffs): ?>
            <div class="lists">
                <?php if ($chars): ?>
                <div class="list-block">
                    <h3>Características</h3>
                    <ul class="check-list"><?php foreach ($chars as $c): ?><li><?= e($c) ?></li><?php endforeach; ?></ul>
                </div>
                <?php endif; ?>
                <?php if ($diffs): ?>
                <div class="list-block">
                    <h3>Diferenciais</h3>
                    <ul class="check-list"><?php foreach ($diffs as $c): ?><li><?= e($c) ?></li><?php endforeach; ?></ul>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <?php if (filled($p['additional_info'])): ?>
            <div class="additional">
                <h3>Informações complementares</h3>
                <div class="prose"><?= paragraphs($p['additional_info']) ?></div>
            </div>
            <?php endif; ?>
        </div>
    </section>
    <?php endif; ?>

    <?php if ($gallery): ?>
    <!-- GALERIA -->
    <section class="section gallery-section" id="galeria" aria-labelledby="t-galeria">
        <div class="container">
            <div class="section-head">
                <p class="section-label">Galeria</p>
                <h2 class="section-title" id="t-galeria">Imagens da propriedade</h2>
            </div>
            <ul class="gallery count-<?= min(count($gallery), 5) ?>">
                <?php foreach ($gallery as $i => $g): ?>
                <li class="gallery-item">
                    <button type="button" data-lightbox="gallery" data-index="<?= $i ?>"
                            data-full="<?= e(media_url($g['image_path'])) ?>"
                            data-caption="<?= e($g['caption']) ?>"
                            aria-label="Ampliar imagem <?= $i + 1 ?><?= $g['caption'] ? ': ' . e($g['caption']) : '' ?>">
                        <img src="<?= e(media_url($g['thumb_path'] ?: $g['image_path'])) ?>"
                             alt="<?= e($g['alt_text'] ?: ($g['caption'] ?: 'Imagem da propriedade ' . ($i + 1))) ?>"
                             loading="lazy" decoding="async"
                             <?php if ($g['width'] && $g['height']): ?>width="<?= (int) $g['width'] ?>" height="<?= (int) $g['height'] ?>"<?php endif; ?>>
                        <?php if ($g['tag'] || $g['caption']): ?>
                        <span class="gallery-caption">
                            <?php if ($g['tag']): ?><em><?= e($g['tag']) ?></em><?php endif; ?>
                            <?= e($g['caption']) ?>
                        </span>
                        <?php endif; ?>
                    </button>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
    </section>
    <?php endif; ?>

    <!-- CTA INTERMEDIÁRIO -->
    <?php if (filled($p['mid_cta_title']) || filled($p['mid_cta_text'])): ?>
    <section class="cta-band" aria-labelledby="t-cta">
        <div class="container cta-inner">
            <?php if (filled($p['mid_cta_title'])): ?><h2 id="t-cta" class="cta-title"><?= e($p['mid_cta_title']) ?></h2><?php endif; ?>
            <?php if (filled($p['mid_cta_text'])): ?><p class="cta-text"><?= e($p['mid_cta_text']) ?></p><?php endif; ?>
            <?php if (filled($p['mid_cta_button'])): ?><a href="#contato" class="btn btn-gold" data-scroll-to-form><?= e($p['mid_cta_button']) ?></a><?php endif; ?>
        </div>
    </section>
    <?php endif; ?>

    <!-- FORMULÁRIO -->
    <section class="section contact" id="contato" aria-labelledby="t-contato">
        <div class="container contact-grid">
            <div class="contact-aside">
                <p class="section-label">Contato reservado</p>
                <h2 class="section-title" id="t-contato"><?= e($p['form_title'] ?: 'Solicite informações') ?></h2>
                <?php if (filled($p['form_intro'])): ?><p class="section-text"><?= e($p['form_intro']) ?></p><?php endif; ?>
                <ul class="assurances">
                    <li>Atendimento direto e confidencial</li>
                    <li>Informações complementares mediante análise de interesse</li>
                    <li>Seus dados são usados apenas para tratar desta oportunidade</li>
                </ul>
            </div>

            <div class="form-card">
                <div class="form-success" data-form-success<?= $leadSent ? '' : ' hidden' ?> tabindex="-1" role="status">
                    <div class="success-icon" aria-hidden="true">
                        <svg viewBox="0 0 24 24" width="28" height="28"><path fill="none" stroke="currentColor" stroke-width="1.6" d="M5 12.5 10 17.5 19 7"/></svg>
                    </div>
                    <h3><?= e($p['success_title'] ?: 'Recebemos seu interesse.') ?></h3>
                    <p><?= nl2br(e($p['success_message'] ?: 'Nossa equipe analisará as informações e, caso exista aderência, entraremos em contato através dos dados informados.'), false) ?></p>
                </div>

                <form class="lead-form" action="<?= e(url('api/lead.php')) ?>" method="post" novalidate data-lead-form<?= $leadSent ? ' hidden' : '' ?>>
                    <div class="form-alert" data-form-alert role="alert" hidden></div>

                    <div class="field">
                        <label for="f-name">Nome</label>
                        <input id="f-name" name="name" type="text" required maxlength="150" autocomplete="name" placeholder="Seu nome completo">
                        <p class="field-error" data-error-for="name"></p>
                    </div>
                    <div class="field-row">
                        <div class="field">
                            <label for="f-whatsapp">WhatsApp</label>
                            <input id="f-whatsapp" name="whatsapp" type="tel" required maxlength="40" autocomplete="tel" inputmode="tel" placeholder="(00) 00000-0000" data-phone-mask>
                            <p class="field-hint">Fora do Brasil? Comece com + e o código do país.</p>
                            <p class="field-error" data-error-for="whatsapp"></p>
                        </div>
                        <div class="field">
                            <label for="f-email">E-mail</label>
                            <input id="f-email" name="email" type="email" required maxlength="190" autocomplete="email" inputmode="email" placeholder="voce@empresa.com.br">
                            <p class="field-error" data-error-for="email"></p>
                        </div>
                    </div>
                    <div class="field-row">
                        <div class="field">
                            <label for="f-company">Nome da empresa</label>
                            <input id="f-company" name="company" type="text" required maxlength="190" autocomplete="organization" placeholder="Empresa">
                            <p class="field-error" data-error-for="company"></p>
                        </div>
                        <div class="field">
                            <label for="f-site">Site e/ou Instagram da empresa</label>
                            <input id="f-site" name="website_instagram" type="text" required maxlength="255" autocomplete="url" autocapitalize="off" spellcheck="false" placeholder="@empresa ou empresa.com.br">
                            <p class="field-error" data-error-for="website_instagram"></p>
                        </div>
                    </div>
                    <div class="field">
                        <label for="f-reason">Qual o motivo pelo qual você tem interesse em falar conosco sobre esta propriedade?</label>
                        <textarea id="f-reason" name="interest_reason" required rows="5" maxlength="5000" placeholder="Conte-nos sobre o seu interesse e o projeto que imagina para a área."></textarea>
                        <p class="field-error" data-error-for="interest_reason"></p>
                    </div>

                    <!-- Campos técnicos (origem do lead) -->
                    <input type="hidden" name="utm_source">
                    <input type="hidden" name="utm_medium">
                    <input type="hidden" name="utm_campaign">
                    <input type="hidden" name="utm_content">
                    <input type="hidden" name="utm_term">
                    <input type="hidden" name="landing_page">
                    <input type="hidden" name="referrer">
                    <input type="hidden" name="submission_id">
                    <input type="hidden" name="form_token" value="<?= e(lead_form_token()) ?>">
                    <div class="hp" aria-hidden="true">
                        <label for="f-hp">Não preencha este campo</label>
                        <input id="f-hp" type="text" name="company_fax" tabindex="-1" autocomplete="off">
                    </div>

                    <p class="lgpd">
                        <?= e(setting('lgpd_notice', 'Ao enviar seus dados, você concorda em ser contatado por nossa equipe para tratar exclusivamente sobre esta oportunidade.')) ?>
                        <a href="<?= e($privacyUrl) ?>" target="_blank" rel="noopener">Política de Privacidade</a>.
                    </p>
                    <button type="submit" class="btn btn-dark btn-block" data-submit>
                        <span data-submit-label><?= e($p['form_button'] ?: 'Enviar') ?></span>
                        <span class="spinner" aria-hidden="true"></span>
                    </button>
                </form>
            </div>
        </div>
    </section>
</main>

<footer class="site-footer">
    <div class="container footer-inner">
        <div>
            <p class="footer-brand"><?= e($siteName) ?></p>
            <?php if (setting('footer_text') !== ''): ?><p class="footer-text"><?= e(setting('footer_text')) ?></p><?php endif; ?>
        </div>
        <div class="footer-links">
            <a href="<?= e($privacyUrl) ?>">Política de Privacidade</a>
            <span>© <?= $year ?></span>
        </div>
    </div>
</footer>

<a href="<?= e(url('admin/')) ?>" class="admin-gear" aria-label="Área administrativa" title="Área administrativa" rel="nofollow">⚙</a>

<!-- Lightbox -->
<div class="lightbox" data-lb hidden role="dialog" aria-modal="true" aria-label="Visualização ampliada">
    <div class="lb-toolbar">
        <span class="lb-counter" data-lb-counter></span>
        <div class="lb-actions">
            <button type="button" class="lb-btn" data-lb-zoom-out aria-label="Diminuir zoom">−</button>
            <button type="button" class="lb-btn" data-lb-zoom-in aria-label="Aumentar zoom">+</button>
            <a class="lb-btn lb-open" data-lb-open href="#" target="_blank" rel="noopener" aria-label="Abrir imagem original">↗</a>
            <button type="button" class="lb-btn" data-lb-close aria-label="Fechar">✕</button>
        </div>
    </div>
    <div class="lb-stage" data-lb-stage>
        <img class="lb-img" data-lb-img alt="">
        <div class="lb-loading" data-lb-loading></div>
    </div>
    <button type="button" class="lb-nav lb-prev" data-lb-prev aria-label="Imagem anterior">‹</button>
    <button type="button" class="lb-nav lb-next" data-lb-next aria-label="Próxima imagem">›</button>
    <p class="lb-caption" data-lb-caption></p>
</div>

<script src="<?= e(asset('assets/js/site.js')) ?>" defer></script>
</body>
</html>
