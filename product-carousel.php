<?php
/*
Plugin Name: Product Carousel for WooCommerce
Description: Display WooCommerce products in responsive carousels with category filtering
Version: 1.0.0
Author: Your Name
Requires at least: 5.6
Requires PHP: 7.4
*/

if (!defined('ABSPATH')) exit;

// Define constants
define('PC_VERSION', '1.0.0');
define('PC_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('PC_PLUGIN_URL', plugin_dir_url(__FILE__));

// Check if WooCommerce is active
if (!in_array('woocommerce/woocommerce.php', apply_filters('active_plugins', get_option('active_plugins')))) {
    add_action('admin_notices', function() {
        echo '<div class="error"><p>';
        _e('Product Carousel requires WooCommerce to be installed and active.', 'product-carousel');
        echo '</p></div>';
    });
    return;
}

// Include core files
require_once PC_PLUGIN_DIR . 'includes/class-pc-db.php';
require_once PC_PLUGIN_DIR . 'includes/class-pc-admin.php';
require_once PC_PLUGIN_DIR . 'includes/class-pc-frontend.php';
require_once PC_PLUGIN_DIR . 'includes/class-pc-shortcode.php';

// Initialize components
register_activation_hook(__FILE__, ['PC_DB', 'create_tables']);
add_action('plugins_loaded', function() {
    PC_DB::check_tables();
    new PC_Admin();
    new PC_Frontend();
    new PC_Shortcode();
});