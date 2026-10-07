<?php
defined('APP') or exit;

/**
 * Localização da propriedade no Google Maps.
 * Guardada na tabela settings (map_lat, map_lng, map_link, map_zoom, map_type, map_title, map_text).
 */

const MAP_DEFAULTS = [
    'map_lat'   => '-5.880816',
    'map_lng'   => '-35.293365',
    'map_link'  => 'https://maps.app.goo.gl/NswKX8wBaycpBT298',
    'map_zoom'  => '15',
    'map_type'  => 'h',
    'map_title' => 'Localização',
    'map_text'  => 'Localização exata da propriedade. Use o mapa para visualizar o entorno e os acessos.',
];

/** Grava os valores iniciais do mapa uma única vez (instalações já existentes recebem o ponto informado). */
function ensure_map_defaults(): void
{
    if (array_key_exists('map_lat', settings())) {
        return;
    }
    foreach (MAP_DEFAULTS as $k => $v) {
        db_exec('INSERT IGNORE INTO settings (setting_key, setting_value) VALUES (?, ?)', [$k, $v]);
    }
    settings(true);
}

/** Dados do mapa prontos para a página, ou null quando não há localização cadastrada. */
function map_data(): ?array
{
    $lat = setting('map_lat');
    $lng = setting('map_lng');
    if (!valid_coordinates($lat, $lng)) {
        return null;
    }
    $zoom = max(3, min(20, (int) setting('map_zoom', '15')));
    $type = in_array(setting('map_type', 'h'), ['m', 'k', 'h'], true) ? setting('map_type', 'h') : 'h';
    $point = $lat . ',' . $lng;
    return [
        'lat'   => $lat,
        'lng'   => $lng,
        'title' => setting('map_title', 'Localização'),
        'text'  => setting('map_text'),
        'embed' => 'https://maps.google.com/maps?' . http_build_query(['q' => $point, 't' => $type, 'z' => $zoom, 'hl' => 'pt-BR', 'output' => 'embed']),
        'open'  => setting('map_link') ?: 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($point),
        'route' => 'https://www.google.com/maps/dir/?api=1&destination=' . rawurlencode($point),
    ];
}

function valid_coordinates(string $lat, string $lng): bool
{
    return is_numeric($lat) && is_numeric($lng)
        && (float) $lat >= -90 && (float) $lat <= 90
        && (float) $lng >= -180 && (float) $lng <= 180
        && !((float) $lat == 0.0 && (float) $lng == 0.0);
}

/**
 * Extrai latitude/longitude de um link do Google Maps ou de coordenadas digitadas.
 * Aceita: "-5.88, -35.29", links com @lat,lng, ?q=lat,lng, /search/lat,lng, !3dlat!4dlng
 * e links curtos (maps.app.goo.gl / goo.gl), que são resolvidos pelo servidor.
 * Retorna [lat, lng] ou null.
 */
function parse_map_location(string $input): ?array
{
    $input = trim($input);
    if ($input === '') {
        return null;
    }
    if (preg_match('~^https?://(maps\.app\.goo\.gl|goo\.gl)/~i', $input)) {
        $resolved = resolve_short_link($input);
        if ($resolved === null) {
            return null;
        }
        $input = $resolved;
    }
    $text = urldecode($input);
    $num = '(-?\d{1,3}(?:\.\d+)?)';
    $patterns = [
        "~!3d{$num}!4d{$num}~",                 // marcador exato do lugar
        "~[?&](?:q|query|ll|destination)=\s*{$num}\s*,\s*\+?{$num}~i",
        "~/(?:search|place|dir)/\s*{$num}\s*,\s*\+?{$num}~i",
        "~@{$num},{$num}~",                       // centro do mapa
        "~^\s*{$num}\s*[,; ]\s*\+?{$num}\s*$~",   // coordenadas digitadas
    ];
    foreach ($patterns as $re) {
        if (preg_match($re, $text, $m) && valid_coordinates($m[1], $m[2])) {
            return [$m[1], $m[2]];
        }
    }
    return null;
}

/** Segue o redirecionamento de um link curto do Google Maps (sem baixar a página). */
function resolve_short_link(string $url): ?string
{
    for ($i = 0; $i < 4; $i++) {
        $location = null;
        if (function_exists('curl_init')) {
            $ch = curl_init($url);
            curl_setopt_array($ch, [
                CURLOPT_NOBODY => true, CURLOPT_HEADER => true, CURLOPT_RETURNTRANSFER => true,
                CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 8, CURLOPT_USERAGENT => 'Mozilla/5.0',
            ]);
            $headers = (string) curl_exec($ch);
            curl_close($ch);
            if (preg_match('/^location:\s*(\S+)/im', $headers, $m)) {
                $location = trim($m[1]);
            }
        }
        if (!$location) {
            return $i > 0 ? $url : null;
        }
        $url = $location;
        if (!preg_match('~^https?://(maps\.app\.goo\.gl|goo\.gl)/~i', $url)) {
            return $url;
        }
    }
    return $url;
}
