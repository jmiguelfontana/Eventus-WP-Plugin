<?php
/*
Plugin Name: Eventus API
Description: Conecta con la API Eventus mediante API Key.
Version: 1.0.3
Author: UDITrace
*/

if (!defined('ABSPATH')) exit;

// Cargar clases necesarias
require_once plugin_dir_path(__FILE__) . 'includes/class-ba-admin-settings.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-ba-shortcode-search.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-ba-shortcode-device.php';

register_activation_hook(__FILE__, 'ba_eventusapi_activate');

/**
 * Valores por defecto al activar plugin
 */
function ba_eventusapi_activate() {
    $defaults = [
        'ba_api_search_endpoint' => '', // 🔹 endpoint búsqueda
        'ba_api_device_endpoint' => '', // 🔹 endpoint detalle por id
        'ba_api_key'             => '',
        'ba_ssl_verify'          => 1,
        'ba_http_timeout'        => 15,
    ];

    foreach ($defaults as $option => $default) {
        if (get_option($option, null) === null) {
            add_option($option, $default);
        }
    }
}

/**
 * Cargar CSS y JS
 */
add_action('wp_enqueue_scripts', function () {
    // CSS principal
    wp_enqueue_style(
        'buscador-api',
        plugins_url('assets/buscador-api.css', __FILE__),
        [],
        '1.4'
    );

    // DataTables
    wp_register_style(
        'ba-fontawesome',
        'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css',
        [],
        '6.5.2'
    );
    wp_register_style(
        'ba-datatables',
        'https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css',
        ['ba-fontawesome'],
        '1.13.6'
    );
    wp_register_style(
        'ba-datatables-buttons',
        'https://cdn.datatables.net/buttons/2.4.1/css/buttons.dataTables.min.css',
        ['ba-datatables'],
        '2.4.1'
    );
    wp_register_script(
        'ba-datatables',
        'https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js',
        ['jquery'],
        '1.13.6',
        true
    );
    wp_register_script(
        'ba-jszip',
        'https://cdnjs.cloudflare.com/ajax/libs/jszip/3.10.1/jszip.min.js',
        [],
        '3.10.1',
        true
    );
    wp_register_script(
        'ba-datatables-buttons',
        'https://cdn.datatables.net/buttons/2.4.1/js/dataTables.buttons.min.js',
        ['ba-datatables'],
        '2.4.1',
        true
    );
    wp_register_script(
        'ba-datatables-buttons-html5',
        'https://cdn.datatables.net/buttons/2.4.1/js/buttons.html5.min.js',
        ['ba-jszip', 'ba-datatables-buttons'],
        '2.4.1',
        true
    );

    // Script de inicializaci�n
    wp_register_script(
        'ba-datatables-init',
        plugins_url('assets/buscador-api.js', __FILE__),
        ['jquery', 'ba-datatables-buttons-html5'],
        '1.0.3',
        true
    );

    // Pasar ajaxurl a JS
    wp_localize_script('ba-datatables-init', 'baAjax', [
        'url'   => admin_url('admin-ajax.php'),
        'nonce' => wp_create_nonce('ba_datatables_search'),
    ]);
});

/**
 * Endpoint AJAX para DataTables
 */
add_action('wp_ajax_ba_datatables_search', 'ba_datatables_search');
add_action('wp_ajax_nopriv_ba_datatables_search', 'ba_datatables_search');

function ba_datatables_search() {
    check_ajax_referer('ba_datatables_search', 'nonce');

    $term = sanitize_text_field($_POST['term'] ?? '');
    if ($term === '') {
        wp_send_json(['data' => []]);
    }

    $base_url  = rtrim(get_option('ba_api_search_endpoint', ''), '/');
    $api_key   = trim((string) get_option('ba_api_key', ''));
    $timeout   = (int) get_option('ba_http_timeout', 15);
    $sslverify = (bool) get_option('ba_ssl_verify', true);

    if (empty($base_url)) {
        wp_send_json(['data' => [], 'error' => 'Endpoint no configurado']);
    }

    // Construir URL
    $url = str_replace('{ref}', rawurlencode($term), $base_url);

    $args = [
        'timeout'   => max(5, min(120, $timeout)),
        'sslverify' => $sslverify,
        'headers'   => [],
    ];
    if (!empty($api_key)) {
        $args['headers']['Authorization'] = 'Bearer ' . $api_key;
    }

    $response = wp_remote_get($url, $args);
    if (is_wp_error($response)) {
        wp_send_json(['data' => [], 'error' => $response->get_error_message()]);
    }

    $data = json_decode(wp_remote_retrieve_body($response), true);

    if (empty($data['data'])) {
        wp_send_json(['data' => []]);
    }

    $rows = [];
    foreach ($data['data'] as $item) {
        $rows[] = [
            'deviceName'    => $item['deviceName']    ?? '',
            'primaryId'     => $item['primaryId']     ?? '',
            'manufacturer'  => $item['manufacturer']  ?? '',
            'version'       => $item['version']       ?? '',
            'catalogNumber' => $item['catalogNumber'] ?? '',
        ];
    }

    wp_send_json(['data' => $rows]);
}

