<?php
if (!defined('ABSPATH')) {
    exit;
}

class BA_Device_DetailRenderer {

    /**
     * Captura el HTML de los detalles en un buffer y lo devuelve como string.
     */
    public static function capture_details(array $item) {
        ob_start();
        self::render_overview($item);
        self::render_identifiers($item);
        self::render_pi_types($item);
        return trim(ob_get_clean());
    }

    /**
     * Renderiza la tabla de overview del dispositivo.
     */
    public static function render_overview(array $item) {
        $name         = BA_Device_Translator::get_name($item);
        $nameLoc      = BA_Device_Translator::get_locale_for_name($item);
        $description  = BA_Device_Translator::get_description($item);
        $descriptions = BA_Device_Translator::get_all_descriptions($item);

        $manufacturer = BA_Device_Extractor::manufacturer($item);
        $implantable  = BA_Device_Extractor::implantable($item);
        $risk         = isset($item['riskClass']) && $item['riskClass'] !== null ? (string) $item['riskClass'] : '';

        $nomenclature = BA_Device_Extractor::nomenclature($item);
        $nomenLabel   = BA_Device_Extractor::format_nomenclature_label($nomenclature);
        $nomenDesc    = isset($nomenclature['description']) ? trim((string) $nomenclature['description']) : '';
        $nomenLang    = isset($nomenclature['lang']) ? trim((string) $nomenclature['lang']) : '';

        echo '<table class="widefat fixed striped" style="max-width:100%;">';
        echo '<tbody>';

        // Nombre + descripción
        if ($name !== '') {
            $suffix = $nameLoc !== '' ? ' (' . esc_html($nameLoc) . ')' : '';
            $cell  = esc_html($name) . $suffix;

            // Añadir primera descripción si existe
            $devDescText   = '';
            $devDescLocale = '';
            if (!empty($descriptions)) {
                foreach ($descriptions as $desc) {
                    $t = isset($desc['name']) ? trim((string) $desc['name']) : '';
                    if ($t !== '') {
                        $devDescText   = $t;
                        $devDescLocale = isset($desc['locale']) ? trim((string) $desc['locale']) : '';
                        break;
                    }
                }
            }
            if ($devDescText === '' && $description !== '') {
                $devDescText   = $description;
                $devDescLocale = BA_Device_Translator::guess_locale($item);
            }

            if ($devDescText !== '') {
                $cell .= '<div class="ba-nomenclature-desc">' . esc_html($devDescText);
                if ($devDescLocale !== '') {
                    $cell .= ' (' . esc_html($devDescLocale) . ')';
                }
                $cell .= '</div>';
            }

            echo '<tr><th style="width:220px;">Nombre</th><td>' . $cell . '</td></tr>';
        }

        // Fabricante
        if ($manufacturer !== '') {
            echo '<tr><th>Fabricante</th><td>' . esc_html($manufacturer) . '</td></tr>';
        }

        // Implantable
        if ($implantable !== '') {
            echo '<tr><th>Implantable</th><td>' . esc_html($implantable) . '</td></tr>';
        }

        // Clase de riesgo
        if ($risk !== '') {
            echo '<tr><th>Clase de riesgo</th><td>' . esc_html($risk) . '</td></tr>';
        }

        // Nomenclatura
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

    /**
     * Renderiza la tabla de identificadores.
     */
    public static function render_identifiers(array $item) {
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

            $active = BA_Device_Extractor::format_identifier_active_value($identifier);

            echo '<tr>'
                . '<td>' . esc_html($type) . '</td>'
                . '<td>' . esc_html($ref) . '</td>'
                . '<td>' . esc_html($agency) . '</td>'
                . '<td>' . esc_html($active) . '</td>'
                . '</tr>';
        }

        echo '</tbody></table>';
    }

    /**
     * Renderiza los PI types (identificadores de producción).
     */
    public static function render_pi_types(array $item) {
        $piTexts = self::collect_pi_texts($item);
        if (empty($piTexts)) {
            return;
        }

        echo '<h4 style="margin-top:1rem;">Identificadores de Producción</h4>';
        echo '<p>' . esc_html(implode(', ', $piTexts)) . '</p>';
    }

    /**
     * Interno: recoge todos los PI types del item.
     */
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
}
