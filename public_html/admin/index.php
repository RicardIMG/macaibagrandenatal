<?php
require __DIR__ . '/../app/admin.php';

$stats = lead_stats();
$latest = db_all('SELECT * FROM leads ORDER BY created_at DESC, id DESC LIMIT 8');

admin_header('Dashboard', 'dashboard');
?>
<section class="cards">
    <div class="card stat-card"><span class="stat-label">Total de interessados</span><strong class="stat-value"><?= $stats['total'] ?></strong></div>
    <div class="card stat-card"><span class="stat-label">Interessados hoje</span><strong class="stat-value"><?= $stats['today'] ?></strong></div>
    <div class="card stat-card"><span class="stat-label">Últimos 7 dias</span><strong class="stat-value"><?= $stats['d7'] ?></strong></div>
    <div class="card stat-card"><span class="stat-label">Últimos 30 dias</span><strong class="stat-value"><?= $stats['d30'] ?></strong></div>
</section>

<section class="card">
    <div class="card-head">
        <h2>Por status</h2>
    </div>
    <div class="status-strip">
        <?php foreach (lead_statuses() as $key => $label): ?>
            <a href="<?= e(url('admin/leads.php?status=' . $key)) ?>" class="status-chip">
                <span class="badge badge-<?= e($key) ?>"><?= e($label) ?></span>
                <strong><?= (int) ($stats['by_status'][$key] ?? 0) ?></strong>
            </a>
        <?php endforeach; ?>
    </div>
</section>

<section class="card">
    <div class="card-head">
        <h2>Últimos leads recebidos</h2>
        <a href="<?= e(url('admin/leads.php')) ?>" class="btn btn-ghost btn-sm">Ver todos</a>
    </div>
    <?php if (!$latest): ?>
        <p class="empty">Nenhum interessado ainda. Os envios do formulário do site aparecerão aqui.</p>
    <?php else: ?>
    <div class="table-wrap">
        <table class="table">
            <thead><tr><th>Data/hora</th><th>Nome</th><th>Empresa</th><th>WhatsApp</th><th>Origem</th><th>Status</th></tr></thead>
            <tbody>
            <?php foreach ($latest as $l): $link = url('admin/lead.php?id=' . $l['id']); ?>
                <tr class="row-link" data-href="<?= e($link) ?>">
                    <td class="nowrap"><?= e(format_datetime($l['created_at'])) ?></td>
                    <td><a href="<?= e($link) ?>"><strong><?= e($l['name']) ?></strong></a><br><span class="muted small"><?= e($l['email']) ?></span></td>
                    <td><?= e($l['company']) ?></td>
                    <td class="nowrap"><?= e($l['whatsapp']) ?></td>
                    <td><?= e(lead_origin($l)) ?></td>
                    <td><span class="badge badge-<?= e($l['status']) ?>"><?= e(status_label($l['status'])) ?></span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</section>
<?php admin_footer();
