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
     */
    public static function render_items(array $items) {
        if (empty($items)) {
            return;
        }

        BA_Device_Table::open();

        foreach ($items as $idx => $item) {
            $row = BA_Device_RowBuilder::prepare_row($item, $idx);
            BA_Device_Table::render_summary_row($row);
            BA_Device_Table::render_detail_row($item, $row['toggle_id']);
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
     */
    public static function get_details_html(array $item) {
        return BA_Device_DetailRenderer::capture_details($item);
    }
}

