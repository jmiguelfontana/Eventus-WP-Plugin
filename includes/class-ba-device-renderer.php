<?php
if (!defined('ABSPATH')) {
    exit;
}

class BA_Device_Renderer {
    private static $preferredLocale = '';

    /**
     * Establece el locale preferido para nombres/descripciones.
     */
    public static function set_preferred_locale($locale) {
        self::$preferredLocale = is_string($locale) ? trim($locale) : '';
        BA_Device_Translator::set_preferred_locale(self::$preferredLocale);
    }

    /**
     * Renderiza una lista de dispositivos en tabla.
     * 
     * Ahora SOLO se renderiza la cabecera (resumen).
     * El detalle se carga por AJAX cuando se expande la fila.
     */
    public static function render_items(array $items) {
        if (empty($items)) {
            return;
        }

        BA_Device_Table::open();

        foreach ($items as $idx => $item) {
            $row = BA_Device_RowBuilder::prepare_row($item, $idx);
            BA_Device_Table::render_summary_row($row);

            // 🔹 Fila de detalle vacía, se llenará vía AJAX
            echo '<tr id="' . esc_attr($row['toggle_id']) . '" class="ba-device-details" style="display:none;">';
            echo '<td colspan="6"><em>Seleccione para cargar detalles...</em></td>';
            echo '</tr>';
        }

        BA_Device_Table::close();
        BA_Device_Scripts::maybe_output_script();
    }

    /**
     * Devuelve un array con los datos resumidos de un dispositivo.
     */
    public static function summarize_item(array $item, $idx) {
        return BA_Device_RowBuilder::prepare_row($item, $idx);
    }

    /**
     * Devuelve el HTML de los detalles de un dispositivo.
     * 
     * OJO: Ya no se usa directamente en render_items.
     * Ahora lo invoca el AJAX cuando se pide un detalle.
     */
    public static function get_details_html(array $item) {
        return BA_Device_DetailRenderer::capture_details($item);
    }
}