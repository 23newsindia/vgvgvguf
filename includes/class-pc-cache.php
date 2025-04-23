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

        // Clear cache when carousel settings are updated
        add_action('pc_carousel_settings_updated', [$this, 'clear_carousel_cache']);
    }

    public function get_cache_key($type, $identifier, $settings = []) {
        $key_parts = [$type, $identifier];
        
        if (!empty($settings)) {
            // Include relevant settings in cache key
            if (!empty($settings['category'])) {
                $key_parts[] = 'cat_' . $settings['category'];
            }
            if (!empty($settings['discount_rule'])) {
                $key_parts[] = 'rule_' . $settings['discount_rule'];
            }
            if (!empty($settings['order_by'])) {
                $key_parts[] = 'order_' . $settings['order_by'];
            }
            $key_parts[] = 'limit_' . ($settings['products_per_page'] ?? 10);
        }
        
        return 'pc_' . md5(implode('_', $key_parts)) . '_' . PC_VERSION;
    }

    public function get_carousel($slug, $settings = []) {
        $key = $this->get_cache_key('carousel', $slug, $settings);
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
        $key = $this->get_cache_key('products', $slug, $settings);
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
        // Clear term-specific cache
        $key = $this->get_cache_key('term', $term_id);
        wp_cache_delete($key, self::CACHE_GROUP);
        
        // Get child terms
        $children = get_term_children($term_id, 'product_cat');
        foreach ($children as $child_id) {
            $child_key = $this->get_cache_key('term', $child_id);
            wp_cache_delete($child_key, self::CACHE_GROUP);
        }
        
        // Clear related carousels
        $this->clear_related_carousels_by_term($term_id);
        
        // Clear transients
        $this->clear_transients();
    }

    public function clear_carousel_cache($carousel_id) {
        global $wpdb;
        $carousel = PC_DB::get_carousel_by_id($carousel_id);
        
        if ($carousel) {
            // Clear specific carousel cache
            $key = $this->get_cache_key('carousel', $carousel->slug);
            wp_cache_delete($key, self::CACHE_GROUP);
            
            // Clear products cache for this carousel
            $products_key = $this->get_cache_key('products', $carousel->slug);
            wp_cache_delete($products_key, self::CACHE_GROUP);
        }
        
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
                $this->clear_carousel_cache($carousel->carousel_id);
            }
        }
    }

    protected function clear_related_carousels_by_term($term_id) {
        global $wpdb;
        $carousels = $wpdb->get_results("SELECT carousel_id, settings FROM {$wpdb->prefix}product_carousels");
        
        foreach ($carousels as $carousel) {
            $settings = json_decode($carousel->settings, true);
            if (!empty($settings['category'])) {
                // Check if term is the selected category or its ancestor
                $term_ancestors = get_ancestors($settings['category'], 'product_cat');
                if ($settings['category'] == $term_id || in_array($term_id, $term_ancestors)) {
                    $this->clear_carousel_cache($carousel->carousel_id);
                }
            }
        }
    }

    protected function carousel_contains_product($settings, $product_id) {
        $args = [
            'post__in' => [$product_id],
            'post_type' => 'product',
            'posts_per_page' => 1,
            'tax_query' => [
                'relation' => 'AND'
            ]
        ];
        
        if (!empty($settings['category'])) {
            $category_terms = get_term_children($settings['category'], 'product_cat');
            $category_terms[] = $settings['category'];
            
            $args['tax_query'][] = [
                'taxonomy' => 'product_cat',
                'field' => 'term_id',
                'terms' => $category_terms,
                'operator' => 'IN',
                'include_children' => true
            ];
        }
        
        $query = new WP_Query($args);
        return $query->found_posts > 0;
    }
}