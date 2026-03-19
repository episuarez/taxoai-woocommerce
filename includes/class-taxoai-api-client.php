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
     * Callable used for sleep between retries. Overridable in tests to avoid real delays.
     *
     * @var callable
     */
    public $sleep_fn = 'sleep';

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
     * Make an HTTP request with exponential backoff retry.
     *
     * Retries on 429 (rate limit) and 5xx (server errors) up to $max_tries times.
     * Delays: 1s → 2s → 4s between attempts.
     *
     * @param string $method     HTTP method ('POST' or 'GET').
     * @param string $url        Full URL to request.
     * @param array  $args       wp_remote_* arguments (headers, body, timeout).
     * @param string $context    Human-readable context for error messages.
     * @param int    $max_tries  Maximum number of attempts (default 3).
     * @return array|WP_Error Parsed body on success, WP_Error on failure.
     */
    private function request_with_retry( $method, $url, $args, $context = '', $max_tries = 3 ) {
        $attempt = 0;
        $delay   = 1; // seconds

        while ( $attempt < $max_tries ) {
            $attempt++;

            if ( 'POST' === $method ) {
                $response = wp_remote_post( $url, $args );
            } else {
                $response = wp_remote_get( $url, $args );
            }

            // On cURL/WP_Error, only retry on transient network errors.
            if ( is_wp_error( $response ) ) {
                if ( $attempt < $max_tries ) {
                    call_user_func( $this->sleep_fn, $delay );
                    $delay *= 2;
                    continue;
                }
                return $this->parse_response( $response, $context );
            }

            $code = wp_remote_retrieve_response_code( $response );

            // Retry on 429 or 5xx.
            if ( ( 429 === $code || $code >= 500 ) && $attempt < $max_tries ) {
                $retry_after = wp_remote_retrieve_header( $response, 'retry-after' );
                $wait        = $retry_after ? (int) $retry_after : $delay;
                call_user_func( $this->sleep_fn, min( $wait, 10 ) ); // cap at 10s for UX
                $delay *= 2;
                continue;
            }

            return $this->parse_response( $response, $context );
        }

        return $this->parse_response( $response, $context ); // last attempt result
    }

    /**
     * Analyze a product via the TaxoAI API.
     *
     * @param array $data Product data: name, description, price, image_urls, language, analyze_images.
     * @return array|WP_Error Parsed response or error.
     */
    public function analyze_product( array $data ) {
        return $this->request_with_retry(
            'POST',
            TAXOAI_API_URL . '/v1/products/analyze',
            array(
                'timeout' => self::TIMEOUT,
                'headers' => $this->get_headers(),
                'body'    => wp_json_encode( $data ),
            ),
            'analyze_product'
        );
    }

    /**
     * Get current usage information.
     *
     * @return array|WP_Error Parsed response or error.
     */
    public function get_usage() {
        return $this->request_with_retry(
            'GET',
            TAXOAI_API_URL . '/v1/usage',
            array(
                'timeout' => self::TIMEOUT,
                'headers' => $this->get_headers(),
            ),
            'get_usage'
        );
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

        return $this->request_with_retry(
            'GET',
            $url,
            array(
                'timeout' => self::TIMEOUT,
                'headers' => $this->get_headers(),
            ),
            'search_taxonomies'
        );
    }

    /**
     * Submit a batch of products for analysis.
     *
     * @param array $products Array of product data arrays.
     * @return array|WP_Error Parsed response containing job_id, or error.
     */
    public function submit_batch( array $products ) {
        return $this->request_with_retry(
            'POST',
            TAXOAI_API_URL . '/v1/products/batch',
            array(
                'timeout' => self::TIMEOUT,
                'headers' => $this->get_headers(),
                'body'    => wp_json_encode( array( 'products' => $products ) ),
            ),
            'submit_batch'
        );
    }

    /**
     * Poll the status of a batch job.
     *
     * @param string $job_id The job ID returned from submit_batch.
     * @return array|WP_Error Parsed response or error.
     */
    public function get_job( $job_id ) {
        return $this->request_with_retry(
            'GET',
            TAXOAI_API_URL . '/v1/jobs/' . rawurlencode( $job_id ),
            array(
                'timeout' => self::TIMEOUT,
                'headers' => $this->get_headers(),
            ),
            'get_job'
        );
    }
}
