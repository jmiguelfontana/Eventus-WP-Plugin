<?php
/*
Plugin Name: Eventus API
Description: Conecta con la API Eventus mediante API Key.
Version: 1.0.1
Author: UDITrace
*/

if (!defined('ABSPATH')) exit;

// Cargar clases
require_once plugin_dir_path(__FILE__) . 'includes/class-ba-device-renderer.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-ba-device-rowbuilder.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-ba-device-detailrenderer.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-ba-device-scripts.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-ba-device-table.php';

// Cargar settings y shortcode
//require_once plugin_dir_path(__FILE__) . 'includes/admin-settings.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-ba-admin-settings.php';
require_once plugin_dir_path(__FILE__) . 'includes/class-ba-shortcode-search.php';

register_activation_hook(__FILE__, 'ba_eventusapi_activate');

function ba_eventusapi_activate() {
    $defaults = [
        'ba_api_endpoint' => '',
        'ba_api_key'      => '',
        'ba_ssl_verify'   => 1,
        'ba_http_timeout' => 15,
    ];

    foreach ($defaults as $option => $default) {
        if (get_option($option, null) === null) {
            add_option($option, $default);
        }
    }
}

add_action('wp_enqueue_scripts', function () {
    wp_enqueue_style(
        'buscador-api',
        plugins_url('assets/buscador-api.css', __FILE__),
        [],
        '1.4'
    );

    wp_register_style('ba-datatables', 'https://cdn.datatables.net/1.13.6/css/jquery.dataTables.min.css', [], '1.13.6');
    wp_register_script('ba-datatables', 'https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js', ['jquery'], '1.13.6', true);
    wp_register_script('ba-datatables-init', plugins_url('assets/buscador-api.js', __FILE__), ['jquery', 'ba-datatables'], '1.0.1', true);
});


add_action('wp_ajax_ba_load_device_details', 'ba_load_device_details');
add_action('wp_ajax_nopriv_ba_load_device_details', 'ba_load_device_details');
function ba_load_device_details() {
    $device_id = isset($_GET['id']) ? intval($_GET['id']) : 0;
    if ($device_id <= 0) {
        wp_send_json_error(__('ID no válido', 'eventusapi'));
    }

    $base_url  = rtrim(get_option('ba_api_device_endpoint', ''), '/');
    $api_key   = trim((string) get_option('ba_api_key', ''));
    $timeout   = (int) get_option('ba_http_timeout', 15);
    $sslverify = (bool) get_option('ba_ssl_verify', true);

    if (empty($base_url)) {
        wp_send_json_error(__('El endpoint de la API no está configurado.', 'eventusapi'));
    }

    // Construir URL final
    $url = $base_url . '/' . $device_id;

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
        wp_send_json_error(__('Error en API: ', 'eventusapi') . $response->get_error_message());
    }

    $data = json_decode(wp_remote_retrieve_body($response), true);
    if (empty($data) || empty($data['data'])) {
        wp_send_json_error(__('No se encontraron datos de detalle.', 'eventusapi'));
    }

    // La API devuelve un único objeto en data
    $device = $data['data'];

    // Renderizar HTML usando el renderer centralizado
    $html = BA_Device_DetailRenderer::capture_details($device);

    wp_send_json_success(['html' => $html]);
}
