<?php
/**
 * TaxoAI for WooCommerce Uninstall.
 *
 * Fired when the plugin is uninstalled (deleted) from the WordPress admin.
 * Removes all plugin options, post meta, and transients.
 *
 * @package TaxoAI
 */

// Abort if not called by WordPress.
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Delete plugin options.
$options = array(
    'taxoai_api_key',
    'taxoai_language',
    'taxoai_auto_analyze',
    'taxoai_confidence_threshold',
    'taxoai_update_title',
    'taxoai_update_description',
    'taxoai_auto_map_categories',
    'taxoai_analyze_images',
    'taxoai_usage_count',
    'taxoai_usage_month',
);

foreach ( $options as $option ) {
    delete_option( $option );
}

// Delete all _taxoai_ post meta across all products.
global $wpdb;

$wpdb->query(
    $wpdb->prepare(
        "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE %s",
        $wpdb->esc_like( '_taxoai_' ) . '%'
    )
);

// Also clean up Google category meta that we set.
$wpdb->query(
    $wpdb->prepare(
        "DELETE FROM {$wpdb->postmeta} WHERE meta_key = %s OR meta_key = %s",
        '_google_product_category',
        '_google_product_category_id'
    )
);

// Delete transients.
delete_transient( 'taxoai_usage_cache' );

// Clean up any lingering job map transients.
$wpdb->query(
    $wpdb->prepare(
        "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
        $wpdb->esc_like( '_transient_taxoai_job_map_' ) . '%'
    )
);
$wpdb->query(
    $wpdb->prepare(
        "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
        $wpdb->esc_like( '_transient_timeout_taxoai_job_map_' ) . '%'
    )
);
