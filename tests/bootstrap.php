<?php
/**
 * TaxoAI Test Bootstrap.
 *
 * Sets up WP_Mock and defines stubs for WordPress/WooCommerce functions
 * so that unit tests can run without a full WordPress installation.
 *
 * @package TaxoAI
 */

require_once dirname( __DIR__ ) . '/vendor/autoload.php';

WP_Mock::bootstrap();

// Define constants used by the plugin.
if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', '/tmp/wordpress/' );
}
if ( ! defined( 'TAXOAI_VERSION' ) ) {
    define( 'TAXOAI_VERSION', '1.0.0' );
}
if ( ! defined( 'TAXOAI_PLUGIN_DIR' ) ) {
    define( 'TAXOAI_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
}
if ( ! defined( 'TAXOAI_PLUGIN_URL' ) ) {
    define( 'TAXOAI_PLUGIN_URL', 'https://example.com/wp-content/plugins/woocommerce-taxoai/' );
}
if ( ! defined( 'TAXOAI_API_URL' ) ) {
    define( 'TAXOAI_API_URL', 'https://api.taxoai.dev' );
}
if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
    define( 'HOUR_IN_SECONDS', 3600 );
}

// Load the classes under test.
require_once TAXOAI_PLUGIN_DIR . 'includes/class-taxoai-api-client.php';
require_once TAXOAI_PLUGIN_DIR . 'includes/class-taxoai-usage-tracker.php';
require_once TAXOAI_PLUGIN_DIR . 'includes/class-taxoai-seo-integrator.php';
require_once TAXOAI_PLUGIN_DIR . 'includes/class-taxoai-category-mapper.php';
require_once TAXOAI_PLUGIN_DIR . 'includes/class-taxoai-attribute-mapper.php';
require_once TAXOAI_PLUGIN_DIR . 'includes/class-taxoai-product-analyzer.php';
