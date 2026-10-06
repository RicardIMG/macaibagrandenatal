<?php
defined('APP') or exit;

/** Token assinado incluído no formulário público (anti-spam / anti-robô). */
function lead_form_token(): string
{
    $ts = (string) time();
    return $ts . '.' . hash_hmac('sha256', 'lead-form|' . $ts, app_secret());
}

/** Valida o token do formulário. Retorna '' se ok ou o motivo. */
function lead_form_token_problem(string $token): string
{
    if (!preg_match('/^(\d{10})\.([a-f0-9]{64})$/', $token, $m)) {
        return 'invalid';
    }
    $expected = hash_hmac('sha256', 'lead-form|' . $m[1], app_secret());
    if (!hash_equals($expected, $m[2])) {
        return 'invalid';
    }
    $age = time() - (int) $m[1];
    if ($age < 3) {
        return 'too_fast';
    }
    if ($age > 86400 * 7) {
        return 'expired';
    }
    return '';
}

/** Normaliza o telefone mantendo "+" inicial e dígitos/espaços/hífens/parênteses. */
function normalize_phone(string $phone): string
{
    $phone = trim($phone);
    $plus = strpos($phone, '+') === 0;
    $clean = preg_replace('/[^\d\s\-()]/', '', $phone);
    $clean = trim(preg_replace('/\s+/', ' ', $clean));
    return ($plus ? '+' : '') . $clean;
}

/**
 * Valida os dados do formulário público. Retorna [dados_limpos, erros].
 */
function validate_lead(array $in): array
{
    $d = [
        'name'              => input_str($in, 'name', 150),
        'whatsapp'          => normalize_phone(input_str($in, 'whatsapp', 40)),
        'email'             => mb_strtolower(input_str($in, 'email', 190)),
        'company'           => input_str($in, 'company', 190),
        'website_instagram' => input_str($in, 'website_instagram', 255),
        'interest_reason'   => input_str($in, 'interest_reason', 5000, true),
    ];
    $errors = [];

    if (mb_strlen($d['name']) < 2) {
        $errors['name'] = 'Informe seu nome.';
    }
    $digits = preg_replace('/\D/', '', $d['whatsapp']);
    if (strlen($digits) < 8 || strlen($digits) > 15) {
        $errors['whatsapp'] = 'Informe um WhatsApp válido (com DDD ou código do país).';
    }
    if (!filter_var($d['email'], FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Informe um e-mail válido.';
    }
    if (mb_strlen($d['company']) < 2) {
        $errors['company'] = 'Informe o nome da empresa.';
    }
    if (mb_strlen($d['website_instagram']) < 3) {
        $errors['website_instagram'] = 'Informe o site ou Instagram da empresa.';
    } elseif (preg_match('/[<>"\s]/', $d['website_instagram'])) {
        $errors['website_instagram'] = 'Informe apenas o endereço, ex.: @empresa ou empresa.com.br';
    }
    if (mb_strlen($d['interest_reason']) < 10) {
        $errors['interest_reason'] = 'Conte-nos brevemente o motivo do seu interesse.';
    }

    // Rastreamento (opcional)
    foreach (['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term'] as $k) {
        $v = input_str($in, $k, 190);
        $d[$k] = $v === '' ? null : $v;
    }
    $landing = input_str($in, 'landing_page', 500);
    $d['landing_page'] = $landing !== '' && preg_match('~^https?://~i', $landing) ? $landing : null;
    $ref = input_str($in, 'referrer', 500);
    if ($ref === '') {
        $ref = mb_substr((string) ($_SERVER['HTTP_REFERER'] ?? ''), 0, 500);
    }
    // Só interessa a referência externa (Google, Instagram etc.), não a própria página.
    $refHost = (string) parse_url($ref, PHP_URL_HOST);
    $ownHost = (string) parse_url('http://' . ($_SERVER['HTTP_HOST'] ?? ''), PHP_URL_HOST);
    $d['referrer'] = $ref !== '' && preg_match('~^https?://~i', $ref) && strcasecmp($refHost, $ownHost) !== 0 ? $ref : null;

    $sid = (string) ($in['submission_id'] ?? '');
    $d['submission_id'] = preg_match('/^[a-f0-9]{32}$/', $sid) ? $sid : null;

    return [$d, $errors];
}

/**
 * Grava o lead. Retorna ['id' => int, 'duplicate' => bool].
 */
function create_lead(array $d): array
{
    // Mesmo envio repetido (clique duplo, reenvio, nova tentativa de rede)
    if ($d['submission_id']) {
        $id = db_value('SELECT id FROM leads WHERE submission_id = ?', [$d['submission_id']]);
        if ($id) {
            return ['id' => (int) $id, 'duplicate' => true];
        }
    }
    // Mesmo e-mail com o mesmo conteúdo nos últimos 30 minutos
    $id = db_value(
        'SELECT id FROM leads WHERE email = ? AND interest_reason = ? AND created_at > ? LIMIT 1',
        [$d['email'], $d['interest_reason'], date('Y-m-d H:i:s', time() - 1800)]
    );
    if ($id) {
        return ['id' => (int) $id, 'duplicate' => true];
    }

    $now = now();
    try {
        db_exec(
            'INSERT INTO leads (submission_id, name, whatsapp, email, company, website_instagram, interest_reason,
                status, utm_source, utm_medium, utm_campaign, utm_content, utm_term, landing_page, referrer,
                ip_address, user_agent, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, \'novo\', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $d['submission_id'], $d['name'], $d['whatsapp'], $d['email'], $d['company'],
                $d['website_instagram'], $d['interest_reason'],
                $d['utm_source'], $d['utm_medium'], $d['utm_campaign'], $d['utm_content'], $d['utm_term'],
                $d['landing_page'], $d['referrer'], client_ip(),
                mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 255), $now, $now,
            ]
        );
    } catch (PDOException $e) {
        // Corrida entre dois envios idênticos simultâneos (chave única)
        if ($e->getCode() === '23000' && $d['submission_id']) {
            $id = db_value('SELECT id FROM leads WHERE submission_id = ?', [$d['submission_id']]);
            if ($id) {
                return ['id' => (int) $id, 'duplicate' => true];
            }
        }
        throw $e;
    }
    return ['id' => (int) db()->lastInsertId(), 'duplicate' => false];
}

function leads_from_ip_last_hour(): int
{
    return (int) db_value(
        'SELECT COUNT(*) FROM leads WHERE ip_address = ? AND created_at > ?',
        [client_ip(), date('Y-m-d H:i:s', time() - 3600)]
    );
}

/** Origem legível do lead: utm_source, domínio de referência ou "Direto". */
function lead_origin(array $l): string
{
    if (!empty($l['utm_source'])) {
        return $l['utm_source'] . (!empty($l['utm_medium']) ? ' / ' . $l['utm_medium'] : '');
    }
    if (!empty($l['referrer'])) {
        $host = parse_url($l['referrer'], PHP_URL_HOST);
        $self = parse_url(site_url(), PHP_URL_HOST);
        if ($host && $host !== $self) {
            return preg_replace('/^www\./', '', $host);
        }
    }
    return 'Direto';
}

/** Monta WHERE para busca/filtro de leads. */
function leads_where(string $q, string $status): array
{
    $where = [];
    $params = [];
    if ($q !== '') {
        $like = '%' . str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q) . '%';
        $digits = preg_replace('/\D/', '', $q);
        $cond = '(name LIKE ? OR email LIKE ? OR company LIKE ? OR whatsapp LIKE ?';
        array_push($params, $like, $like, $like, $like);
        if (strlen($digits) >= 4) {
            $cond .= " OR REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(whatsapp,' ',''),'-',''),'(',''),')',''),'+','') LIKE ?";
            $params[] = '%' . $digits . '%';
        }
        $where[] = $cond . ')';
    }
    if ($status !== '' && isset(lead_statuses()[$status])) {
        $where[] = 'status = ?';
        $params[] = $status;
    }
    return [$where ? 'WHERE ' . implode(' AND ', $where) : '', $params];
}

function lead_stats(): array
{
    $today = date('Y-m-d 00:00:00');
    return [
        'total' => (int) db_value('SELECT COUNT(*) FROM leads'),
        'today' => (int) db_value('SELECT COUNT(*) FROM leads WHERE created_at >= ?', [$today]),
        'd7'    => (int) db_value('SELECT COUNT(*) FROM leads WHERE created_at >= ?', [date('Y-m-d H:i:s', strtotime('-7 days'))]),
        'd30'   => (int) db_value('SELECT COUNT(*) FROM leads WHERE created_at >= ?', [date('Y-m-d H:i:s', strtotime('-30 days'))]),
        'by_status' => array_column(
            db_all('SELECT status, COUNT(*) AS n FROM leads GROUP BY status'),
            'n',
            'status'
        ),
    ];
}
