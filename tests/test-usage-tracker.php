<?php
/**
 * Tests for TaxoAI_Usage_Tracker.
 *
 * @package TaxoAI
 */

use WP_Mock\Tools\TestCase;

class Test_TaxoAI_Usage_Tracker extends TestCase {

    /**
     * Mocked API client.
     *
     * @var TaxoAI_API_Client|\Mockery\MockInterface
     */
    private $api_client;

    /**
     * Usage tracker under test.
     *
     * @var TaxoAI_Usage_Tracker
     */
    private $tracker;

    public function setUp(): void {
        parent::setUp();
        $this->api_client = \Mockery::mock( 'TaxoAI_API_Client' );
        $this->tracker    = new TaxoAI_Usage_Tracker( $this->api_client );
    }

    public function tearDown(): void {
        parent::tearDown();
    }

    /**
     * Test that can_analyze calls GET /v1/usage via the API client.
     */
    public function test_can_analyze_calls_server() {
        $usage = array(
            'tier'                     => 'free',
            'products_used_this_month' => 5,
            'products_limit'           => 25,
        );

        // No cached value.
        WP_Mock::userFunction( 'get_transient' )
            ->with( 'taxoai_usage_cache' )
            ->andReturn( false );

        $this->api_client
            ->shouldReceive( 'get_usage' )
            ->once()
            ->andReturn( $usage );

        WP_Mock::userFunction( 'set_transient' )
            ->once()
            ->with( 'taxoai_usage_cache', $usage, 300 );

        WP_Mock::userFunction( 'is_wp_error' )
            ->andReturn( false );

        $result = $this->tracker->can_analyze();

        $this->assertTrue( $result );
    }

    /**
     * Test that free tier is blocked at 25 products used.
     */
    public function test_blocks_at_25_for_free_tier() {
        $usage = array(
            'tier'                     => 'free',
            'products_used_this_month' => 25,
            'products_limit'           => 25,
        );

        WP_Mock::userFunction( 'get_transient' )
            ->with( 'taxoai_usage_cache' )
            ->andReturn( false );

        $this->api_client
            ->shouldReceive( 'get_usage' )
            ->once()
            ->andReturn( $usage );

        WP_Mock::userFunction( 'set_transient' )
            ->once();

        WP_Mock::userFunction( 'is_wp_error' )
            ->andReturn( false );

        $result = $this->tracker->can_analyze();

        $this->assertFalse( $result );
    }

    /**
     * Test that paid tier users are allowed even when exceeding 25.
     */
    public function test_allows_paid_tier_over_25() {
        $usage = array(
            'tier'                     => 'starter',
            'products_used_this_month' => 100,
            'products_limit'           => 500,
        );

        WP_Mock::userFunction( 'get_transient' )
            ->with( 'taxoai_usage_cache' )
            ->andReturn( false );

        $this->api_client
            ->shouldReceive( 'get_usage' )
            ->once()
            ->andReturn( $usage );

        WP_Mock::userFunction( 'set_transient' )
            ->once();

        WP_Mock::userFunction( 'is_wp_error' )
            ->andReturn( false );

        $result = $this->tracker->can_analyze();

        $this->assertTrue( $result );
    }

    /**
     * Test fallback to local counter when the API call fails.
     */
    public function test_fallback_to_local_on_network_error() {
        $wp_error = \Mockery::mock( 'WP_Error' );
        $wp_error->shouldReceive( 'get_error_message' )->andReturn( 'Timeout' );

        // No cache.
        WP_Mock::userFunction( 'get_transient' )
            ->with( 'taxoai_usage_cache' )
            ->andReturn( false );

        $this->api_client
            ->shouldReceive( 'get_usage' )
            ->once()
            ->andReturn( $wp_error );

        WP_Mock::userFunction( 'is_wp_error' )
            ->andReturnUsing( function ( $val ) use ( $wp_error ) {
                return $val === $wp_error;
            } );

        // Local counter fallback.
        WP_Mock::userFunction( 'get_option' )
            ->with( 'taxoai_usage_month', '' )
            ->andReturn( gmdate( 'Y-m' ) );

        WP_Mock::userFunction( 'get_option' )
            ->with( 'taxoai_usage_count', 0 )
            ->andReturn( 10 );

        // get_transient for cached tier (no cache = free).
        WP_Mock::userFunction( 'get_transient' )
            ->with( 'taxoai_usage_cache' )
            ->andReturn( false );

        $result = $this->tracker->can_analyze();

        $this->assertTrue( $result );
    }

    /**
     * Test that the local counter resets on a new month.
     */
    public function test_local_counter_resets_on_new_month() {
        $wp_error = \Mockery::mock( 'WP_Error' );
        $wp_error->shouldReceive( 'get_error_message' )->andReturn( 'Timeout' );

        WP_Mock::userFunction( 'get_transient' )
            ->with( 'taxoai_usage_cache' )
            ->andReturn( false );

        $this->api_client
            ->shouldReceive( 'get_usage' )
            ->once()
            ->andReturn( $wp_error );

        WP_Mock::userFunction( 'is_wp_error' )
            ->andReturnUsing( function ( $val ) use ( $wp_error ) {
                return $val === $wp_error;
            } );

        // Old month stored.
        WP_Mock::userFunction( 'get_option' )
            ->with( 'taxoai_usage_month', '' )
            ->andReturn( '2024-01' ); // Old month.

        // Expect reset.
        WP_Mock::userFunction( 'update_option' )
            ->with( 'taxoai_usage_count', 0 )
            ->once();

        WP_Mock::userFunction( 'update_option' )
            ->with( 'taxoai_usage_month', gmdate( 'Y-m' ) )
            ->once();

        // After reset, count is 0.
        WP_Mock::userFunction( 'get_option' )
            ->with( 'taxoai_usage_count', 0 )
            ->andReturn( 0 );

        $result = $this->tracker->can_analyze();

        $this->assertTrue( $result );
    }

    /**
     * Test that cached usage is returned within 5 minutes (no API call).
     */
    public function test_cache_expires_after_5_minutes() {
        $cached_usage = array(
            'tier'                     => 'free',
            'products_used_this_month' => 5,
            'products_limit'           => 25,
        );

        // Cache hit.
        WP_Mock::userFunction( 'get_transient' )
            ->with( 'taxoai_usage_cache' )
            ->andReturn( $cached_usage );

        // API should NOT be called.
        $this->api_client
            ->shouldNotReceive( 'get_usage' );

        WP_Mock::userFunction( 'is_wp_error' )
            ->andReturn( false );

        $result = $this->tracker->get_usage();

        $this->assertIsArray( $result );
        $this->assertEquals( 5, $result['products_used_this_month'] );
    }
}
