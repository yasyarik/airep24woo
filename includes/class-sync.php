<?php

if (!defined('ABSPATH')) {
    exit;
}

final class AiRep24Woo_Sync
{
    public static function init()
    {
        add_action('save_post_product', [__CLASS__, 'queue_product_event'], 20, 3);
        add_action('before_delete_post', [__CLASS__, 'product_deleted_event'], 20, 1);
    }

    public static function queue_product_event($post_id, $post, $update)
    {
        if (wp_is_post_revision($post_id) || $post->post_type !== 'product' || !AiRep24Woo_Settings::is_connected()) {
            return;
        }

        $product = wc_get_product($post_id);
        if (!$product) {
            return;
        }

        $client = new AiRep24Woo_API_Client();
        $client->send_event($update ? 'product.updated' : 'product.created', self::product_payload($product));
    }

    public static function product_deleted_event($post_id)
    {
        if (get_post_type($post_id) !== 'product' || !AiRep24Woo_Settings::is_connected()) {
            return;
        }

        $client = new AiRep24Woo_API_Client();
        $client->send_event('product.deleted', ['id' => (string) $post_id]);
    }

    public static function build_store_payload()
    {
        $products = [];
        if (function_exists('wc_get_products')) {
            $items = wc_get_products([
                'limit' => 250,
                'status' => ['publish'],
                'return' => 'objects',
            ]);
            foreach ($items as $product) {
                $products[] = self::product_payload($product);
            }
        }

        return [
            'store' => [
                'name' => get_bloginfo('name'),
                'url' => home_url('/'),
                'language' => get_locale(),
                'currency' => function_exists('get_woocommerce_currency') ? get_woocommerce_currency() : '',
                'timezone' => wp_timezone_string(),
            ],
            'products' => $products,
            'pages' => self::pages_payload(),
            'themePalette' => self::theme_palette(),
        ];
    }

    private static function product_payload($product)
    {
        $categories = wp_get_post_terms($product->get_id(), 'product_cat', ['fields' => 'names']);
        $images = [];
        $image_id = $product->get_image_id();
        if ($image_id) {
            $images[] = wp_get_attachment_url($image_id);
        }

        return [
            'id' => (string) $product->get_id(),
            'type' => $product->get_type(),
            'name' => $product->get_name(),
            'url' => get_permalink($product->get_id()),
            'status' => $product->get_status(),
            'sku' => $product->get_sku(),
            'price' => $product->get_price(),
            'regularPrice' => $product->get_regular_price(),
            'salePrice' => $product->get_sale_price(),
            'stockStatus' => $product->get_stock_status(),
            'description' => wp_strip_all_tags($product->get_description()),
            'shortDescription' => wp_strip_all_tags($product->get_short_description()),
            'categories' => is_array($categories) ? $categories : [],
            'images' => array_values(array_filter($images)),
        ];
    }

    private static function pages_payload()
    {
        $pages = get_posts([
            'post_type' => 'page',
            'post_status' => 'publish',
            'numberposts' => 50,
        ]);

        return array_map(static function ($page) {
            return [
                'id' => (string) $page->ID,
                'title' => get_the_title($page),
                'url' => get_permalink($page),
                'content' => wp_strip_all_tags($page->post_content),
            ];
        }, $pages);
    }

    private static function theme_palette()
    {
        $colors = [];
        $gradients = [];
        $uploads = wp_get_upload_dir();
        $theme = wp_get_theme();

        foreach ([$theme->get_stylesheet_directory(), $theme->get_template_directory(), $uploads['basedir']] as $dir) {
            if (!is_dir($dir)) {
                continue;
            }
            foreach (glob(trailingslashit($dir) . '*.css') ?: [] as $file) {
                $content = file_get_contents($file);
                if (!$content) {
                    continue;
                }
                preg_match_all('/#[0-9a-fA-F]{6}\b/', $content, $matches);
                foreach ($matches[0] as $color) {
                    $colors[] = strtolower($color);
                }
                preg_match_all('/linear-gradient\([^)]+\)/i', $content, $gradientMatches);
                foreach ($gradientMatches[0] as $gradient) {
                    $gradients[] = $gradient;
                }
            }
        }

        $colors = array_values(array_slice(array_unique($colors), 0, 8));
        $gradients = array_values(array_slice(array_unique($gradients), 0, 4));

        return [
            'colors' => $colors,
            'gradients' => $gradients,
        ];
    }
}
