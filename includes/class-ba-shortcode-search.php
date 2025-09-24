<?php
if (!defined('ABSPATH')) exit;

class BA_Shortcode_Search {

    public function __construct() {
        add_shortcode('buscador_api', [$this, 'shortcode_entry']);
        add_action('wp_ajax_ba_datatables_search', [$this, 'ajax_search']);
        add_action('wp_ajax_nopriv_ba_datatables_search', [$this, 'ajax_search']);
    }

    /** ===== Shortcode ===== */
    public function shortcode_entry() {
        $endpoint = get_option('ba_api_search_endpoint', '');
        $api_key  = get_option('ba_api_key', '');

        ob_start();
        $this->render_form();

        if (isset($_POST['ba_nonce']) && wp_verify_nonce($_POST['ba_nonce'], 'ba_buscar')) {
            $term = sanitize_text_field(wp_unslash($_POST['termino_busqueda'] ?? ''));
            $this->render_results($term, $endpoint, $api_key);
        }

        return ob_get_clean();
    }

    private function render_form() {
        static $localized = false;

        wp_enqueue_style('ba-datatables');
        wp_enqueue_script('ba-datatables');
        wp_enqueue_script('ba-datatables-init');

        if (!$localized) {
            $language = [
                'processing'   => __('Procesando...', 'eventusapi'),
                'lengthMenu'   => __('Mostrar _MENU_ registros', 'eventusapi'),
                'zeroRecords'  => __('Sin coincidencias.', 'eventusapi'),
                'info'         => __('Mostrando _START_ a _END_ de _TOTAL_ registros', 'eventusapi'),
                'infoEmpty'    => __('Mostrando 0 registros', 'eventusapi'),
                'infoFiltered' => __('(filtrado de _MAX_ registros totales)', 'eventusapi'),
                'emptyTable'   => __('No hay datos disponibles.', 'eventusapi'),
                'search'       => __('Buscar:', 'eventusapi'),
                'paginate'     => [
                    'first'    => __('Primero', 'eventusapi'),
                    'last'     => __('Último', 'eventusapi'),
                    'next'     => __('Siguiente', 'eventusapi'),
                    'previous' => __('Anterior', 'eventusapi'),
                ],
            ];
            $i18n = [
                'noResults' => __('Sin resultado.', 'eventusapi'),
                'unexpected' => __('Se produjo un error inesperado.', 'eventusapi'),
                'emptyTerm' => __('Introduce un término de búsqueda.', 'eventusapi'),
                'noDetails' => __('Sin detalles disponibles.', 'eventusapi'),
                'headers' => [
                    'id'          => __('ID', 'eventusapi'),
                    'name'        => __('Nombre del dispositivo', 'eventusapi'),
                    'primaryId'   => __('Primary ID', 'eventusapi'),
                    'manufacturer'=> __('Fabricante', 'eventusapi'),
                    'version'     => __('Versión/Modelo', 'eventusapi'),
                    'catalog'     => __('Catalog Number', 'eventusapi'),
                ],
            ];
            wp_localize_script('ba-datatables-init', 'baDataTables', [
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce'    => wp_create_nonce('ba_datatables_search'),
                'language' => $language,
                'i18n'     => $i18n,
            ]);
            $localized = true;
        }

        ?>
        <form class="buscador-api wp-block-group" method="post" novalidate>
            <?php wp_nonce_field('ba_buscar', 'ba_nonce'); ?>
            <div class="wp-block-group__inner-container">
                <label for="ba-term" class="screen-reader-text"><?php esc_html_e('Término de búsqueda', 'eventusapi'); ?></label>
                <input id="ba-term" class="wp-block-search__input" type="text" name="termino_busqueda"
                       placeholder="Primary DI, Version, Model, Catalog Number..." required />
                <button type="submit" name="buscar_api" class="button button-primary"><?php esc_html_e('Buscar', 'eventusapi'); ?></button>
            </div>
        </form>
        <?php
    }

    private function render_results($term, $endpoint, $api_key) {
        $items = self::search_items($term, $endpoint, $api_key);

        echo '<div class="ba-resultados" style="margin-top:1rem;">';
        if (is_wp_error($items)) {
            printf('<div class="notice notice-error"><p>%s</p></div>', esc_html($items->get_error_message()));
        } elseif (empty($items)) {
            echo '<div class="notice notice-warning"><p>' . esc_html__('Sin resultado.', 'eventusapi') . '</p></div>';
        }
        echo '</div>';
    }

    /** ===== Lógica de búsqueda ===== */
    public static function search_items($term, $endpoint, $api_key) {
        if ($term === '') {
            return [];
        }

        $url  = self::build_url($endpoint, $term);        
        $args = self::build_args($url, $api_key);
        $resp = self::http_get_body($url, $args);

        if (is_wp_error($resp)) return $resp;
        if (empty($resp)) return [];

        return self::parse_items($resp);
    }

    public static function build_url($endpoint, $term) {
        $encoded = rawurlencode($term);
        if (strpos($endpoint, '{ref}') !== false) return str_replace('{ref}', $encoded, $endpoint);
        if (strpos($endpoint, '{query}') !== false) return str_replace('{query}', $encoded, $endpoint);
        return add_query_arg(['ref' => $term], $endpoint);
    }

    public static function build_args($url, $api_key) {
        $timeout = max(1, (int) get_option('ba_http_timeout', 15));
        $args = [
            'timeout' => $timeout,
            'sslverify' => (bool) get_option('ba_ssl_verify', 1),
            'headers' => ['X-Requested-With' => 'XMLHttpRequest']
        ];
        if (!empty($api_key)) {
            $args['headers']['X-API-KEY'] = $api_key;
        }
        return apply_filters('ba_request_args', $args, $url);
    }

    public static function http_get_body($url, $args) {
        $response = wp_remote_get($url, $args);
        if (is_wp_error($response)) {
            return $response;
        }
        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        if ($code === 404 || $code === 204) return '';
        if ($code < 200 || $code >= 300 || empty($body)) {
            return new WP_Error('ba_bad_status', sprintf(__('Error de API (%d).', 'eventusapi'), $code));
        }
        return $body;
    }

    public static function parse_items($raw) {
        $decoded = json_decode((string) $raw, true);
        if (json_last_error() !== JSON_ERROR_NONE) return [];
        return $decoded['data'] ?? $decoded;
    }

    /** ===== Ajax ===== */
    public function ajax_search() {
        $nonce = sanitize_text_field(wp_unslash($_POST['nonce'] ?? ''));
        if (!wp_verify_nonce($nonce, 'ba_datatables_search')) {
            wp_send_json(['data' => [], 'error' => __('Acceso no permitido.', 'eventusapi')], 403);
        }

        $term = sanitize_text_field(wp_unslash($_POST['term'] ?? ''));
        if ($term === '') {
            wp_send_json(['data' => [], 'error' => __('Introduce un término de búsqueda.', 'eventusapi')]);
        }

        $endpoint = get_option('ba_api_search_endpoint', '');
        $api_key  = get_option('ba_api_key', '');
        $items    = self::search_items($term, $endpoint, $api_key);

        if (is_wp_error($items)) {
            wp_send_json(['data' => [], 'error' => $items->get_error_message()]);
        }

        $rows = [];
        foreach ($items as $i => $item) {
            $rows[] = [
                'id'             => $item['id']             ?? '',
                'deviceName'     => $item['deviceName']     ?? '',
                'primaryId'      => $item['primaryId']      ?? '',
                'manufacturer'   => $item['manufacturer']   ?? '',
                'version'        => $item['version']        ?? '',
                'catalogNumber'  => $item['catalogNumber']  ?? ''
            ];
        }

        wp_send_json(['data' => $rows]);
    }
}

// Inicializar
new BA_Shortcode_Search();
