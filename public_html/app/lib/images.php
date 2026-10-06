<?php
defined('APP') or exit;

/**
 * Upload e processamento de imagens.
 *  - valida erro de upload, tamanho, extensão, MIME real (finfo) e conteúdo (getimagesize);
 *  - gera nome de arquivo aleatório e seguro;
 *  - recodifica a imagem com GD (remove metadados/conteúdo embutido e otimiza o peso);
 *  - gera versões redimensionadas (grande e miniatura).
 */

const IMAGE_TYPES = [
    'jpg'  => ['mime' => 'image/jpeg', 'type' => IMAGETYPE_JPEG],
    'jpeg' => ['mime' => 'image/jpeg', 'type' => IMAGETYPE_JPEG],
    'png'  => ['mime' => 'image/png',  'type' => IMAGETYPE_PNG],
    'webp' => ['mime' => 'image/webp', 'type' => IMAGETYPE_WEBP],
];

/** Perfis de processamento por categoria. */
function image_profiles(): array
{
    return [
        'hero'        => ['large' => 2560, 'thumb' => 1280, 'keep_original' => false],
        'topographic' => ['large' => 2400, 'thumb' => null, 'keep_original' => true],
        'gallery'     => ['large' => 2048, 'thumb' => 900,  'keep_original' => false],
        'og'          => ['large' => 1200, 'thumb' => null, 'keep_original' => false],
        'favicon'     => ['square' => 256],
    ];
}

function upload_max_bytes(): int
{
    return (int) config('upload_max_mb', 15) * 1024 * 1024;
}

function upload_error_message(int $code): string
{
    switch ($code) {
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return 'O arquivo excede o tamanho máximo permitido pelo servidor.';
        case UPLOAD_ERR_PARTIAL:
            return 'O envio foi interrompido. Tente novamente.';
        case UPLOAD_ERR_NO_FILE:
            return 'Nenhum arquivo foi selecionado.';
        default:
            return 'Não foi possível receber o arquivo (código ' . $code . ').';
    }
}

/**
 * Normaliza $_FILES['campo'] (simples ou múltiplo) em lista de arquivos.
 */
function uploaded_files(string $field): array
{
    if (empty($_FILES[$field])) {
        return [];
    }
    $f = $_FILES[$field];
    if (!is_array($f['name'])) {
        return [$f];
    }
    $out = [];
    foreach ($f['name'] as $i => $name) {
        $out[] = [
            'name' => $name, 'type' => $f['type'][$i], 'tmp_name' => $f['tmp_name'][$i],
            'error' => $f['error'][$i], 'size' => $f['size'][$i],
        ];
    }
    return $out;
}

/**
 * Valida um arquivo enviado. Retorna [ext, info] ou lança RuntimeException com mensagem amigável.
 */
function validate_image_upload(array $file): array
{
    $err = (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE);
    if ($err !== UPLOAD_ERR_OK) {
        throw new RuntimeException(upload_error_message($err));
    }
    $tmp = (string) ($file['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        throw new RuntimeException('Arquivo inválido.');
    }
    $size = (int) filesize($tmp);
    if ($size <= 0 || $size > upload_max_bytes()) {
        throw new RuntimeException('Arquivo muito grande. Máximo: ' . config('upload_max_mb', 15) . ' MB.');
    }
    $ext = strtolower(pathinfo((string) ($file['name'] ?? ''), PATHINFO_EXTENSION));
    if (!isset(IMAGE_TYPES[$ext])) {
        throw new RuntimeException('Formato não permitido. Envie JPG, PNG ou WEBP.');
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string) $finfo->file($tmp);
    if ($mime !== IMAGE_TYPES[$ext]['mime']) {
        throw new RuntimeException('O conteúdo do arquivo não corresponde a uma imagem ' . strtoupper($ext) . ' válida.');
    }
    $info = @getimagesize($tmp);
    if (!$info || (int) $info[2] !== IMAGE_TYPES[$ext]['type']) {
        throw new RuntimeException('Imagem corrompida ou inválida.');
    }
    if ($info[0] < 16 || $info[1] < 16 || $info[0] > 14000 || $info[1] > 14000 || $info[0] * $info[1] > 80000000) {
        throw new RuntimeException('Dimensões da imagem fora do limite permitido.');
    }
    return [$ext === 'jpeg' ? 'jpg' : $ext, $info];
}

function random_filename(string $ext, string $suffix = ''): string
{
    return date('Ymd') . '-' . bin2hex(random_bytes(10)) . ($suffix !== '' ? '-' . $suffix : '') . '.' . $ext;
}

function ensure_upload_dir(): void
{
    if (!is_dir(UPLOAD_DIR) && !mkdir(UPLOAD_DIR, 0755, true)) {
        throw new RuntimeException('A pasta uploads/ não existe e não pôde ser criada.');
    }
    if (!is_writable(UPLOAD_DIR)) {
        throw new RuntimeException('A pasta uploads/ não tem permissão de escrita (use 755).');
    }
}

/** Carrega imagem GD a partir do arquivo, aplicando a orientação EXIF de fotos JPEG. */
function gd_load(string $path, string $ext)
{
    switch ($ext) {
        case 'jpg':
            $img = @imagecreatefromjpeg($path);
            if ($img && function_exists('exif_read_data')) {
                $exif = @exif_read_data($path);
                $o = (int) ($exif['Orientation'] ?? 1);
                if ($o === 3) {
                    $img = imagerotate($img, 180, 0);
                } elseif ($o === 6) {
                    $img = imagerotate($img, -90, 0);
                } elseif ($o === 8) {
                    $img = imagerotate($img, 90, 0);
                }
            }
            return $img;
        case 'png':
            return @imagecreatefrompng($path);
        case 'webp':
            return function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false;
    }
    return false;
}

function gd_save($img, string $path, string $ext): bool
{
    switch ($ext) {
        case 'jpg':
            imageinterlace($img, true); // JPEG progressivo
            return imagejpeg($img, $path, 82);
        case 'png':
            imagesavealpha($img, true);
            return imagepng($img, $path, 7);
        case 'webp':
            return imagewebp($img, $path, 80);
    }
    return false;
}

/** Redimensiona mantendo proporção, sem ampliar. */
function gd_resize($src, int $max)
{
    $w = imagesx($src);
    $h = imagesy($src);
    $scale = min(1, $max / max($w, $h));
    $nw = max(1, (int) round($w * $scale));
    $nh = max(1, (int) round($h * $scale));
    $dst = imagecreatetruecolor($nw, $nh);
    imagealphablending($dst, false);
    imagesavealpha($dst, true);
    $transparent = imagecolorallocatealpha($dst, 255, 255, 255, 127);
    imagefilledrectangle($dst, 0, 0, $nw, $nh, $transparent);
    imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);
    return $dst;
}

/**
 * Processa e salva uma imagem conforme o perfil.
 * Retorna ['image' => 'uploads/..', 'thumb' => 'uploads/..'|null, 'full' => 'uploads/..'|null, 'width' => int, 'height' => int]
 */
function store_image(array $file, string $profile): array
{
    $profiles = image_profiles();
    if (!isset($profiles[$profile])) {
        throw new RuntimeException('Categoria de imagem inválida.');
    }
    $cfg = $profiles[$profile];
    [$ext, $info] = validate_image_upload($file);
    ensure_upload_dir();
    @ini_set('memory_limit', '512M');
    @set_time_limit(120);

    $tmp = $file['tmp_name'];
    $src = gd_load($tmp, $ext);
    if (!$src) {
        throw new RuntimeException('Não foi possível processar a imagem. Tente um arquivo JPG ou PNG.');
    }

    $result = ['image' => null, 'thumb' => null, 'full' => null, 'width' => imagesx($src), 'height' => imagesy($src)];

    if ($profile === 'favicon') {
        $size = $cfg['square'];
        $w = imagesx($src);
        $h = imagesy($src);
        $side = min($w, $h);
        $dst = imagecreatetruecolor($size, $size);
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagefilledrectangle($dst, 0, 0, $size, $size, imagecolorallocatealpha($dst, 255, 255, 255, 127));
        imagecopyresampled($dst, $src, 0, 0, (int) (($w - $side) / 2), (int) (($h - $side) / 2), $size, $size, $side, $side);
        $name = random_filename('png', 'icon');
        imagepng($dst, UPLOAD_DIR . '/' . $name, 7);
        imagedestroy($dst);
        imagedestroy($src);
        $result['image'] = 'uploads/' . $name;
        return $result;
    }

    $base = random_filename($ext);
    $stem = pathinfo($base, PATHINFO_FILENAME);

    $large = gd_resize($src, $cfg['large']);
    $largeName = $base;
    if (!gd_save($large, UPLOAD_DIR . '/' . $largeName, $ext)) {
        throw new RuntimeException('Falha ao gravar a imagem no servidor.');
    }
    $result['image'] = 'uploads/' . $largeName;
    $result['width'] = imagesx($large);
    $result['height'] = imagesy($large);
    imagedestroy($large);

    if (!empty($cfg['thumb'])) {
        $thumb = gd_resize($src, $cfg['thumb']);
        $thumbName = $stem . '-sm.' . $ext;
        gd_save($thumb, UPLOAD_DIR . '/' . $thumbName, $ext);
        imagedestroy($thumb);
        $result['thumb'] = 'uploads/' . $thumbName;
    }

    // Topográfico: mantém a resolução máxima para análise detalhada (recodificada, sem metadados).
    if (!empty($cfg['keep_original']) && max(imagesx($src), imagesy($src)) > $cfg['large']) {
        $fullName = $stem . '-full.' . $ext;
        if (gd_save($src, UPLOAD_DIR . '/' . $fullName, $ext)) {
            $result['full'] = 'uploads/' . $fullName;
        }
    }
    imagedestroy($src);
    return $result;
}

/** Remove com segurança um arquivo de uploads/ (ignora caminhos fora da pasta). */
function delete_media(?string $path): void
{
    if (!$path || strpos($path, 'uploads/') !== 0) {
        return;
    }
    $full = realpath(APP_ROOT . '/' . $path);
    $dir = realpath(UPLOAD_DIR);
    if ($full && $dir && strpos($full, $dir . DIRECTORY_SEPARATOR) === 0 && is_file($full)) {
        @unlink($full);
    }
}
