<?php
require __DIR__ . '/../app/admin.php';

$fields = property_fields();

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
<?php admin_footer();
