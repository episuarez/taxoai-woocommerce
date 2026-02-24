<?php
/**
 * TaxoAI Attribute Mapper.
 *
 * @package TaxoAI
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class TaxoAI_Attribute_Mapper
 *
 * Maps TaxoAI-detected attributes (color, material, gender, style) to
 * WooCommerce global product attributes.
 */
class TaxoAI_Attribute_Mapper {

    /**
     * Mapping from API attribute keys to WooCommerce attribute slugs.
     *
     * @var array
     */
    private $attribute_map = array(
        'color'    => 'pa_color',
        'material' => 'pa_material',
        'gender'   => 'pa_gender',
        'style'    => 'pa_style',
    );

    /**
     * Map attributes from the API response onto a WooCommerce product.
     *
     * @param int   $product_id Product (post) ID.
     * @param array $attributes Attribute data from the API (color[], material, gender, style, extra{}).
     */
    public function map_attributes( $product_id, array $attributes ) {
        $product = wc_get_product( $product_id );
        if ( ! $product ) {
            return;
        }

        $existing_attributes = $product->get_attributes();
        $new_attributes      = array();

        // Preserve existing attributes that we are not touching.
        foreach ( $existing_attributes as $key => $attr ) {
            $new_attributes[ $key ] = $attr;
        }

        foreach ( $this->attribute_map as $api_key => $taxonomy ) {
            if ( ! isset( $attributes[ $api_key ] ) || empty( $attributes[ $api_key ] ) ) {
                continue;
            }

            // Normalise to an array of values.
            $values = $attributes[ $api_key ];
            if ( ! is_array( $values ) ) {
                $values = array( $values );
            }

            // Sanitize.
            $values = array_map( 'sanitize_text_field', $values );
            $values = array_filter( $values );
            if ( empty( $values ) ) {
                continue;
            }

            // Ensure the global attribute taxonomy exists.
            $attribute_name = str_replace( 'pa_', '', $taxonomy );
            $this->ensure_attribute_taxonomy( $attribute_name );

            // Add terms to the taxonomy.
            $term_ids = array();
            foreach ( $values as $value ) {
                $term = $this->ensure_term( $value, $taxonomy );
                if ( $term ) {
                    $term_ids[] = $term;
                }
            }

            if ( empty( $term_ids ) ) {
                continue;
            }

            // Assign terms to the product.
            wp_set_object_terms( $product_id, $term_ids, $taxonomy, false );

            // Build a WC_Product_Attribute object.
            $attribute = new WC_Product_Attribute();
            $attribute->set_id( wc_attribute_taxonomy_id_by_name( $attribute_name ) );
            $attribute->set_name( $taxonomy );
            $attribute->set_options( $term_ids );
            $attribute->set_position( count( $new_attributes ) );
            $attribute->set_visible( true );
            $attribute->set_variation( false );

            $new_attributes[ $taxonomy ] = $attribute;
        }

        // Save the attributes to the product.
        $product->set_attributes( $new_attributes );
        $product->save();
    }

    /**
     * Ensure a global WooCommerce attribute taxonomy exists.
     *
     * @param string $attribute_name Attribute slug (e.g. "color", not "pa_color").
     */
    private function ensure_attribute_taxonomy( $attribute_name ) {
        // Check if the attribute taxonomy already exists.
        $existing_id = wc_attribute_taxonomy_id_by_name( $attribute_name );
        if ( $existing_id ) {
            return;
        }

        // Create the global attribute.
        $args = array(
            'name'         => ucfirst( $attribute_name ),
            'slug'         => $attribute_name,
            'type'         => 'select',
            'order_by'     => 'menu_order',
            'has_archives' => false,
        );

        $result = wc_create_attribute( $args );

        if ( is_wp_error( $result ) ) {
            return;
        }

        // Register the taxonomy so it is available immediately.
        $taxonomy = 'pa_' . $attribute_name;
        if ( ! taxonomy_exists( $taxonomy ) ) {
            register_taxonomy(
                $taxonomy,
                array( 'product' ),
                array(
                    'hierarchical' => false,
                    'label'        => ucfirst( $attribute_name ),
                    'query_var'    => true,
                    'rewrite'      => array( 'slug' => $attribute_name ),
                )
            );
        }
    }

    /**
     * Ensure a term exists in a taxonomy and return its ID.
     *
     * @param string $value    Term name.
     * @param string $taxonomy Taxonomy slug (e.g. "pa_color").
     * @return int|false Term ID on success, false on failure.
     */
    private function ensure_term( $value, $taxonomy ) {
        $term = get_term_by( 'name', $value, $taxonomy );
        if ( $term && ! is_wp_error( $term ) ) {
            return $term->term_id;
        }

        $inserted = wp_insert_term( $value, $taxonomy );
        if ( is_wp_error( $inserted ) ) {
            // May exist by slug.
            $slug_term = get_term_by( 'slug', sanitize_title( $value ), $taxonomy );
            if ( $slug_term && ! is_wp_error( $slug_term ) ) {
                return $slug_term->term_id;
            }
            return false;
        }

        return $inserted['term_id'];
    }
}
