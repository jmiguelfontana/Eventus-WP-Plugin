<?php
if (!defined('ABSPATH')) exit;

add_action('admin_menu', function () {
    add_options_page(
        'Eventus API',
        'Eventus API',
        'manage_options',
        'ba-settings',
        'ba_render_settings_page'
    );
});

add_action('admin_init', function () {
    register_setting('ba_options_group', 'ba_api_endpoint', [
        'type'              => 'string',
        'sanitize_callback' => 'ba_sanitize_required_url',
        'default'           => '',
    ]);

    register_setting('ba_options_group', 'ba_api_key', [
        'type'              => 'string',
        'sanitize_callback' => 'ba_sanitize_required_text',
        'default'           => '',
    ]);

    register_setting('ba_options_group', 'ba_http_timeout', [
        'type'              => 'integer',
        'sanitize_callback' => 'ba_sanitize_timeout',
        'default'           => 15,
    ]);

    add_settings_section('ba_section_main', 'Configuración', '__return_false', 'ba-settings');

    add_settings_field('ba_api_endpoint', 'Endpoint de la API', 'ba_field_endpoint', 'ba-settings', 'ba_section_main');
    add_settings_field('ba_api_key', 'API Key', 'ba_field_apikey', 'ba-settings', 'ba_section_main');
    add_settings_field('ba_http_timeout', 'Timeout (segundos)', 'ba_field_http_timeout', 'ba-settings', 'ba_section_main');
});


function ba_field_endpoint() {
    $endpoint = get_option('ba_api_endpoint', '');
    echo '<input type="text" name="ba_api_endpoint" value="' . esc_attr($endpoint) . '" class="regular-text code" size="60" required />';
    echo '<p class="description">Ejemplo: <code>https://api.ejemplo.com/search?ref={ref}</code></p>';
}

function ba_field_apikey() {
    $api_key = get_option('ba_api_key', '');
    echo '<input type="text" name="ba_api_key" value="' . esc_attr($api_key) . '" class="regular-text" />';
    echo '<p class="description">Opcional. Se usará en la cabecera <code>Authorization: Bearer</code></p>';
}

function ba_field_http_timeout() {
    $timeout = (int) get_option('ba_http_timeout', 15);
    if ($timeout < 1) {
        $timeout = 15;
    }
    echo '<input type="number" name="ba_http_timeout" value="' . esc_attr($timeout) . '" class="small-text" min="5" max="120" step="1" />';
    echo '<p class="description">Tiempo máximo de espera en segundos para las peticiones a la API (recomendado: 15-60).</p>';
}
function ba_render_settings_page() { ?>
    <div class="wrap">
        <?php
        $ba_base_url  = trailingslashit(plugin_dir_url(dirname(__FILE__)));
        $ba_base_path = trailingslashit(plugin_dir_path(dirname(__FILE__)));
        // Detectar logo en assets (svg/png/jpg); si no existe, mostrar placeholder
        $ba_logo_candidates = ['assets/logo.svg','assets/logo.png','assets/logo.jpg','assets/logo.jpeg'];
        $ba_logo_rel = '';
        foreach ($ba_logo_candidates as $cand) {
            if (file_exists($ba_base_path . $cand)) { $ba_logo_rel = $cand; break; }
        }
        $ba_logo_path = $ba_logo_rel ? ($ba_base_path . $ba_logo_rel) : '';
        $ba_logo_url  = $ba_logo_rel ? ($ba_base_url . $ba_logo_rel) : '';
        ?>
        <div class="ba-settings-header" style="margin-top:12px;margin-bottom:16px;">
            <?php if ($ba_logo_rel) : ?>
                <img src="<?php echo esc_url($ba_logo_url); ?>" alt="Logo" style="max-height:64px; max-width:100%; height:auto; object-fit:contain; display:block;" />
            <?php else : ?>
                <div style="height:64px; border:1px dashed #ccd0d4; background:#fff; color:#6c7781; display:flex; align-items:center; justify-content:center;">
                    <span>Espacio para logo (añade <code>assets/logo.svg</code> o <code>assets/logo.png</code>)</span>
                </div>
            <?php endif; ?>
        </div>
        <h1 style="margin-top:0;">Eventus API</h1>
        <form method="post" action="options.php">
            <?php
            settings_fields('ba_options_group');
            do_settings_sections('ba-settings');
            submit_button();
            ?>
        </form>
    </div>
<?php }


// Añade ajuste y campo para controlar la verificación SSL del cliente HTTP
add_action('admin_init', function () {
    // Registrar el ajuste si no existe aún
    register_setting('ba_options_group', 'ba_ssl_verify', [
        'type'              => 'boolean',
        'sanitize_callback' => 'rest_sanitize_boolean',
        'default'           => 1,
    ]);

    // Añadir el campo en la sección principal existente
    add_settings_field('ba_ssl_verify', 'Verificar SSL', 'ba_field_sslverify', 'ba-settings', 'ba_section_main');
});

function ba_field_sslverify() {
    $ssl = get_option('ba_ssl_verify', 1);
    echo '<label><input type="checkbox" name="ba_ssl_verify" value="1" ' . checked(1, (int) $ssl, false) . ' /> Verificar certificado SSL</label>';
    echo '<p class="description">Desmarca en desarrollo si usas un certificado autofirmado (no recomendado en producción).</p>';
}

// Sanitizers: obligar a que los campos no esten vacios
function ba_sanitize_required_url($value) {
    $value = trim((string) $value);
    if ($value === '') {
        add_settings_error('ba_api_endpoint', 'ba_required_endpoint', 'El campo "Endpoint de la API" es obligatorio.');
        return get_option('ba_api_endpoint', '');
    }
    return esc_url_raw($value);
}

function ba_sanitize_required_text($value) {
    $value = trim((string) $value);
    if ($value === '') {
        add_settings_error('ba_api_key', 'ba_required_apikey', 'El campo "API Key" es obligatorio.');
        return get_option('ba_api_key', '');
    }
    return sanitize_text_field($value);
}

function ba_sanitize_timeout($value) {
    if ($value === null || $value === '') {
        return get_option('ba_http_timeout', 15);
    }

    $value = absint($value);
    if ($value < 5) {
        $value = 5;
        add_settings_error('ba_http_timeout', 'ba_timeout_min', 'El timeout mínimo es de 5 segundos.');
    } elseif ($value > 120) {
        $value = 120;
        add_settings_error('ba_http_timeout', 'ba_timeout_max', 'El timeout máximo permitido es de 120 segundos.');
    }

    return $value > 0 ? $value : 15;
}