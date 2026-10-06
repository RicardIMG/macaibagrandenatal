<?php
/** Exporta os leads (respeitando busca/filtro atuais) em CSV compatível com Excel. */
require __DIR__ . '/../app/admin.php';

$q      = input_str($_GET, 'q', 100);
$status = input_str($_GET, 'status', 30);
[$where, $params] = leads_where($q, $status);

$st = db()->prepare("SELECT * FROM leads $where ORDER BY created_at DESC, id DESC");
$st->execute($params);

$filename = 'leads-' . date('Y-m-d-His') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$out = fopen('php://output', 'w');
fwrite($out, "\xEF\xBB\xBF"); // BOM para acentuação correta no Excel

/** Evita injeção de fórmulas em planilhas (=, +, -, @). */
$safe = function ($v) {
    $v = (string) ($v ?? '');
    return preg_match('/^[=+\-@\t\r]/', $v) ? "'" . $v : $v;
};

fputcsv($out, [
    'ID', 'Data/hora', 'Nome', 'WhatsApp', 'E-mail', 'Empresa', 'Site/Instagram', 'Motivo do interesse',
    'Status', 'Observações internas', 'Origem', 'utm_source', 'utm_medium', 'utm_campaign', 'utm_content',
    'utm_term', 'Página de entrada', 'Referência', 'Atualizado em',
], ';');

while ($l = $st->fetch()) {
    fputcsv($out, array_map($safe, [
        $l['id'], format_datetime($l['created_at'], 'd/m/Y H:i:s'), $l['name'], $l['whatsapp'], $l['email'],
        $l['company'], $l['website_instagram'], $l['interest_reason'], status_label($l['status']), $l['notes'],
        lead_origin($l), $l['utm_source'], $l['utm_medium'], $l['utm_campaign'], $l['utm_content'], $l['utm_term'],
        $l['landing_page'], $l['referrer'], format_datetime($l['updated_at'], 'd/m/Y H:i:s'),
    ]), ';');
}
fclose($out);
