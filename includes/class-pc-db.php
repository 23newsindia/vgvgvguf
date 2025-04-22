<?php
class PC_DB {
    private static $table_name = 'product_carousels';
    private static $db_version = '1.0';

    public static function create_tables() {
        global $wpdb;
        $table = $wpdb->prefix . self::$table_name;
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE IF NOT EXISTS $table (
            carousel_id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            name VARCHAR(255) NOT NULL,
            slug VARCHAR(255) NOT NULL,
            settings LONGTEXT NOT NULL,
            created_at DATETIME NOT NULL,
            updated_at DATETIME NOT NULL,
            PRIMARY KEY  (carousel_id),
            UNIQUE KEY slug (slug),
            KEY name (name),
            KEY created_at (created_at),
            KEY updated_at (updated_at)
        ) $charset;";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);

        update_option('pc_db_version', self::$db_version);
    }

    public static function check_tables() {
        global $wpdb;
        $table = $wpdb->prefix . self::$table_name;
        
        if ($wpdb->get_var("SHOW TABLES LIKE '$table'") != $table || 
            get_option('pc_db_version') !== self::$db_version) {
            self::create_tables();
        }
    }

    public static function get_carousel($slug) {
        global $wpdb;
        $table = $wpdb->prefix . self::$table_name;
        
        $sql = $wpdb->prepare(
            "SELECT * FROM $table WHERE slug = %s LIMIT 1",
            $slug
        );
        
        $result = $wpdb->get_row($sql);
        
        if ($result) {
            $result->settings = json_decode($result->settings, true);
        }
        
        return $result;
    }

    public static function get_carousel_by_id($id) {
        global $wpdb;
        $table = $wpdb->prefix . self::$table_name;
        
        $sql = $wpdb->prepare(
            "SELECT * FROM $table WHERE carousel_id = %d LIMIT 1",
            $id
        );
        
        $result = $wpdb->get_row($sql);
        
        if ($result) {
            $result->settings = json_decode($result->settings, true);
        }
        
        return $result;
    }

    public static function get_all_carousels($args = []) {
        global $wpdb;
        $table = $wpdb->prefix . self::$table_name;
        
        $defaults = [
            'orderby' => 'created_at',
            'order' => 'DESC',
            'limit' => 100,
            'offset' => 0,
            'search' => ''
        ];
        
        $args = wp_parse_args($args, $defaults);
        
        $where = '1=1';
        $values = [];
        
        if (!empty($args['search'])) {
            $where .= ' AND (name LIKE %s OR slug LIKE %s)';
            $search_term = '%' . $wpdb->esc_like($args['search']) . '%';
            $values[] = $search_term;
            $values[] = $search_term;
        }
        
        $orderby = sanitize_sql_orderby($args['orderby'] . ' ' . $args['order']);
        
        $limit = '';
        if ($args['limit'] > 0) {
            $limit = $wpdb->prepare(' LIMIT %d OFFSET %d', 
                $args['limit'], 
                $args['offset']
            );
        }
        
        $sql = "SELECT carousel_id, name, slug, created_at 
                FROM $table 
                WHERE $where 
                ORDER BY $orderby
                $limit";
                
        if (!empty($values)) {
            $sql = $wpdb->prepare($sql, $values);
        }
        
        return $wpdb->get_results($sql);
    }

    public static function save_carousel($data) {
        global $wpdb;
        $table = $wpdb->prefix . self::$table_name;
        
        $defaults = [
            'name' => '',
            'slug' => sanitize_title($data['name']),
            'settings' => wp_json_encode([
                'desktop_columns' => 5,
                'mobile_columns' => 2,
                'visible_mobile' => 2,
                'category' => '',
                'discount_rule' => '',
                'order_by' => 'popular',
                'products_per_page' => 10
            ]),
            'created_at' => current_time('mysql', true),
            'updated_at' => current_time('mysql', true)
        ];
        
        $data = wp_parse_args($data, $defaults);
        
        // Validate JSON data
        if (isset($data['settings']) && is_array($data['settings'])) {
            $data['settings'] = wp_json_encode($data['settings']);
        }
        
        // Ensure proper date format
        $data['created_at'] = gmdate('Y-m-d H:i:s', strtotime($data['created_at']));
        $data['updated_at'] = gmdate('Y-m-d H:i:s', strtotime($data['updated_at']));
        
        $result = $wpdb->insert($table, $data, [
            '%s', // name
            '%s', // slug
            '%s', // settings
            '%s', // created_at
            '%s'  // updated_at
        ]);
        
        if ($result === false) {
            return new WP_Error('db_insert_error', 
                __('Could not insert carousel into database.', 'product-carousel'),
                $wpdb->last_error
            );
        }
        
        return $wpdb->insert_id;
    }

    public static function update_carousel($id, $data) {
        global $wpdb;
        $table = $wpdb->prefix . self::$table_name;
        
        // Ensure we have valid data
        if (empty($data)) {
            return new WP_Error('invalid_data', 
                __('No data provided for update.', 'product-carousel')
            );
        }
        
        // Always update the updated_at timestamp
        $data['updated_at'] = current_time('mysql', true);
        
        // Validate JSON data
        if (isset($data['settings']) && is_array($data['settings'])) {
            $data['settings'] = wp_json_encode($data['settings']);
        }
        
        // Prepare format array based on data structure
        $formats = [];
        foreach ($data as $key => $value) {
            $formats[] = '%s';
        }
        
        $result = $wpdb->update(
            $table,
            $data,
            ['carousel_id' => $id],
            $formats,
            ['%d']
        );
        
        if ($result === false) {
            return new WP_Error('db_update_error', 
                __('Could not update carousel in database.', 'product-carousel'),
                $wpdb->last_error
            );
        }
        
        return true;
    }

    public static function delete_carousel($id) {
        global $wpdb;
        $table = $wpdb->prefix . self::$table_name;
        
        $result = $wpdb->delete(
            $table,
            ['carousel_id' => $id],
            ['%d']
        );
        
        if ($result === false) {
            return new WP_Error('db_delete_error', 
                __('Could not delete carousel from database.', 'product-carousel'),
                $wpdb->last_error
            );
        }
        
        return true;
    }

    public static function count_carousels($args = []) {
        global $wpdb;
        $table = $wpdb->prefix . self::$table_name;
        
        $where = '1=1';
        $values = [];
        
        if (!empty($args['search'])) {
            $where .= ' AND (name LIKE %s OR slug LIKE %s)';
            $search_term = '%' . $wpdb->esc_like($args['search']) . '%';
            $values[] = $search_term;
            $values[] = $search_term;
        }
        
        $sql = "SELECT COUNT(*) FROM $table WHERE $where";
        
        if (!empty($values)) {
            $sql = $wpdb->prepare($sql, $values);
        }
        
        return (int) $wpdb->get_var($sql);
    }
}