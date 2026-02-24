<?php
/**
 * TaxoAI API Client.
 *
 * @package TaxoAI
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class TaxoAI_API_Client
 *
 * Handles all HTTP communication with the TaxoAI REST API.
 */
class TaxoAI_API_Client {

    /**
     * Default request timeout in seconds.
     *
     * @var int
     */
    const TIMEOUT = 15;

    /**
     * Get the stored API key.
     *
     * @return string
     */
    private function get_api_key() {
        return (string) get_option( 'taxoai_api_key', '' );
    }

    /**
     * Build default request headers.
     *
     * @return array
     */
    private function get_headers() {
        return array(
            'Content-Type' => 'application/json',
            'X-API-Key'    => $this->get_api_key(),
            'Accept'       => 'application/json',
        );
    }

    /**
     * Parse an API response.
     *
     * @param array|WP_Error $response Raw wp_remote response.
     * @param string         $context  Human-readable context for error messages.
     * @return array|WP_Error Parsed body array on success, WP_Error on failure.
     */
    private function parse_response( $response, $context = '' ) {
        if ( is_wp_error( $response ) ) {
            return new WP_Error(
                'taxoai_request_failed',
                sprintf(
                    /* translators: 1: context string 2: error message */
                    __( 'TaxoAI API request failed (%1$s): %2$s', 'woocommerce-taxoai' ),
                    $context,
                    $response->get_error_message()
                )
            );
        }

        $code = wp_remote_retrieve_response_code( $response );
        $body = wp_remote_retrieve_body( $response );
        $data = json_decode( $body, true );

        if ( 200 === $code || 201 === $code ) {
            return is_array( $data ) ? $data : array();
        }

        // Handle specific error codes.
        switch ( $code ) {
            case 401:
                return new WP_Error(
                    'taxoai_unauthorized',
                    __( 'Invalid API key. Please check your TaxoAI settings.', 'woocommerce-taxoai' )
                );

            case 402:
                return new WP_Error(
                    'taxoai_payment_required',
                    __( 'Payment required. Please upgrade your TaxoAI plan.', 'woocommerce-taxoai' )
                );

            case 429:
                $retry_after = wp_remote_retrieve_header( $response, 'retry-after' );
                $message     = __( 'Rate limit exceeded. Please try again later.', 'woocommerce-taxoai' );
                if ( $retry_after ) {
                    $message = sprintf(
                        /* translators: %s: number of seconds */
                        __( 'Rate limit exceeded. Please try again in %s seconds.', 'woocommerce-taxoai' ),
                        $retry_after
                    );
                }
                return new WP_Error( 'taxoai_rate_limited', $message, array( 'retry_after' => $retry_after ) );

            case 500:
            case 502:
            case 503:
                return new WP_Error(
                    'taxoai_server_error',
                    __( 'TaxoAI server error. Please try again later.', 'woocommerce-taxoai' )
                );

            default:
                $error_message = isset( $data['message'] ) ? $data['message'] : __( 'Unknown error', 'woocommerce-taxoai' );
                return new WP_Error(
                    'taxoai_api_error',
                    sprintf(
                        /* translators: 1: HTTP status code 2: error message */
                        __( 'TaxoAI API error (HTTP %1$d): %2$s', 'woocommerce-taxoai' ),
                        $code,
                        $error_message
                    )
                );
        }
    }

    /**
     * Analyze a product via the TaxoAI API.
     *
     * @param array $data Product data: name, description, price, image_urls, language, analyze_images.
     * @return array|WP_Error Parsed response or error.
     */
    public function analyze_product( array $data ) {
        $response = wp_remote_post(
            TAXOAI_API_URL . '/v1/products/analyze',
            array(
                'timeout' => self::TIMEOUT,
                'headers' => $this->get_headers(),
                'body'    => wp_json_encode( $data ),
            )
        );

        return $this->parse_response( $response, 'analyze_product' );
    }

    /**
     * Get current usage information.
     *
     * @return array|WP_Error Parsed response or error.
     */
    public function get_usage() {
        $response = wp_remote_get(
            TAXOAI_API_URL . '/v1/usage',
            array(
                'timeout' => self::TIMEOUT,
                'headers' => $this->get_headers(),
            )
        );

        return $this->parse_response( $response, 'get_usage' );
    }

    /**
     * Search taxonomies.
     *
     * @param string $query Search term.
     * @param int    $limit Maximum results (default 10).
     * @return array|WP_Error Parsed response or error.
     */
    public function search_taxonomies( $query, $limit = 10 ) {
        $url = add_query_arg(
            array(
                'q'     => rawurlencode( $query ),
                'limit' => absint( $limit ),
            ),
            TAXOAI_API_URL . '/v1/taxonomies/search'
        );

        $response = wp_remote_get(
            $url,
            array(
                'timeout' => self::TIMEOUT,
                'headers' => $this->get_headers(),
            )
        );

        return $this->parse_response( $response, 'search_taxonomies' );
    }

    /**
     * Submit a batch of products for analysis.
     *
     * @param array $products Array of product data arrays.
     * @return array|WP_Error Parsed response containing job_id, or error.
     */
    public function submit_batch( array $products ) {
        $response = wp_remote_post(
            TAXOAI_API_URL . '/v1/products/batch',
            array(
                'timeout' => self::TIMEOUT,
                'headers' => $this->get_headers(),
                'body'    => wp_json_encode( array( 'products' => $products ) ),
            )
        );

        return $this->parse_response( $response, 'submit_batch' );
    }

    /**
     * Poll the status of a batch job.
     *
     * @param string $job_id The job ID returned from submit_batch.
     * @return array|WP_Error Parsed response or error.
     */
    public function get_job( $job_id ) {
        $response = wp_remote_get(
            TAXOAI_API_URL . '/v1/jobs/' . rawurlencode( $job_id ),
            array(
                'timeout' => self::TIMEOUT,
                'headers' => $this->get_headers(),
            )
        );

        return $this->parse_response( $response, 'get_job' );
    }
}
