<?php
class PC_Admin {
    public function __construct() {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        add_action('wp_ajax_pc_save_carousel', [$this, 'save_carousel_ajax']);
        add_action('wp_ajax_pc_get_carousel', [$this, 'get_carousel_ajax']);
        add_action('wp_ajax_pc_delete_carousel', [$this, 'delete_carousel_ajax']);
    }

    public function add_admin_menu() {
        add_menu_page(
            __('Product Carousels', 'product-carousel'),
            __('Product Carousels', 'product-carousel'),
            'manage_options',
            'product-carousels',
            [$this, 'render_admin_page'],
            'dashicons-slides',
            56
        );
    }

    public function enqueue_assets($hook) {
        if ($hook !== 'toplevel_page_product-carousels') return;
        
        wp_enqueue_style('pc-admin-css', PC_PLUGIN_URL . 'assets/css/admin.css');
        wp_enqueue_script('pc-admin-js', PC_PLUGIN_URL . 'assets/js/admin.js', 
            ['jquery', 'wp-util', 'select2'], 
            PC_VERSION, 
            true
        );
        
        wp_enqueue_style('select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css');
        wp_enqueue_script('select2', 'https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js', ['jquery']);
        
        // Update enqueue_assets() localization
wp_localize_script('pc-admin-js', 'pc_admin_vars', [
    'ajax_url' => admin_url('admin-ajax.php'),
    'nonce' => wp_create_nonce('pc_admin_nonce'),
    'categories' => $this->get_product_categories(),
    'discount_rules' => $this->get_discount_rules(), // Add this line
    'translations' => [
        'select_category' => __('Select a category', 'product-carousel'),
        'select_rule' => __('Select a discount rule', 'product-carousel'), // Add this
        'no_category' => __('All Categories', 'product-carousel')
    ]
]);
    }

    protected function get_product_categories() {
        $categories = get_terms([
            'taxonomy' => 'product_cat',
            'hide_empty' => false,
        ]);
        
        $options = [];
        foreach ($categories as $category) {
            $options[] = [
                'id' => $category->term_id,
                'text' => $category->name
            ];
        }
        
        return $options;
    }
    
    // Add the new method here
protected function get_discount_rules() {
    if (!class_exists('CTD_DB')) return [];
    
    $rules = CTD_DB::get_all_rules();
    $options = [];
    
    foreach ($rules as $rule) {
        $options[] = [
            'id' => $rule->rule_id,
            'text' => $rule->name
        ];
    }
    
    return $options;
}

    public function save_carousel_ajax() {
        try {
            if (!check_ajax_referer('pc_admin_nonce', 'nonce', false)) {
                throw new Exception(__('Security check failed', 'product-carousel'));
            }

            if (!current_user_can('manage_options')) {
                throw new Exception(__('Permission denied', 'product-carousel'));
            }

            // Validate required fields
            if (empty($_POST['name'])) {
                throw new Exception(__('Name is required', 'product-carousel'));
            }

            // Get and validate settings
            $settings = json_decode(stripslashes($_POST['settings']), true);
            if (json_last_error() !== JSON_ERROR_NONE || !is_array($settings)) {
                throw new Exception(__('Invalid settings data', 'product-carousel'));
            }

            // Sanitize settings
            // Update sanitized settings in PC_Admin::save_carousel_ajax()
// Update sanitized settings in PC_Admin::save_carousel_ajax()
$sanitized_settings = [
    'desktop_columns' => absint($settings['desktop_columns']),
    'mobile_columns' => absint($settings['mobile_columns']),
    'visible_mobile' => absint($settings['visible_mobile']),
    'category' => sanitize_text_field($settings['category']),
    'discount_rule' => absint($settings['discount_rule']), // Add this line
    'order_by' => sanitize_text_field($settings['order_by']),
    'products_per_page' => absint($settings['products_per_page'])
];

            // Prepare data for database
            $data = [
                'name' => sanitize_text_field($_POST['name']),
                'slug' => sanitize_title($_POST['slug']),
                'settings' => wp_json_encode($sanitized_settings),
                'updated_at' => current_time('mysql')
            ];

            global $wpdb;
            $table_name = $wpdb->prefix . 'product_carousels';

            // Insert or update based on carousel_id
            if (!empty($_POST['carousel_id'])) {
                $result = $wpdb->update(
                    $table_name,
                    $data,
                    ['carousel_id' => absint($_POST['carousel_id'])]
                );
            } else {
                $data['created_at'] = current_time('mysql');
                $result = $wpdb->insert($table_name, $data);
            }

            if ($result === false) {
                throw new Exception($wpdb->last_error ?: __('Database error occurred', 'product-carousel'));
            }

            wp_send_json_success(__('Carousel saved successfully', 'product-carousel'));

        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    public function get_carousel_ajax() {
        try {
            if (!check_ajax_referer('pc_admin_nonce', 'nonce', false)) {
                throw new Exception(__('Security check failed', 'product-carousel'));
            }

            if (!current_user_can('manage_options')) {
                throw new Exception(__('Permission denied', 'product-carousel'));
            }

            $carousel_id = absint($_POST['id']);
            if (!$carousel_id) {
                throw new Exception(__('Invalid carousel ID', 'product-carousel'));
            }

            $carousel = PC_DB::get_carousel_by_id($carousel_id);
            if (!$carousel) {
                throw new Exception(__('Carousel not found', 'product-carousel'));
            }

            wp_send_json_success([
                'id' => $carousel->carousel_id,
                'name' => $carousel->name,
                'slug' => $carousel->slug,
                'settings' => json_decode($carousel->settings, true)
            ]);

        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    public function delete_carousel_ajax() {
        try {
            if (!check_ajax_referer('pc_admin_nonce', 'nonce', false)) {
                throw new Exception(__('Security check failed', 'product-carousel'));
            }

            if (!current_user_can('manage_options')) {
                throw new Exception(__('Permission denied', 'product-carousel'));
            }

            $carousel_id = absint($_POST['id']);
            if (!$carousel_id) {
                throw new Exception(__('Invalid carousel ID', 'product-carousel'));
            }

            $result = PC_DB::delete_carousel($carousel_id);
            if ($result === false) {
                throw new Exception(__('Failed to delete carousel', 'product-carousel'));
            }

            wp_send_json_success(__('Carousel deleted successfully', 'product-carousel'));

        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    public function render_admin_page() {
        ?>
        <div class="wrap pc-admin-container">
            <div class="pc-admin-header">
                <h1><?php esc_html_e('Product Carousels', 'product-carousel'); ?></h1>
                <button id="pc-add-new" class="button button-primary">
                    <?php esc_html_e('Add New Carousel', 'product-carousel'); ?>
                </button>
            </div>

            <div class="pc-carousel-list">
                <?php $this->render_carousels_table(); ?>
            </div>

            <div class="pc-carousel-editor" style="display:none;">
                <?php $this->render_carousel_editor(); ?>
            </div>
        </div>
        <?php
    }

    protected function render_carousels_table() {
        $carousels = PC_DB::get_all_carousels();
        ?>
        <table class="wp-list-table widefat fixed striped pc-carousels-table">
            <thead>
                <tr>
                    <th><?php esc_html_e('Name', 'product-carousel'); ?></th>
                    <th><?php esc_html_e('Shortcode', 'product-carousel'); ?></th>
                    <th><?php esc_html_e('Created', 'product-carousel'); ?></th>
                    <th><?php esc_html_e('Actions', 'product-carousel'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($carousels as $carousel) : ?>
                    <tr>
                        <td><?php echo esc_html($carousel->name); ?></td>
                        <td><code>[product_carousel slug="<?php echo esc_attr($carousel->slug); ?>"]</code></td>
                        <td><?php echo date_i18n(get_option('date_format'), strtotime($carousel->created_at)); ?></td>
                        <td>
                            <button class="button pc-edit-carousel" data-id="<?php echo esc_attr($carousel->carousel_id); ?>">
                                <?php esc_html_e('Edit', 'product-carousel'); ?>
                            </button>
                            <button class="button pc-delete-carousel" data-id="<?php echo esc_attr($carousel->carousel_id); ?>">
                                <?php esc_html_e('Delete', 'product-carousel'); ?>
                            </button>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    protected function render_carousel_editor() {
        ?>
        <div class="pc-editor-container">
            <div class="pc-editor-header">
                <h2><?php esc_html_e('Edit Product Carousel', 'product-carousel'); ?></h2>
                <div class="pc-editor-actions">
                    <button id="pc-save-carousel" class="button button-primary">
                        <?php esc_html_e('Save Carousel', 'product-carousel'); ?>
                    </button>
                    <button id="pc-cancel-edit" class="button">
                        <?php esc_html_e('Cancel', 'product-carousel'); ?>
                    </button>
                </div>
            </div>

            <div class="pc-form-section">
                <div class="pc-form-group">
                    <label for="pc-carousel-name"><?php esc_html_e('Carousel Name', 'product-carousel'); ?></label>
                    <input type="text" id="pc-carousel-name" class="regular-text" required>
                </div>

                <div class="pc-form-group">
                    <label for="pc-carousel-slug"><?php esc_html_e('Carousel Slug', 'product-carousel'); ?></label>
                    <input type="text" id="pc-carousel-slug" class="regular-text" required>
                    <p class="description"><?php esc_html_e('Used in the shortcode', 'product-carousel'); ?></p>
                </div>
            </div>

            <div class="pc-form-section">
                <h3><?php esc_html_e('Content Settings', 'product-carousel'); ?></h3>
                <div class="pc-settings-grid">
                    <div class="pc-form-group">
                        <label for="pc-category"><?php esc_html_e('Product Category', 'product-carousel'); ?></label>
                        <select id="pc-category" class="pc-category-select" style="width:100%">
                            <option value=""><?php esc_html_e('All Categories', 'product-carousel'); ?></option>
                        </select>
                    </div>
                    
                     <!-- Add the discount rule select group here -->
                <div class="pc-form-group">
                    <label for="pc-discount-rule"><?php esc_html_e('Discount Rule', 'product-carousel'); ?></label>
                    <select id="pc-discount-rule" class="pc-discount-rule-select" style="width:100%">
                        <option value=""><?php esc_html_e('No Rule Selected', 'product-carousel'); ?></option>
                    </select>
                </div>

                    <div class="pc-form-group">
                        <label for="pc-order-by"><?php esc_html_e('Order By', 'product-carousel'); ?></label>
                        <select id="pc-order-by">
                            <option value="popular"><?php esc_html_e('Popularity', 'product-carousel'); ?></option>
                            <option value="latest"><?php esc_html_e('Latest', 'product-carousel'); ?></option>
                            <option value="rating"><?php esc_html_e('Rating', 'product-carousel'); ?></option>
                            <option value="price_low"><?php esc_html_e('Price: Low to High', 'product-carousel'); ?></option>
                            <option value="price_high"><?php esc_html_e('Price: High to Low', 'product-carousel'); ?></option>
                        </select>
                    </div>

                    <div class="pc-form-group">
                        <label for="pc-products-per-page"><?php esc_html_e('Number of Products', 'product-carousel'); ?></label>
                        <input type="number" id="pc-products-per-page" min="1" max="50" value="10">
                    </div>
                </div>
            </div>

            <div class="pc-form-section">
                <h3><?php esc_html_e('Display Settings', 'product-carousel'); ?></h3>
                <div class="pc-settings-grid">
                    <div class="pc-form-group">
                        <label for="pc-desktop-columns"><?php esc_html_e('Desktop Columns', 'product-carousel'); ?></label>
                        <select id="pc-desktop-columns">
                            <option value="2">2</option>
                            <option value="3">3</option>
                            <option value="4">4</option>
                            <option value="5" selected>5</option>
                            <option value="6">6</option>
                        </select>
                    </div>

                    <div class="pc-form-group">
                        <label for="pc-mobile-columns"><?php esc_html_e('Mobile Columns', 'product-carousel'); ?></label>
                        <select id="pc-mobile-columns">
                            <option value="1">1</option>
                            <option value="2" selected>2</option>
                        </select>
                    </div>

                    <div class="pc-form-group">
                        <label for="pc-visible-mobile"><?php esc_html_e('Visible Items on Mobile', 'product-carousel'); ?></label>
                        <select id="pc-visible-mobile">
                            <option value="1">1</option>
                            <option value="2" selected>2</option>
                        </select>
                    </div>
                </div>
            </div>
        </div>
        <?php
    }
}