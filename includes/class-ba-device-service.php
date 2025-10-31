<?php
if (!defined('ABSPATH')) {
    exit;
}

class BA_Device_Service {

    /** @var string */
    private $endpoint;

    /** @var string */
    private $api_key;

    public function __construct($endpoint, $api_key) {
        $this->endpoint = (string) $endpoint;
        $this->api_key  = (string) $api_key;
    }

    /**
     * Fetch device data from the remote API.
     *
     * @param int|string $id
     * @return array|WP_Error
     */
    public function fetch($id) {
        if (empty($id)) {
            return [];
        }

        $url = $this->build_url($id);
        if (is_wp_error($url)) {
            return $url;
        }

        $args = $this->build_args($url);
        $response = $this->http_get_body($url, $args);

        if (is_wp_error($response)) {
            return $response;
        }

        if ($response === '') {
            return [];
        }

        return $this->parse_item($response);
    }

    /**
     * Build the endpoint URL based on configuration and device id.
     *
     * @param int|string $id
     * @return string|WP_Error
     */
    private function build_url($id) {
        $endpoint = trim($this->endpoint);
        if ($endpoint === '') {
            return new WP_Error('ba_missing_endpoint', __('El endpoint de dispositivo no está configurado.', 'eventusapi'));
        }

        $encoded = rawurlencode($id);
        if (strpos($endpoint, '{id}') !== false) {
            return str_replace('{id}', $encoded, $endpoint);
        }
        if (strpos($endpoint, '{query}') !== false) {
            return str_replace('{query}', $encoded, $endpoint);
        }

        return add_query_arg(['ref' => $id], $endpoint);
    }

    /**
     * Prepare request arguments for wp_remote_get.
     *
     * @param string $url
     * @return array
     */
    private function build_args($url) {
        $timeout = max(1, (int) get_option('ba_http_timeout', 15));
        $args = [
            'timeout'   => $timeout,
            'sslverify' => (bool) get_option('ba_ssl_verify', 1),
            'headers'   => ['X-Requested-With' => 'XMLHttpRequest'],
        ];

        if ($this->api_key !== '') {
            $args['headers']['X-API-KEY'] = $this->api_key;
        }

        return apply_filters('ba_request_args', $args, $url);
    }

    /**
     * Execute the HTTP request and return body.
     *
     * @param string $url
     * @param array  $args
     * @return string|WP_Error
     */
    private function http_get_body($url, $args) {
        $response = wp_remote_get($url, $args);
        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        if ($code === 404 || $code === 204) {
            return '';
        }

        if ($code < 200 || $code >= 300 || $body === '') {
            return new WP_Error('ba_bad_status', sprintf(__('Error de API (%d).', 'eventusapi'), $code));
        }

        return $body;
    }

    /**
     * Decode response body and return device data.
     *
     * @param string $raw
     * @return array
     */
    private function parse_item($raw) {
        $decoded = json_decode((string) $raw, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            return [];
        }

        $data = $decoded['data'] ?? $decoded;
        return is_array($data) ? $data : [];
    }
}
