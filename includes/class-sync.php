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
            'categories' => self::categories_payload(),
            'coupons' => self::coupons_payload(),
            'shipping' => self::shipping_payload(),
            'payments' => self::payment_payload(),
            'taxes' => self::tax_payload(),
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
        foreach ($product->get_gallery_image_ids() as $gallery_id) {
            $images[] = wp_get_attachment_url($gallery_id);
        }

        $attributes = [];
        foreach ($product->get_attributes() as $attribute) {
            $name = wc_attribute_label($attribute->get_name());
            $values = [];
            if ($attribute->is_taxonomy()) {
                $values = wc_get_product_terms($product->get_id(), $attribute->get_name(), ['fields' => 'names']);
            } else {
                $values = $attribute->get_options();
            }
            $attributes[] = [
                'name' => $name,
                'values' => array_values(array_filter(array_map('strval', is_array($values) ? $values : []))),
                'visible' => (bool) $attribute->get_visible(),
                'variation' => (bool) $attribute->get_variation(),
            ];
        }

        $variant_data = [];
        if ($product->is_type('variable')) {
            foreach ($product->get_children() as $variation_id) {
                $variation = wc_get_product($variation_id);
                if (!$variation) {
                    continue;
                }
                $variant_data[] = [
                    'id' => (string) $variation->get_id(),
                    'numericId' => (string) $variation->get_id(),
                    'sku' => $variation->get_sku(),
                    'price' => $variation->get_price(),
                    'regularPrice' => $variation->get_regular_price(),
                    'salePrice' => $variation->get_sale_price(),
                    'stockStatus' => $variation->get_stock_status(),
                    'attributes' => $variation->get_attributes(),
                ];
            }
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
            'inventoryTotal' => $product->managing_stock() ? (int) $product->get_stock_quantity() : null,
            'weight' => $product->get_weight(),
            'dimensions' => [
                'length' => $product->get_length(),
                'width' => $product->get_width(),
                'height' => $product->get_height(),
            ],
            'description' => wp_strip_all_tags($product->get_description()),
            'shortDescription' => wp_strip_all_tags($product->get_short_description()),
            'categories' => is_array($categories) ? $categories : [],
            'images' => array_values(array_filter($images)),
            'image' => $images[0] ?? '',
            'featuredImage' => $images[0] ?? '',
            'attributes' => $attributes,
            'variantData' => $variant_data,
            'firstVariantId' => (string) $product->get_id(),
            'firstAvailableVariantId' => (string) $product->get_id(),
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

    private static function categories_payload()
    {
        $terms = get_terms([
            'taxonomy' => 'product_cat',
            'hide_empty' => false,
        ]);
        if (is_wp_error($terms) || !is_array($terms)) {
            return [];
        }
        return array_map(static function ($term) {
            return [
                'id' => (string) $term->term_id,
                'name' => $term->name,
                'slug' => $term->slug,
                'url' => get_term_link($term),
                'description' => wp_strip_all_tags($term->description),
                'count' => (int) $term->count,
            ];
        }, $terms);
    }

    private static function coupons_payload()
    {
        if (!class_exists('WC_Coupon')) {
            return [];
        }
        $posts = get_posts([
            'post_type' => 'shop_coupon',
            'post_status' => 'publish',
            'numberposts' => 100,
        ]);
        return array_map(static function ($post) {
            $coupon = new WC_Coupon($post->ID);
            return [
                'id' => (string) $coupon->get_id(),
                'code' => $coupon->get_code(),
                'type' => $coupon->get_discount_type(),
                'amount' => $coupon->get_amount(),
                'freeShipping' => (bool) $coupon->get_free_shipping(),
                'minimumAmount' => $coupon->get_minimum_amount(),
                'maximumAmount' => $coupon->get_maximum_amount(),
                'description' => wp_strip_all_tags($coupon->get_description()),
            ];
        }, $posts);
    }

    private static function shipping_payload()
    {
        if (!class_exists('WC_Shipping_Zones')) {
            return [];
        }
        $zones = WC_Shipping_Zones::get_zones();
        $zones[] = [
            'zone_id' => 0,
            'zone_name' => __('Rest of the world', 'woocommerce'),
            'shipping_methods' => WC_Shipping_Zones::get_zone(0)->get_shipping_methods(),
        ];
        return array_map(static function ($zone) {
            $methods = [];
            foreach ($zone['shipping_methods'] as $method) {
                $methods[] = [
                    'id' => $method->id,
                    'title' => $method->get_title(),
                    'enabled' => $method->enabled,
                    'settings' => $method->settings,
                ];
            }
            return [
                'id' => (string) $zone['zone_id'],
                'name' => $zone['zone_name'],
                'methods' => $methods,
            ];
        }, $zones);
    }

    private static function payment_payload()
    {
        if (!function_exists('WC') || !WC()->payment_gateways()) {
            return [];
        }
        $gateways = WC()->payment_gateways()->payment_gateways();
        return array_map(static function ($gateway) {
            return [
                'id' => $gateway->id,
                'title' => $gateway->get_title(),
                'description' => wp_strip_all_tags($gateway->get_description()),
                'enabled' => $gateway->enabled,
            ];
        }, array_values($gateways));
    }

    private static function tax_payload()
    {
        if (!class_exists('WC_Tax')) {
            return [];
        }
        return [
            'pricesIncludeTax' => wc_prices_include_tax(),
            'taxDisplayShop' => get_option('woocommerce_tax_display_shop'),
            'taxDisplayCart' => get_option('woocommerce_tax_display_cart'),
        ];
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
