<?php
/**
 * Tests for TaxoAI_SEO_Integrator.
 *
 * @package TaxoAI
 */

use WP_Mock\Tools\TestCase;

class Test_TaxoAI_SEO_Integrator extends TestCase {

    /**
     * @var TaxoAI_SEO_Integrator
     */
    private $integrator;

    public function setUp(): void {
        parent::setUp();
        $this->integrator = new TaxoAI_SEO_Integrator();
    }

    public function tearDown(): void {
        parent::tearDown();
    }

    /**
     * Helper: standard SEO data.
     *
     * @return array
     */
    private function get_seo_data() {
        return array(
            'optimized_title'       => 'Premium Blue T-Shirt',
            'meta_title'            => 'Blue T-Shirt | Best Deals',
            'meta_description'      => 'Buy our premium blue t-shirt for everyday comfort.',
            'optimized_description' => '<p>A premium blue cotton t-shirt.</p>',
            'keywords'              => array(
                array( 'keyword' => 'blue t-shirt', 'volume' => 5400 ),
                array( 'keyword' => 'cotton shirt', 'volume' => 3200 ),
            ),
            'tags' => array( 't-shirt', 'cotton', 'blue' ),
        );
    }

    /**
     * Test that Yoast meta keys are set when Yoast is active.
     */
    public function test_yoast_meta_keys() {
        $seo = $this->get_seo_data();

        WP_Mock::userFunction( 'is_plugin_active' )
            ->with( 'wordpress-seo/wp-seo.php' )
            ->andReturn( true );

        WP_Mock::userFunction( 'is_plugin_active' )
            ->with( 'wordpress-seo-premium/wp-seo-premium.php' )
            ->andReturn( false );

        WP_Mock::userFunction( 'sanitize_text_field' )
            ->andReturnArg( 0 );

        WP_Mock::userFunction( 'wp_kses_post' )
            ->andReturnArg( 0 );

        // Expect Yoast meta.
        WP_Mock::userFunction( 'update_post_meta' )
            ->with( 42, '_yoast_wpseo_title', 'Blue T-Shirt | Best Deals' )
            ->once();

        WP_Mock::userFunction( 'update_post_meta' )
            ->with( 42, '_yoast_wpseo_metadesc', 'Buy our premium blue t-shirt for everyday comfort.' )
            ->once();

        WP_Mock::userFunction( 'update_post_meta' )
            ->with( 42, '_yoast_wpseo_focuskw', 'blue t-shirt' )
            ->once();

        // Tags and keywords.
        WP_Mock::userFunction( 'wp_set_object_terms' )
            ->once()
            ->with( 42, array( 't-shirt', 'cotton', 'blue' ), 'product_tag', true );

        WP_Mock::userFunction( 'update_post_meta' )
            ->with( 42, '_taxoai_keywords', $seo['keywords'] )
            ->once();

        // Allow any other update_post_meta.
        WP_Mock::userFunction( 'update_post_meta' )->andReturn( true );

        $settings = array( 'update_title' => false, 'update_description' => false );
        $this->integrator->apply_seo( 42, $seo, $settings );
    }

    /**
     * Test that Rank Math meta keys are set when Rank Math is active.
     */
    public function test_rank_math_meta_keys() {
        $seo = $this->get_seo_data();

        WP_Mock::userFunction( 'is_plugin_active' )
            ->with( 'wordpress-seo/wp-seo.php' )
            ->andReturn( false );

        WP_Mock::userFunction( 'is_plugin_active' )
            ->with( 'wordpress-seo-premium/wp-seo-premium.php' )
            ->andReturn( false );

        WP_Mock::userFunction( 'is_plugin_active' )
            ->with( 'seo-by-rank-math/rank-math.php' )
            ->andReturn( true );

        WP_Mock::userFunction( 'sanitize_text_field' )->andReturnArg( 0 );
        WP_Mock::userFunction( 'wp_kses_post' )->andReturnArg( 0 );

        WP_Mock::userFunction( 'update_post_meta' )
            ->with( 42, 'rank_math_title', 'Blue T-Shirt | Best Deals' )
            ->once();

        WP_Mock::userFunction( 'update_post_meta' )
            ->with( 42, 'rank_math_description', 'Buy our premium blue t-shirt for everyday comfort.' )
            ->once();

        WP_Mock::userFunction( 'update_post_meta' )
            ->with( 42, 'rank_math_focus_keyword', 'blue t-shirt' )
            ->once();

        WP_Mock::userFunction( 'wp_set_object_terms' )->once();
        WP_Mock::userFunction( 'update_post_meta' )->andReturn( true );

        $settings = array( 'update_title' => false, 'update_description' => false );
        $this->integrator->apply_seo( 42, $seo, $settings );
    }

    /**
     * Test that fallback meta keys are used when no SEO plugin is active.
     */
    public function test_fallback_meta_keys() {
        $seo = $this->get_seo_data();

        WP_Mock::userFunction( 'is_plugin_active' )->andReturn( false );
        WP_Mock::userFunction( 'sanitize_text_field' )->andReturnArg( 0 );
        WP_Mock::userFunction( 'wp_kses_post' )->andReturnArg( 0 );

        WP_Mock::userFunction( 'update_post_meta' )
            ->with( 42, '_taxoai_seo_title', 'Blue T-Shirt | Best Deals' )
            ->once();

        WP_Mock::userFunction( 'update_post_meta' )
            ->with( 42, '_taxoai_seo_meta_description', 'Buy our premium blue t-shirt for everyday comfort.' )
            ->once();

        WP_Mock::userFunction( 'wp_set_object_terms' )->once();
        WP_Mock::userFunction( 'update_post_meta' )->andReturn( true );

        $settings = array( 'update_title' => false, 'update_description' => false );
        $this->integrator->apply_seo( 42, $seo, $settings );
    }

    /**
     * Test that product tags are added from seo.tags.
     */
    public function test_adds_product_tags() {
        $seo = $this->get_seo_data();

        WP_Mock::userFunction( 'is_plugin_active' )->andReturn( false );
        WP_Mock::userFunction( 'sanitize_text_field' )->andReturnArg( 0 );
        WP_Mock::userFunction( 'wp_kses_post' )->andReturnArg( 0 );
        WP_Mock::userFunction( 'update_post_meta' )->andReturn( true );

        WP_Mock::userFunction( 'wp_set_object_terms' )
            ->once()
            ->withArgs( function ( $id, $tags, $taxonomy, $append ) {
                return $id === 42
                    && $tags === array( 't-shirt', 'cotton', 'blue' )
                    && $taxonomy === 'product_tag'
                    && $append === true;
            } );

        $settings = array( 'update_title' => false, 'update_description' => false );
        $this->integrator->apply_seo( 42, $seo, $settings );
    }

    /**
     * Test that the product title is updated when the setting is enabled.
     */
    public function test_updates_post_title_when_enabled() {
        $seo = $this->get_seo_data();

        WP_Mock::userFunction( 'is_plugin_active' )->andReturn( false );
        WP_Mock::userFunction( 'sanitize_text_field' )->andReturnArg( 0 );
        WP_Mock::userFunction( 'wp_kses_post' )->andReturnArg( 0 );
        WP_Mock::userFunction( 'update_post_meta' )->andReturn( true );
        WP_Mock::userFunction( 'wp_set_object_terms' )->andReturn( true );

        WP_Mock::userFunction( 'wp_update_post' )
            ->once()
            ->withArgs( function ( $args ) {
                return $args['ID'] === 42
                    && $args['post_title'] === 'Premium Blue T-Shirt';
            } );

        $settings = array( 'update_title' => true, 'update_description' => false );
        $this->integrator->apply_seo( 42, $seo, $settings );
    }

    /**
     * Test that the product description is updated when the setting is enabled.
     */
    public function test_updates_post_content_when_enabled() {
        $seo = $this->get_seo_data();

        WP_Mock::userFunction( 'is_plugin_active' )->andReturn( false );
        WP_Mock::userFunction( 'sanitize_text_field' )->andReturnArg( 0 );
        WP_Mock::userFunction( 'wp_kses_post' )->andReturnArg( 0 );
        WP_Mock::userFunction( 'update_post_meta' )->andReturn( true );
        WP_Mock::userFunction( 'wp_set_object_terms' )->andReturn( true );

        WP_Mock::userFunction( 'wp_update_post' )
            ->once()
            ->withArgs( function ( $args ) {
                return $args['ID'] === 42
                    && $args['post_content'] === '<p>A premium blue cotton t-shirt.</p>';
            } );

        $settings = array( 'update_title' => false, 'update_description' => true );
        $this->integrator->apply_seo( 42, $seo, $settings );
    }
}
