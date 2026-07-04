<?php

if (!defined('ABSPATH')) {
    exit;
}

final class AiRep24Woo_API_Client
{
    private $settings;

    public function __construct()
    {
        $this->settings = AiRep24Woo_Settings::get();
    }

    public function onboarding_url()
    {
        $return_url = admin_url('admin.php?page=airep24woo');
        $params = [
            'platform' => 'woocommerce',
            'returnUrl' => $return_url,
            'storeUrl' => home_url('/'),
            'storeName' => get_bloginfo('name'),
        ];

        return trailingslashit($this->settings['api_base_url']) . 'auth/register?' . http_build_query($params, '', '&');
    }

    public function checkout_url($plan = '')
    {
        $params = [
            'platform' => 'woocommerce',
            'siteId' => $this->settings['site_id'],
            'plan' => sanitize_key($plan),
            'returnUrl' => admin_url('admin.php?page=airep24woo&tab=billing'),
        ];

        return trailingslashit($this->settings['api_base_url']) . 'billing/checkout?' . http_build_query($params, '', '&');
    }

    public function get_remote_config()
    {
        if (empty($this->settings['connection_token'])) {
            return new WP_Error('airep24_not_connected', __('AiRep24 is not connected.', 'airep24woo'));
        }

        return $this->request('GET', '/api/woocommerce/config');
    }

    public function save_remote_config(array $config)
    {
        return $this->request('POST', '/api/woocommerce/config', $config);
    }

    public function sync_store(array $payload)
    {
        return $this->request('POST', '/api/woocommerce/sync', $payload, 45);
    }

    public function send_event($type, array $payload)
    {
        return $this->request('POST', '/api/woocommerce/events', [
            'type' => sanitize_key($type),
            'payload' => $payload,
        ], 15);
    }

    private function request($method, $path, array $body = null, $timeout = 20)
    {
        $url = rtrim($this->settings['api_base_url'], '/') . $path;
        $args = [
            'method' => $method,
            'timeout' => $timeout,
            'headers' => [
                'Accept' => 'application/json',
                'Content-Type' => 'application/json',
                'X-AiRep24-Platform' => 'woocommerce',
                'X-AiRep24-Site' => $this->settings['site_id'],
                'Authorization' => 'Bearer ' . $this->settings['connection_token'],
            ],
        ];

        if ($body !== null) {
            $args['body'] = wp_json_encode($body);
        }

        $response = wp_remote_request($url, $args);
        if (is_wp_error($response)) {
            return $response;
        }

        $code = wp_remote_retrieve_response_code($response);
        $raw = wp_remote_retrieve_body($response);
        $decoded = json_decode($raw, true);
        if ($code < 200 || $code >= 300) {
            return new WP_Error('airep24_api_error', sprintf('AiRep24 API returned HTTP %d.', $code), [
                'status' => $code,
                'body' => $decoded ?: $raw,
            ]);
        }

        return is_array($decoded) ? $decoded : [];
    }
}
