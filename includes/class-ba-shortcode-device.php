<?php
if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/class-ba-device-service.php';
require_once __DIR__ . '/class-ba-device-renderer.php';

class BA_Shortcode_Device {

    /** @var BA_Device_Service */
    private $service;

    /** @var BA_Device_Renderer */
    private $renderer;

    public function __construct() {
        $endpoint = get_option('ba_api_device_endpoint', '');
        $api_key  = get_option('ba_api_key', '');

        $this->service  = new BA_Device_Service($endpoint, $api_key);
        $this->renderer = new BA_Device_Renderer();

        add_shortcode('eventus_device', [$this, 'shortcode_entry']);
    }

    /**
     * Shortcode handler: render device details based on query parameter `id`.
     *
     * @return string
     */
    public function shortcode_entry() {
        $id = isset($_GET['id']) ? intval($_GET['id']) : 0;

        if (!$id) {
            return $this->renderer->render_empty();
        }

        $item = $this->service->fetch($id);

        if (is_wp_error($item)) {
            return $this->renderer->render_error($item);
        }

        if (empty($item)) {
            return $this->renderer->render_empty();
        }

        wp_enqueue_style('buscador-api');
        wp_enqueue_script('ba-device-barcode');

        return $this->renderer->render_device($item);
    }
}

// Inicializar
new BA_Shortcode_Device();
