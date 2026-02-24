<?php
/**
 * TaxoAI Category Mapper.
 *
 * @package TaxoAI
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class TaxoAI_Category_Mapper
 *
 * Maps Google product taxonomy categories to WooCommerce product categories.
 */
class TaxoAI_Category_Mapper {

    /**
     * Map a Google taxonomy category to the product.
     *
     * Always stores the Google Feed-compatible meta fields. Optionally maps
     * to a WooCommerce product_cat term.
     *
     * @param int    $product_id        Product (post) ID.
     * @param string $google_category    Full Google taxonomy path.
     * @param int    $google_category_id Google taxonomy numeric ID.
     */
    public function map( $product_id, $google_category, $google_category_id ) {
        // Always store Google Feed meta (used by Google Shopping feed plugins).
        if ( ! empty( $google_category ) ) {
            update_post_meta( $product_id, '_google_product_category', sanitize_text_field( $google_category ) );
        }
        if ( $google_category_id > 0 ) {
            update_post_meta( $product_id, '_google_product_category_id', (int) $google_category_id );
        }

        // Auto-map to WooCommerce category if enabled.
        if ( '1' !== get_option( 'taxoai_auto_map_categories', '0' ) ) {
            return;
        }

        if ( empty( $google_category ) ) {
            return;
        }

        // Extract the leaf category name from the full path.
        $leaf = $this->get_leaf_category( $google_category );
        if ( empty( $leaf ) ) {
            return;
        }

        // Find or create the WooCommerce product_cat.
        $term = $this->find_or_create_term( $leaf );
        if ( is_wp_error( $term ) || ! $term ) {
            return;
        }

        // Assign the category to the product (append, do not replace).
        wp_set_object_terms( $product_id, array( (int) $term ), 'product_cat', true );
    }

    /**
     * Get the leaf (last segment) of a Google taxonomy path.
     *
     * Example: "Animals & Pet Supplies > Pet Supplies > Dog Supplies > Dog Beds"
     * Returns: "Dog Beds"
     *
     * @param string $full_path Full taxonomy path separated by " > ".
     * @return string Leaf category name, trimmed.
     */
    private function get_leaf_category( $full_path ) {
        $parts = explode( '>', $full_path );
        $leaf  = trim( end( $parts ) );
        return $leaf;
    }

    /**
     * Find an existing WooCommerce product_cat by name or create a new one.
     *
     * @param string $name Category name.
     * @return int|false|WP_Error Term ID on success, false/WP_Error on failure.
     */
    private function find_or_create_term( $name ) {
        // Try to find an existing term.
        $existing = get_term_by( 'name', $name, 'product_cat' );
        if ( $existing && ! is_wp_error( $existing ) ) {
            return $existing->term_id;
        }

        // Create the term.
        $inserted = wp_insert_term( $name, 'product_cat' );
        if ( is_wp_error( $inserted ) ) {
            // The term may already exist with a different case; try by slug.
            $slug_term = get_term_by( 'slug', sanitize_title( $name ), 'product_cat' );
            if ( $slug_term && ! is_wp_error( $slug_term ) ) {
                return $slug_term->term_id;
            }
            return $inserted; // Return the WP_Error.
        }

        return $inserted['term_id'];
    }
}
