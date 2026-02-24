<?php
/**
 * Plugin Name: TaxoAI for WooCommerce
 * Plugin URI:  https://taxoai.dev
 * Description: Auto-categorize WooCommerce products using the TaxoAI API and generate SEO content.
 * Version:     1.0.0
 * Author:      TaxoAI
 * Author URI:  https://taxoai.dev
 * License:     GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: woocommerce-taxoai
 * Domain Path: /languages
 * Requires at least: 5.8
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 9.0
 *
 * @package TaxoAI
 */

defined( 'ABSPATH' ) || exit;

define( 'TAXOAI_VERSION', '1.0.0' );
define( 'TAXOAI_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'TAXOAI_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'TAXOAI_API_URL', 'https://api.taxoai.dev' );

/**
 * Check if WooCommerce is active before loading the plugin.
 *
 * @return bool
 */
function taxoai_is_woocommerce_active() {
    $active_plugins = (array) get_option( 'active_plugins', array() );

    if ( is_multisite() ) {
        $active_plugins = array_merge( $active_plugins, array_keys( get_site_option( 'active_sitewide_plugins', array() ) ) );
    }

    return in_array( 'woocommerce/woocommerce.php', $active_plugins, true );
}

/**
 * Display admin notice if WooCommerce is not active.
 */
function taxoai_woocommerce_missing_notice() {
    ?>
    <div class="notice notice-error">
        <p>
            <?php
            printf(
                /* translators: %s: WooCommerce plugin name */
                esc_html__( '%s requires WooCommerce to be installed and active.', 'woocommerce-taxoai' ),
                '<strong>TaxoAI for WooCommerce</strong>'
            );
            ?>
        </p>
    </div>
    <?php
}

if ( ! taxoai_is_woocommerce_active() ) {
    add_action( 'admin_notices', 'taxoai_woocommerce_missing_notice' );
    return;
}

// Include all class files.
require_once TAXOAI_PLUGIN_DIR . 'includes/class-taxoai-api-client.php';
require_once TAXOAI_PLUGIN_DIR . 'includes/class-taxoai-usage-tracker.php';
require_once TAXOAI_PLUGIN_DIR . 'includes/class-taxoai-seo-integrator.php';
require_once TAXOAI_PLUGIN_DIR . 'includes/class-taxoai-category-mapper.php';
require_once TAXOAI_PLUGIN_DIR . 'includes/class-taxoai-attribute-mapper.php';
require_once TAXOAI_PLUGIN_DIR . 'includes/class-taxoai-product-analyzer.php';
require_once TAXOAI_PLUGIN_DIR . 'includes/class-taxoai-settings.php';
require_once TAXOAI_PLUGIN_DIR . 'includes/class-taxoai-product-metabox.php';
require_once TAXOAI_PLUGIN_DIR . 'includes/class-taxoai-ajax-handler.php';
require_once TAXOAI_PLUGIN_DIR . 'includes/class-taxoai-bulk-analyzer.php';
require_once TAXOAI_PLUGIN_DIR . 'includes/class-taxoai-product-columns.php';
require_once TAXOAI_PLUGIN_DIR . 'includes/class-taxoai-plugin.php';

/**
 * Initialize the plugin.
 */
function taxoai_init() {
    TaxoAI_Plugin::get_instance();
}
add_action( 'plugins_loaded', 'taxoai_init' );

/**
 * Plugin activation hook.
 */
function taxoai_activate() {
    if ( ! taxoai_is_woocommerce_active() ) {
        deactivate_plugins( plugin_basename( __FILE__ ) );
        wp_die(
            esc_html__( 'TaxoAI for WooCommerce requires WooCommerce to be installed and active.', 'woocommerce-taxoai' ),
            'Plugin Activation Error',
            array( 'back_link' => true )
        );
    }

    add_option( 'taxoai_api_key', '' );
    add_option( 'taxoai_language', 'es' );
    add_option( 'taxoai_auto_analyze', '0' );
    add_option( 'taxoai_confidence_threshold', '0.7' );
    add_option( 'taxoai_update_title', '0' );
    add_option( 'taxoai_update_description', '0' );
    add_option( 'taxoai_auto_map_categories', '0' );
    add_option( 'taxoai_analyze_images', '0' );
    add_option( 'taxoai_usage_count', 0 );
    add_option( 'taxoai_usage_month', gmdate( 'Y-m' ) );

    flush_rewrite_rules();
}
register_activation_hook( __FILE__, 'taxoai_activate' );

/**
 * Plugin deactivation hook.
 */
function taxoai_deactivate() {
    delete_transient( 'taxoai_usage_cache' );
    flush_rewrite_rules();
}
register_deactivation_hook( __FILE__, 'taxoai_deactivate' );
