<?php
if (!defined('ABSPATH')) exit;

require_once __DIR__ . '/class-ba-device-renderer.php';
// Shortcode entry (v2, modular)
add_shortcode('buscador_api', 'ba_mostrar_formulario_buscador_v2');

function ba_mostrar_formulario_buscador_v2() {
    $endpoint = get_option('ba_api_endpoint', '');
    $api_key  = get_option('ba_api_key', '');

    ob_start();
    ba_render_search_form();

    if (isset($_POST['ba_nonce']) && wp_verify_nonce($_POST['ba_nonce'], 'ba_buscar')) {
        $termino = sanitize_text_field(wp_unslash($_POST['termino_busqueda'] ?? ''));
        ba_render_results($termino, $endpoint, $api_key);
    }

    return ob_get_clean();
}

function ba_render_search_form() {
    static $ba_dt_localized = false;

    wp_enqueue_style('ba-datatables');
    wp_enqueue_script('ba-datatables');
    wp_enqueue_script('ba-datatables-init');

    if (!$ba_dt_localized) {
        $language = [
            'processing'   => esc_html__('Procesando...', 'eventusapi'),
            'lengthMenu'   => esc_html__('Mostrar _MENU_ registros', 'eventusapi'),
            'zeroRecords'  => esc_html__('Sin coincidencias.', 'eventusapi'),
            'info'         => esc_html__('Mostrando _START_ a _END_ de _TOTAL_ registros', 'eventusapi'),
            'infoEmpty'    => esc_html__('Mostrando 0 registros', 'eventusapi'),
            'infoFiltered' => esc_html__('(filtrado de _MAX_ registros totales)', 'eventusapi'),
            'emptyTable'   => esc_html__('No hay datos disponibles.', 'eventusapi'),
            'search'       => esc_html__('Buscar:', 'eventusapi'),
            'paginate'     => [
                'first'    => esc_html__('Primero', 'eventusapi'),
                'last'     => esc_html__('Ultimo', 'eventusapi'),
                'next'     => esc_html__('Siguiente', 'eventusapi'),
                'previous' => esc_html__('Anterior', 'eventusapi'),
            ],
        ];

        $i18n = [
            'noResults' => esc_html__('Sin resultado.', 'eventusapi'),
            'unexpected' => esc_html__('Se produjo un error inesperado.', 'eventusapi'),
            'emptyTerm' => esc_html__('Introduce un termino de busqueda.', 'eventusapi'),
            'noDetails' => esc_html__('Sin detalles disponibles.', 'eventusapi'),
            'headers' => [
                'name'        => esc_html__('Nombre del dispositivo', 'eventusapi'),
                'primaryId'   => esc_html__('Primary ID', 'eventusapi'),
                'manufacturer'=> esc_html__('Fabricante', 'eventusapi'),
                'version'     => esc_html__('Version/Model', 'eventusapi'),
                'catalog'     => esc_html__('Catalog Number', 'eventusapi'),
            ],
        ];

        wp_localize_script(
            'ba-datatables-init',
            'baDataTables',
            [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce'    => wp_create_nonce('ba_datatables_search'),
                'language' => $language,
                'i18n'     => $i18n,
            ]
        );

        $ba_dt_localized = true;
    }
    ?>
    <form class="buscador-api wp-block-group" method="post" novalidate>
        <?php wp_nonce_field('ba_buscar', 'ba_nonce'); ?>
        <div class="wp-block-group__inner-container">
            <label for="ba-term" class="screen-reader-text">Termino de busqueda</label>
            <input id="ba-term" class="wp-block-search__input" type="text" name="termino_busqueda"
                   placeholder="Primary DI, Version, Model, Catalog Number..." required />
            <button type="submit" name="buscar_api" class="button button-primary">Buscar</button>
        </div>
    </form>
    <script>(function(){
      try {
        var form = document.querySelector('form.buscador-api');
        if (!form) return;
        form.addEventListener('submit', function(){
          var btn = form.querySelector('button[name="buscar_api"]');
          if (!btn) return;
          btn.classList.add('is-busy');
          btn.setAttribute('aria-busy','true');
          btn.setAttribute('disabled','disabled');
          if (!btn.querySelector('.ba-spinner')) {
            var sp = document.createElement('span');
            sp.className = 'ba-spinner';
            sp.setAttribute('aria-hidden','true');
            btn.appendChild(sp);
          }
        }, { passive: true });
      } catch(e) {}
    })();</script>
<?php }

function ba_search_items($termino, $endpoint, $api_key) {
    if ($termino === '') {
        return [];
    }

    $url  = ba_build_url($endpoint, $termino);
    $args = ba_build_args($url, $api_key);
    $resp = ba_http_get_body($url, $args);

    if (is_wp_error($resp)) {
        return $resp;
    }

    if ($resp === '' || $resp === null) {
        return [];
    }

    $items = ba_parse_items_from_json($resp);

    if (!is_array($items)) {
        return [];
    }

    return array_values(array_filter($items, 'ba_item_has_relevant_data'));
}

function ba_render_results($termino, $endpoint, $api_key) {
    $items = ba_search_items($termino, $endpoint, $api_key);

    if (is_wp_error($items)) {
        echo '<div class="notice notice-error" style="margin-top:1rem;"><p>' . esc_html($items->get_error_message()) . '</p></div>';
        return;
    }

    echo '<div class="ba-resultados" style="margin-top:1rem;">';
    if (empty($items)) {
        echo '<div class="notice notice-warning" style="margin-top:1rem;"><p>Sin resultado.</p></div>';
    } else {
        echo '<h3>Resultados</h3>';
        ba_render_items_accordion($items);
    }
    echo '</div>';
}

function ba_build_url($endpoint, $termino) {
    $encoded = rawurlencode($termino);
    if (strpos($endpoint, '{ref}') !== false) {
        return str_replace('{ref}', $encoded, $endpoint);
    }
    if (strpos($endpoint, '{query}') !== false) {
        return str_replace('{query}', $encoded, $endpoint);
    }
    if (strpos($endpoint, '?') !== false) {
        return add_query_arg(['ref' => $termino], $endpoint);
    }
    return add_query_arg(['ref' => $termino], $endpoint);
}

function ba_build_args($url, $api_key) {
    $timeout = absint(get_option('ba_http_timeout', 15));
    if ($timeout < 1) {
        $timeout = 15;
    }

    $args = [ 'timeout' => max(1, $timeout), 'headers' => [] ];
    $args['sslverify'] = (bool) get_option('ba_ssl_verify', 1);
    if (!empty($api_key)) {
        $args['headers']['X-API-KEY'] = $api_key;
        unset($args['headers']['Authorization']);
    }
    $args['headers']['X-Requested-With'] = 'XMLHttpRequest';

    $args['timeout'] = max(1, (int) apply_filters('ba_http_timeout', $args['timeout'], $url));

    return apply_filters('ba_request_args', $args, $url);
}
function ba_http_get_body($url, $args) {
    $response = wp_remote_get($url, $args);
    if (is_wp_error($response)) {
        if ($response->get_error_code() === 'http_request_failed') {
            $message = $response->get_error_message();
            if (strpos($message, 'cURL error 28') !== false || strpos($message, 'timed out') !== false) {
                return new WP_Error(
                    'ba_timeout',
                    esc_html__('La solicitud a la API supero el tiempo de espera configurado. Comprueba el servicio remoto o incrementa el timeout en Ajustes > Eventus API.', 'eventusapi')
                );
            }
        }

        return $response;
    }
    $code = wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);
    if ($code === 404 || $code === 204) {
        return '';
    }
    if ($code < 200 || $code >= 300 || $body === '' || $body === null) {
        $msg = 'Error de API (' . $code . ').';
        if (is_string($body) && $body !== '') {
            $decoded_err = json_decode($body, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                if (!empty($decoded_err['message'])) {
                    $msg .= ' ' . (string) $decoded_err['message'];
                } elseif (!empty($decoded_err['error'])) {
                    $msg .= ' ' . (string) $decoded_err['error'];
                }
            }
        }
        return new WP_Error('ba_bad_status', $msg);
    }
    return $body;
}
function ba_parse_items_from_json($raw) {
    $decoded = json_decode((string) $raw, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        if (!function_exists('wp_doing_ajax') || !wp_doing_ajax()) {
            echo '<pre class="ba-json" style="max-height:40rem;overflow:auto;background:#f6f7f7;border:1px solid #ccd0d4;padding:12px;">'
                . esc_html((string) $raw)
                . '</pre>';
        }
        return [];
    }
    $obj = is_array($decoded) ? $decoded : [];
    $payload = isset($obj['data']) ? $obj['data'] : $obj;
    if (!is_array($payload)) {
        return [];
    }
    $is_assoc = array_keys($payload) !== range(0, count($payload) - 1);
    return $is_assoc ? [$payload] : $payload;
}

add_action('wp_ajax_ba_datatables_search', 'ba_ajax_datatables_search');
add_action('wp_ajax_nopriv_ba_datatables_search', 'ba_ajax_datatables_search');

function ba_ajax_datatables_search() {
    $nonce = isset($_POST['nonce']) ? sanitize_text_field(wp_unslash($_POST['nonce'])) : '';
    if (!wp_verify_nonce($nonce, 'ba_datatables_search')) {
        wp_send_json(['data' => [], 'error' => esc_html__('Acceso no permitido.', 'eventusapi')], 403);
    }

    $term = isset($_POST['term']) ? sanitize_text_field(wp_unslash($_POST['term'])) : '';
    if ($term === '') {
        wp_send_json(['data' => [], 'error' => esc_html__('Introduce un termino de busqueda.', 'eventusapi')]);
    }

    $endpoint = get_option('ba_api_endpoint', '');
    $api_key  = get_option('ba_api_key', '');

    $items = ba_search_items($term, $endpoint, $api_key);

    if (is_wp_error($items)) {
        wp_send_json(['data' => [], 'error' => $items->get_error_message()]);
    }

    $rows = [];
    foreach ($items as $idx => $item) {
        $summary = BA_Device_Renderer::summarize_item($item, $idx);
        $rows[] = [
            'display_name'   => $summary['display_name'],
            'main_id'        => $summary['main_id'],
            'manufacturer'   => $summary['manufacturer'],
            'version_model'  => $summary['version_model'],
            'catalog_number' => $summary['catalog_number'],
            'details_html'   => BA_Device_Renderer::get_details_html($item),
        ];
    }

    wp_send_json(['data' => $rows]);
}

function ba_item_has_relevant_data($item) {
    if (!is_array($item)) {
        return false;
    }

    if (!empty($item['translations'][0]['name'])) {
        return true;
    }

    if (!empty($item['manufacturer']['name']) || !empty($item['manufacturer']['ref'])) {
        return true;
    }

    if (!empty($item['identifiers']) && is_array($item['identifiers'])) {
        foreach ($item['identifiers'] as $identifier) {
            if (!empty($identifier['ref'])) {
                return true;
            }
            if (!empty($identifier['type']['name']) || !empty($identifier['type']['ref'])) {
                return true;
            }
        }
    }

    if (!empty($item['nomenclaturesTerms'][0]['nomenclatureTerm']['translations'][0]['name'])) {
        return true;
    }

    if (isset($item['riskClass']) && $item['riskClass'] !== null && $item['riskClass'] !== '') {
        return true;
    }

    if (array_key_exists('implantable', $item)) {
        return true;
    }

    return false;
}

function ba_render_items_accordion(array $items) {
    BA_Device_Renderer::render_items($items);
}

function ba_render_item_tables(array $item) {
    BA_Device_Renderer::render_item_tables($item);
}
