<?php
defined('APP') or exit;

/**
 * Definição dos campos editáveis da propriedade.
 * type: text | textarea | lines (um item por linha)
 * Campos vazios simplesmente não aparecem no site.
 */
function property_fields(): array
{
    return [
        'Primeira dobra (topo da página)' => [
            'hero_eyebrow'  => ['label' => 'Chamada acima do título', 'type' => 'text', 'max' => 190, 'placeholder' => 'Ex.: Oportunidade de aquisição'],
            'headline'      => ['label' => 'Headline (título principal)', 'type' => 'text', 'max' => 255, 'placeholder' => 'Ex.: 260.000 m² para um novo projeto'],
            'subheadline'   => ['label' => 'Subheadline', 'type' => 'textarea', 'max' => 500, 'rows' => 2, 'placeholder' => 'Frase de apoio abaixo do título'],
            'area_label'    => ['label' => 'Rótulo da área', 'type' => 'text', 'max' => 190, 'placeholder' => 'Ex.: Área total aproximada'],
            'area'          => ['label' => 'Área do terreno', 'type' => 'text', 'max' => 100, 'placeholder' => 'Ex.: 260.000 m²'],
            'hero_cta_text' => ['label' => 'Texto do botão principal', 'type' => 'text', 'max' => 120, 'placeholder' => 'Ex.: Quero receber mais informações'],
            'hero_image_alt'=> ['label' => 'Descrição da imagem principal (acessibilidade/SEO)', 'type' => 'text', 'max' => 255, 'placeholder' => 'Ex.: Vista aérea da propriedade'],
        ],
        'A propriedade' => [
            'description'   => ['label' => 'Descrição', 'type' => 'textarea', 'max' => 8000, 'rows' => 5, 'placeholder' => '[PENDENTE] Texto de apresentação da propriedade'],
            'location'      => ['label' => 'Localização', 'type' => 'textarea', 'max' => 2000, 'rows' => 2, 'placeholder' => '[PENDENTE] Ex.: região, referência, distância de pontos importantes'],
            'municipality'  => ['label' => 'Município', 'type' => 'text', 'max' => 190, 'placeholder' => '[PENDENTE] Município'],
            'state'         => ['label' => 'Estado', 'type' => 'text', 'max' => 100, 'placeholder' => '[PENDENTE] Estado (UF)'],
            'access_info'   => ['label' => 'Acesso', 'type' => 'textarea', 'max' => 3000, 'rows' => 3, 'placeholder' => '[PENDENTE] Vias de acesso, rodovias, condições'],
            'infrastructure'=> ['label' => 'Infraestrutura', 'type' => 'textarea', 'max' => 3000, 'rows' => 3, 'placeholder' => '[PENDENTE] Energia, água, vias internas etc.'],
            'vocation'      => ['label' => 'Vocação da propriedade', 'type' => 'textarea', 'max' => 3000, 'rows' => 3, 'placeholder' => '[PENDENTE] Ex.: usos possíveis / potencial'],
            'urban_info'    => ['label' => 'Informações urbanísticas', 'type' => 'textarea', 'max' => 3000, 'rows' => 3, 'placeholder' => '[PENDENTE] Zoneamento, parâmetros urbanísticos'],
            'documentation' => ['label' => 'Documentação', 'type' => 'textarea', 'max' => 3000, 'rows' => 3, 'placeholder' => '[PENDENTE] Situação documental'],
            'characteristics' => ['label' => 'Características (uma por linha)', 'type' => 'lines', 'max' => 5000, 'rows' => 5, 'placeholder' => "[PENDENTE] Uma característica por linha"],
            'differentials' => ['label' => 'Diferenciais (um por linha)', 'type' => 'lines', 'max' => 5000, 'rows' => 5, 'placeholder' => "[PENDENTE] Um diferencial por linha"],
            'additional_info' => ['label' => 'Informações complementares', 'type' => 'textarea', 'max' => 8000, 'rows' => 4, 'placeholder' => '[PENDENTE] Outros dados relevantes'],
        ],
        'Conheça a área (planta da propriedade)' => [
            'topographic_title' => ['label' => 'Título da seção', 'type' => 'text', 'max' => 190, 'placeholder' => 'Ex.: Conheça a área'],
            'topographic_text'  => ['label' => 'Texto da seção', 'type' => 'textarea', 'max' => 2000, 'rows' => 2, 'placeholder' => 'Texto de apoio à planta da área'],
            'topographic_alt'   => ['label' => 'Descrição da imagem da planta', 'type' => 'text', 'max' => 255, 'placeholder' => 'Ex.: Planta da propriedade com perímetro e coordenadas'],
        ],
        'Chamada intermediária (CTA)' => [
            'mid_cta_title'  => ['label' => 'Título', 'type' => 'text', 'max' => 190, 'placeholder' => 'Ex.: Tem interesse nesta propriedade?'],
            'mid_cta_text'   => ['label' => 'Texto', 'type' => 'textarea', 'max' => 1000, 'rows' => 2, 'placeholder' => ''],
            'mid_cta_button' => ['label' => 'Texto do botão', 'type' => 'text', 'max' => 120, 'placeholder' => 'Ex.: Solicitar contato'],
        ],
        'Formulário' => [
            'form_title'      => ['label' => 'Título do formulário', 'type' => 'text', 'max' => 190, 'placeholder' => 'Ex.: Solicite informações'],
            'form_intro'      => ['label' => 'Texto de apoio', 'type' => 'textarea', 'max' => 1000, 'rows' => 2, 'placeholder' => ''],
            'form_button'     => ['label' => 'Texto do botão de envio', 'type' => 'text', 'max' => 120, 'placeholder' => 'Ex.: Enviar'],
            'success_title'   => ['label' => 'Título da confirmação', 'type' => 'text', 'max' => 190, 'placeholder' => 'Ex.: Recebemos seu interesse.'],
            'success_message' => ['label' => 'Mensagem de confirmação', 'type' => 'textarea', 'max' => 1000, 'rows' => 3, 'placeholder' => ''],
        ],
    ];
}

function property_text_keys(): array
{
    $keys = [];
    foreach (property_fields() as $group) {
        $keys = array_merge($keys, array_keys($group));
    }
    return $keys;
}

/** Retorna o registro da propriedade (cria o registro id=1 se não existir). */
function property(): array
{
    static $cache = null;
    if ($cache !== null) {
        return $cache;
    }
    $row = db_one('SELECT * FROM property WHERE id = 1');
    if (!$row) {
        db_exec('INSERT INTO property (id) VALUES (1)');
        $row = db_one('SELECT * FROM property WHERE id = 1');
    }
    // Atualiza o texto padrão antigo (que chamava a imagem de "levantamento topográfico").
    // Só troca se o texto ainda for exatamente o original; textos editados no painel são mantidos.
    $legacyTopoText = 'Levantamento topográfico do terreno. Clique na imagem para ampliar e analisar os detalhes.';
    if (($row['topographic_text'] ?? '') === $legacyTopoText) {
        $row['topographic_text'] = 'Planta da propriedade com o perímetro da área, a identificação dos vértices, as coordenadas e os confrontantes. Clique na imagem para ampliar e analisar os detalhes.';
        db_exec('UPDATE property SET topographic_text = ? WHERE id = 1', [$row['topographic_text']]);
    }
    foreach ($row as $k => $v) {
        $row[$k] = $v === null ? '' : (string) $v;
    }
    return $cache = $row;
}

function update_property_texts(array $data): void
{
    $keys = property_text_keys();
    $sets = [];
    $params = [];
    foreach ($keys as $k) {
        if (array_key_exists($k, $data)) {
            $sets[] = "`$k` = ?";
            $params[] = $data[$k] === '' ? null : $data[$k];
        }
    }
    if (!$sets) {
        return;
    }
    db_exec('UPDATE property SET ' . implode(', ', $sets) . ' WHERE id = 1', $params);
}

function update_property_images(array $data): void
{
    $allowed = ['hero_image', 'hero_image_thumb', 'topographic_image', 'topographic_full'];
    $sets = [];
    $params = [];
    foreach ($data as $k => $v) {
        if (in_array($k, $allowed, true)) {
            $sets[] = "`$k` = ?";
            $params[] = $v;
        }
    }
    if ($sets) {
        db_exec('UPDATE property SET ' . implode(', ', $sets) . ' WHERE id = 1', $params);
    }
}

function gallery_items(): array
{
    return db_all('SELECT * FROM gallery ORDER BY sort_order ASC, id ASC');
}

/** Itens de informação exibidos na seção "A propriedade" (somente os preenchidos). */
function property_facts(array $p): array
{
    $facts = [];
    $map = [
        'location'       => 'Localização',
        'municipality'   => 'Município',
        'state'          => 'Estado',
        'access_info'    => 'Acesso',
        'infrastructure' => 'Infraestrutura',
        'vocation'       => 'Vocação',
        'urban_info'     => 'Informações urbanísticas',
        'documentation'  => 'Documentação',
    ];
    foreach ($map as $key => $label) {
        if (filled($p[$key] ?? '')) {
            $facts[] = ['key' => $key, 'label' => $label, 'value' => $p[$key]];
        }
    }
    return $facts;
}
