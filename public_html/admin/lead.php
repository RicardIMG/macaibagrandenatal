<?php
require __DIR__ . '/../app/admin.php';

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$lead = $id ? db_one('SELECT * FROM leads WHERE id = ?', [$id]) : null;
if (!$lead) {
    flash('error', 'Lead não encontrado.');
    redirect(url('admin/leads.php'));
}

if (request_is_post()) {
    csrf_verify();
    $action = $_POST['action'] ?? '';
    if ($action === 'update') {
        $status = input_str($_POST, 'status', 30);
        if (!isset(lead_statuses()[$status])) {
            flash('error', 'Status inválido.');
        } else {
            $notes = input_str($_POST, 'notes', 20000, true);
            db_exec('UPDATE leads SET status = ?, notes = ?, updated_at = ? WHERE id = ?', [$status, $notes === '' ? null : $notes, now(), $id]);
            flash('success', 'Lead atualizado.');
        }
        redirect(url('admin/lead.php?id=' . $id));
    }
    if ($action === 'delete') {
        db_exec('DELETE FROM leads WHERE id = ?', [$id]);
        flash('success', 'Lead excluído definitivamente.');
        redirect(url('admin/leads.php'));
    }
}

$site = website_link($lead['website_instagram']);
$prev = db_value('SELECT id FROM leads WHERE (created_at > ? OR (created_at = ? AND id > ?)) ORDER BY created_at ASC, id ASC LIMIT 1', [$lead['created_at'], $lead['created_at'], $id]);
$next = db_value('SELECT id FROM leads WHERE (created_at < ? OR (created_at = ? AND id < ?)) ORDER BY created_at DESC, id DESC LIMIT 1', [$lead['created_at'], $lead['created_at'], $id]);

admin_header('Lead #' . $lead['id'], 'leads');
?>
<div class="toolbar">
    <a href="<?= e(url('admin/leads.php')) ?>" class="btn btn-ghost btn-sm">← Todos os leads</a>
    <span class="grow"></span>
    <?php if ($prev): ?><a class="btn btn-ghost btn-sm" href="<?= e(url('admin/lead.php?id=' . $prev)) ?>">‹ Mais recente</a><?php endif; ?>
    <?php if ($next): ?><a class="btn btn-ghost btn-sm" href="<?= e(url('admin/lead.php?id=' . $next)) ?>">Mais antigo ›</a><?php endif; ?>
</div>

<div class="lead-layout">
    <section class="card">
        <div class="lead-head">
            <div>
                <h2 class="lead-name"><?= e($lead['name']) ?></h2>
                <p class="muted"><?= e($lead['company']) ?> · recebido em <?= e(format_datetime($lead['created_at'], 'd/m/Y \à\s H:i')) ?></p>
            </div>
            <span class="badge badge-<?= e($lead['status']) ?>"><?= e(status_label($lead['status'])) ?></span>
        </div>

        <div class="contact-actions">
            <a class="btn btn-whatsapp" href="<?= e(whatsapp_link($lead['whatsapp'])) ?>" target="_blank" rel="noopener noreferrer">
                <svg viewBox="0 0 24 24" width="18" height="18" aria-hidden="true"><path fill="currentColor" d="M12 2a10 10 0 0 0-8.6 15.1L2 22l5-1.3A10 10 0 1 0 12 2zm0 18.2a8.2 8.2 0 0 1-4.2-1.2l-.3-.2-3 .8.8-2.9-.2-.3A8.2 8.2 0 1 1 12 20.2zm4.5-6.1c-.2-.1-1.5-.7-1.7-.8-.2-.1-.4-.1-.6.1l-.8 1c-.1.2-.3.2-.5.1a6.7 6.7 0 0 1-3.3-2.9c-.3-.4.2-.4.7-1.4.1-.2 0-.3 0-.4l-.8-1.8c-.2-.5-.4-.4-.6-.4h-.5a1 1 0 0 0-.7.3 3 3 0 0 0-.9 2.2 5.2 5.2 0 0 0 1.1 2.7 11.8 11.8 0 0 0 4.5 4c1.7.7 2.3.8 3.2.6a2.7 2.7 0 0 0 1.8-1.2 2.2 2.2 0 0 0 .1-1.3c0-.1-.2-.2-.5-.3z"/></svg>
                Chamar no WhatsApp
            </a>
            <a class="btn btn-ghost" href="mailto:<?= e($lead['email']) ?>">Enviar e-mail</a>
        </div>

        <dl class="detail-list">
            <div><dt>WhatsApp</dt><dd><?= e($lead['whatsapp']) ?></dd></div>
            <div><dt>E-mail</dt><dd><a href="mailto:<?= e($lead['email']) ?>"><?= e($lead['email']) ?></a></dd></div>
            <div><dt>Empresa</dt><dd><?= e($lead['company']) ?></dd></div>
            <div><dt>Site/Instagram</dt><dd>
                <?php if ($site): ?><a href="<?= e($site) ?>" target="_blank" rel="noopener noreferrer nofollow"><?= e($lead['website_instagram']) ?> ↗</a>
                <?php else: ?><?= e($lead['website_instagram']) ?><?php endif; ?>
            </dd></div>
            <div class="full"><dt>Motivo do interesse</dt><dd class="pre"><?= e($lead['interest_reason']) ?></dd></div>
        </dl>

        <h3 class="subhead">Origem</h3>
        <dl class="detail-list compact">
            <div><dt>Origem</dt><dd><?= e(lead_origin($lead)) ?></dd></div>
            <?php foreach (['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term'] as $k): ?>
                <div><dt><?= e($k) ?></dt><dd><?= e($lead[$k] ?: '—') ?></dd></div>
            <?php endforeach; ?>
            <div class="full"><dt>Página de entrada</dt><dd class="break"><?= e($lead['landing_page'] ?: '—') ?></dd></div>
            <div class="full"><dt>Referência</dt><dd class="break"><?= e($lead['referrer'] ?: '—') ?></dd></div>
            <div><dt>Data/hora do envio</dt><dd><?= e(format_datetime($lead['created_at'], 'd/m/Y H:i:s')) ?></dd></div>
            <div><dt>Última atualização</dt><dd><?= e(format_datetime($lead['updated_at'], 'd/m/Y H:i:s')) ?></dd></div>
            <div><dt>IP</dt><dd><?= e($lead['ip_address'] ?: '—') ?></dd></div>
        </dl>
    </section>

    <aside class="stack-lg">
        <section class="card">
            <h2>Acompanhamento</h2>
            <form method="post" class="stack">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int) $lead['id'] ?>">
                <input type="hidden" name="action" value="update">
                <label class="field">
                    <span>Status</span>
                    <select name="status">
                        <?php foreach (lead_statuses() as $k => $label): ?>
                            <option value="<?= e($k) ?>"<?= $lead['status'] === $k ? ' selected' : '' ?>><?= e($label) ?></option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="field">
                    <span>Observações internas</span>
                    <textarea name="notes" rows="8" placeholder="Anotações visíveis apenas no painel"><?= e($lead['notes']) ?></textarea>
                </label>
                <button type="submit" class="btn btn-primary btn-block">Salvar alterações</button>
            </form>
        </section>
        <section class="card danger-zone">
            <h2>Excluir lead</h2>
            <p class="muted small">Use para atender pedidos de exclusão de dados (LGPD). Esta ação não pode ser desfeita.</p>
            <form method="post" data-confirm="Excluir definitivamente este lead?">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int) $lead['id'] ?>">
                <input type="hidden" name="action" value="delete">
                <button type="submit" class="btn btn-danger btn-sm">Excluir lead</button>
            </form>
        </section>
    </aside>
</div>
<?php admin_footer();
