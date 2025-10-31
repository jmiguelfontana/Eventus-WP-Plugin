<?php
if (!defined('ABSPATH')) {
    exit;
}

class BA_Device_Renderer {

    /**
     * Render full device detail markup.
     *
     * @param array $item
     * @return string
     */
    public function render_device(array $item) {
        $sections = [];
        $sections[] = $this->render_header($item);
        $sections[] = $this->render_basic_info($item);
        $sections[] = $this->render_manufacturer($item['manufacturer'] ?? []);
        $sections[] = $this->render_nomenclatures($item['nomenclaturesTerms'] ?? []);
        $sections[] = $this->render_identifiers($item['identifiers'] ?? []);
        $sections[] = $this->render_barcodes($item['identifiers'] ?? []);
        $sections[] = $this->render_production_identifiers($item['productionIdentifiers'] ?? []);

        $body = implode('', array_filter($sections));
        return sprintf('<div class="ba-result">%s</div>', $body);
    }

    /**
     * Render an error notice.
     *
     * @param WP_Error $error
     * @return string
     */
    public function render_error($error) {
        if ($error instanceof WP_Error) {
            return $this->render_notice($error->get_error_message(), 'error');
        }
        return $this->render_notice((string) $error, 'error');
    }

    /**
     * Render an empty result notice.
     *
     * @return string
     */
    public function render_empty() {
        return $this->render_notice(esc_html__('Sin resultado.', 'eventusapi'), 'warning');
    }

    /**
     * Render a generic notice wrapper.
     *
     * @param string $message
     * @param string $type
     * @return string
     */
    public function render_notice($message, $type = 'info') {
        $allowed = ['error', 'warning', 'success', 'info'];
        $type = in_array($type, $allowed, true) ? $type : 'info';
        $class = sprintf('notice notice-%s', $type);

        return sprintf(
            '<div class="ba-result"><div class="%s"><p>%s</p></div></div>',
            esc_attr($class),
            esc_html($message)
        );
    }

    private function render_header(array $item) {
        $translations = isset($item['translations']) && is_array($item['translations']) ? $item['translations'] : [];
        $preferred    = $this->pick_preferred_translation($translations);
        $name         = isset($preferred['name']) ? $preferred['name'] : '';
        $description  = isset($preferred['description']) ? $preferred['description'] : '';

        $output = '';
        if ($name !== '') {
            $output .= '<h2 style="margin-top:0;">' . esc_html($name) . '</h2>';
        }
        $descToShow = $description !== '' ? $description : __('N/D', 'eventusapi');
        $output .= '<p><em>' . esc_html($descToShow) . '</em></p>';
        return $output;
    }

    private function render_basic_info(array $item) {
        $implantable = !empty($item['implantable']) ? esc_html__('Si', 'eventusapi') : esc_html__('No', 'eventusapi');
        $riskClass   = isset($item['riskClass']) && $item['riskClass'] !== '' ? $item['riskClass'] : 'N/D';

        $html  = '<h3>' . esc_html__('Informacion basica', 'eventusapi') . '</h3>';
        $html .= '<ul>';
        $html .= '<li><strong>' . esc_html__('Implantable:', 'eventusapi') . '</strong> ' . esc_html($implantable) . '</li>';
        $html .= '<li><strong>' . esc_html__('Clase de riesgo:', 'eventusapi') . '</strong> ' . esc_html($riskClass) . '</li>';
        $html .= '</ul>';

        return $html;
    }

    private function render_manufacturer($manufacturer) {
        if (empty($manufacturer) || !is_array($manufacturer)) {
            return '';
        }

        $fields = [
            esc_html__('Nombre:', 'eventusapi')   => $manufacturer['name'] ?? '',
            esc_html__('Ref:', 'eventusapi')      => $manufacturer['ref'] ?? '',
            esc_html__('Direccion:', 'eventusapi') => $manufacturer['address'] ?? '',
            esc_html__('Pais:', 'eventusapi')     => $manufacturer['country'] ?? '',
            esc_html__('Email:', 'eventusapi')    => $manufacturer['email'] ?? '',
            esc_html__('Teléfono:', 'eventusapi') => $manufacturer['phone'] ?? '',
        ];

        $items = [];
        foreach ($fields as $label => $value) {
            if ($value === '') {
                continue;
            }
            $items[] = '<li><strong>' . esc_html($label) . '</strong> ' . esc_html($value) . '</li>';
        }

        if (empty($items)) {
            return '';
        }

        return '<h3>' . esc_html__('Fabricante', 'eventusapi') . '</h3><ul>' . implode('', $items) . '</ul>';
    }

    private function render_nomenclatures($nomenclatures) {
        if (empty($nomenclatures) || !is_array($nomenclatures)) {
            return '';
        }

        $items = [];
        foreach ($nomenclatures as $nom) {
            $term = isset($nom['nomenclatureTerm']) && is_array($nom['nomenclatureTerm']) ? $nom['nomenclatureTerm'] : [];
            if (!$term) {
                continue;
            }

            $translations = isset($term['translations']) && is_array($term['translations']) ? $term['translations'] : [];
            $preferred    = $this->pick_preferred_translation($translations);
            $name         = $preferred['name'] ?? '';
            $description  = $preferred['description'] ?? '';

            $typeName = $term['type']['name'] ?? '';
            $typeRef  = $term['type']['ref'] ?? '';
            $code     = $term['ref'] ?? '';

            $detail  = '<li class="ba-detail-item ba-nomenclature-item">';
            if ($name !== '') {
                $detail .= '<strong class="ba-detail-name ba-nomenclature-name">' . esc_html($name) . '</strong>';
            }

            $meta = [];
            $typeDisplay = '';
            if ($typeName !== '') {
                $typeDisplay = $typeName;
            }
            if ($typeRef !== '') {
                if ($typeDisplay === '') {
                    $typeDisplay = $typeRef;
                } elseif (stripos($typeDisplay, $typeRef) === false) {
                    $typeDisplay = sprintf('%s (%s)', $typeDisplay, $typeRef);
                }
            }
            if ($typeDisplay !== '') {
                $meta[] = [
                    'label' => esc_html__('Tipo:', 'eventusapi'),
                    'value' => $typeDisplay,
                ];
            }
            if ($code !== '') {
                $meta[] = [
                    'label' => esc_html__('Codigo:', 'eventusapi'),
                    'value' => $code,
                ];
            }

            $detail .= $this->render_detail_meta($meta);

            if ($description !== '') {
                $detail .= '<p class="ba-detail-description ba-nomenclature-description">' . esc_html($description) . '</p>';
            }

            $detail .= '</li>';
            $items[] = $detail;
        }

        if (empty($items)) {
            return '';
        }

        return '<h3>' . esc_html__('Nomenclaturas', 'eventusapi') . '</h3><ul class="ba-detail-list ba-nomenclature-list">' . implode('', $items) . '</ul>';
    }

    private function render_barcodes($identifiers) {
        $barcodes = $this->collect_barcode_values($identifiers);
        if (empty($barcodes)) {
            return '';
        }

        $items = [];
        foreach ($barcodes as $barcode) {
            $format = $barcode['format'];
            $value  = $barcode['value'];
            $label  = $barcode['label'];
            $aria   = $barcode['aria'];

            $items[] = sprintf(
                '<li class="ba-detail-item ba-barcode-item" data-barcode-format="%s" data-barcode-value="%s" data-barcode-label="%s"><strong class="ba-detail-name ba-barcode-name">%s</strong><div class="ba-barcode-canvas" aria-hidden="true"></div><code class="ba-barcode-value">%s</code></li>',
                esc_attr($format),
                esc_attr($value),
                esc_attr($aria),
                esc_html($label),
                esc_html($value)
            );
        }

        return '<h3>' . esc_html__('Códigos de barras', 'eventusapi') . '</h3><ul class="ba-detail-list ba-barcode-list">' . implode('', $items) . '</ul>';
    }

    private function render_identifiers($identifiers) {
        if (empty($identifiers) || !is_array($identifiers)) {
            return '';
        }

        $rows = [];
        foreach ($identifiers as $identifier) {
            $typeName  = $identifier['type']['name'] ?? '';
            $agency    = $identifier['agency']['name'] ?? '';
            $reference = $identifier['ref'] ?? '';

            $rows[] = sprintf(
                '<tr><td>%s</td><td>%s</td><td>%s</td></tr>',
                esc_html($typeName),
                esc_html($agency),
                esc_html($reference)
            );
        }

        if (empty($rows)) {
            return '';
        }

        $header = sprintf(
            '<tr><th>%s</th><th>%s</th><th>%s</th></tr>',
            esc_html__('Tipo', 'eventusapi'),
            esc_html__('Agencia', 'eventusapi'),
            esc_html__('Referencia', 'eventusapi')
        );

        $table = '<table style="width:100%;border-collapse:collapse;" border="1" cellpadding="5">';
        $table .= $header . implode('', $rows) . '</table>';

        return '<h3>' . esc_html__('Identificadores', 'eventusapi') . '</h3>' . $table;
    }

    private function render_production_identifiers($productionIdentifiers) {
        if (empty($productionIdentifiers) || !is_array($productionIdentifiers)) {
            return '';
        }

        $items = [];
        foreach ($productionIdentifiers as $prod) {
            $type     = isset($prod['type']) && is_array($prod['type']) ? $prod['type'] : [];
            $typeName = isset($type['name']) && is_string($type['name']) ? $type['name'] : '';
            $typeRef  = isset($type['ref']) && is_string($type['ref']) ? $type['ref'] : '';

            if ($typeName === '' && $typeRef !== '') {
                $typeName = $typeRef;
            }

            if ($typeName === '') {
                continue;
            }

            $items[] = '<li class="ba-detail-item ba-production-item"><strong class="ba-detail-name ba-production-name">' . esc_html($typeName) . '</strong></li>';
        }

        if (empty($items)) {
            return '';
        }

        return '<h3>Production Identifiers</h3><ul class="ba-detail-list ba-production-list">' . implode('', $items) . '</ul>';
    }

    /**
     * Collect barcode-capable identifiers.
     *
     * @param array $identifiers
     * @return array
     */
    private function collect_barcode_values($identifiers) {
        if (empty($identifiers) || !is_array($identifiers)) {
            return [];
        }

        $detected = [];

        foreach ($identifiers as $identifier) {
            if (!is_array($identifier)) {
                continue;
            }

            $value = trim((string) ($identifier['ref'] ?? ''));
            if ($value === '') {
                continue;
            }

            $agencyName = strtolower((string) ($identifier['agency']['name'] ?? $identifier['agency']['ref'] ?? ''));
            $typeName   = strtolower((string) ($identifier['type']['name'] ?? ''));
            $typeRef    = strtolower((string) ($identifier['type']['ref'] ?? ''));

            $format = '';
            if (strpos($agencyName, 'gs1') !== false || strpos($typeName, 'gs1') !== false || strpos($typeRef, 'gs1') !== false) {
                $format = 'gs1';
            } elseif (strpos($agencyName, 'hibc') !== false || strpos($typeName, 'hibc') !== false || strpos($typeRef, 'hibc') !== false) {
                $format = 'hibc';
            }

            if ($format === '') {
                continue;
            }

            $key = $format . '|' . $value;
            if (isset($detected[$key])) {
                continue;
            }

            $label = strtoupper($format);
            $aria  = sprintf(esc_html__('Codigo de barras %s', 'eventusapi'), $label);

            $detected[$key] = [
                'format' => $format,
                'value'  => $value,
                'label'  => $label,
                'aria'   => $aria,
            ];
        }

        return array_values($detected);
    }

    /**
     * Render detail meta badges.
     *
     * @param array $metaPairs
     * @return string
     */
    private function render_detail_meta(array $metaPairs) {
        if (empty($metaPairs)) {
            return '';
        }

        $items = [];
        foreach ($metaPairs as $pair) {
            $label = isset($pair['label']) ? $pair['label'] : '';
            $value = isset($pair['value']) ? $pair['value'] : '';
            if ($value === '') {
                continue;
            }
            $items[] = sprintf(
                '<li class="ba-detail-meta-item"><strong>%s</strong><span>%s</span></li>',
                esc_html($label),
                esc_html($value)
            );
        }

        if (empty($items)) {
            return '';
        }

        return '<ul class="ba-detail-meta">' . implode('', $items) . '</ul>';
    }

    /**
     * Pick translation matching the current locale where possible.
     *
     * @param array $translations
     * @return array
     */
    private function pick_preferred_translation($translations) {
        if (empty($translations) || !is_array($translations)) {
            return [];
        }

        $list = array_values(array_filter($translations, 'is_array'));
        if (!$list) {
            return [];
        }

        $preferredLocale = function_exists('get_locale') ? strtolower(str_replace('-', '_', (string) get_locale())) : '';
        if ($preferredLocale) {
            foreach ($list as $tr) {
                $locale = strtolower(str_replace('-', '_', $tr['locale'] ?? ''));
                if ($locale === $preferredLocale) {
                    return $tr;
                }
            }
            $lang = substr($preferredLocale, 0, 2);
            if ($lang) {
                foreach ($list as $tr) {
                    $locale = strtolower(str_replace('-', '_', $tr['locale'] ?? ''));
                    if ($locale === $lang || strpos($locale, $lang . '_') === 0) {
                        return $tr;
                    }
                }
            }
        }

        return $list[0];
    }
}


















