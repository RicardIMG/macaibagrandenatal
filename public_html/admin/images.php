<?php
require __DIR__ . '/../app/admin.php';

$tags = ['Aérea', 'Drone', 'Acesso', 'Vegetação', 'Topografia', 'Entorno', 'Mapa', 'Terreno'];

/** POST maior que post_max_size chega vazio: avisa em vez de acusar CSRF. */
if (request_is_post() && empty($_POST) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    flash('error', 'O envio excede o limite do servidor (' . ini_get('post_max_size') . '). Envie menos arquivos por vez ou imagens menores.');
    redirect(url('admin/images.php'));
}

if (request_is_post()) {
    csrf_verify();
    $action = (string) ($_POST['action'] ?? '');
    $p = property();

    try {
        switch ($action) {
            case 'hero_upload':
                $files = uploaded_files('image');
                if (!$files) {
                    throw new RuntimeException('Selecione uma imagem.');
                }
                $r = store_image($files[0], 'hero');
                update_property_images(['hero_image' => $r['image'], 'hero_image_thumb' => $r['thumb']]);
                delete_media($p['hero_image']);
                delete_media($p['hero_image_thumb']);
                flash('success', 'Imagem principal atualizada.');
                break;

            case 'hero_remove':
                update_property_images(['hero_image' => null, 'hero_image_thumb' => null]);
                delete_media($p['hero_image']);
                delete_media($p['hero_image_thumb']);
                flash('success', 'Imagem principal removida.');
                break;

            case 'topo_upload':
                $files = uploaded_files('image');
                if (!$files) {
                    throw new RuntimeException('Selecione uma imagem.');
                }
                $r = store_image($files[0], 'topographic');
                update_property_images(['topographic_image' => $r['image'], 'topographic_full' => $r['full']]);
                delete_media($p['topographic_image']);
                delete_media($p['topographic_full']);
                flash('success', 'Imagem topográfica atualizada.');
                break;

            case 'topo_remove':
                update_property_images(['topographic_image' => null, 'topographic_full' => null]);
                delete_media($p['topographic_image']);
                delete_media($p['topographic_full']);
                flash('success', 'Imagem topográfica removida.');
                break;

            case 'gallery_upload':
                $files = array_filter(uploaded_files('images'), function ($f) {
                    return (int) $f['error'] !== UPLOAD_ERR_NO_FILE;
                });
                if (!$files) {
                    throw new RuntimeException('Selecione ao menos uma imagem.');
                }
                $tag = input_str($_POST, 'tag', 60);
                $order = (int) db_value('SELECT COALESCE(MAX(sort_order), 0) FROM gallery');
                $ok = 0;
                $fail = [];
                foreach ($files as $f) {
                    try {
                        $r = store_image($f, 'gallery');
                        $order += 10;
                        db_exec(
                            'INSERT INTO gallery (image_path, thumb_path, tag, width, height, sort_order, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)',
                            [$r['image'], $r['thumb'], $tag === '' ? null : $tag, $r['width'], $r['height'], $order, now()]
                        );
                        $ok++;
                    } catch (RuntimeException $e) {
                        $fail[] = mb_substr((string) $f['name'], 0, 80) . ': ' . $e->getMessage();
                    }
                }
                if ($ok) {
                    flash('success', $ok . ' imagem(ns) adicionada(s) à galeria.');
                }
                foreach ($fail as $msg) {
                    flash('error', $msg);
                }
                break;

            case 'gallery_replace':
                $id = (int) ($_POST['id'] ?? 0);
                $item = db_one('SELECT * FROM gallery WHERE id = ?', [$id]);
                $files = uploaded_files('image');
                if (!$item || !$files) {
                    throw new RuntimeException('Selecione a nova imagem.');
                }
                $r = store_image($files[0], 'gallery');
                db_exec('UPDATE gallery SET image_path = ?, thumb_path = ?, width = ?, height = ? WHERE id = ?', [$r['image'], $r['thumb'], $r['width'], $r['height'], $id]);
                delete_media($item['image_path']);
                delete_media($item['thumb_path']);
                flash('success', 'Imagem substituída.');
                break;

            case 'gallery_delete':
                $id = (int) ($_POST['id'] ?? 0);
                $item = db_one('SELECT * FROM gallery WHERE id = ?', [$id]);
                if ($item) {
                    db_exec('DELETE FROM gallery WHERE id = ?', [$id]);
                    delete_media($item['image_path']);
                    delete_media($item['thumb_path']);
                    flash('success', 'Imagem removida da galeria.');
                }
                break;

            case 'gallery_move':
                $id = (int) ($_POST['id'] ?? 0);
                $dir = ($_POST['dir'] ?? '') === 'up' ? -1 : 1;
                $ids = array_map('intval', array_column(gallery_items(), 'id'));
                $pos = array_search($id, $ids, true);
                if ($pos !== false && isset($ids[$pos + $dir])) {
                    [$ids[$pos], $ids[$pos + $dir]] = [$ids[$pos + $dir], $ids[$pos]];
                    foreach ($ids as $i => $gid) {
                        db_exec('UPDATE gallery SET sort_order = ? WHERE id = ?', [($i + 1) * 10, $gid]);
                    }
                }
                break;

            case 'gallery_save':
                $order = array_map('intval', (array) ($_POST['order'] ?? []));
                $existing = array_map('intval', array_column(gallery_items(), 'id'));
                $pos = 0;
                foreach ($order as $gid) {
                    if (in_array($gid, $existing, true)) {
                        $pos += 10;
                        db_exec(
                            'UPDATE gallery SET sort_order = ?, caption = ?, alt_text = ?, tag = ? WHERE id = ?',
                            [
                                $pos,
                                input_str((array) ($_POST['caption'] ?? []), (string) $gid, 255) ?: null,
                                input_str((array) ($_POST['alt'] ?? []), (string) $gid, 255) ?: null,
                                input_str((array) ($_POST['tag'] ?? []), (string) $gid, 60) ?: null,
                                $gid,
                            ]
                        );
                    }
                }
                flash('success', 'Galeria salva (ordem e legendas).');
                break;

            default:
                throw new RuntimeException('Ação inválida.');
        }
    } catch (RuntimeException $e) {
        flash('error', $e->getMessage());
    }
    redirect(url('admin/images.php') . (str_starts_with($action, 'gallery') ? '#galeria' : ''));
}

$p = property();
$gallery = gallery_items();
$maxMb = (int) config('upload_max_mb', 15);
$serverMax = ini_get('upload_max_filesize');

admin_header('Imagens', 'images');
?>
<p class="lead-text">Formatos aceitos: <strong>JPG, PNG ou WEBP</strong>, até <strong><?= $maxMb ?> MB</strong> por arquivo
(limite atual do servidor: <?= e($serverMax) ?>). As imagens são otimizadas automaticamente.</p>

<div class="grid-2">
    <section class="card">
        <h2>Imagem principal</h2>
        <p class="muted small">Fotografia de destaque da primeira dobra. Recomendado: horizontal, mínimo 2400 px de largura.</p>
        <div class="image-preview">
            <?php if ($p['hero_image']): ?>
                <img src="<?= e(media_url($p['hero_image_thumb'] ?: $p['hero_image'])) ?>" alt="">
            <?php else: ?>
                <div class="image-empty">Nenhuma imagem enviada — o site exibe uma ilustração neutra.</div>
            <?php endif; ?>
        </div>
        <form method="post" enctype="multipart/form-data" class="upload-form">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="hero_upload">
            <input type="file" name="image" accept="image/jpeg,image/png,image/webp" required>
            <button type="submit" class="btn btn-primary"><?= $p['hero_image'] ? 'Substituir' : 'Enviar' ?></button>
        </form>
        <?php if ($p['hero_image']): ?>
        <form method="post" data-confirm="Remover a imagem principal?">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="hero_remove">
            <button type="submit" class="btn btn-link-danger">Remover imagem</button>
        </form>
        <?php endif; ?>
    </section>

    <section class="card">
        <h2>Topográfico</h2>
        <p class="muted small">Levantamento / mapa topográfico. Envie na maior resolução disponível: a versão completa fica disponível para ampliação.</p>
        <div class="image-preview contain">
            <?php if ($p['topographic_image']): ?>
                <img src="<?= e(media_url($p['topographic_image'])) ?>" alt="">
            <?php else: ?>
                <div class="image-empty">Nenhum topográfico enviado — o site exibe uma ilustração marcada como “imagem ilustrativa”.</div>
            <?php endif; ?>
        </div>
        <form method="post" enctype="multipart/form-data" class="upload-form">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="topo_upload">
            <input type="file" name="image" accept="image/jpeg,image/png,image/webp" required>
            <button type="submit" class="btn btn-primary"><?= $p['topographic_image'] ? 'Substituir' : 'Enviar' ?></button>
        </form>
        <?php if ($p['topographic_image']): ?>
        <p class="small"><a href="<?= e(media_url($p['topographic_full'] ?: $p['topographic_image'])) ?>" target="_blank" rel="noopener">Abrir versão em alta resolução ↗</a></p>
        <form method="post" data-confirm="Remover a imagem topográfica?">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="topo_remove">
            <button type="submit" class="btn btn-link-danger">Remover imagem</button>
        </form>
        <?php endif; ?>
    </section>
</div>

<section class="card" id="galeria">
    <div class="card-head">
        <h2>Galeria <span class="muted">(<?= count($gallery) ?>)</span></h2>
    </div>
    <form method="post" enctype="multipart/form-data" class="upload-form gallery-upload">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="gallery_upload">
        <input type="file" name="images[]" accept="image/jpeg,image/png,image/webp" multiple required>
        <input type="text" name="tag" list="tag-options" placeholder="Categoria (opcional)" maxlength="60">
        <button type="submit" class="btn btn-primary">Adicionar à galeria</button>
    </form>
    <datalist id="tag-options">
        <?php foreach ($tags as $t): ?><option value="<?= e($t) ?>"><?php endforeach; ?>
    </datalist>

    <?php if (!$gallery): ?>
        <p class="empty">A galeria está vazia. Enquanto não houver imagens, a seção não aparece no site.</p>
    <?php else: ?>
        <p class="muted small">Arraste os itens para reordenar (ou use as setas) e clique em <strong>Salvar galeria</strong>.</p>
        <form method="post" id="gallery-form">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="gallery_save">
            <ol class="gallery-admin" data-sortable>
                <?php foreach ($gallery as $i => $g): $gid = (int) $g['id']; ?>
                <li class="gallery-row" draggable="true" data-id="<?= $gid ?>">
                    <input type="hidden" name="order[]" value="<?= $gid ?>">
                    <span class="drag-handle" aria-hidden="true">⋮⋮</span>
                    <a href="<?= e(media_url($g['image_path'])) ?>" target="_blank" rel="noopener" class="gallery-thumb">
                        <img src="<?= e(media_url($g['thumb_path'] ?: $g['image_path'])) ?>" alt="" loading="lazy">
                    </a>
                    <div class="gallery-fields">
                        <input type="text" name="caption[<?= $gid ?>]" value="<?= e($g['caption']) ?>" placeholder="Legenda (opcional)" maxlength="255">
                        <div class="gallery-fields-row">
                            <input type="text" name="tag[<?= $gid ?>]" value="<?= e($g['tag']) ?>" placeholder="Categoria" list="tag-options" maxlength="60">
                            <input type="text" name="alt[<?= $gid ?>]" value="<?= e($g['alt_text']) ?>" placeholder="Texto alternativo (acessibilidade)" maxlength="255">
                        </div>
                    </div>
                    <div class="gallery-actions">
                        <button type="submit" form="move-<?= $gid ?>-up" class="icon-btn" title="Mover para cima" <?= $i === 0 ? 'disabled' : '' ?>>↑</button>
                        <button type="submit" form="move-<?= $gid ?>-down" class="icon-btn" title="Mover para baixo" <?= $i === count($gallery) - 1 ? 'disabled' : '' ?>>↓</button>
                        <label class="icon-btn file-btn" title="Substituir imagem">⟳
                            <input type="file" name="image" form="replace-<?= $gid ?>" accept="image/jpeg,image/png,image/webp" data-autosubmit>
                        </label>
                        <button type="submit" form="delete-<?= $gid ?>" class="icon-btn danger" title="Excluir">✕</button>
                    </div>
                </li>
                <?php endforeach; ?>
            </ol>
            <div class="sticky-actions">
                <button type="submit" class="btn btn-primary">Salvar galeria</button>
            </div>
        </form>

        <?php foreach ($gallery as $g): $gid = (int) $g['id']; ?>
            <form method="post" id="move-<?= $gid ?>-up" hidden><?= csrf_field() ?><input type="hidden" name="action" value="gallery_move"><input type="hidden" name="id" value="<?= $gid ?>"><input type="hidden" name="dir" value="up"></form>
            <form method="post" id="move-<?= $gid ?>-down" hidden><?= csrf_field() ?><input type="hidden" name="action" value="gallery_move"><input type="hidden" name="id" value="<?= $gid ?>"><input type="hidden" name="dir" value="down"></form>
            <form method="post" id="delete-<?= $gid ?>" data-confirm="Excluir esta imagem da galeria?" hidden><?= csrf_field() ?><input type="hidden" name="action" value="gallery_delete"><input type="hidden" name="id" value="<?= $gid ?>"></form>
            <form method="post" id="replace-<?= $gid ?>" enctype="multipart/form-data" hidden><?= csrf_field() ?><input type="hidden" name="action" value="gallery_replace"><input type="hidden" name="id" value="<?= $gid ?>"></form>
        <?php endforeach; ?>
    <?php endif; ?>
</section>
<?php admin_footer();
