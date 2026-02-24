<?php
/**
 * TaxoAI AJAX Handler.
 *
 * @package TaxoAI
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class TaxoAI_Ajax_Handler
 *
 * Handles all wp_ajax_* callbacks for the plugin.
 */
class TaxoAI_Ajax_Handler {

    /**
     * Product analyzer instance.
     *
     * @var TaxoAI_Product_Analyzer
     */
    private $analyzer;

    /**
     * API client instance.
     *
     * @var TaxoAI_API_Client
     */
    private $api_client;

    /**
     * Constructor.
     *
     * @param TaxoAI_Product_Analyzer $analyzer   Product analyzer.
     * @param TaxoAI_API_Client       $api_client API client.
     */
    public function __construct( TaxoAI_Product_Analyzer $analyzer, TaxoAI_API_Client $api_client ) {
        $this->analyzer   = $analyzer;
        $this->api_client = $api_client;
    }

    /**
     * AJAX: Analyze a single product.
     */
    public function analyze_product() {
        check_ajax_referer( 'taxoai_nonce', 'nonce' );

        if ( ! current_user_can( 'edit_products' ) ) {
            wp_send_json_error( array(
                'message' => __( 'You do not have permission to perform this action.', 'woocommerce-taxoai' ),
            ), 403 );
        }

        $product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;

        if ( ! $product_id ) {
            wp_send_json_error( array(
                'message' => __( 'Invalid product ID.', 'woocommerce-taxoai' ),
            ), 400 );
        }

        $result = $this->analyzer->analyze( $product_id, true );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array(
                'message' => $result->get_error_message(),
                'code'    => $result->get_error_code(),
            ), 400 );
        }

        // Fetch updated meta for the response.
        $response_data = array(
            'result'      => $result,
            'analyzed_at' => get_post_meta( $product_id, '_taxoai_analyzed_at', true ),
            'confidence'  => get_post_meta( $product_id, '_taxoai_confidence', true ),
            'category'    => get_post_meta( $product_id, '_taxoai_google_category', true ),
            'category_id' => get_post_meta( $product_id, '_taxoai_google_category_id', true ),
        );

        wp_send_json_success( $response_data );
    }

    /**
     * AJAX: Search Google taxonomies.
     */
    public function search_taxonomy() {
        check_ajax_referer( 'taxoai_nonce', 'nonce' );

        $query = isset( $_GET['query'] ) ? sanitize_text_field( wp_unslash( $_GET['query'] ) ) : '';
        $limit = isset( $_GET['limit'] ) ? absint( $_GET['limit'] ) : 10;

        if ( empty( $query ) ) {
            wp_send_json_success( array( 'categories' => array() ) );
        }

        $result = $this->api_client->search_taxonomies( $query, $limit );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array(
                'message' => $result->get_error_message(),
            ), 400 );
        }

        wp_send_json_success( $result );
    }

    /**
     * AJAX: Submit a batch analysis job.
     */
    public function bulk_analyze() {
        check_ajax_referer( 'taxoai_nonce', 'nonce' );

        if ( ! current_user_can( 'edit_products' ) ) {
            wp_send_json_error( array(
                'message' => __( 'You do not have permission to perform this action.', 'woocommerce-taxoai' ),
            ), 403 );
        }

        $product_ids = isset( $_POST['product_ids'] ) ? array_map( 'absint', (array) $_POST['product_ids'] ) : array();
        $product_ids = array_filter( $product_ids );

        if ( empty( $product_ids ) ) {
            wp_send_json_error( array(
                'message' => __( 'No products selected.', 'woocommerce-taxoai' ),
            ), 400 );
        }

        // Build batch payload.
        $products = array();
        $id_map   = array();
        $language = get_option( 'taxoai_language', 'es' );
        $analyze_images = '1' === get_option( 'taxoai_analyze_images', '0' );

        foreach ( $product_ids as $product_id ) {
            $product = wc_get_product( $product_id );
            if ( ! $product ) {
                continue;
            }

            $payload = array(
                'name'     => $product->get_name(),
                'language' => $language,
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
            $image_urls = array();
            $featured   = $product->get_image_id();
            if ( $featured ) {
                $url = wp_get_attachment_url( $featured );
                if ( $url ) {
                    $image_urls[] = $url;
                }
            }
            $gallery = $product->get_gallery_image_ids();
            if ( is_array( $gallery ) ) {
                foreach ( $gallery as $att_id ) {
                    $url = wp_get_attachment_url( $att_id );
                    if ( $url ) {
                        $image_urls[] = $url;
                    }
                }
            }
            if ( ! empty( $image_urls ) ) {
                $payload['image_urls'] = $image_urls;
            }

            if ( $analyze_images ) {
                $payload['analyze_images'] = true;
            }

            $products[] = $payload;
            $id_map[]   = $product_id;
        }

        if ( empty( $products ) ) {
            wp_send_json_error( array(
                'message' => __( 'No valid products found.', 'woocommerce-taxoai' ),
            ), 400 );
        }

        $result = $this->api_client->submit_batch( $products );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array(
                'message' => $result->get_error_message(),
            ), 400 );
        }

        // Store the mapping of job products to WC product IDs.
        $job_id = isset( $result['job_id'] ) ? $result['job_id'] : '';
        if ( ! empty( $job_id ) ) {
            set_transient( 'taxoai_job_map_' . $job_id, $id_map, HOUR_IN_SECONDS );
        }

        wp_send_json_success( array(
            'job_id'         => $job_id,
            'status'         => isset( $result['status'] ) ? $result['status'] : 'pending',
            'total_products' => count( $products ),
            'product_ids'    => $id_map,
        ) );
    }

    /**
     * AJAX: Poll a batch job status.
     */
    public function poll_job() {
        check_ajax_referer( 'taxoai_nonce', 'nonce' );

        $job_id = isset( $_GET['job_id'] ) ? sanitize_text_field( wp_unslash( $_GET['job_id'] ) ) : '';

        if ( empty( $job_id ) ) {
            wp_send_json_error( array(
                'message' => __( 'Invalid job ID.', 'woocommerce-taxoai' ),
            ), 400 );
        }

        $result = $this->api_client->get_job( $job_id );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array(
                'message' => $result->get_error_message(),
            ), 400 );
        }

        // If completed, store individual results.
        $status = isset( $result['status'] ) ? $result['status'] : '';
        if ( 'completed' === $status && ! empty( $result['result'] ) ) {
            $id_map = get_transient( 'taxoai_job_map_' . $job_id );
            if ( is_array( $id_map ) && is_array( $result['result'] ) ) {
                $plugin = TaxoAI_Plugin::get_instance();
                foreach ( $result['result'] as $index => $single_result ) {
                    if ( isset( $id_map[ $index ] ) ) {
                        $product_id = (int) $id_map[ $index ];
                        // Store and apply result via the analyzer's internal mechanisms.
                        update_post_meta( $product_id, '_taxoai_analysis_result', $single_result );
                        update_post_meta( $product_id, '_taxoai_analyzed_at', current_time( 'mysql' ) );

                        if ( isset( $single_result['classification'] ) ) {
                            $cls = $single_result['classification'];
                            if ( isset( $cls['google_category'] ) ) {
                                update_post_meta( $product_id, '_taxoai_google_category', sanitize_text_field( $cls['google_category'] ) );
                            }
                            if ( isset( $cls['google_category_id'] ) ) {
                                update_post_meta( $product_id, '_taxoai_google_category_id', (int) $cls['google_category_id'] );
                            }
                            if ( isset( $cls['confidence'] ) ) {
                                update_post_meta( $product_id, '_taxoai_confidence', (float) $cls['confidence'] );
                            }
                        }
                    }
                }
                delete_transient( 'taxoai_job_map_' . $job_id );
            }
        }

        wp_send_json_success( array(
            'status'             => $status,
            'total_products'     => isset( $result['total_products'] ) ? (int) $result['total_products'] : 0,
            'processed_products' => isset( $result['processed_products'] ) ? (int) $result['processed_products'] : 0,
        ) );
    }
}
