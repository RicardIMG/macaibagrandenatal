<?php
require __DIR__ . '/../app/admin.php';

if (request_is_post() && empty($_POST) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    flash('error', 'O envio excede o limite do servidor (' . ini_get('post_max_size') . ').');
    redirect(url('admin/settings.php'));
}

if (request_is_post()) {
    csrf_verify();
    $action = (string) ($_POST['action'] ?? '');

    try {
        if ($action === 'general') {
            $canonical = input_str($_POST, 'canonical_url', 255);
            if ($canonical !== '' && !filter_var($canonical, FILTER_VALIDATE_URL)) {
                throw new RuntimeException('URL canônica inválida. Use o endereço completo, ex.: https://www.seudominio.com.br/');
            }
            $privacyUrl = input_str($_POST, 'privacy_url', 255);
            if ($privacyUrl !== '' && !filter_var($privacyUrl, FILTER_VALIDATE_URL)) {
                throw new RuntimeException('Link da Política de Privacidade inválido. Use o endereço completo (https://...).');
            }
            set_setting('site_name', input_str($_POST, 'site_name', 120));
            set_setting('seo_title', input_str($_POST, 'seo_title', 160));
            set_setting('seo_description', input_str($_POST, 'seo_description', 320));
            set_setting('canonical_url', $canonical);
            set_setting('noindex_site', !empty($_POST['noindex_site']) ? '1' : '0');
            set_setting('lgpd_notice', input_str($_POST, 'lgpd_notice', 500));
            set_setting('privacy_url', $privacyUrl);
            set_setting('privacy_text', input_str($_POST, 'privacy_text', 30000, true));
            set_setting('footer_text', input_str($_POST, 'footer_text', 500));
            flash('success', 'Configurações salvas.');
        } elseif ($action === 'og_upload' || $action === 'favicon_upload') {
            $files = uploaded_files('image');
            if (!$files) {
                throw new RuntimeException('Selecione uma imagem.');
            }
            $key = $action === 'og_upload' ? 'og_image' : 'favicon';
            $r = store_image($files[0], $action === 'og_upload' ? 'og' : 'favicon');
            delete_media(setting($key));
            set_setting($key, $r['image']);
            flash('success', $key === 'og_image' ? 'Imagem de compartilhamento atualizada.' : 'Favicon atualizado.');
        } elseif ($action === 'og_remove' || $action === 'favicon_remove') {
            $key = $action === 'og_remove' ? 'og_image' : 'favicon';
            delete_media(setting($key));
            set_setting($key, '');
            flash('success', 'Imagem removida.');
        } elseif ($action === 'account_email') {
            $email = mb_strtolower(input_str($_POST, 'email', 190));
            $current = (string) ($_POST['current_password'] ?? '');
            $row = db_one('SELECT password_hash FROM admins WHERE id = ?', [$admin['id']]);
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                throw new RuntimeException('Informe um e-mail válido.');
            }
            if (!$row || !password_verify($current, $row['password_hash'])) {
                throw new RuntimeException('Senha atual incorreta.');
            }
            if (db_value('SELECT id FROM admins WHERE email = ? AND id <> ?', [$email, $admin['id']])) {
                throw new RuntimeException('Este e-mail já está em uso por outro administrador.');
            }
            db_exec('UPDATE admins SET email = ? WHERE id = ?', [$email, $admin['id']]);
            $_SESSION['admin_email'] = $email;
            flash('success', 'E-mail de acesso atualizado. Use-o no próximo login.');
        } elseif ($action === 'account_password') {
            $current = (string) ($_POST['current_password'] ?? '');
            $new = (string) ($_POST['new_password'] ?? '');
            $confirm = (string) ($_POST['confirm_password'] ?? '');
            $row = db_one('SELECT password_hash FROM admins WHERE id = ?', [$admin['id']]);
            if (!$row || !password_verify($current, $row['password_hash'])) {
                throw new RuntimeException('Senha atual incorreta.');
            }
            if ($new !== $confirm) {
                throw new RuntimeException('A confirmação não confere com a nova senha.');
            }
            if ($problem = password_problem($new)) {
                throw new RuntimeException($problem);
            }
            db_exec('UPDATE admins SET password_hash = ? WHERE id = ?', [hash_password($new), $admin['id']]);
            session_regenerate_id(true);
            flash('success', 'Senha alterada com sucesso.');
        } else {
            throw new RuntimeException('Ação inválida.');
        }
    } catch (RuntimeException $e) {
        flash('error', $e->getMessage());
    }
    redirect(url('admin/settings.php'));
}

$s = settings();
$v = function (string $k) use ($s) { return $s[$k] ?? ''; };

admin_header('Configurações', 'settings');
?>
<form method="post" class="stack-lg">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="general">

    <section class="card">
        <h2>Identidade e SEO</h2>
        <div class="form-grid">
            <label class="field">
                <span>Nome do site (marca exibida no topo e rodapé)</span>
                <input type="text" name="site_name" value="<?= e($v('site_name')) ?>" maxlength="120">
            </label>
            <label class="field">
                <span>Título da página (title)</span>
                <input type="text" name="seo_title" value="<?= e($v('seo_title')) ?>" maxlength="160">
            </label>
            <label class="field span-2">
                <span>Meta description <small class="muted">(ideal: até 160 caracteres)</small></span>
                <textarea name="seo_description" rows="2" maxlength="320"><?= e($v('seo_description')) ?></textarea>
            </label>
            <label class="field span-2">
                <span>URL canônica <small class="muted">(ex.: https://www.seudominio.com.br/ — vazio = automática)</small></span>
                <input type="url" name="canonical_url" value="<?= e($v('canonical_url')) ?>" maxlength="255" placeholder="https://www.seudominio.com.br/">
            </label>
            <label class="check span-2">
                <input type="checkbox" name="noindex_site" value="1"<?= $v('noindex_site') === '1' ? ' checked' : '' ?>>
                <span>Ocultar o site dos mecanismos de busca (útil enquanto a página está em preparação)</span>
            </label>
        </div>
    </section>

    <section class="card">
        <h2>Privacidade (LGPD) e rodapé</h2>
        <div class="form-grid">
            <label class="field span-2">
                <span>Aviso exibido junto ao botão de envio</span>
                <textarea name="lgpd_notice" rows="2" maxlength="500"><?= e($v('lgpd_notice')) ?></textarea>
            </label>
            <label class="field span-2">
                <span>Link externo da Política de Privacidade <small class="muted">(opcional — se vazio, usa a página interna abaixo)</small></span>
                <input type="url" name="privacy_url" value="<?= e($v('privacy_url')) ?>" maxlength="255" placeholder="https://...">
            </label>
            <label class="field span-2">
                <span>Texto da Política de Privacidade (página <a href="<?= e(url('privacidade.php')) ?>" target="_blank" rel="noopener">/privacidade.php</a>)
                    <small class="muted">— se vazio, é exibido um modelo padrão com campos [A DEFINIR]</small></span>
                <textarea name="privacy_text" rows="10" maxlength="30000" placeholder="Cole aqui o texto da sua Política de Privacidade. Separe parágrafos com uma linha em branco. Linhas iniciadas com ## viram subtítulos."><?= e($v('privacy_text')) ?></textarea>
            </label>
            <label class="field span-2">
                <span>Texto do rodapé</span>
                <textarea name="footer_text" rows="2" maxlength="500"><?= e($v('footer_text')) ?></textarea>
            </label>
        </div>
    </section>

    <div class="sticky-actions">
        <button type="submit" class="btn btn-primary">Salvar configurações</button>
    </div>
</form>

<div class="grid-2 mt">
    <section class="card">
        <h2>Imagem de compartilhamento (Open Graph)</h2>
        <p class="muted small">Exibida ao compartilhar o link no WhatsApp, LinkedIn etc. Ideal: 1200 × 630 px. Se vazia, usa a imagem principal.</p>
        <?php if ($v('og_image')): ?><div class="image-preview"><img src="<?= e(media_url($v('og_image'))) ?>" alt=""></div><?php endif; ?>
        <form method="post" enctype="multipart/form-data" class="upload-form">
            <?= csrf_field() ?><input type="hidden" name="action" value="og_upload">
            <input type="file" name="image" accept="image/jpeg,image/png,image/webp" required>
            <button type="submit" class="btn btn-primary">Enviar</button>
        </form>
        <?php if ($v('og_image')): ?>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="og_remove"><button class="btn btn-link-danger">Remover</button></form>
        <?php endif; ?>
    </section>

    <section class="card">
        <h2>Favicon</h2>
        <p class="muted small">Ícone da aba do navegador. Envie uma imagem quadrada PNG (mínimo 256 × 256 px).</p>
        <?php if ($v('favicon')): ?><div class="favicon-preview"><img src="<?= e(media_url($v('favicon'))) ?>" alt="" width="64" height="64"></div><?php endif; ?>
        <form method="post" enctype="multipart/form-data" class="upload-form">
            <?= csrf_field() ?><input type="hidden" name="action" value="favicon_upload">
            <input type="file" name="image" accept="image/png,image/jpeg,image/webp" required>
            <button type="submit" class="btn btn-primary">Enviar</button>
        </form>
        <?php if ($v('favicon')): ?>
        <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="favicon_remove"><button class="btn btn-link-danger">Remover</button></form>
        <?php endif; ?>
    </section>
</div>

<div class="grid-2 mt">
    <section class="card">
        <h2>E-mail de acesso</h2>
        <form method="post" class="stack" autocomplete="off">
            <?= csrf_field() ?><input type="hidden" name="action" value="account_email">
            <label class="field"><span>E-mail do administrador</span>
                <input type="email" name="email" value="<?= e($admin['email']) ?>" required maxlength="190" autocomplete="username"></label>
            <label class="field"><span>Senha atual (confirmação)</span>
                <input type="password" name="current_password" required autocomplete="current-password"></label>
            <button type="submit" class="btn btn-primary">Alterar e-mail</button>
        </form>
    </section>

    <section class="card">
        <h2>Alterar senha</h2>
        <form method="post" class="stack" autocomplete="off">
            <?= csrf_field() ?><input type="hidden" name="action" value="account_password">
            <label class="field"><span>Senha atual</span>
                <input type="password" name="current_password" required autocomplete="current-password"></label>
            <label class="field"><span>Nova senha <small class="muted">(mín. 10 caracteres, com letras e números)</small></span>
                <input type="password" name="new_password" required minlength="10" autocomplete="new-password"></label>
            <label class="field"><span>Confirmar nova senha</span>
                <input type="password" name="confirm_password" required minlength="10" autocomplete="new-password"></label>
            <button type="submit" class="btn btn-primary">Alterar senha</button>
        </form>
    </section>
</div>
<?php admin_footer();
