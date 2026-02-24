<?php
/**
 * Tests for free tier security and abuse prevention.
 *
 * @package TaxoAI
 */

use WP_Mock\Tools\TestCase;

class Test_Free_Tier_Security extends TestCase {

    /**
     * @var TaxoAI_API_Client|\Mockery\MockInterface
     */
    private $api_client;

    /**
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
     * Test that /v1/usage is always called before every analysis.
     */
    public function test_server_check_always_called() {
        $usage = array(
            'tier'                     => 'free',
            'products_used_this_month' => 10,
            'products_limit'           => 25,
        );

        // First call: no cache.
        WP_Mock::userFunction( 'get_transient' )
            ->with( 'taxoai_usage_cache' )
            ->once()
            ->ordered()
            ->andReturn( false );

        $this->api_client
            ->shouldReceive( 'get_usage' )
            ->once()
            ->andReturn( $usage );

        WP_Mock::userFunction( 'set_transient' )
            ->with( 'taxoai_usage_cache', $usage, 300 )
            ->once();

        WP_Mock::userFunction( 'is_wp_error' )
            ->andReturn( false );

        $result = $this->tracker->can_analyze();
        $this->assertTrue( $result );
    }

    /**
     * Test that resetting the local counter does not bypass the server check.
     */
    public function test_local_reset_does_not_bypass() {
        // Simulate: local counter was reset to 0, but server says 25 used.
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

        WP_Mock::userFunction( 'set_transient' )->once();
        WP_Mock::userFunction( 'is_wp_error' )->andReturn( false );

        // Even though local counter might be 0, the server says 25. Should block.
        $result = $this->tracker->can_analyze();
        $this->assertFalse( $result );
    }

    /**
     * Test that cached usage prevents rapid API abuse.
     */
    public function test_cache_prevents_rapid_abuse() {
        $cached_usage = array(
            'tier'                     => 'free',
            'products_used_this_month' => 24,
            'products_limit'           => 25,
        );

        // Cache returns data (simulating we're within the 5-minute window).
        WP_Mock::userFunction( 'get_transient' )
            ->with( 'taxoai_usage_cache' )
            ->andReturn( $cached_usage );

        // API should NOT be called when cache is valid.
        $this->api_client
            ->shouldNotReceive( 'get_usage' );

        WP_Mock::userFunction( 'is_wp_error' )->andReturn( false );

        // Still under limit from cached data.
        $result = $this->tracker->can_analyze();
        $this->assertTrue( $result );
    }

    /**
     * Test that a paid tier user bypasses the 25-product limit.
     */
    public function test_paid_tier_bypasses_limit() {
        $usage = array(
            'tier'                     => 'pro',
            'products_used_this_month' => 500,
            'products_limit'           => 5000,
        );

        WP_Mock::userFunction( 'get_transient' )
            ->with( 'taxoai_usage_cache' )
            ->andReturn( false );

        $this->api_client
            ->shouldReceive( 'get_usage' )
            ->once()
            ->andReturn( $usage );

        WP_Mock::userFunction( 'set_transient' )->once();
        WP_Mock::userFunction( 'is_wp_error' )->andReturn( false );

        $result = $this->tracker->can_analyze();
        $this->assertTrue( $result );
    }
}
