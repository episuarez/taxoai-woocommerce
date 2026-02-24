<?php
/**
 * TaxoAI Product Columns.
 *
 * @package TaxoAI
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class TaxoAI_Product_Columns
 *
 * Adds a "TaxoAI" column to the WooCommerce products list table.
 */
class TaxoAI_Product_Columns {

    /**
     * Add the TaxoAI column to the products list table.
     *
     * @param array $columns Existing columns.
     * @return array Modified columns.
     */
    public function add_column( $columns ) {
        // Insert after the 'name' column if possible.
        $new_columns = array();
        foreach ( $columns as $key => $label ) {
            $new_columns[ $key ] = $label;
            if ( 'name' === $key ) {
                $new_columns['taxoai'] = __( 'TaxoAI', 'woocommerce-taxoai' );
            }
        }

        // Fallback: if 'name' was not found, just append.
        if ( ! isset( $new_columns['taxoai'] ) ) {
            $new_columns['taxoai'] = __( 'TaxoAI', 'woocommerce-taxoai' );
        }

        return $new_columns;
    }

    /**
     * Render the TaxoAI column content for a product.
     *
     * @param string $column  Column identifier.
     * @param int    $post_id Post ID.
     */
    public function render_column( $column, $post_id ) {
        if ( 'taxoai' !== $column ) {
            return;
        }

        $analyzed   = get_post_meta( $post_id, '_taxoai_analyzed_at', true );
        $confidence = get_post_meta( $post_id, '_taxoai_confidence', true );
        $threshold  = (float) get_option( 'taxoai_confidence_threshold', 0.7 );

        if ( empty( $analyzed ) ) {
            // Not analyzed.
            echo '<span class="taxoai-col-icon taxoai-col-none" title="' . esc_attr__( 'Not analyzed', 'woocommerce-taxoai' ) . '">&mdash;</span>';
            echo ' <a href="#" class="taxoai-quick-analyze" data-product-id="' . esc_attr( $post_id ) . '">';
            echo esc_html__( 'Analyze', 'woocommerce-taxoai' );
            echo '</a>';
        } elseif ( $confidence !== '' && (float) $confidence >= $threshold ) {
            // Analyzed, high confidence.
            echo '<span class="taxoai-col-icon taxoai-col-ok dashicons dashicons-yes-alt" style="color:#00a32a;" title="' . esc_attr(
                sprintf(
                    /* translators: %s: confidence percentage */
                    __( 'Analyzed - %s%% confidence', 'woocommerce-taxoai' ),
                    round( (float) $confidence * 100 )
                )
            ) . '"></span>';
        } else {
            // Analyzed, low confidence.
            echo '<span class="taxoai-col-icon taxoai-col-warn dashicons dashicons-warning" style="color:#dba617;" title="' . esc_attr(
                sprintf(
                    /* translators: %s: confidence percentage */
                    __( 'Low confidence - %s%%', 'woocommerce-taxoai' ),
                    $confidence !== '' ? round( (float) $confidence * 100 ) : '0'
                )
            ) . '"></span>';
            echo ' <a href="#" class="taxoai-quick-analyze" data-product-id="' . esc_attr( $post_id ) . '">';
            echo esc_html__( 'Re-analyze', 'woocommerce-taxoai' );
            echo '</a>';
        }
    }
}
