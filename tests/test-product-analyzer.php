<?php
/**
 * Tests for TaxoAI_Product_Analyzer.
 *
 * @package TaxoAI
 */

use WP_Mock\Tools\TestCase;

class Test_TaxoAI_Product_Analyzer extends TestCase {

    /**
     * @var TaxoAI_API_Client|\Mockery\MockInterface
     */
    private $api_client;

    /**
     * @var TaxoAI_Usage_Tracker|\Mockery\MockInterface
     */
    private $usage_tracker;

    /**
     * @var TaxoAI_SEO_Integrator|\Mockery\MockInterface
     */
    private $seo_integrator;

    /**
     * @var TaxoAI_Category_Mapper|\Mockery\MockInterface
     */
    private $category_mapper;

    /**
     * @var TaxoAI_Attribute_Mapper|\Mockery\MockInterface
     */
    private $attribute_mapper;

    /**
     * @var TaxoAI_Product_Analyzer
     */
    private $analyzer;

    public function setUp(): void {
        parent::setUp();

        $this->api_client       = \Mockery::mock( 'TaxoAI_API_Client' );
        $this->usage_tracker    = \Mockery::mock( 'TaxoAI_Usage_Tracker' );
        $this->seo_integrator   = \Mockery::mock( 'TaxoAI_SEO_Integrator' );
        $this->category_mapper  = \Mockery::mock( 'TaxoAI_Category_Mapper' );
        $this->attribute_mapper = \Mockery::mock( 'TaxoAI_Attribute_Mapper' );

        $this->analyzer = new TaxoAI_Product_Analyzer(
            $this->api_client,
            $this->usage_tracker,
            $this->seo_integrator,
            $this->category_mapper,
            $this->attribute_mapper
        );
    }

    public function tearDown(): void {
        parent::tearDown();
    }

    /**
     * Helper: create a mock WC_Product.
     *
     * @return \Mockery\MockInterface
     */
    private function create_mock_product() {
        $product = \Mockery::mock( 'WC_Product' );
        $product->shouldReceive( 'get_id' )->andReturn( 42 );
        $product->shouldReceive( 'get_name' )->andReturn( 'Blue Cotton T-Shirt' );
        $product->shouldReceive( 'get_description' )->andReturn( 'A comfortable blue cotton t-shirt.' );
        $product->shouldReceive( 'get_price' )->andReturn( '29.99' );
        $product->shouldReceive( 'get_image_id' )->andReturn( 101 );
        $product->shouldReceive( 'get_gallery_image_ids' )->andReturn( array( 102, 103 ) );
        return $product;
    }

    /**
     * Helper: standard API response.
     *
     * @return array
     */
    private function get_api_response() {
        return array(
            'classification' => array(
                'google_category'    => 'Apparel & Accessories > Clothing > Shirts & Tops',
                'google_category_id' => 212,
                'confidence'         => 0.92,
            ),
            'attributes' => array(
                'color'    => array( 'Blue' ),
                'material' => 'Cotton',
                'gender'   => 'Unisex',
                'style'    => 'Casual',
            ),
            'seo' => array(
                'optimized_title'       => 'Premium Blue Cotton T-Shirt',
                'meta_title'            => 'Blue Cotton T-Shirt | Shop Now',
                'meta_description'      => 'Buy our premium blue cotton t-shirt.',
                'optimized_description' => 'A premium blue cotton t-shirt for everyday wear.',
                'keywords'              => array( array( 'keyword' => 'cotton t-shirt', 'volume' => 5400 ) ),
                'tags'                  => array( 't-shirt', 'cotton' ),
            ),
        );
    }

    /**
     * Test that the analyzer builds the correct payload from a WC_Product.
     */
    public function test_builds_correct_payload() {
        $product = $this->create_mock_product();

        WP_Mock::userFunction( 'get_option' )
            ->with( 'taxoai_language', 'es' )
            ->andReturn( 'en' );

        WP_Mock::userFunction( 'get_option' )
            ->with( 'taxoai_analyze_images', '0' )
            ->andReturn( '1' );

        WP_Mock::userFunction( 'wp_strip_all_tags' )
            ->andReturnUsing( function ( $str ) { return strip_tags( $str ); } );

        WP_Mock::userFunction( 'wp_get_attachment_url' )
            ->with( 101 )
            ->andReturn( 'https://shop.example.com/img/101.jpg' );
        WP_Mock::userFunction( 'wp_get_attachment_url' )
            ->with( 102 )
            ->andReturn( 'https://shop.example.com/img/102.jpg' );
        WP_Mock::userFunction( 'wp_get_attachment_url' )
            ->with( 103 )
            ->andReturn( 'https://shop.example.com/img/103.jpg' );

        $payload = $this->analyzer->build_payload( $product );

        $this->assertEquals( 'Blue Cotton T-Shirt', $payload['name'] );
        $this->assertEquals( 'en', $payload['language'] );
        $this->assertEquals( 29.99, $payload['price'] );
        $this->assertCount( 3, $payload['image_urls'] );
        $this->assertTrue( $payload['analyze_images'] );
    }

    /**
     * Test that the analyzer stores the result in post meta.
     */
    public function test_stores_result_in_post_meta() {
        $product  = $this->create_mock_product();
        $response = $this->get_api_response();

        WP_Mock::userFunction( 'wc_get_product' )
            ->with( 42 )
            ->andReturn( $product );

        WP_Mock::userFunction( 'get_option' )
            ->andReturnUsing( function ( $key, $default = '' ) {
                $map = array(
                    'taxoai_api_key'                => 'test-key',
                    'taxoai_language'               => 'en',
                    'taxoai_analyze_images'         => '0',
                    'taxoai_confidence_threshold'   => '0.7',
                    'taxoai_update_title'           => '0',
                    'taxoai_update_description'     => '0',
                );
                return isset( $map[ $key ] ) ? $map[ $key ] : $default;
            } );

        $this->usage_tracker->shouldReceive( 'can_analyze' )->once()->andReturn( true );
        $this->usage_tracker->shouldReceive( 'increment' )->once();

        $this->api_client
            ->shouldReceive( 'analyze_product' )
            ->once()
            ->andReturn( $response );

        WP_Mock::userFunction( 'is_wp_error' )->andReturn( false );

        WP_Mock::userFunction( 'wp_strip_all_tags' )
            ->andReturnUsing( function ( $s ) { return strip_tags( $s ); } );

        WP_Mock::userFunction( 'wp_get_attachment_url' )
            ->andReturn( 'https://shop.example.com/img/test.jpg' );

        WP_Mock::userFunction( 'current_time' )
            ->with( 'mysql' )
            ->andReturn( '2025-06-01 12:00:00' );

        WP_Mock::userFunction( 'sanitize_text_field' )
            ->andReturnUsing( function ( $s ) { return $s; } );

        // Expect post meta updates for storage.
        WP_Mock::userFunction( 'update_post_meta' )
            ->with( 42, '_taxoai_analysis_result', $response )
            ->once();

        WP_Mock::userFunction( 'update_post_meta' )
            ->with( 42, '_taxoai_analyzed_at', '2025-06-01 12:00:00' )
            ->once();

        WP_Mock::userFunction( 'update_post_meta' )
            ->with( 42, '_taxoai_google_category', 'Apparel & Accessories > Clothing > Shirts & Tops' )
            ->once();

        WP_Mock::userFunction( 'update_post_meta' )
            ->with( 42, '_taxoai_google_category_id', 212 )
            ->once();

        WP_Mock::userFunction( 'update_post_meta' )
            ->with( 42, '_taxoai_confidence', 0.92 )
            ->once();

        // Delegated calls (allow them).
        $this->seo_integrator->shouldReceive( 'apply_seo' )->once();
        $this->category_mapper->shouldReceive( 'map' )->once();
        $this->attribute_mapper->shouldReceive( 'map_attributes' )->once();

        // Allow any other update_post_meta calls from delegated classes.
        WP_Mock::userFunction( 'update_post_meta' )->andReturn( true );

        $result = $this->analyzer->analyze( 42, true );

        $this->assertIsArray( $result );
        $this->assertEquals( 0.92, $result['classification']['confidence'] );
    }

    /**
     * Test that analysis is skipped when the usage limit is reached.
     */
    public function test_skips_when_limit_reached() {
        $product = $this->create_mock_product();

        WP_Mock::userFunction( 'wc_get_product' )
            ->with( 42 )
            ->andReturn( $product );

        WP_Mock::userFunction( 'get_option' )
            ->with( 'taxoai_api_key', '' )
            ->andReturn( 'test-key' );

        $this->usage_tracker->shouldReceive( 'can_analyze' )->once()->andReturn( false );

        $result = $this->analyzer->analyze( 42 );

        $this->assertInstanceOf( 'WP_Error', $result );
        $this->assertEquals( 'taxoai_limit_reached', $result->get_error_code() );
    }

    /**
     * Test that results are not auto-applied when confidence is below threshold.
     */
    public function test_respects_confidence_threshold() {
        $product  = $this->create_mock_product();
        $response = $this->get_api_response();
        // Set confidence below the threshold.
        $response['classification']['confidence'] = 0.4;

        WP_Mock::userFunction( 'wc_get_product' )
            ->with( 42 )
            ->andReturn( $product );

        WP_Mock::userFunction( 'get_option' )
            ->andReturnUsing( function ( $key, $default = '' ) {
                $map = array(
                    'taxoai_api_key'                => 'test-key',
                    'taxoai_language'               => 'en',
                    'taxoai_analyze_images'         => '0',
                    'taxoai_confidence_threshold'   => '0.7',
                    'taxoai_update_title'           => '0',
                    'taxoai_update_description'     => '0',
                );
                return isset( $map[ $key ] ) ? $map[ $key ] : $default;
            } );

        $this->usage_tracker->shouldReceive( 'can_analyze' )->andReturn( true );
        $this->usage_tracker->shouldReceive( 'increment' )->once();

        $this->api_client->shouldReceive( 'analyze_product' )->once()->andReturn( $response );

        WP_Mock::userFunction( 'is_wp_error' )->andReturn( false );
        WP_Mock::userFunction( 'wp_strip_all_tags' )->andReturnArg( 0 );
        WP_Mock::userFunction( 'wp_get_attachment_url' )->andReturn( 'https://img.test/1.jpg' );
        WP_Mock::userFunction( 'current_time' )->andReturn( '2025-06-01 12:00:00' );
        WP_Mock::userFunction( 'sanitize_text_field' )->andReturnArg( 0 );
        WP_Mock::userFunction( 'update_post_meta' )->andReturn( true );

        // These should NOT be called because confidence < threshold.
        $this->seo_integrator->shouldNotReceive( 'apply_seo' );
        $this->category_mapper->shouldNotReceive( 'map' );
        $this->attribute_mapper->shouldNotReceive( 'map_attributes' );

        $result = $this->analyzer->analyze( 42, true );

        $this->assertIsArray( $result );
    }

    /**
     * Test that analysis delegates to the SEO integrator.
     */
    public function test_delegates_to_seo_integrator() {
        $product  = $this->create_mock_product();
        $response = $this->get_api_response();

        WP_Mock::userFunction( 'wc_get_product' )->with( 42 )->andReturn( $product );
        WP_Mock::userFunction( 'get_option' )->andReturnUsing( function ( $key, $default = '' ) {
            $map = array(
                'taxoai_api_key'                => 'test-key',
                'taxoai_language'               => 'en',
                'taxoai_analyze_images'         => '0',
                'taxoai_confidence_threshold'   => '0.5',
                'taxoai_update_title'           => '1',
                'taxoai_update_description'     => '0',
            );
            return isset( $map[ $key ] ) ? $map[ $key ] : $default;
        } );

        $this->usage_tracker->shouldReceive( 'can_analyze' )->andReturn( true );
        $this->usage_tracker->shouldReceive( 'increment' );

        $this->api_client->shouldReceive( 'analyze_product' )->andReturn( $response );

        WP_Mock::userFunction( 'is_wp_error' )->andReturn( false );
        WP_Mock::userFunction( 'wp_strip_all_tags' )->andReturnArg( 0 );
        WP_Mock::userFunction( 'wp_get_attachment_url' )->andReturn( 'https://img.test/1.jpg' );
        WP_Mock::userFunction( 'current_time' )->andReturn( '2025-06-01 12:00:00' );
        WP_Mock::userFunction( 'sanitize_text_field' )->andReturnArg( 0 );
        WP_Mock::userFunction( 'update_post_meta' )->andReturn( true );

        $this->seo_integrator->shouldReceive( 'apply_seo' )
            ->once()
            ->withArgs( function ( $id, $seo, $settings ) {
                return $id === 42
                    && isset( $seo['meta_title'] )
                    && true === $settings['update_title'];
            } );

        $this->category_mapper->shouldReceive( 'map' )->once();
        $this->attribute_mapper->shouldReceive( 'map_attributes' )->once();

        $this->analyzer->analyze( 42, true );
    }

    /**
     * Test that API errors are returned gracefully.
     */
    public function test_handles_api_error_gracefully() {
        $product  = $this->create_mock_product();
        $wp_error = \Mockery::mock( 'WP_Error' );
        $wp_error->shouldReceive( 'get_error_code' )->andReturn( 'taxoai_server_error' );
        $wp_error->shouldReceive( 'get_error_message' )->andReturn( 'Server error' );

        WP_Mock::userFunction( 'wc_get_product' )->with( 42 )->andReturn( $product );
        WP_Mock::userFunction( 'get_option' )->andReturnUsing( function ( $key, $default = '' ) {
            $map = array(
                'taxoai_api_key'        => 'test-key',
                'taxoai_language'       => 'en',
                'taxoai_analyze_images' => '0',
            );
            return isset( $map[ $key ] ) ? $map[ $key ] : $default;
        } );

        $this->usage_tracker->shouldReceive( 'can_analyze' )->andReturn( true );
        $this->api_client->shouldReceive( 'analyze_product' )->andReturn( $wp_error );

        WP_Mock::userFunction( 'is_wp_error' )
            ->andReturnUsing( function ( $v ) use ( $wp_error ) {
                return $v === $wp_error;
            } );

        WP_Mock::userFunction( 'wp_strip_all_tags' )->andReturnArg( 0 );
        WP_Mock::userFunction( 'wp_get_attachment_url' )->andReturn( 'https://img.test/1.jpg' );

        $result = $this->analyzer->analyze( 42 );

        $this->assertInstanceOf( 'WP_Error', $result );
        $this->assertEquals( 'taxoai_server_error', $result->get_error_code() );
    }
}
