<?php
if (!defined('ABSPATH')) {
    exit;
}

class BA_Device_Table {

    /**
     * Renderiza apertura de la tabla principal.
     */
    public static function open() {
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

    /**
     * Renderiza cierre de tabla.
     */
    public static function close() {
        echo '</tbody></table>';
    }

    /**
     * Renderiza una fila resumen (la que se ve inicialmente).
     *
     * @param array $row Fila preparada por BA_Device_RowBuilder.
     */
    public static function render_summary_row(array $row) {
        echo '<tr class="ba-device-row">';
        echo '<td><strong>' . esc_html($row['display_name']) . '</strong></td>';
        echo '<td>' . esc_html($row['main_id']) . '</td>';
        echo '<td>' . esc_html($row['manufacturer']) . '</td>';
        echo '<td>' . esc_html($row['version_model']) . '</td>';
        echo '<td>' . esc_html($row['catalog_number']) . '</td>';
        echo '<td class="dt-control" role="button" aria-expanded="false"></td>';
        echo '</tr>';
    }

    /**
     * Renderiza la fila de detalle (oculta por defecto).
     *
     * @param array  $item     Datos completos del dispositivo.
     * @param string $toggleId ID del detalle asociado a la fila.
     */
    public static function render_detail_row(array $item, $toggleId) {
        $detailsHtml = BA_Device_DetailRenderer::capture_details($item);
        echo '<tr id="' . esc_attr($toggleId) . '" class="ba-device-details" style="display:none;">';
        echo '<td colspan="6">' . ($detailsHtml !== '' ? $detailsHtml : '<em>Sin detalles adicionales.</em>') . '</td>';
        echo '</tr>';
    }
}
