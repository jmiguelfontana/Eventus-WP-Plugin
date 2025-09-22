<?php
if (!defined('ABSPATH')) {
    exit;
}

class BA_Device_RowBuilder {

    /**
     * Prepara una fila de datos resumidos del dispositivo.
     *
     * @param array $item Datos del dispositivo.
     * @param int   $idx  Índice de la fila.
     * @return array
     */
    public static function prepare_row(array $item, $idx) {
        $name          = BA_Device_Translator::get_name($item);
        $mainId        = BA_Device_Extractor::main_identifier($item);
        $manufacturer  = BA_Device_Extractor::manufacturer($item);
        $versionModel  = BA_Device_Extractor::identifier_by_keywords($item, ['version', 'model']);
        $catalogNumber = BA_Device_Extractor::identifier_by_keywords($item, ['catalog']);

        // Nombre visible en tabla (sin sufijo de locale)
        $displayName = ($name !== '') ? $name : '';

        return [
            'display_name'   => $displayName !== '' 
                                ? $displayName 
                                : ($mainId !== '' ? $mainId : 'Producto ' . ($idx + 1)),
            'main_id'        => $mainId,
            'manufacturer'   => $manufacturer,
            'version_model'  => $versionModel,
            'catalog_number' => $catalogNumber,
            'toggle_id'      => 'ba-device-details-' . $idx,
        ];
    }
}
