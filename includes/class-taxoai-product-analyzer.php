<?php
/**
 * TaxoAI Product Analyzer.
 *
 * @package TaxoAI
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class TaxoAI_Product_Analyzer
 *
 * Orchestrates product analysis: extracts data, calls the API, stores results,
 * and delegates to SEO, category, and attribute integrators.
 */
class TaxoAI_Product_Analyzer {

    /**
     * API client.
     *
     * @var TaxoAI_API_Client
     */
    private $api_client;

    /**
     * Usage tracker.
     *
     * @var TaxoAI_Usage_Tracker
     */
    private $usage_tracker;

    /**
     * SEO integrator.
     *
     * @var TaxoAI_SEO_Integrator
     */
    private $seo_integrator;

    /**
     * Category mapper.
     *
     * @var TaxoAI_Category_Mapper
     */
    private $category_mapper;

    /**
     * Attribute mapper.
     *
     * @var TaxoAI_Attribute_Mapper
     */
    private $attribute_mapper;

    /**
     * Constructor.
     *
     * @param TaxoAI_API_Client       $api_client       API client.
     * @param TaxoAI_Usage_Tracker    $usage_tracker     Usage tracker.
     * @param TaxoAI_SEO_Integrator   $seo_integrator    SEO integrator.
     * @param TaxoAI_Category_Mapper  $category_mapper   Category mapper.
     * @param TaxoAI_Attribute_Mapper $attribute_mapper  Attribute mapper.
     */
    public function __construct(
        TaxoAI_API_Client $api_client,
        TaxoAI_Usage_Tracker $usage_tracker,
        TaxoAI_SEO_Integrator $seo_integrator,
        TaxoAI_Category_Mapper $category_mapper,
        TaxoAI_Attribute_Mapper $attribute_mapper
    ) {
        $this->api_client       = $api_client;
        $this->usage_tracker    = $usage_tracker;
        $this->seo_integrator   = $seo_integrator;
        $this->category_mapper  = $category_mapper;
        $this->attribute_mapper = $attribute_mapper;
    }

    /**
     * Analyze a WooCommerce product.
     *
     * @param int  $product_id WooCommerce product (post) ID.
     * @param bool $force      If true, skip the "already analyzed" check.
     * @return array|WP_Error Analysis result or error.
     */
    public function analyze( $product_id, $force = false ) {
        $product = wc_get_product( $product_id );

        if ( ! $product ) {
            return new WP_Error(
                'taxoai_invalid_product',
                __( 'Invalid product ID.', 'woocommerce-taxoai' )
            );
        }

        // Check API key.
        $api_key = get_option( 'taxoai_api_key', '' );
        if ( empty( $api_key ) ) {
            return new WP_Error(
                'taxoai_no_api_key',
                __( 'TaxoAI API key is not configured.', 'woocommerce-taxoai' )
            );
        }

        // Check usage limits.
        if ( ! $this->usage_tracker->can_analyze() ) {
            return new WP_Error(
                'taxoai_limit_reached',
                __( 'Monthly analysis limit reached.', 'woocommerce-taxoai' )
            );
        }

        // Build the API payload.
        $payload = $this->build_payload( $product );

        // Call the API.
        $result = $this->api_client->analyze_product( $payload );

        if ( is_wp_error( $result ) ) {
            return $result;
        }

        // Increment usage.
        $this->usage_tracker->increment();

        // Store the raw result.
        $this->store_result( $product_id, $result );

        // Apply results based on confidence threshold.
        $this->apply_result( $product_id, $result );

        return $result;
    }

    /**
     * Build the API request payload from a WC_Product.
     *
     * @param WC_Product $product WooCommerce product.
     * @return array
     */
    public function build_payload( $product ) {
        $payload = array(
            'name'       => $product->get_name(),
            'language'   => get_option( 'taxoai_language', 'es' ),
            'product_id' => (string) $product->get_id(),
        );

        $description = $product->get_description();
        if ( ! empty( $description ) ) {
            $payload['description'] = wp_strip_all_tags( $description );
        }

        $price = $product->get_price();
        if ( '' !== $price && null !== $price ) {
            $payload['price'] = (float) $price;
        }

        // Collect image URLs.
        $image_urls = $this->get_product_image_urls( $product );
        if ( ! empty( $image_urls ) ) {
            $payload['image_urls'] = $image_urls;
        }

        // Image analysis setting.
        if ( '1' === get_option( 'taxoai_analyze_images', '0' ) ) {
            $payload['analyze_images'] = true;
        }

        return $payload;
    }

    /**
     * Get all image URLs for a product (featured + gallery).
     *
     * @param WC_Product $product WooCommerce product.
     * @return array Array of image URL strings.
     */
    private function get_product_image_urls( $product ) {
        $urls = array();

        // Featured image.
        $featured_id = $product->get_image_id();
        if ( $featured_id ) {
            $url = wp_get_attachment_url( $featured_id );
            if ( $url ) {
                $urls[] = $url;
            }
        }

        // Gallery images.
        $gallery_ids = $product->get_gallery_image_ids();
        if ( is_array( $gallery_ids ) ) {
            foreach ( $gallery_ids as $attachment_id ) {
                $url = wp_get_attachment_url( $attachment_id );
                if ( $url ) {
                    $urls[] = $url;
                }
            }
        }

        return $urls;
    }

    /**
     * Store the analysis result in post meta.
     *
     * @param int   $product_id Product ID.
     * @param array $result     API response.
     */
    private function store_result( $product_id, array $result ) {
        update_post_meta( $product_id, '_taxoai_analysis_result', $result );
        update_post_meta( $product_id, '_taxoai_analyzed_at', current_time( 'mysql' ) );

        if ( isset( $result['classification'] ) ) {
            $classification = $result['classification'];
            if ( isset( $classification['google_category'] ) ) {
                update_post_meta( $product_id, '_taxoai_google_category', sanitize_text_field( $classification['google_category'] ) );
            }
            if ( isset( $classification['google_category_id'] ) ) {
                update_post_meta( $product_id, '_taxoai_google_category_id', (int) $classification['google_category_id'] );
            }
            if ( isset( $classification['confidence'] ) ) {
                update_post_meta( $product_id, '_taxoai_confidence', (float) $classification['confidence'] );
            }
        }
    }

    /**
     * Apply the analysis result to the product, respecting the confidence threshold.
     *
     * @param int   $product_id Product ID.
     * @param array $result     API response.
     */
    private function apply_result( $product_id, array $result ) {
        $threshold  = (float) get_option( 'taxoai_confidence_threshold', 0.7 );
        $confidence = 0.0;

        if ( isset( $result['classification']['confidence'] ) ) {
            $confidence = (float) $result['classification']['confidence'];
        }

        // Only auto-apply if confidence meets threshold.
        if ( $confidence < $threshold ) {
            return;
        }

        // Gather settings for SEO integrator.
        $settings = array(
            'update_title'       => '1' === get_option( 'taxoai_update_title', '0' ),
            'update_description' => '1' === get_option( 'taxoai_update_description', '0' ),
        );

        // SEO.
        if ( isset( $result['seo'] ) ) {
            $this->seo_integrator->apply_seo( $product_id, $result['seo'], $settings );
        }

        // Category mapping.
        if ( isset( $result['classification'] ) ) {
            $classification = $result['classification'];
            $google_cat     = isset( $classification['google_category'] ) ? $classification['google_category'] : '';
            $google_cat_id  = isset( $classification['google_category_id'] ) ? (int) $classification['google_category_id'] : 0;
            $this->category_mapper->map( $product_id, $google_cat, $google_cat_id );
        }

        // Attribute mapping.
        if ( isset( $result['attributes'] ) ) {
            $this->attribute_mapper->map_attributes( $product_id, $result['attributes'] );
        }
    }
}
