<?php
class PC_Cache {
    const CACHE_GROUP = 'product_carousel';
    const CACHE_EXPIRY = HOUR_IN_SECONDS;
    const PRODUCTS_EXPIRY = 15 * MINUTE_IN_SECONDS;

    public function __construct() {
        add_action('save_post_product', [$this, 'clear_product_cache']);
        add_action('woocommerce_update_product', [$this, 'clear_product_cache']);
        add_action('woocommerce_product_set_stock', [$this, 'clear_product_cache']);
        add_action('woocommerce_variation_set_stock', [$this, 'clear_product_cache']);
        add_action('created_product_cat', [$this, 'clear_term_cache']);
        add_action('edited_product_cat', [$this, 'clear_term_cache']);
        add_action('delete_product_cat', [$this, 'clear_term_cache']);
        add_action('ctd_rule_updated', [$this, 'clear_all_cache']);
        
        // Clear cache on price changes
        add_action('woocommerce_product_set_sale_price', [$this, 'clear_product_cache']);
        add_action('woocommerce_product_set_regular_price', [$this, 'clear_product_cache']);
        
        // Clear cache on product status changes
        add_action('woocommerce_product_set_status', [$this, 'clear_product_cache']);
        
        // Clear cache on variation updates
        add_action('woocommerce_update_product_variation', [$this, 'clear_product_cache']);
        add_action('woocommerce_save_product_variation', [$this, 'clear_product_cache']);
        
        // Clear cache on bulk actions
        add_action('woocommerce_product_bulk_edit_save', [$this, 'clear_all_cache']);
        add_action('woocommerce_product_import_inserted_product_object', [$this, 'clear_all_cache']);
    }

    public function get_cache_key($type, $identifier) {
        return sprintf('pc_%s_%s_%s', $type, md5($identifier), PC_VERSION);
    }

    public function get_carousel($slug) {
        $key = $this->get_cache_key('carousel', $slug);
        $carousel = wp_cache_get($key, self::CACHE_GROUP);
        
        if ($carousel === false) {
            $carousel = PC_DB::get_carousel($slug);
            if ($carousel) {
                wp_cache_set($key, $carousel, self::CACHE_GROUP, self::CACHE_EXPIRY);
            }
        }
        
        return $carousel;
    }

    public function get_products($settings, $slug) {
        $key = $this->get_cache_key('products', $slug . '_' . json_encode($settings));
        $products = wp_cache_get($key, self::CACHE_GROUP);
        
        if ($products === false) {
            $products = $this->get_filtered_products($settings);
            if ($products) {
                wp_cache_set($key, $products, self::CACHE_GROUP, self::PRODUCTS_EXPIRY);
            }
        }
        
        return $products;
    }

    public function clear_product_cache($product_id) {
        $product = wc_get_product($product_id);
        if (!$product) return;
        
        // Clear product-specific cache
        $key = $this->get_cache_key('product', $product_id);
        wp_cache_delete($key, self::CACHE_GROUP);
        
        // Clear category caches
        $categories = wc_get_product_term_ids($product_id, 'product_cat');
        foreach ($categories as $category_id) {
            $this->clear_term_cache($category_id);
        }
        
        // Clear carousels containing this product
        $this->clear_related_carousels($product_id);
        
        // Clear transients
        $this->clear_transients();
    }

    public function clear_term_cache($term_id) {
        $key = $this->get_cache_key('term', $term_id);
        wp_cache_delete($key, self::CACHE_GROUP);
        
        // Clear related carousels
        $this->clear_related_carousels_by_term($term_id);
        
        // Clear transients
        $this->clear_transients();
    }

    public function clear_all_cache() {
        wp_cache_flush();
        $this->clear_transients();
    }

    protected function clear_transients() {
        global $wpdb;
        
        $wpdb->query(
            "DELETE FROM $wpdb->options 
             WHERE option_name LIKE '_transient_pc_%' 
             OR option_name LIKE '_transient_timeout_pc_%'"
        );
    }

    protected function clear_related_carousels($product_id) {
        global $wpdb;
        $carousels = $wpdb->get_results("SELECT carousel_id, settings FROM {$wpdb->prefix}product_carousels");
        
        foreach ($carousels as $carousel) {
            $settings = json_decode($carousel->settings, true);
            if ($this->carousel_contains_product($settings, $product_id)) {
                $key = $this->get_cache_key('carousel', $carousel->carousel_id);
                wp_cache_delete($key, self::CACHE_GROUP);
            }
        }
    }

    protected function clear_related_carousels_by_term($term_id) {
        global $wpdb;
        $carousels = $wpdb->get_results("SELECT carousel_id, settings FROM {$wpdb->prefix}product_carousels");
        
        foreach ($carousels as $carousel) {
            $settings = json_decode($carousel->settings, true);
            if (!empty($settings['category']) && $settings['category'] == $term_id) {
                $key = $this->get_cache_key('carousel', $carousel->carousel_id);
                wp_cache_delete($key, self::CACHE_GROUP);
            }
        }
    }

    protected function carousel_contains_product($settings, $product_id) {
        $args = [
            'post__in' => [$product_id],
            'post_type' => 'product',
            'posts_per_page' => 1
        ];
        
        if (!empty($settings['category'])) {
            $args['tax_query'] = [
                [
                    'taxonomy' => 'product_cat',
                    'field' => 'term_id',
                    'terms' => $settings['category']
                ]
            ];
        }
        
        $query = new WP_Query($args);
        return $query->found_posts > 0;
    }
}

// Initialize in main plugin file
new PC_Cache();