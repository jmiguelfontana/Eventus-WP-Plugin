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
        $name         = !empty($item['translations'][0]['name']) ? $item['translations'][0]['name'] : '';
        $description  = !empty($item['translations'][0]['description']) ? $item['translations'][0]['description'] : '';
        $manufacturer = self::build_manufacturer_label($item);
        $implantable  = isset($item['implantable']) ? ($item['implantable'] ? 'Sí' : 'No') : '';
        $risk         = isset($item['riskClass']) && $item['riskClass'] !== null ? (string) $item['riskClass'] : '';
        $nomenclature = self::extract_nomenclature($item);

        echo '<table class="widefat fixed striped" style="max-width:100%;">';
        echo '<tbody>';

        if ($name !== '') {
            $cell = esc_html($name);
            if ($description !== '') {
                $cell .= '<div class="ba-nomenclature-desc">' . esc_html($description) . '</div>';
            }
            echo '<tr><th style="width:220px;">Nombre</th><td>' . $cell . '</td></tr>';
        }

        if ($manufacturer !== '') {
            echo '<tr><th>Fabricante</th><td>' . esc_html($manufacturer) . '</td></tr>';
        }

        if ($implantable !== '') {
            echo '<tr><th>Implantable</th><td>' . esc_html($implantable) . '</td></tr>';
        }

        if ($risk !== '') {
            echo '<tr><th>Clase de riesgo</th><td>' . esc_html($risk) . '</td></tr>';
        }

        if ($nomenclature !== '') {
            echo '<tr><th>Nomenclatura</th><td>' . esc_html($nomenclature) . '</td></tr>';
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
            $type   = !empty($identifier['type']['name']) ? $identifier['type']['name'] : '';
            $ref    = !empty($identifier['ref']) ? $identifier['ref'] : '';
            $agency = !empty($identifier['agency']['name']) ? $identifier['agency']['name'] : '';
            $active = isset($identifier['isActive']) ? ($identifier['isActive'] ? 'Sí' : 'No') : '';

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
     * Renderiza los identificadores de producción.
     */
    public static function render_pi_types(array $item) {
        if (empty($item['productionIdentifiers']) || !is_array($item['productionIdentifiers'])) {
            return;
        }

        $names = [];
        foreach ($item['productionIdentifiers'] as $pi) {
            if (!empty($pi['type']['name'])) {
                $names[] = $pi['type']['name'];
            }
        }

        if (!empty($names)) {
            echo '<h4 style="margin-top:1rem;">Identificadores de Producción</h4>';
            echo '<p>' . esc_html(implode(', ', $names)) . '</p>';
        }
    }

    /* ==== Helpers ==== */

    private static function build_manufacturer_label(array $item) {
        if (empty($item['manufacturer'])) {
            return '';
        }
        $m = $item['manufacturer'];
        $parts = [];
        if (!empty($m['name'])) {
            $parts[] = $m['name'];
        }
        if (!empty($m['ref'])) {
            $parts[] = '(' . $m['ref'] . ')';
        }
        return implode(' ', $parts);
    }

    private static function extract_nomenclature(array $item) {
        if (empty($item['nomenclaturesTerms'][0]['nomenclatureTerm']['translations'][0]['name'])) {
            return '';
        }
        $t    = $item['nomenclaturesTerms'][0]['nomenclatureTerm']['translations'][0];
        $term = $t['name'];
        $desc = !empty($t['description']) ? $t['description'] : '';
        return $desc ? $term . ' - ' . $desc : $term;
    }
}
