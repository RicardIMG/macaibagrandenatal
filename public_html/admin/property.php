<?php
require __DIR__ . '/../app/admin.php';

$fields = property_fields();

if (request_is_post() && ($_POST['action'] ?? '') === 'map') {
    csrf_verify();
    $input = input_str($_POST, 'map_location', 500);
    if ($input === '') {
        foreach (['map_lat', 'map_lng', 'map_link'] as $k) {
            set_setting($k, '');
        }
        flash('success', 'Localização removida: a seção do mapa não aparece mais no site.');
    } else {
        $coords = parse_map_location($input);
        if (!$coords) {
            flash('error', 'Não consegui identificar a localização. Cole o link do Google Maps (botão Compartilhar) ou digite as coordenadas, ex.: -5.880816, -35.293365');
            redirect(url('admin/property.php#mapa'));
        }
        set_setting('map_lat', $coords[0]);
        set_setting('map_lng', $coords[1]);
        set_setting('map_link', preg_match('~^https?://~i', $input) ? $input : '');
        flash('success', 'Localização salva: ' . $coords[0] . ', ' . $coords[1] . '.');
    }
    set_setting('map_zoom', (string) max(3, min(20, (int) ($_POST['map_zoom'] ?? 15))));
    set_setting('map_type', in_array($_POST['map_type'] ?? '', ['m', 'k', 'h'], true) ? $_POST['map_type'] : 'h');
    set_setting('map_title', input_str($_POST, 'map_title', 120));
    set_setting('map_text', input_str($_POST, 'map_text', 500));
    redirect(url('admin/property.php#mapa'));
}

if (request_is_post()) {
    csrf_verify();
    $data = [];
    foreach ($fields as $group) {
        foreach ($group as $key => $f) {
            $multiline = $f['type'] !== 'text';
            $data[$key] = input_str($_POST, $key, $f['max'], $multiline);
        }
    }
    update_property_texts($data);
    flash('success', 'Informações da propriedade salvas. As alterações já estão no site.');
    redirect(url('admin/property.php'));
}

$p = property();
ensure_map_defaults();
$map = map_data();
$mapInput = setting('map_link') ?: ($map ? $map['lat'] . ', ' . $map['lng'] : '');
admin_header('Propriedade', 'property');
?>
<p class="lead-text">Edite os textos exibidos no site. <strong>Campos vazios não aparecem na página</strong> — preencha apenas o que já estiver confirmado.
As imagens (principal, planta da área e galeria) são gerenciadas em <a href="<?= e(url('admin/images.php')) ?>">Imagens</a>.</p>

<form method="post" class="stack-lg">
    <?= csrf_field() ?>
    <?php foreach ($fields as $groupTitle => $group): ?>
    <section class="card">
        <h2><?= e($groupTitle) ?></h2>
        <div class="form-grid">
            <?php foreach ($group as $key => $f): $value = $p[$key] ?? ''; $wide = $f['type'] !== 'text'; ?>
            <label class="field<?= $wide ? ' span-2' : '' ?>">
                <span>
                    <?= e($f['label']) ?>
                    <?php if (trim($value) === ''): ?><em class="tag-muted">vazio · não exibido</em><?php endif; ?>
                </span>
                <?php if ($f['type'] === 'text'): ?>
                    <input type="text" name="<?= e($key) ?>" value="<?= e($value) ?>" maxlength="<?= (int) $f['max'] ?>" placeholder="<?= e($f['placeholder']) ?>">
                <?php else: ?>
                    <textarea name="<?= e($key) ?>" rows="<?= (int) ($f['rows'] ?? 3) ?>" maxlength="<?= (int) $f['max'] ?>" placeholder="<?= e($f['placeholder']) ?>"><?= e($value) ?></textarea>
                    <?php if ($f['type'] === 'lines'): ?><small class="muted">Cada linha vira um item da lista no site.</small><?php endif; ?>
                <?php endif; ?>
            </label>
            <?php endforeach; ?>
        </div>
    </section>
    <?php endforeach; ?>

    <div class="sticky-actions">
        <a href="<?= e(url('')) ?>" target="_blank" rel="noopener" class="btn btn-ghost">Ver site ↗</a>
        <button type="submit" class="btn btn-primary">Salvar informações</button>
    </div>
</form>
<section class="card mt" id="mapa">
    <h2>Localização no mapa (Google Maps)</h2>
    <p class="muted small">No Google Maps, clique no ponto da propriedade → <strong>Compartilhar</strong> → <strong>Copiar link</strong> e cole abaixo.
        Também aceita coordenadas, ex.: <code>-5.880816, -35.293365</code>. Deixe vazio para ocultar a seção do mapa.</p>
    <form method="post" class="stack">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="map">
        <label class="field"><span>Link do Google Maps ou coordenadas</span>
            <input type="text" name="map_location" value="<?= e($mapInput) ?>" maxlength="500" placeholder="https://maps.app.goo.gl/...">
            <?php if ($map): ?><small class="muted">Ponto atual: <?= e($map['lat']) ?>, <?= e($map['lng']) ?> · <a href="<?= e($map['open']) ?>" target="_blank" rel="noopener">conferir no Google Maps ↗</a></small><?php endif; ?>
        </label>
        <div class="form-grid">
            <label class="field"><span>Visualização</span>
                <select name="map_type">
                    <?php foreach (['h' => 'Satélite com nomes de ruas', 'k' => 'Satélite', 'm' => 'Mapa'] as $k => $label): ?>
                        <option value="<?= $k ?>"<?= setting('map_type', 'h') === $k ? ' selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select></label>
            <label class="field"><span>Aproximação (zoom)</span>
                <select name="map_zoom">
                    <?php foreach ([12 => 'Região', 14 => 'Bairro', 15 => 'Entorno (padrão)', 16 => 'Próximo', 17 => 'Bem próximo'] as $z => $label): ?>
                        <option value="<?= $z ?>"<?= (int) setting('map_zoom', '15') === $z ? ' selected' : '' ?>><?= e($label) ?></option>
                    <?php endforeach; ?>
                </select></label>
            <label class="field"><span>Título da seção</span>
                <input type="text" name="map_title" value="<?= e(setting('map_title')) ?>" maxlength="120" placeholder="Localização"></label>
            <label class="field span-2"><span>Texto da seção</span>
                <textarea name="map_text" rows="2" maxlength="500"><?= e(setting('map_text')) ?></textarea></label>
        </div>
        <div><button type="submit" class="btn btn-primary">Salvar localização</button></div>
    </form>
</section>
<?php admin_footer();
