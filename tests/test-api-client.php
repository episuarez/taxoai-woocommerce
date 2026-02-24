<?php
/**
 * Tests for TaxoAI_API_Client.
 *
 * @package TaxoAI
 */

use WP_Mock\Tools\TestCase;

class Test_TaxoAI_API_Client extends TestCase {

    /**
     * API client instance.
     *
     * @var TaxoAI_API_Client
     */
    private $client;

    public function setUp(): void {
        parent::setUp();
        $this->client = new TaxoAI_API_Client();
    }

    public function tearDown(): void {
        parent::tearDown();
    }

    /**
     * Test that analyze_product sends correct request and returns parsed data.
     */
    public function test_analyze_product_success() {
        $api_key = 'test-api-key-123';
        $payload = array(
            'name'     => 'Blue Cotton T-Shirt',
            'language' => 'en',
            'price'    => 29.99,
        );

        $api_response = array(
            'classification' => array(
                'google_category'    => 'Apparel & Accessories > Clothing > Shirts & Tops',
                'google_category_id' => 212,
                'confidence'         => 0.95,
            ),
            'seo' => array(
                'meta_title'       => 'Blue Cotton T-Shirt | Premium Quality',
                'meta_description' => 'Buy our premium blue cotton t-shirt.',
                'keywords'         => array( array( 'keyword' => 'cotton t-shirt', 'volume' => 5400 ) ),
                'tags'             => array( 't-shirt', 'cotton', 'blue' ),
            ),
        );

        WP_Mock::userFunction( 'get_option' )
            ->with( 'taxoai_api_key', '' )
            ->andReturn( $api_key );

        WP_Mock::userFunction( 'wp_remote_post' )
            ->once()
            ->withArgs( function ( $url, $args ) use ( $api_key, $payload ) {
                return $url === TAXOAI_API_URL . '/v1/products/analyze'
                    && $args['headers']['X-API-Key'] === $api_key
                    && $args['headers']['Content-Type'] === 'application/json'
                    && $args['timeout'] === 15;
            } )
            ->andReturn( array(
                'response' => array( 'code' => 200 ),
                'body'     => json_encode( $api_response ),
            ) );

        WP_Mock::userFunction( 'wp_remote_retrieve_response_code' )
            ->andReturn( 200 );

        WP_Mock::userFunction( 'wp_remote_retrieve_body' )
            ->andReturn( json_encode( $api_response ) );

        WP_Mock::userFunction( 'wp_json_encode' )
            ->andReturnUsing( function ( $data ) {
                return json_encode( $data );
            } );

        WP_Mock::userFunction( 'is_wp_error' )
            ->andReturn( false );

        $result = $this->client->analyze_product( $payload );

        $this->assertIsArray( $result );
        $this->assertArrayHasKey( 'classification', $result );
        $this->assertEquals( 0.95, $result['classification']['confidence'] );
        $this->assertEquals( 212, $result['classification']['google_category_id'] );
    }

    /**
     * Test that a 401 response returns a WP_Error.
     */
    public function test_analyze_product_401() {
        WP_Mock::userFunction( 'get_option' )
            ->with( 'taxoai_api_key', '' )
            ->andReturn( 'bad-key' );

        WP_Mock::userFunction( 'wp_remote_post' )
            ->once()
            ->andReturn( array(
                'response' => array( 'code' => 401 ),
                'body'     => json_encode( array( 'message' => 'Invalid API key' ) ),
            ) );

        WP_Mock::userFunction( 'wp_remote_retrieve_response_code' )
            ->andReturn( 401 );

        WP_Mock::userFunction( 'wp_remote_retrieve_body' )
            ->andReturn( json_encode( array( 'message' => 'Invalid API key' ) ) );

        WP_Mock::userFunction( 'wp_json_encode' )
            ->andReturnUsing( function ( $data ) {
                return json_encode( $data );
            } );

        WP_Mock::userFunction( 'is_wp_error' )
            ->andReturn( false );

        $result = $this->client->analyze_product( array( 'name' => 'Test' ) );

        $this->assertInstanceOf( 'WP_Error', $result );
        $this->assertEquals( 'taxoai_unauthorized', $result->get_error_code() );
    }

    /**
     * Test that a 429 response returns a WP_Error with retry info.
     */
    public function test_analyze_product_429() {
        WP_Mock::userFunction( 'get_option' )
            ->with( 'taxoai_api_key', '' )
            ->andReturn( 'key' );

        WP_Mock::userFunction( 'wp_remote_post' )
            ->once()
            ->andReturn( array(
                'response' => array( 'code' => 429 ),
                'headers'  => array( 'retry-after' => '60' ),
                'body'     => json_encode( array( 'message' => 'Rate limited' ) ),
            ) );

        WP_Mock::userFunction( 'wp_remote_retrieve_response_code' )
            ->andReturn( 429 );

        WP_Mock::userFunction( 'wp_remote_retrieve_body' )
            ->andReturn( json_encode( array( 'message' => 'Rate limited' ) ) );

        WP_Mock::userFunction( 'wp_remote_retrieve_header' )
            ->with( \WP_Mock\Functions::type( 'array' ), 'retry-after' )
            ->andReturn( '60' );

        WP_Mock::userFunction( 'wp_json_encode' )
            ->andReturnUsing( function ( $data ) {
                return json_encode( $data );
            } );

        WP_Mock::userFunction( 'is_wp_error' )
            ->andReturn( false );

        $result = $this->client->analyze_product( array( 'name' => 'Test' ) );

        $this->assertInstanceOf( 'WP_Error', $result );
        $this->assertEquals( 'taxoai_rate_limited', $result->get_error_code() );
    }

    /**
     * Test that a network timeout returns a WP_Error.
     */
    public function test_analyze_product_timeout() {
        WP_Mock::userFunction( 'get_option' )
            ->with( 'taxoai_api_key', '' )
            ->andReturn( 'key' );

        $wp_error = \Mockery::mock( 'WP_Error' );
        $wp_error->shouldReceive( 'get_error_message' )->andReturn( 'Connection timed out' );
        $wp_error->shouldReceive( 'get_error_code' )->andReturn( 'http_request_failed' );

        WP_Mock::userFunction( 'wp_remote_post' )
            ->once()
            ->andReturn( $wp_error );

        WP_Mock::userFunction( 'is_wp_error' )
            ->andReturnUsing( function ( $val ) use ( $wp_error ) {
                return $val === $wp_error;
            } );

        WP_Mock::userFunction( 'wp_json_encode' )
            ->andReturnUsing( function ( $data ) {
                return json_encode( $data );
            } );

        $result = $this->client->analyze_product( array( 'name' => 'Test' ) );

        $this->assertInstanceOf( 'WP_Error', $result );
    }

    /**
     * Test that get_usage returns parsed data on success.
     */
    public function test_get_usage_success() {
        $usage_data = array(
            'tier'                     => 'free',
            'products_used_this_month' => 10,
            'products_limit'           => 25,
            'percentage_used'          => 40.0,
        );

        WP_Mock::userFunction( 'get_option' )
            ->with( 'taxoai_api_key', '' )
            ->andReturn( 'key' );

        WP_Mock::userFunction( 'wp_remote_get' )
            ->once()
            ->withArgs( function ( $url ) {
                return $url === TAXOAI_API_URL . '/v1/usage';
            } )
            ->andReturn( array(
                'response' => array( 'code' => 200 ),
                'body'     => json_encode( $usage_data ),
            ) );

        WP_Mock::userFunction( 'wp_remote_retrieve_response_code' )
            ->andReturn( 200 );

        WP_Mock::userFunction( 'wp_remote_retrieve_body' )
            ->andReturn( json_encode( $usage_data ) );

        WP_Mock::userFunction( 'is_wp_error' )
            ->andReturn( false );

        $result = $this->client->get_usage();

        $this->assertIsArray( $result );
        $this->assertEquals( 'free', $result['tier'] );
        $this->assertEquals( 10, $result['products_used_this_month'] );
    }

    /**
     * Test that submit_batch returns job data on success.
     */
    public function test_submit_batch_success() {
        $batch_response = array(
            'job_id'   => 'job-abc-123',
            'status'   => 'pending',
            'poll_url' => '/v1/jobs/job-abc-123',
        );

        WP_Mock::userFunction( 'get_option' )
            ->with( 'taxoai_api_key', '' )
            ->andReturn( 'key' );

        WP_Mock::userFunction( 'wp_remote_post' )
            ->once()
            ->withArgs( function ( $url ) {
                return $url === TAXOAI_API_URL . '/v1/products/batch';
            } )
            ->andReturn( array(
                'response' => array( 'code' => 200 ),
                'body'     => json_encode( $batch_response ),
            ) );

        WP_Mock::userFunction( 'wp_remote_retrieve_response_code' )
            ->andReturn( 200 );

        WP_Mock::userFunction( 'wp_remote_retrieve_body' )
            ->andReturn( json_encode( $batch_response ) );

        WP_Mock::userFunction( 'wp_json_encode' )
            ->andReturnUsing( function ( $data ) {
                return json_encode( $data );
            } );

        WP_Mock::userFunction( 'is_wp_error' )
            ->andReturn( false );

        $result = $this->client->submit_batch( array(
            array( 'name' => 'Product 1' ),
            array( 'name' => 'Product 2' ),
        ) );

        $this->assertIsArray( $result );
        $this->assertEquals( 'job-abc-123', $result['job_id'] );
    }
}
