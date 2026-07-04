<?php

if (!defined('ABSPATH')) {
    exit;
}

final class AiRep24Woo_Admin_Page
{
    public static function init()
    {
        add_action('admin_menu', [__CLASS__, 'register_menu']);
        add_action('admin_enqueue_scripts', [__CLASS__, 'enqueue_assets']);
        add_action('admin_post_airep24woo_connect', [__CLASS__, 'handle_connect']);
        add_action('admin_post_airep24woo_save', [__CLASS__, 'handle_save']);
        add_action('admin_post_airep24woo_sync', [__CLASS__, 'handle_sync']);
    }

    public static function register_menu()
    {
        add_menu_page(
            __('AiRep24', 'airep24woo'),
            __('AiRep24', 'airep24woo'),
            'manage_woocommerce',
            'airep24woo',
            [__CLASS__, 'render'],
            'dashicons-format-chat',
            56
        );
    }

    public static function enqueue_assets($hook)
    {
        if ($hook !== 'toplevel_page_airep24woo') {
            return;
        }
        wp_enqueue_style('airep24woo-admin', AIREP24WOO_URL . 'assets/admin.css', [], AIREP24WOO_VERSION);
        wp_enqueue_script('airep24woo-admin', AIREP24WOO_URL . 'assets/admin.js', [], AIREP24WOO_VERSION, true);
    }

    public static function handle_connect()
    {
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Permission denied.', 'airep24woo'));
        }
        check_admin_referer('airep24woo_connect');

        $settings = AiRep24Woo_Settings::get();
        $client = new AiRep24Woo_API_Client();
        $result = $client->connect_store(AiRep24Woo_Sync::build_store_payload());

        if (is_wp_error($result)) {
            $settings['last_sync_status'] = $result->get_error_message();
            AiRep24Woo_Settings::update($settings);
            wp_safe_redirect(admin_url('admin.php?page=airep24woo&tab=connection&connected=0'));
            exit;
        }

        $settings['connection_token'] = sanitize_text_field($result['connectionToken'] ?? $settings['connection_token']);
        $settings['tenant_id'] = sanitize_text_field($result['tenantId'] ?? $settings['tenant_id']);
        $settings['site_id'] = sanitize_text_field($result['siteId'] ?? $settings['site_id']);
        $settings['bot_key'] = sanitize_text_field($result['botKey'] ?? $settings['bot_key']);
        $settings['bot_name'] = sanitize_text_field($result['botName'] ?? $settings['bot_name']);
        if (!empty($result['billing']) && is_array($result['billing'])) {
            $settings['plan'] = sanitize_text_field($result['billing']['plan'] ?? $settings['plan']);
            $settings['plan_status'] = sanitize_text_field($result['billing']['status'] ?? $settings['plan_status']);
        }
        if (!empty($result['widget']) && is_array($result['widget'])) {
            $settings['widget_enabled'] = empty($result['widget']['enabled']) ? '0' : '1';
            $settings['voice_enabled'] = empty($result['widget']['voiceModeEnabled']) ? '0' : '1';
            $settings['primary_color'] = AiRep24Woo_Settings::sanitize_css_color_value($result['widget']['primaryGradient'] ?? $result['widget']['primaryColor'] ?? '', $settings['primary_color']);
            $settings['background_color'] = AiRep24Woo_Settings::sanitize_css_color_value($result['widget']['backgroundColor'] ?? '', $settings['background_color']);
            $settings['position'] = sanitize_key($result['widget']['position'] ?? $settings['position']);
            $settings['avatar_id'] = sanitize_key($result['widget']['characterId'] ?? $settings['avatar_id']);
        }
        if (!empty($result['persona']) && is_array($result['persona'])) {
            $settings['welcome_message'] = sanitize_textarea_field($result['persona']['welcomeMessage'] ?? $settings['welcome_message']);
            $settings['tone'] = sanitize_key($result['persona']['tone'] ?? $settings['tone']);
        }
        if (!empty($result['sitePalette']) && is_array($result['sitePalette'])) {
            $settings['site_palette'] = $result['sitePalette'];
        }
        $settings['last_sync_at'] = current_time('mysql');
        $stored = isset($result['sync']['stored']) ? (int) $result['sync']['stored'] : 0;
        $settings['last_sync_status'] = sprintf(__('Connected and synced %d knowledge items.', 'airep24woo'), $stored);

        AiRep24Woo_Settings::update($settings);
        wp_safe_redirect(admin_url('admin.php?page=airep24woo&connected=1'));
        exit;
    }

    public static function handle_save()
    {
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Permission denied.', 'airep24woo'));
        }
        check_admin_referer('airep24woo_save');

        $settings = AiRep24Woo_Settings::get();
        $settings['api_base_url'] = esc_url_raw($_POST['api_base_url'] ?? $settings['api_base_url']);
        $settings['widget_base_url'] = esc_url_raw($_POST['widget_base_url'] ?? $settings['widget_base_url']);
        $settings['connection_token'] = sanitize_text_field($_POST['connection_token'] ?? $settings['connection_token']);
        $settings['tenant_id'] = sanitize_text_field($_POST['tenant_id'] ?? $settings['tenant_id']);
        $settings['site_id'] = sanitize_text_field($_POST['site_id'] ?? $settings['site_id']);
        $settings['bot_key'] = sanitize_text_field($_POST['bot_key'] ?? $settings['bot_key']);
        $settings['bot_name'] = sanitize_text_field($_POST['bot_name'] ?? $settings['bot_name']);
        $settings['widget_enabled'] = empty($_POST['widget_enabled']) ? '0' : '1';
        $settings['voice_enabled'] = empty($_POST['voice_enabled']) ? '0' : '1';
        $settings['primary_color'] = AiRep24Woo_Settings::sanitize_css_color_value($_POST['primary_color'] ?? '', $settings['primary_color']);
        $settings['background_color'] = AiRep24Woo_Settings::sanitize_css_color_value($_POST['background_color'] ?? '', $settings['background_color']);
        $settings['position'] = sanitize_key($_POST['position'] ?? $settings['position']);
        $settings['avatar_id'] = sanitize_key($_POST['avatar_id'] ?? $settings['avatar_id']);
        $settings['welcome_message'] = sanitize_textarea_field($_POST['welcome_message'] ?? $settings['welcome_message']);
        $settings['tone'] = sanitize_key($_POST['tone'] ?? $settings['tone']);

        AiRep24Woo_Settings::update($settings);

        if (AiRep24Woo_Settings::is_connected()) {
            $client = new AiRep24Woo_API_Client();
            $client->save_remote_config([
                'botName' => $settings['bot_name'],
                'widgetEnabled' => $settings['widget_enabled'] === '1',
                'voiceEnabled' => $settings['voice_enabled'] === '1',
                'primaryColor' => $settings['primary_color'],
                'backgroundColor' => $settings['background_color'],
                'position' => $settings['position'],
                'avatarId' => $settings['avatar_id'],
                'welcomeMessage' => $settings['welcome_message'],
                'tone' => $settings['tone'],
            ]);
        }

        wp_safe_redirect(admin_url('admin.php?page=airep24woo&saved=1'));
        exit;
    }

    public static function handle_sync()
    {
        if (!current_user_can('manage_woocommerce')) {
            wp_die(__('Permission denied.', 'airep24woo'));
        }
        check_admin_referer('airep24woo_sync');

        $settings = AiRep24Woo_Settings::get();
        $client = new AiRep24Woo_API_Client();
        $result = $client->sync_store(AiRep24Woo_Sync::build_store_payload());

        $settings['last_sync_at'] = current_time('mysql');
        if (is_wp_error($result)) {
            $settings['last_sync_status'] = $result->get_error_message();
        } else {
            $stored = isset($result['sync']['stored']) ? (int) $result['sync']['stored'] : 0;
            $settings['last_sync_status'] = sprintf(__('Synced %d knowledge items.', 'airep24woo'), $stored);
        }
        if (!is_wp_error($result) && isset($result['sitePalette'])) {
            $settings['site_palette'] = $result['sitePalette'];
        }
        AiRep24Woo_Settings::update($settings);

        wp_safe_redirect(admin_url('admin.php?page=airep24woo&tab=knowledge&synced=1'));
        exit;
    }

    public static function render()
    {
        if (!current_user_can('manage_woocommerce')) {
            return;
        }

        $settings = AiRep24Woo_Settings::get();
        $client = new AiRep24Woo_API_Client();
        $tab = sanitize_key($_GET['tab'] ?? 'assistant');
        $tabs = [
            'assistant' => __('Assistant', 'airep24woo'),
            'widget' => __('Widget', 'airep24woo'),
            'knowledge' => __('Knowledge', 'airep24woo'),
            'billing' => __('Billing', 'airep24woo'),
            'connection' => __('Connection', 'airep24woo'),
        ];
        ?>
        <div class="wrap airep24woo">
            <div class="airep24woo-hero">
                <div>
                    <p class="airep24woo-kicker">AiRep24 for WooCommerce</p>
                    <h1><?php esc_html_e('AI assistant control center', 'airep24woo'); ?></h1>
                    <p><?php esc_html_e('Configure the same assistant, widget, voice, knowledge sync, trial and billing flow from your WordPress admin.', 'airep24woo'); ?></p>
                </div>
                <div class="airep24woo-hero-actions">
                    <?php if (AiRep24Woo_Settings::is_connected()) : ?>
                        <a class="button button-primary airep24woo-primary" href="<?php echo esc_url($client->onboarding_url()); ?>" target="_blank" rel="noreferrer"><?php esc_html_e('Open AiRep24', 'airep24woo'); ?></a>
                    <?php else : ?>
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                            <?php wp_nonce_field('airep24woo_connect'); ?>
                            <input type="hidden" name="action" value="airep24woo_connect" />
                            <button class="button button-primary airep24woo-primary" type="submit"><?php esc_html_e('Connect store', 'airep24woo'); ?></button>
                        </form>
                        <a class="button" href="<?php echo esc_url($client->onboarding_url()); ?>" target="_blank" rel="noreferrer"><?php esc_html_e('Create AiRep24 account', 'airep24woo'); ?></a>
                    <?php endif; ?>
                </div>
            </div>

            <nav class="nav-tab-wrapper">
                <?php foreach ($tabs as $key => $label) : ?>
                    <a class="nav-tab <?php echo $tab === $key ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url(admin_url('admin.php?page=airep24woo&tab=' . $key)); ?>">
                        <?php echo esc_html($label); ?>
                    </a>
                <?php endforeach; ?>
            </nav>

            <?php if (!AiRep24Woo_Settings::is_connected()) : ?>
                <div class="notice notice-warning inline">
                    <p><?php esc_html_e('Connect the store first. AiRep24 will create the assistant, scan products/pages/colors, and enable the storefront widget after a usable knowledge base is created.', 'airep24woo'); ?></p>
                </div>
            <?php endif; ?>

            <?php if ($tab === 'knowledge') : ?>
                <?php self::render_knowledge($settings); ?>
            <?php elseif ($tab === 'billing') : ?>
                <?php self::render_billing($settings, $client); ?>
            <?php else : ?>
                <?php self::render_settings_form($settings, $tab); ?>
            <?php endif; ?>
        </div>
        <?php
    }

    private static function render_settings_form(array $settings, $tab)
    {
        ?>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="airep24woo-card">
            <?php wp_nonce_field('airep24woo_save'); ?>
            <input type="hidden" name="action" value="airep24woo_save" />

            <?php if ($tab === 'connection') : ?>
                <?php self::field('api_base_url', __('AiRep24 API base URL', 'airep24woo'), $settings['api_base_url']); ?>
                <?php self::field('widget_base_url', __('Widget base URL', 'airep24woo'), $settings['widget_base_url']); ?>
                <?php self::field('connection_token', __('Connection token', 'airep24woo'), $settings['connection_token']); ?>
                <?php self::field('tenant_id', __('Tenant ID', 'airep24woo'), $settings['tenant_id']); ?>
                <?php self::field('site_id', __('Site ID', 'airep24woo'), $settings['site_id']); ?>
                <?php self::field('bot_key', __('Bot key', 'airep24woo'), $settings['bot_key']); ?>
            <?php elseif ($tab === 'widget') : ?>
                <?php self::checkbox('widget_enabled', __('Widget enabled', 'airep24woo'), $settings['widget_enabled']); ?>
                <?php self::checkbox('voice_enabled', __('Voice mode enabled', 'airep24woo'), $settings['voice_enabled']); ?>
                <?php self::field('primary_color', __('Primary color or gradient', 'airep24woo'), $settings['primary_color']); ?>
                <?php self::field('background_color', __('Background color or gradient', 'airep24woo'), $settings['background_color']); ?>
                <?php self::select('position', __('Position', 'airep24woo'), $settings['position'], [
                    'bottom-right' => __('Bottom right', 'airep24woo'),
                    'bottom-left' => __('Bottom left', 'airep24woo'),
                ]); ?>
                <?php self::render_palette($settings); ?>
            <?php else : ?>
                <?php self::field('bot_name', __('Assistant name', 'airep24woo'), $settings['bot_name']); ?>
                <?php self::select('avatar_id', __('Avatar', 'airep24woo'), $settings['avatar_id'], [
                    'anna' => 'Anna',
                    'mia' => 'Mia',
                    'luna' => 'Luna',
                    'sofia' => 'Sofia',
                    'elisa' => 'Elisa',
                ]); ?>
                <?php self::select('tone', __('Tone', 'airep24woo'), $settings['tone'], [
                    'friendly' => __('Friendly', 'airep24woo'),
                    'expert' => __('Expert', 'airep24woo'),
                    'concise' => __('Concise', 'airep24woo'),
                    'sales' => __('Sales focused', 'airep24woo'),
                ]); ?>
                <?php self::textarea('welcome_message', __('Welcome message', 'airep24woo'), $settings['welcome_message']); ?>
            <?php endif; ?>

            <p>
                <button class="button button-primary airep24woo-primary" type="submit"><?php esc_html_e('Save settings', 'airep24woo'); ?></button>
            </p>
        </form>
        <?php
    }

    private static function render_knowledge(array $settings)
    {
        ?>
        <section class="airep24woo-card">
            <h2><?php esc_html_e('Store knowledge sync', 'airep24woo'); ?></h2>
            <p><?php esc_html_e('Sync products, categories, prices, stock, pages and theme colors into AiRep24 knowledge.', 'airep24woo'); ?></p>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <?php wp_nonce_field('airep24woo_sync'); ?>
                <input type="hidden" name="action" value="airep24woo_sync" />
                <button class="button button-primary airep24woo-primary" type="submit"><?php esc_html_e('Sync store now', 'airep24woo'); ?></button>
            </form>
            <dl class="airep24woo-meta">
                <dt><?php esc_html_e('Last sync', 'airep24woo'); ?></dt>
                <dd><?php echo esc_html($settings['last_sync_at'] ?: __('Never', 'airep24woo')); ?></dd>
                <dt><?php esc_html_e('Status', 'airep24woo'); ?></dt>
                <dd><?php echo esc_html($settings['last_sync_status'] ?: __('No sync yet.', 'airep24woo')); ?></dd>
            </dl>
            <?php self::render_palette($settings); ?>
        </section>
        <?php
    }

    private static function render_billing(array $settings, AiRep24Woo_API_Client $client)
    {
        ?>
        <section class="airep24woo-card">
            <h2><?php esc_html_e('Plan & billing', 'airep24woo'); ?></h2>
            <p><?php esc_html_e('Trial and payment are handled by AiRep24 billing, so subscription limits stay consistent across Web, Shopify and WooCommerce.', 'airep24woo'); ?></p>
            <div class="airep24woo-plan-grid">
                <?php foreach (['starter' => '$19', 'growth' => '$49', 'scale' => '$149'] as $plan => $price) : ?>
                    <article class="airep24woo-plan">
                        <h3><?php echo esc_html(ucfirst($plan)); ?></h3>
                        <strong><?php echo esc_html($price); ?></strong>
                        <a class="button" href="<?php echo esc_url($client->checkout_url($plan)); ?>" target="_blank" rel="noreferrer">
                            <?php esc_html_e('Start trial / choose plan', 'airep24woo'); ?>
                        </a>
                    </article>
                <?php endforeach; ?>
            </div>
            <dl class="airep24woo-meta">
                <dt><?php esc_html_e('Current plan', 'airep24woo'); ?></dt>
                <dd><?php echo esc_html($settings['plan'] ?: __('Not connected', 'airep24woo')); ?></dd>
                <dt><?php esc_html_e('Status', 'airep24woo'); ?></dt>
                <dd><?php echo esc_html($settings['plan_status'] ?: __('Unknown', 'airep24woo')); ?></dd>
            </dl>
        </section>
        <?php
    }

    private static function render_palette(array $settings)
    {
        $palette = $settings['site_palette'];
        if (empty($palette['colors']) && empty($palette['gradients'])) {
            return;
        }
        ?>
        <div class="airep24woo-palette">
            <h3><?php esc_html_e('Recommended store colors', 'airep24woo'); ?></h3>
            <div class="airep24woo-swatches">
                <?php foreach ($palette['colors'] as $color) : ?>
                    <?php $value = is_array($color) ? ($color['value'] ?? '') : $color; ?>
                    <?php if (!$value) continue; ?>
                    <button type="button" class="airep24woo-swatch" title="<?php echo esc_attr($value); ?>" data-airep24-color="<?php echo esc_attr($value); ?>" style="background: <?php echo esc_attr($value); ?>"></button>
                <?php endforeach; ?>
                <?php foreach ($palette['gradients'] as $gradient) : ?>
                    <?php $value = is_array($gradient) ? ($gradient['value'] ?? '') : $gradient; ?>
                    <?php if (!$value) continue; ?>
                    <button type="button" class="airep24woo-swatch airep24woo-gradient" title="<?php echo esc_attr($value); ?>" data-airep24-color="<?php echo esc_attr($value); ?>" style="background: <?php echo esc_attr($value); ?>"></button>
                <?php endforeach; ?>
            </div>
        </div>
        <?php
    }

    private static function field($name, $label, $value, $type = 'text')
    {
        ?>
        <label class="airep24woo-field">
            <span><?php echo esc_html($label); ?></span>
            <input type="<?php echo esc_attr($type); ?>" name="<?php echo esc_attr($name); ?>" value="<?php echo esc_attr($value); ?>" />
        </label>
        <?php
    }

    private static function textarea($name, $label, $value)
    {
        ?>
        <label class="airep24woo-field">
            <span><?php echo esc_html($label); ?></span>
            <textarea name="<?php echo esc_attr($name); ?>" rows="4"><?php echo esc_textarea($value); ?></textarea>
        </label>
        <?php
    }

    private static function checkbox($name, $label, $value)
    {
        ?>
        <label class="airep24woo-check">
            <input type="checkbox" name="<?php echo esc_attr($name); ?>" value="1" <?php checked($value, '1'); ?> />
            <span><?php echo esc_html($label); ?></span>
        </label>
        <?php
    }

    private static function select($name, $label, $value, array $options)
    {
        ?>
        <label class="airep24woo-field">
            <span><?php echo esc_html($label); ?></span>
            <select name="<?php echo esc_attr($name); ?>">
                <?php foreach ($options as $key => $optionLabel) : ?>
                    <option value="<?php echo esc_attr($key); ?>" <?php selected($value, $key); ?>><?php echo esc_html($optionLabel); ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <?php
    }
}
