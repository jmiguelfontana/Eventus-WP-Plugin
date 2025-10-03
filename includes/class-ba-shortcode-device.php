<?php
if (!defined('ABSPATH')) exit;

class BA_Shortcode_Device {

    public function __construct() {
        add_shortcode('eventus_device', [$this, 'shortcode_entry']);
    }

    /** ===== Shortcode ===== */
    public function shortcode_entry() {
        $id = isset($_GET['id']) ? intval($_GET['id']) : 0;

        $endpoint = get_option('ba_api_device_endpoint', '');
        $api_key  = get_option('ba_api_key', '');

        ob_start();
        $this->render_result($id, $endpoint, $api_key);

        return ob_get_clean();
    }
    
    private function render_result($id, $endpoint, $api_key) {        
        $item = self::search_item($id, $endpoint, $api_key);

        echo '<div class="ba-result">';

        if (is_wp_error($item)) {
            printf('<div class="notice notice-error"><p>%s</p></div>', esc_html($item->get_error_message()));
            echo '</div>';
            return;
        } elseif (empty($item)) {
            echo '<div class="notice notice-warning"><p>' . esc_html__('Sin resultado.', 'eventusapi') . '</p></div>';
            echo '</div>';
            return;
        }

        // =======================
        // Nombre y descripción
        // =======================
        $translation = $item['translations'][0] ?? [];
        $name        = $translation['name'] ?? '';
        $description = $translation['description'] ?? '';

        echo '<h2 style="margin-top:0;">' . esc_html($name) . '</h2>';
        if ($description) {
            echo '<p><em>' . esc_html($description) . '</em></p>';
        }

        // =======================
        // Información básica
        // =======================
        echo '<h3>Información básica</h3>';
        echo '<ul>';
        //echo '<li><strong>ID:</strong> ' . esc_html($item['id'] ?? '') . '</li>';
        echo '<li><strong>Implantable:</strong> ' . (!empty($item['implantable']) ? 'Sí' : 'No') . '</li>';
        echo '<li><strong>Clase de riesgo:</strong> ' . esc_html($item['riskClass'] ?? 'N/D') . '</li>';
        echo '</ul>';

        // =======================
        // Fabricante
        // =======================
        if (!empty($item['manufacturer'])) {
            $m = $item['manufacturer'];
            echo '<h3>Fabricante</h3>';
            echo '<ul>';
            echo '<li><strong>Nombre:</strong> ' . esc_html($m['name'] ?? '') . '</li>';
            echo '<li><strong>Ref:</strong> ' . esc_html($m['ref'] ?? '') . '</li>';
            echo '<li><strong>Dirección:</strong> ' . esc_html($m['address'] ?? '') . '</li>';
            echo '<li><strong>País:</strong> ' . esc_html($m['country'] ?? '') . '</li>';
            echo '<li><strong>Email:</strong> ' . esc_html($m['email'] ?? '') . '</li>';
            echo '<li><strong>Teléfono:</strong> ' . esc_html($m['phone'] ?? '') . '</li>';
            echo '</ul>';
        }

        // =======================
        // Nomenclaturas
        // =======================
        if (!empty($item['nomenclaturesTerms'])) {
            echo '<h3>Nomenclaturas</h3>';
            echo '<ul>';
            foreach ($item['nomenclaturesTerms'] as $nom) {
                $term = $nom['nomenclatureTerm'] ?? [];
                $trans = $term['translations'][0] ?? [];
                echo '<li>';
                echo '<strong>' . esc_html($trans['name'] ?? '') . '</strong><br>';
                echo '<em>' . esc_html($trans['description'] ?? '') . '</em>';
                echo '</li>';
            }
            echo '</ul>';
        }

        // =======================
        // Identificadores
        // =======================
        if (!empty($item['identifiers'])) {
            echo '<h3>Identificadores</h3>';
            echo '<table style="width:100%;border-collapse:collapse;" border="1" cellpadding="5">';
            echo '<tr><th>Tipo</th><th>Agencia</th><th>Referencia</th></tr>';
            foreach ($item['identifiers'] as $iden) {
                echo '<tr>';
                echo '<td>' . esc_html($iden['type']['name'] ?? '') . '</td>';
                echo '<td>' . esc_html($iden['agency']['name'] ?? '') . '</td>';
                echo '<td>' . esc_html($iden['ref'] ?? '') . '</td>';
                echo '</tr>';
            }
            echo '</table>';
        }

        // =======================
        // Production Identifiers
        // =======================
        if (!empty($item['productionIdentifiers'])) {
            echo '<h3>Production Identifiers</h3>';
            echo '<ul>';
            foreach ($item['productionIdentifiers'] as $prod) {
                echo '<li><strong>' . esc_html($prod['type']['name'] ?? '') . '</strong></li>';
            }
            echo '</ul>';
        }

        echo '</div>';
    }

    public static function search_item($id, $endpoint, $api_key) {
        if ($id === '') {
            return [];
        }

        $url  = self::build_url($endpoint, $id);        
        $args = self::build_args($url, $api_key);
        $resp = self::http_get_body($url, $args);

        if (is_wp_error($resp)) return $resp;
        if (empty($resp)) return [];

        return self::parse_item($resp);
    }

    public static function build_url($endpoint, $id) {
        $encoded = rawurlencode($id);
        if (strpos($endpoint, '{id}') !== false) return str_replace('{id}', $encoded, $endpoint);
        if (strpos($endpoint, '{query}') !== false) return str_replace('{query}', $encoded, $endpoint);
        return add_query_arg(['ref' => $id], $endpoint);
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

    public static function parse_item($raw) {
        $decoded = json_decode((string) $raw, true);
        if (json_last_error() !== JSON_ERROR_NONE) return [];
        return $decoded['data'] ?? $decoded;
    }
}

// Inicializar
new BA_Shortcode_Device();