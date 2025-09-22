<?php
if (!defined('ABSPATH')) {
    exit;
}

class BA_Device_Renderer {
    private static $detailsScriptPrinted = false;

    public static function render_items(array $items) {
        if (empty($items)) {
            return;
        }

        self::render_table_open();

        foreach ($items as $idx => $item) {
            $row = self::prepare_row($item, $idx);
            self::render_summary_row($row);
            self::render_detail_row($item, $row['toggle_id']);
        }

        self::render_table_close();
        self::maybe_output_script();
    }

    public static function summarize_item(array $item, $idx) {
        return self::prepare_row($item, $idx);
    }

    public static function get_details_html(array $item) {
        return self::capture_device_details_html($item);
    }

    private static function render_table_open() {
        echo '<table class="widefat fixed striped ba-devices-table" style="width:100%;max-width:100%;">';
        echo '<thead><tr>'
            . '<th>Nombre del dispositivo</th>'
            . '<th>Primary ID</th>'
            . '<th>Fabricante</th>'
            . '<th>Version/Model</th>'
            . '<th>Catalog Number</th>'
            . '<th></th>'
            . '</tr></thead><tbody>';
    }

    private static function render_table_close() {
        echo '</tbody></table>';
    }

    private static function prepare_row(array $item, $idx) {
        $name          = self::extract_translation_name($item);
        $mainId        = self::extract_main_identifier($item);
        $manufacturer  = self::build_manufacturer_label($item);
        $versionModel  = self::extract_identifier_value_by_keywords($item, ['version', 'model']);
        $catalogNumber = self::extract_identifier_value_by_keywords($item, ['catalog']);

        return [
            'display_name' => $name !== '' ? $name : ($mainId !== '' ? $mainId : 'Producto ' . ($idx + 1)),
            'main_id' => $mainId,
            'manufacturer' => $manufacturer,
            'version_model' => $versionModel,
            'catalog_number' => $catalogNumber,
            'toggle_id' => 'ba-device-details-' . $idx,
        ];
    }

    private static function render_summary_row(array $row) {
        echo '<tr class="ba-device-row">';
        echo '<td><strong>' . esc_html($row['display_name']) . '</strong></td>';
        echo '<td>' . esc_html($row['main_id']) . '</td>';
        echo '<td>' . esc_html($row['manufacturer']) . '</td>';
        echo '<td>' . esc_html($row['version_model']) . '</td>';
        echo '<td>' . esc_html($row['catalog_number']) . '</td>';
        echo '<td class="dt-control" role="button" aria-expanded="false"></td>';
        echo '</tr>';
    }

    private static function render_detail_row(array $item, $toggleId) {
        $detailsHtml = self::capture_device_details_html($item);
        echo '<tr id="' . esc_attr($toggleId) . '" class="ba-device-details" style="display:none;">';
        echo '<td colspan="6">' . ($detailsHtml !== '' ? $detailsHtml : '<em>Sin detalles adicionales.</em>') . '</td>';
        echo '</tr>';
    }

    private static function capture_device_details_html(array $item) {
        ob_start();
        self::render_item_tables($item);
        return trim(ob_get_clean());
    }

    public static function render_item_tables(array $item) {
        self::render_device_overview_table($item);
        self::render_device_identifiers_table($item);
        self::render_device_pi_types($item);
    }

    private static function extract_translation_name(array $item) {
        if (empty($item['translations']) || !is_array($item['translations'])) {
            return isset($item['name']) ? (string) $item['name'] : '';
        }

        foreach ($item['translations'] as $translation) {
            if (is_array($translation) && !empty($translation['name'])) {
                return (string) $translation['name'];
            }
        }

        return isset($item['name']) ? (string) $item['name'] : '';
    }

    private static function extract_translation_description(array $item) {
        if (empty($item['translations']) || !is_array($item['translations'])) {
            return isset($item['description']) ? (string) $item['description'] : '';
        }

        foreach ($item['translations'] as $translation) {
            if (!is_array($translation)) {
                continue;
            }
            if (!empty($translation['description'])) {
                return (string) $translation['description'];
            }
            if (!empty($translation['text'])) {
                return (string) $translation['text'];
            }
        }

        return isset($item['description']) ? (string) $item['description'] : '';
    }

    private static function collect_translation_descriptions(array $item) {
        if (empty($item['translations']) || !is_array($item['translations'])) {
            return [];
        }

        $descriptions = [];
        foreach ($item['translations'] as $translation) {
            if (!is_array($translation)) {
                continue;
            }

            $text = '';
            if (!empty($translation['description'])) {
                $text = (string) $translation['description'];
            } elseif (!empty($translation['text'])) {
                $text = (string) $translation['text'];
            }

            if ($text === '') {
                continue;
            }

            $label = '';
            if (!empty($translation['language']['name'])) {
                $label = (string) $translation['language']['name'];
            } elseif (!empty($translation['language']['code'])) {
                $label = (string) $translation['language']['code'];
            }

            $descriptions[] = [
                'text'  => $text,
                'label' => $label,
            ];
        }

        return $descriptions;
    }

    private static function extract_main_identifier(array $item) {
        if (empty($item['identifiers']) || !is_array($item['identifiers'])) {
            return isset($item['primaryIdentifier']) ? (string) $item['primaryIdentifier'] : '';
        }

        $fallback = '';
        foreach ($item['identifiers'] as $identifier) {
            if (!is_array($identifier)) {
                continue;
            }

            if (!empty($identifier['isPrimary']) && !empty($identifier['ref'])) {
                return (string) $identifier['ref'];
            }

            if ($fallback === '' && !empty($identifier['ref'])) {
                $fallback = (string) $identifier['ref'];
            }

            if (!empty($identifier['type']['name']) && stripos($identifier['type']['name'], 'primary') !== false && !empty($identifier['ref'])) {
                return (string) $identifier['ref'];
            }
        }

        return $fallback;
    }

    private static function build_manufacturer_label(array $item) {
        if (empty($item['manufacturer']) || !is_array($item['manufacturer'])) {
            return '';
        }

        $parts = [];
        if (!empty($item['manufacturer']['name'])) {
            $parts[] = (string) $item['manufacturer']['name'];
        }
        if (!empty($item['manufacturer']['ref'])) {
            $parts[] = '(' . (string) $item['manufacturer']['ref'] . ')';
        }

        return trim(implode(' ', $parts));
    }

    private static function extract_identifier_value_by_keywords(array $item, array $keywords) {
        if (empty($item['identifiers']) || !is_array($item['identifiers'])) {
            return '';
        }

        foreach ($item['identifiers'] as $identifier) {
            if (!is_array($identifier)) {
                continue;
            }

            $typeName = '';
            if (!empty($identifier['type']['name'])) {
                $typeName = (string) $identifier['type']['name'];
            } elseif (!empty($identifier['type']['ref'])) {
                $typeName = (string) $identifier['type']['ref'];
            }

            foreach ($keywords as $keyword) {
                if ($typeName !== '' && stripos($typeName, $keyword) !== false && !empty($identifier['ref'])) {
                    return (string) $identifier['ref'];
                }
            }
        }

        return '';
    }

    private static function format_implantable_value(array $item) {
        if (!array_key_exists('implantable', $item)) {
            return '';
        }

        $value = $item['implantable'];
        if ($value === null || $value === '') {
            return '';
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 'Sí' : 'No';
    }

    private static function extract_nomenclature_data(array $item) {
        $term = null;
        if (!empty($item['nomenclaturesTerms']) && is_array($item['nomenclaturesTerms'])) {
            $term = $item['nomenclaturesTerms'][0];
            if (is_array($term) && !empty($term['nomenclatureTerm'])) {
                $term = $term['nomenclatureTerm'];
            }
        } elseif (!empty($item['nomenclatureTerm']) && is_array($item['nomenclatureTerm'])) {
            $term = $item['nomenclatureTerm'];
        }

        if (!is_array($term)) {
            return [
                'term'        => '',
                'type'        => '',
                'description' => '',
                'lang'        => '',
            ];
        }

        $data = [
            'term'        => '',
            'type'        => '',
            'description' => '',
            'lang'        => '',
        ];

        $firstTranslation = null;
        if (!empty($term['translations']) && is_array($term['translations'])) {
            $candidate = $term['translations'][0] ?? null;
            if (is_array($candidate)) {
                $firstTranslation = $candidate;
            }
        }

        if ($firstTranslation) {
            if (!empty($firstTranslation['name'])) {
                $data['term'] = (string) $firstTranslation['name'];
            }
            if (!empty($firstTranslation['description'])) {
                $data['description'] = (string) $firstTranslation['description'];
            }
            if (!empty($firstTranslation['language']['code'])) {
                $data['lang'] = (string) $firstTranslation['language']['code'];
            } elseif (!empty($firstTranslation['language']['name'])) {
                $data['lang'] = (string) $firstTranslation['language']['name'];
            } elseif (!empty($firstTranslation['language'])) {
                $data['lang'] = (string) $firstTranslation['language'];
            }
        }

        if ($data['term'] === '' && !empty($term['name'])) {
            $data['term'] = (string) $term['name'];
        }

        if ($data['description'] === '' && !empty($term['description'])) {
            $data['description'] = (string) $term['description'];
        }

        if ($data['type'] === '' && !empty($term['type']['name'])) {
            $data['type'] = (string) $term['type']['name'];
        } elseif ($data['type'] === '' && !empty($term['type']['ref'])) {
            $data['type'] = (string) $term['type']['ref'];
        }

        return $data;
    }

    private static function format_nomenclature_label(array $nomenclature) {
        $term = isset($nomenclature['term']) ? trim((string) $nomenclature['term']) : '';
        $type = isset($nomenclature['type']) ? trim((string) $nomenclature['type']) : '';

        if ($term === '' && $type === '') {
            return '';
        }

        if ($type === '') {
            return $term;
        }

        if ($term === '') {
            return $type;
        }

        return $type . ': ' . $term;
    }

    private static function format_identifier_active_value(array $identifier) {
        if (!array_key_exists('isActive', $identifier)) {
            return '';
        }

        $value = $identifier['isActive'];
        if ($value === null || $value === '') {
            return '';
        }

        return filter_var($value, FILTER_VALIDATE_BOOLEAN) ? 'Sí' : 'No';
    }

    private static function collect_pi_texts(array $item) {
        $texts = [];
        $candidates = [
            'productionIdentifiers',
            'productionIdentifierTypes',
            'productionIdentifiersTypes',
            'applicablePiTypes',
            'piTypes',
        ];

        foreach ($candidates as $candidate) {
            if (!empty($item[$candidate])) {
                $texts = array_merge($texts, self::collect_pi_texts_from_value($item[$candidate]));
            }
        }

        if (!empty($texts)) {
            return self::normalise_pi_texts($texts);
        }

        if (empty($item['identifiers']) || !is_array($item['identifiers'])) {
            return [];
        }

        foreach ($item['identifiers'] as $identifier) {
            foreach ($candidates as $candidate) {
                if (!empty($identifier[$candidate])) {
                    $texts = array_merge($texts, self::collect_pi_texts_from_value($identifier[$candidate]));
                }
            }
        }

        return self::normalise_pi_texts($texts);
    }

    private static function collect_pi_texts_from_value($value) {
        $texts = [];

        if (is_string($value)) {
            $texts[] = $value;
            return $texts;
        }

        if (!is_array($value)) {
            return $texts;
        }

        foreach ($value as $entry) {
            if (is_string($entry)) {
                $texts[] = $entry;
                continue;
            }
            if (!is_array($entry)) {
                continue;
            }
            if (!empty($entry['name'])) {
                $texts[] = (string) $entry['name'];
                continue;
            }
            if (!empty($entry['ref'])) {
                $texts[] = (string) $entry['ref'];
                continue;
            }
            if (!empty($entry['type'])) {
                if (is_array($entry['type'])) {
                    if (!empty($entry['type']['name'])) {
                        $texts[] = (string) $entry['type']['name'];
                        continue;
                    }
                    if (!empty($entry['type']['ref'])) {
                        $texts[] = (string) $entry['type']['ref'];
                        continue;
                    }
                } elseif (is_string($entry['type'])) {
                    $texts[] = (string) $entry['type'];
                    continue;
                }
            }
        }

        return $texts;
    }

    private static function normalise_pi_texts(array $texts) {
        $texts = array_map('strval', $texts);
        $texts = array_map('trim', $texts);
        $texts = array_filter($texts, 'strlen');
        return array_values(array_unique($texts));
    }

    private static function render_device_overview_table(array $item) {
        $name         = self::extract_translation_name($item);
        $description  = self::extract_translation_description($item);
        $descriptions = self::collect_translation_descriptions($item);
        $manufacturer = self::build_manufacturer_label($item);
        $implantable  = self::format_implantable_value($item);
        $risk         = isset($item['riskClass']) && $item['riskClass'] !== null ? (string) $item['riskClass'] : '';
        $nomenclature = self::extract_nomenclature_data($item);
        $nomenLabel   = self::format_nomenclature_label($nomenclature);
        $nomenDesc    = isset($nomenclature['description']) ? trim((string) $nomenclature['description']) : '';
        $nomenLang    = isset($nomenclature['lang']) ? trim((string) $nomenclature['lang']) : '';

        echo '<table class="widefat fixed striped" style="max-width:100%;">';
        echo '<tbody>';
        if ($name !== '') {
            echo '<tr><th style="width:220px;">Nombre</th><td>' . esc_html($name) . '</td></tr>';
        }

        echo '<tr><th>Descripcion</th><td>';
        if (!empty($descriptions)) {
            echo '<div class="ba-translation-descriptions">';
            foreach ($descriptions as $desc) {
                if (empty($desc['text'])) {
                    continue;
                }
                $label = !empty($desc['label']) ? ' (' . esc_html($desc['label']) . ')' : '';
                echo '<div>' . esc_html($desc['text']) . $label . '</div>';
            }
            echo '</div>';
        } else {
            echo ($description !== '' ? esc_html($description) : '-');
        }
        echo '</td></tr>';

        if ($manufacturer !== '') {
            echo '<tr><th>Fabricante</th><td>' . esc_html($manufacturer) . '</td></tr>';
        }
        if ($implantable !== '') {
            echo '<tr><th>Implantable</th><td>' . esc_html($implantable) . '</td></tr>';
        }
        if ($risk !== '') {
            echo '<tr><th>Clase de riesgo</th><td>' . esc_html($risk) . '</td></tr>';
        }
        if ($nomenLabel !== '' || $nomenDesc !== '') {
            $value = $nomenLabel !== '' ? esc_html($nomenLabel) : '';
            if ($nomenDesc !== '') {
                $value .= '<div class="ba-nomenclature-desc">' . esc_html($nomenDesc);
                if ($nomenLang !== '') {
                    $value .= ' (' . esc_html($nomenLang) . ')';
                }
                $value .= '</div>';
            } elseif ($nomenLang !== '') {
                $value .= ' (' . esc_html($nomenLang) . ')';
            }
            if ($value === '') {
                $value = '-';
            }
            echo '<tr><th>Nomenclatura</th><td>' . $value . '</td></tr>';
        }
        echo '</tbody></table>';
    }

    private static function render_device_identifiers_table(array $item) {
        if (empty($item['identifiers']) || !is_array($item['identifiers'])) {
            return;
        }

        echo '<h4 style="margin-top:1rem;">Identificadores</h4>';
        echo '<table class="widefat fixed striped" style="max-width:100%;">';
        echo '<thead><tr><th>Tipo</th><th>Referencia</th><th>Agencia</th><th>Activo</th></tr></thead><tbody>';

        foreach ($item['identifiers'] as $identifier) {
            $type = '';
            if (!empty($identifier['type']['name'])) {
                $type = (string) $identifier['type']['name'];
            } elseif (!empty($identifier['type']['ref'])) {
                $type = (string) $identifier['type']['ref'];
            }

            $ref    = isset($identifier['ref']) ? (string) $identifier['ref'] : '';
            $agency = '';
            if (!empty($identifier['agency']['name'])) {
                $agency = (string) $identifier['agency']['name'];
            } elseif (!empty($identifier['agency']['ref'])) {
                $agency = (string) $identifier['agency']['ref'];
            }

            $active = self::format_identifier_active_value($identifier);

            echo '<tr>'
                . '<td>' . esc_html($type) . '</td>'
                . '<td>' . esc_html($ref) . '</td>'
                . '<td>' . esc_html($agency) . '</td>'
                . '<td>' . esc_html($active) . '</td>'
                . '</tr>';
        }

        echo '</tbody></table>';
    }

    private static function render_device_pi_types(array $item) {
        $piTexts = self::collect_pi_texts($item);
        if (empty($piTexts)) {
            return;
        }

        echo '<h4 style="margin-top:1rem;">Identificadores de Produccion</h4>';
        echo '<p>' . esc_html(implode(', ', $piTexts)) . '</p>';
    }

    private static function maybe_output_script() {
        if (self::$detailsScriptPrinted) {
            return;
        }

        self::$detailsScriptPrinted = true;
        echo '<style>.ba-devices-table td.dt-control{cursor:pointer;}</style>';
        echo '<script>(function(){document.addEventListener("click",function(e){var cell=e.target.closest("td.dt-control");if(!cell){return;}var row=cell.parentElement;var details=row.nextElementSibling;if(!details||!details.classList.contains("ba-device-details")){return;}var isHidden=details.style.display==="none"||details.style.display==="";details.style.display=isHidden?"table-row":"none";row.classList.toggle("is-expanded",isHidden);row.classList.toggle("dt-hasChild",isHidden);row.classList.toggle("shown",isHidden);cell.setAttribute("aria-expanded",isHidden?"true":"false");});})();</script>';
    }
}
