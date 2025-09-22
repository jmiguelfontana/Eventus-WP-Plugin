<?php
if (!defined('ABSPATH')) exit;

class BA_Admin_Settings {

    public function __construct() {
        add_action('admin_menu', [$this, 'add_menu_page']);
        add_action('admin_init', [$this, 'register_settings']);
    }

    public function add_menu_page() {
        add_options_page(
            __('Eventus API', 'eventusapi'),
            __('Eventus API', 'eventusapi'),
            'manage_options',
            'ba-settings',
            [$this, 'render_settings_page']
        );
    }

    public function register_settings() {
        // Sección principal
        add_settings_section('ba_section_main', __('Configuración', 'eventusapi'), '__return_false', 'ba-settings');

        // Endpoint
        register_setting('ba_options_group', 'ba_api_endpoint', [
            'type'              => 'string',
            'sanitize_callback' => [$this, 'sanitize_required_url'],
            'default'           => '',
        ]);
        add_settings_field('ba_api_endpoint', __('Endpoint de la API', 'eventusapi'), [$this, 'field_endpoint'], 'ba-settings', 'ba_section_main');

        // API Key
        register_setting('ba_options_group', 'ba_api_key', [
            'type'              => 'string',
            'sanitize_callback' => [$this, 'sanitize_required_text'],
            'default'           => '',
        ]);
        add_settings_field('ba_api_key', __('API Key', 'eventusapi'), [$this, 'field_apikey'], 'ba-settings', 'ba_section_main');

        // Timeout
        register_setting('ba_options_group', 'ba_http_timeout', [
            'type'              => 'integer',
            'sanitize_callback' => [$this, 'sanitize_timeout'],
            'default'           => 15,
        ]);
        add_settings_field('ba_http_timeout', __('Timeout (segundos)', 'eventusapi'), [$this, 'field_http_timeout'], 'ba-settings', 'ba_section_main');

        // SSL verify
        register_setting('ba_options_group', 'ba_ssl_verify', [
            'type'              => 'boolean',
            'sanitize_callback' => 'rest_sanitize_boolean',
            'default'           => 1,
        ]);
        add_settings_field('ba_ssl_verify', __('Verificar SSL', 'eventusapi'), [$this, 'field_sslverify'], 'ba-settings', 'ba_section_main');
    }

    /** ====== Render de los campos ====== */
    public function field_endpoint() {
        $endpoint = get_option('ba_api_endpoint', '');
        printf(
            '<input type="text" name="ba_api_endpoint" value="%s" class="regular-text code" size="60" required />',
            esc_attr($endpoint)
        );
        echo '<p class="description">' . esc_html__('Ejemplo:', 'eventusapi') . ' <code>https://api.ejemplo.com/search?ref={ref}</code></p>';
    }

    public function field_apikey() {
        $api_key = get_option('ba_api_key', '');
        printf(
            '<input type="text" name="ba_api_key" value="%s" class="regular-text" />',
            esc_attr($api_key)
        );
        echo '<p class="description">' . esc_html__('Opcional. Se usará en la cabecera Authorization: Bearer', 'eventusapi') . '</p>';
    }

    public function field_http_timeout() {
        $timeout = max(5, (int) get_option('ba_http_timeout', 15));
        printf(
            '<input type="number" name="ba_http_timeout" value="%s" class="small-text" min="5" max="120" step="1" />',
            esc_attr($timeout)
        );
        echo '<p class="description">' . esc_html__('Tiempo máximo de espera en segundos para las peticiones a la API (recomendado: 15-60).', 'eventusapi') . '</p>';
    }

    public function field_sslverify() {
        $ssl = (int) get_option('ba_ssl_verify', 1);
        printf(
            '<label><input type="checkbox" name="ba_ssl_verify" value="1" %s /> %s</label>',
            checked(1, $ssl, false),
            esc_html__('Verificar certificado SSL', 'eventusapi')
        );
        echo '<p class="description">' . esc_html__('Desmarca en desarrollo si usas un certificado autofirmado (no recomendado en producción).', 'eventusapi') . '</p>';
    }

    /** ====== Página de ajustes ====== */
    public function render_settings_page() {
        $ba_base_url  = trailingslashit(plugin_dir_url(dirname(__FILE__)));
        $ba_base_path = trailingslashit(plugin_dir_path(dirname(__FILE__)));

        $logo_candidates = ['assets/logo.svg','assets/logo.png','assets/logo.jpg','assets/logo.jpeg'];
        $logo_rel = '';
        foreach ($logo_candidates as $cand) {
            if (file_exists($ba_base_path . $cand)) { $logo_rel = $cand; break; }
        }
        $logo_url  = $logo_rel ? ($ba_base_url . $logo_rel) : '';

        echo '<div class="wrap">';
        echo '<div class="ba-settings-header" style="margin-top:12px;margin-bottom:16px;">';
        if ($logo_rel) {
            printf('<img src="%s" alt="Logo" style="max-height:64px; height:auto; display:block;" />', esc_url($logo_url));
        } else {
            echo '<div style="height:64px; border:1px dashed #ccd0d4; background:#fff; color:#6c7781; display:flex; align-items:center; justify-content:center;">';
            echo '<span>' . esc_html__('Espacio para logo (añade assets/logo.svg o assets/logo.png)', 'eventusapi') . '</span>';
            echo '</div>';
        }
        echo '</div>';

        echo '<h1 style="margin-top:0;">Eventus API</h1>';
        echo '<form method="post" action="options.php">';
        settings_fields('ba_options_group');
        do_settings_sections('ba-settings');
        submit_button();
        echo '</form></div>';
    }

    /** ====== Sanitizers ====== */
    public function sanitize_required_url($value) {
        $value = trim((string) $value);
        if ($value === '') {
            add_settings_error('ba_api_endpoint', 'ba_required_endpoint', __('El campo "Endpoint de la API" es obligatorio.', 'eventusapi'));
            return get_option('ba_api_endpoint', '');
        }
        return esc_url_raw($value);
    }

    public function sanitize_required_text($value) {
        $value = trim((string) $value);
        if ($value === '') {
            add_settings_error('ba_api_key', 'ba_required_apikey', __('El campo "API Key" es obligatorio.', 'eventusapi'));
            return get_option('ba_api_key', '');
        }
        return sanitize_text_field($value);
    }

    public function sanitize_timeout($value) {
        if ($value === null || $value === '') {
            return get_option('ba_http_timeout', 15);
        }
        $value = absint($value);
        if ($value < 5) {
            add_settings_error('ba_http_timeout', 'ba_timeout_min', __('El timeout mínimo es de 5 segundos.', 'eventusapi'));
            return 5;
        }
        if ($value > 120) {
            add_settings_error('ba_http_timeout', 'ba_timeout_max', __('El timeout máximo permitido es de 120 segundos.', 'eventusapi'));
            return 120;
        }
        return $value;
    }
}

// Inicializar
new BA_Admin_Settings();
