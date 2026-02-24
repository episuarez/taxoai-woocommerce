<?php
/**
 * TaxoAI Usage Tracker.
 *
 * @package TaxoAI
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class TaxoAI_Usage_Tracker
 *
 * Tracks API usage with server-side verification and local fallback.
 */
class TaxoAI_Usage_Tracker {

    /**
     * Free tier product limit.
     *
     * @var int
     */
    const FREE_TIER_LIMIT = 25;

    /**
     * Cache transient key.
     *
     * @var string
     */
    const CACHE_KEY = 'taxoai_usage_cache';

    /**
     * Cache duration in seconds (5 minutes).
     *
     * @var int
     */
    const CACHE_TTL = 300;

    /**
     * API client.
     *
     * @var TaxoAI_API_Client
     */
    private $api_client;

    /**
     * Constructor.
     *
     * @param TaxoAI_API_Client $api_client API client instance.
     */
    public function __construct( TaxoAI_API_Client $api_client ) {
        $this->api_client = $api_client;
    }

    /**
     * Check whether the user can perform another analysis.
     *
     * Always attempts server-side verification first.
     * Falls back to the local counter only when the API call fails.
     *
     * @return bool True if analysis is allowed.
     */
    public function can_analyze() {
        $usage = $this->get_usage();

        if ( is_wp_error( $usage ) ) {
            // Fallback to local counter.
            return $this->can_analyze_local();
        }

        $tier = isset( $usage['tier'] ) ? $usage['tier'] : 'free';
        $used = isset( $usage['products_used_this_month'] ) ? (int) $usage['products_used_this_month'] : 0;

        // Paid tiers have no 25-product limit.
        if ( 'free' !== $tier ) {
            return true;
        }

        return $used < self::FREE_TIER_LIMIT;
    }

    /**
     * Increment the local usage counter (fallback bookkeeping).
     */
    public function increment() {
        $this->ensure_month_reset();

        $count = (int) get_option( 'taxoai_usage_count', 0 );
        update_option( 'taxoai_usage_count', $count + 1 );

        // Invalidate the cache so the next can_analyze() fetches fresh data.
        delete_transient( self::CACHE_KEY );
    }

    /**
     * Get usage data from the server (cached for 5 minutes).
     *
     * @param bool $force_refresh Force a fresh API call.
     * @return array|WP_Error Usage data or error.
     */
    public function get_usage( $force_refresh = false ) {
        if ( ! $force_refresh ) {
            $cached = get_transient( self::CACHE_KEY );
            if ( false !== $cached && is_array( $cached ) ) {
                return $cached;
            }
        }

        $result = $this->api_client->get_usage();

        if ( ! is_wp_error( $result ) ) {
            set_transient( self::CACHE_KEY, $result, self::CACHE_TTL );
        }

        return $result;
    }

    /**
     * Get the cached tier string.
     *
     * @return string Tier name (defaults to "free").
     */
    public function get_cached_tier() {
        $cached = get_transient( self::CACHE_KEY );
        if ( is_array( $cached ) && isset( $cached['tier'] ) ) {
            return $cached['tier'];
        }
        return 'free';
    }

    /**
     * Local fallback: can the user analyze based on the local counter?
     *
     * @return bool
     */
    private function can_analyze_local() {
        $this->ensure_month_reset();

        // If the cached tier is not free, allow.
        if ( 'free' !== $this->get_cached_tier() ) {
            return true;
        }

        $count = (int) get_option( 'taxoai_usage_count', 0 );
        return $count < self::FREE_TIER_LIMIT;
    }

    /**
     * Reset the local counter at the start of a new month.
     */
    private function ensure_month_reset() {
        $stored_month  = get_option( 'taxoai_usage_month', '' );
        $current_month = gmdate( 'Y-m' );

        if ( $stored_month !== $current_month ) {
            update_option( 'taxoai_usage_count', 0 );
            update_option( 'taxoai_usage_month', $current_month );
        }
    }
}
