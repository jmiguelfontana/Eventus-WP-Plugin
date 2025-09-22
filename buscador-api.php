<?php
/*
Plugin Name: Eventus API
Description: Conecta con la API Eventus mediante API Key.
Version: 1.0.1
Author: UDITrace
*/

if (!defined('ABSPATH')) exit;

require_once plugin_dir_path(__FILE__) . 'includes/admin-settings.php';
require_once plugin_dir_path(__FILE__) . 'includes/shortcode-formulario.php';

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