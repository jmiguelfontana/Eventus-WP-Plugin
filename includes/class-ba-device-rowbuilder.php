<?php
if (!defined('ABSPATH')) {
    exit;
}

class BA_Device_RowBuilder {

    /**
     * Prepara una fila de datos resumidos del dispositivo (cabecera).
     *
     * @param array $item Datos del dispositivo (simplificados desde API).
     * @param int   $idx  Índice de la fila.
     * @return array
     */
    public static function prepare_row(array $item, $idx) {
        $id            = isset($item['id']) ? (int) $item['id'] : 0;
        $name          = isset($item['deviceName']) ? trim((string) $item['deviceName']) : '';
        $mainId        = isset($item['primaryId']) ? trim((string) $item['primaryId']) : '';
        $manufacturer  = isset($item['manufacturer']) ? trim((string) $item['manufacturer']) : '';
        $versionModel  = isset($item['version']) ? trim((string) $item['version']) : '';
        $catalogNumber = isset($item['catalogNumber']) ? trim((string) $item['catalogNumber']) : '';

        return [
            'id'             => $id,
            'display_name'   => $name !== '' ? $name : ($mainId !== '' ? $mainId : 'Producto ' . ($idx + 1)),
            'main_id'        => $mainId,
            'manufacturer'   => $manufacturer,
            'version_model'  => $versionModel,
            'catalog_number' => $catalogNumber,
            'toggle_id'      => 'ba-device-details-' . $idx,
        ];
    }
}
