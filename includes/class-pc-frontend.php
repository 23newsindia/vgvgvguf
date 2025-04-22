<?php
class PC_Frontend {
    private $has_carousel = false;
    private static $assets_enqueued = false;

    public function __construct() {
        add_action('wp_enqueue_scripts', [$this, 'register_assets']);
        add_action('wp_footer', [$this, 'maybe_enqueue_assets'], 5);
        add_filter('script_loader_tag', [$this, 'add_async_defer'], 10, 2);
    }

    public function register_assets() {
        // Register CSS with preload
        wp_register_style(
            'pc-frontend-css',
            PC_PLUGIN_URL . 'assets/css/frontend.css',
            [],
            PC_VERSION
        );

        // Register JS with defer
        wp_register_script(
            'pc-frontend-js',
            PC_PLUGIN_URL . 'assets/js/frontend.js',
            ['jquery'],
            PC_VERSION,
            true
        );

        // Add preload hint for CSS
        add_action('wp_head', function() {
            if ($this->should_load_assets()) {
                printf(
                    '<link rel="preload" href="%s" as="style">',
                    esc_url(PC_PLUGIN_URL . 'assets/css/frontend.css')
                );
            }
        }, 1);
    }

    public function maybe_enqueue_assets() {
        if ($this->should_load_assets() && !self::$assets_enqueued) {
            $this->enqueue_assets();
        }
    }

    public function add_async_defer($tag, $handle) {
        if ('pc-frontend-js' === $handle) {
            return str_replace(' src', ' defer src', $tag);
        }
        return $tag;
    }

    protected function should_load_assets() {
        if ($this->has_carousel) {
            return true;
        }

        // Check main content
        global $post;
        if (is_a($post, 'WP_Post') && 
            (has_shortcode($post->post_content, 'product_carousel') || 
             has_block('product-carousel/carousel'))) {
            $this->has_carousel = true;
            return true;
        }

        // Check widgets efficiently
        if (is_active_widget(false, false, 'text', true)) {
            $widgets = wp_cache_get('widget_text', 'widget');
            if (false === $widgets) {
                $widgets = get_option('widget_text');
                wp_cache_set('widget_text', $widgets, 'widget');
            }

            foreach ((array)$widgets as $widget) {
                if (isset($widget['text']) && 
                    (has_shortcode($widget['text'], 'product_carousel') || 
                     has_block('product-carousel/carousel'))) {
                    $this->has_carousel = true;
                    return true;
                }
            }
        }

        // For AJAX requests
        if (wp_doing_ajax() && 
            isset($_POST['action']) && 
            strpos($_POST['action'], 'pc_') === 0) {
            return true;
        }

        return false;
    }

    protected function enqueue_assets() {
        // Load theme's product styles if available
        if (function_exists('wc_get_theme_template_path')) {
            $theme_product_css = get_template_directory_uri() . '/woocommerce/content-product.css';
            if ($this->remote_file_exists($theme_product_css)) {
                wp_enqueue_style(
                    'theme-product-style',
                    $theme_product_css,
                    [],
                    PC_VERSION
                );
            }
        }

        // Enqueue our styles and scripts
        wp_enqueue_style('pc-frontend-css');
        wp_enqueue_script('pc-frontend-js');

        // Localize script with minimal data
        wp_localize_script('pc-frontend-js', 'pc_frontend_vars', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('pc_ajax_nonce'),
            'is_rtl' => is_rtl()
        ]);

        self::$assets_enqueued = true;
    }

    protected function remote_file_exists($url) {
        $cache_key = 'pc_file_exists_' . md5($url);
        $exists = wp_cache_get($cache_key);

        if (false === $exists) {
            $response = wp_remote_head($url, [
                'timeout' => 5,
                'sslverify' => false
            ]);
            
            $exists = !is_wp_error($response) && 200 === wp_remote_retrieve_response_code($response);
            wp_cache_set($cache_key, $exists, '', HOUR_IN_SECONDS);
        }

        return $exists;
    }

    public function is_carousel_loaded() {
        return $this->has_carousel;
    }
}