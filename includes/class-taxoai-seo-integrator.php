<?php
/**
 * TaxoAI SEO Integrator.
 *
 * @package TaxoAI
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class TaxoAI_SEO_Integrator
 *
 * Applies SEO data from TaxoAI analysis to the product, integrating with
 * Yoast SEO, Rank Math, or falling back to custom meta keys.
 */
class TaxoAI_SEO_Integrator {

    /**
     * Apply SEO data to a product.
     *
     * @param int   $product_id Product (post) ID.
     * @param array $seo_data   SEO data from the API response.
     * @param array $settings   Plugin settings: update_title, update_description.
     */
    public function apply_seo( $product_id, array $seo_data, array $settings ) {
        $meta_title       = isset( $seo_data['meta_title'] ) ? sanitize_text_field( $seo_data['meta_title'] ) : '';
        $meta_description = isset( $seo_data['meta_description'] ) ? sanitize_text_field( $seo_data['meta_description'] ) : '';
        $optimized_title  = isset( $seo_data['optimized_title'] ) ? sanitize_text_field( $seo_data['optimized_title'] ) : '';
        $optimized_desc   = isset( $seo_data['optimized_description'] ) ? wp_kses_post( $seo_data['optimized_description'] ) : '';
        $keywords         = isset( $seo_data['keywords'] ) && is_array( $seo_data['keywords'] ) ? $seo_data['keywords'] : array();
        $tags             = isset( $seo_data['tags'] ) && is_array( $seo_data['tags'] ) ? $seo_data['tags'] : array();

        // Determine the primary keyword for SEO plugins.
        $focus_keyword = '';
        if ( ! empty( $keywords ) ) {
            // Pick the first keyword (typically the highest-priority one).
            $first = reset( $keywords );
            $focus_keyword = isset( $first['keyword'] ) ? sanitize_text_field( $first['keyword'] ) : '';
        }

        // Apply to the appropriate SEO plugin.
        if ( $this->is_yoast_active() ) {
            $this->apply_yoast( $product_id, $meta_title, $meta_description, $focus_keyword );
        } elseif ( $this->is_rank_math_active() ) {
            $this->apply_rank_math( $product_id, $meta_title, $meta_description, $focus_keyword );
        } else {
            $this->apply_fallback( $product_id, $meta_title, $meta_description );
        }

        // Optionally update the product title.
        if ( ! empty( $settings['update_title'] ) && $settings['update_title'] && ! empty( $optimized_title ) ) {
            wp_update_post( array(
                'ID'         => $product_id,
                'post_title' => $optimized_title,
            ) );
        }

        // Optionally update the product description.
        if ( ! empty( $settings['update_description'] ) && $settings['update_description'] && ! empty( $optimized_desc ) ) {
            wp_update_post( array(
                'ID'           => $product_id,
                'post_content' => $optimized_desc,
            ) );
        }

        // Add tags as WooCommerce product_tag terms.
        if ( ! empty( $tags ) ) {
            $sanitized_tags = array_map( 'sanitize_text_field', $tags );
            wp_set_object_terms( $product_id, $sanitized_tags, 'product_tag', true );
        }

        // Store keywords as serialized meta.
        if ( ! empty( $keywords ) ) {
            update_post_meta( $product_id, '_taxoai_keywords', $keywords );
        }
    }

    /**
     * Check if Yoast SEO is active.
     *
     * @return bool
     */
    private function is_yoast_active() {
        return is_plugin_active( 'wordpress-seo/wp-seo.php' ) || is_plugin_active( 'wordpress-seo-premium/wp-seo-premium.php' );
    }

    /**
     * Check if Rank Math is active.
     *
     * @return bool
     */
    private function is_rank_math_active() {
        return is_plugin_active( 'seo-by-rank-math/rank-math.php' );
    }

    /**
     * Apply SEO meta to Yoast.
     *
     * @param int    $product_id   Product ID.
     * @param string $title        Meta title.
     * @param string $description  Meta description.
     * @param string $focus_keyword Focus keyword.
     */
    private function apply_yoast( $product_id, $title, $description, $focus_keyword ) {
        if ( ! empty( $title ) ) {
            update_post_meta( $product_id, '_yoast_wpseo_title', $title );
        }
        if ( ! empty( $description ) ) {
            update_post_meta( $product_id, '_yoast_wpseo_metadesc', $description );
        }
        if ( ! empty( $focus_keyword ) ) {
            update_post_meta( $product_id, '_yoast_wpseo_focuskw', $focus_keyword );
        }
    }

    /**
     * Apply SEO meta to Rank Math.
     *
     * @param int    $product_id   Product ID.
     * @param string $title        Meta title.
     * @param string $description  Meta description.
     * @param string $focus_keyword Focus keyword.
     */
    private function apply_rank_math( $product_id, $title, $description, $focus_keyword ) {
        if ( ! empty( $title ) ) {
            update_post_meta( $product_id, 'rank_math_title', $title );
        }
        if ( ! empty( $description ) ) {
            update_post_meta( $product_id, 'rank_math_description', $description );
        }
        if ( ! empty( $focus_keyword ) ) {
            update_post_meta( $product_id, 'rank_math_focus_keyword', $focus_keyword );
        }
    }

    /**
     * Apply SEO meta using TaxoAI's own meta keys (fallback).
     *
     * @param int    $product_id  Product ID.
     * @param string $title       Meta title.
     * @param string $description Meta description.
     */
    private function apply_fallback( $product_id, $title, $description ) {
        if ( ! empty( $title ) ) {
            update_post_meta( $product_id, '_taxoai_seo_title', $title );
        }
        if ( ! empty( $description ) ) {
            update_post_meta( $product_id, '_taxoai_seo_meta_description', $description );
        }
    }
}
