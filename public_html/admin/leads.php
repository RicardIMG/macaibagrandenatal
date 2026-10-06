<?php
require __DIR__ . '/../app/admin.php';

$q      = input_str($_GET, 'q', 100);
$status = input_str($_GET, 'status', 30);
$page   = max(1, (int) ($_GET['page'] ?? 1));
$per    = 25;

[$where, $params] = leads_where($q, $status);
$total = (int) db_value("SELECT COUNT(*) FROM leads $where", $params);
$pages = max(1, (int) ceil($total / $per));
$page  = min($page, $pages);
$offset = ($page - 1) * $per;
$leads = db_all("SELECT * FROM leads $where ORDER BY created_at DESC, id DESC LIMIT $per OFFSET $offset", $params);

$qs = function (array $extra = []) use ($q, $status) {
    return http_build_query(array_filter(array_merge(['q' => $q, 'status' => $status], $extra), 'strlen'));
};

admin_header('Leads', 'leads');
?>
<section class="card">
    <form method="get" class="filters" role="search">
        <label class="field grow">
            <span>Pesquisar</span>
            <input type="search" name="q" value="<?= e($q) ?>" placeholder="Nome, e-mail, WhatsApp ou empresa">
        </label>
        <label class="field">
            <span>Status</span>
            <select name="status">
                <option value="">Todos</option>
                <?php foreach (lead_statuses() as $k => $label): ?>
                    <option value="<?= e($k) ?>"<?= $status === $k ? ' selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <div class="filter-actions">
            <button type="submit" class="btn btn-primary">Filtrar</button>
            <?php if ($q !== '' || $status !== ''): ?><a href="<?= e(url('admin/leads.php')) ?>" class="btn btn-ghost">Limpar</a><?php endif; ?>
            <a href="<?= e(url('admin/export.php') . '?' . $qs()) ?>" class="btn btn-ghost">Exportar CSV</a>
        </div>
    </form>
</section>

<section class="card">
    <div class="card-head">
        <h2><?= $total ?> <?= $total === 1 ? 'interessado' : 'interessados' ?></h2>
    </div>
    <?php if (!$leads): ?>
        <p class="empty">Nenhum lead encontrado<?= ($q !== '' || $status !== '') ? ' para este filtro' : '' ?>.</p>
    <?php else: ?>
    <div class="table-wrap">
        <table class="table table-leads">
            <thead>
                <tr>
                    <th>Data/hora</th><th>Nome</th><th>WhatsApp</th><th>E-mail</th><th>Empresa</th>
                    <th>Site/Instagram</th><th>Motivo do interesse</th><th>Origem</th><th>Campanha/UTM</th><th>Status</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($leads as $l): $link = url('admin/lead.php?id=' . $l['id']); ?>
                <tr class="row-link" data-href="<?= e($link) ?>">
                    <td class="nowrap"><?= e(format_datetime($l['created_at'])) ?></td>
                    <td><a href="<?= e($link) ?>"><strong><?= e($l['name']) ?></strong></a></td>
                    <td class="nowrap"><?= e($l['whatsapp']) ?></td>
                    <td><?= e($l['email']) ?></td>
                    <td><?= e($l['company']) ?></td>
                    <td><?= e($l['website_instagram']) ?></td>
                    <td class="clip" title="<?= e($l['interest_reason']) ?>"><?= e(mb_strimwidth($l['interest_reason'], 0, 90, '…')) ?></td>
                    <td><?= e(lead_origin($l)) ?></td>
                    <td><?= e($l['utm_campaign'] ?: '—') ?></td>
                    <td><span class="badge badge-<?= e($l['status']) ?>"><?= e(status_label($l['status'])) ?></span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>

    <?php if ($pages > 1): ?>
    <nav class="pagination" aria-label="Paginação">
        <?php if ($page > 1): ?><a href="?<?= e($qs(['page' => $page - 1])) ?>">← Anterior</a><?php endif; ?>
        <span>Página <?= $page ?> de <?= $pages ?></span>
        <?php if ($page < $pages): ?><a href="?<?= e($qs(['page' => $page + 1])) ?>">Próxima →</a><?php endif; ?>
    </nav>
    <?php endif; ?>
    <?php endif; ?>
</section>
<?php admin_footer();
