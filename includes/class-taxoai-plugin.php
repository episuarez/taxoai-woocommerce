<?php
/**
 * Main TaxoAI Plugin class.
 *
 * @package TaxoAI
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class TaxoAI_Plugin
 *
 * Singleton entry point that wires up all sub-classes and WordPress hooks.
 */
class TaxoAI_Plugin {

    /**
     * Singleton instance.
     *
     * @var TaxoAI_Plugin|null
     */
    private static $instance = null;

    /**
     * API client instance.
     *
     * @var TaxoAI_API_Client
     */
    public $api_client;

    /**
     * Usage tracker instance.
     *
     * @var TaxoAI_Usage_Tracker
     */
    public $usage_tracker;

    /**
     * SEO integrator instance.
     *
     * @var TaxoAI_SEO_Integrator
     */
    public $seo_integrator;

    /**
     * Category mapper instance.
     *
     * @var TaxoAI_Category_Mapper
     */
    public $category_mapper;

    /**
     * Attribute mapper instance.
     *
     * @var TaxoAI_Attribute_Mapper
     */
    public $attribute_mapper;

    /**
     * Product analyzer instance.
     *
     * @var TaxoAI_Product_Analyzer
     */
    public $product_analyzer;

    /**
     * Settings page instance.
     *
     * @var TaxoAI_Settings
     */
    public $settings;

    /**
     * Product metabox instance.
     *
     * @var TaxoAI_Product_Metabox
     */
    public $product_metabox;

    /**
     * AJAX handler instance.
     *
     * @var TaxoAI_Ajax_Handler
     */
    public $ajax_handler;

    /**
     * Bulk analyzer instance.
     *
     * @var TaxoAI_Bulk_Analyzer
     */
    public $bulk_analyzer;

    /**
     * Product columns instance.
     *
     * @var TaxoAI_Product_Columns
     */
    public $product_columns;

    /**
     * Get singleton instance.
     *
     * @return TaxoAI_Plugin
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Private constructor.
     */
    private function __construct() {
        $this->init();
    }

    /**
     * Initialize all sub-classes and register hooks.
     */
    private function init() {
        // Load translations.
        load_plugin_textdomain( 'woocommerce-taxoai', false, dirname( plugin_basename( TAXOAI_PLUGIN_DIR . 'woocommerce-taxoai.php' ) ) . '/languages' );

        // Instantiate core services.
        $this->api_client       = new TaxoAI_API_Client();
        $this->usage_tracker    = new TaxoAI_Usage_Tracker( $this->api_client );
        $this->seo_integrator   = new TaxoAI_SEO_Integrator();
        $this->category_mapper  = new TaxoAI_Category_Mapper();
        $this->attribute_mapper = new TaxoAI_Attribute_Mapper();
        $this->product_analyzer = new TaxoAI_Product_Analyzer(
            $this->api_client,
            $this->usage_tracker,
            $this->seo_integrator,
            $this->category_mapper,
            $this->attribute_mapper
        );

        // Instantiate admin components.
        $this->settings        = new TaxoAI_Settings( $this->api_client );
        $this->product_metabox = new TaxoAI_Product_Metabox();
        $this->ajax_handler    = new TaxoAI_Ajax_Handler( $this->product_analyzer, $this->api_client );
        $this->bulk_analyzer   = new TaxoAI_Bulk_Analyzer();
        $this->product_columns = new TaxoAI_Product_Columns();

        // Register hooks.
        $this->register_hooks();
    }

    /**
     * Register all WordPress hooks.
     */
    private function register_hooks() {
        // Auto-analyze on product save.
        add_action( 'save_post_product', array( $this, 'maybe_auto_analyze' ), 20, 2 );

        // Admin scripts and styles.
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );

        // Admin menu pages.
        add_action( 'admin_menu', array( $this->settings, 'register_menu' ) );
        add_action( 'admin_menu', array( $this->bulk_analyzer, 'register_menu' ) );
        add_action( 'admin_init', array( $this->settings, 'register_settings' ) );

        // Meta boxes.
        add_action( 'add_meta_boxes', array( $this->product_metabox, 'register_metabox' ) );

        // AJAX handlers.
        add_action( 'wp_ajax_taxoai_analyze_product', array( $this->ajax_handler, 'analyze_product' ) );
        add_action( 'wp_ajax_taxoai_search_taxonomy', array( $this->ajax_handler, 'search_taxonomy' ) );
        add_action( 'wp_ajax_taxoai_bulk_analyze', array( $this->ajax_handler, 'bulk_analyze' ) );
        add_action( 'wp_ajax_taxoai_poll_job', array( $this->ajax_handler, 'poll_job' ) );

        // Product list columns.
        add_filter( 'manage_product_posts_columns', array( $this->product_columns, 'add_column' ) );
        add_action( 'manage_product_posts_custom_column', array( $this->product_columns, 'render_column' ), 10, 2 );

        // Plugin action links.
        add_filter( 'plugin_action_links_' . plugin_basename( TAXOAI_PLUGIN_DIR . 'woocommerce-taxoai.php' ), array( $this, 'add_action_links' ) );
    }

    /**
     * Conditionally auto-analyze a product on save.
     *
     * @param int     $post_id Post ID.
     * @param WP_Post $post    Post object.
     */
    public function maybe_auto_analyze( $post_id, $post ) {
        // Bail if auto-analyze is disabled.
        if ( '1' !== get_option( 'taxoai_auto_analyze', '0' ) ) {
            return;
        }

        // Bail on autosave, revision, or wrong post type.
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }
        if ( wp_is_post_revision( $post_id ) ) {
            return;
        }
        if ( 'product' !== $post->post_type ) {
            return;
        }

        // Only analyze published products.
        if ( 'publish' !== $post->post_status ) {
            return;
        }

        // Prevent infinite loops.
        remove_action( 'save_post_product', array( $this, 'maybe_auto_analyze' ), 20 );
        $this->product_analyzer->analyze( $post_id );
        add_action( 'save_post_product', array( $this, 'maybe_auto_analyze' ), 20, 2 );
    }

    /**
     * Enqueue admin scripts and styles on relevant pages.
     *
     * @param string $hook_suffix Current admin page hook suffix.
     */
    public function enqueue_admin_assets( $hook_suffix ) {
        $screen = get_current_screen();

        if ( ! $screen ) {
            return;
        }

        // Product edit screen.
        if ( 'product' === $screen->id || 'edit-product' === $screen->id ) {
            wp_enqueue_style(
                'taxoai-admin-metabox',
                TAXOAI_PLUGIN_URL . 'assets/css/admin-metabox.css',
                array(),
                TAXOAI_VERSION
            );

            wp_enqueue_script(
                'taxoai-admin-metabox',
                TAXOAI_PLUGIN_URL . 'assets/js/admin-metabox.js',
                array( 'jquery' ),
                TAXOAI_VERSION,
                true
            );

            wp_localize_script( 'taxoai-admin-metabox', 'taxoai_metabox', array(
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'nonce'    => wp_create_nonce( 'taxoai_nonce' ),
                'i18n'     => array(
                    'analyzing'       => __( 'Analyzing...', 'woocommerce-taxoai' ),
                    'analyze_now'     => __( 'Analyze Now', 'woocommerce-taxoai' ),
                    'error'           => __( 'An error occurred. Please try again.', 'woocommerce-taxoai' ),
                    'limit_reached'   => __( 'Monthly analysis limit reached.', 'woocommerce-taxoai' ),
                    'no_api_key'      => __( 'Please configure your TaxoAI API key in settings.', 'woocommerce-taxoai' ),
                    'search_placeholder' => __( 'Search Google categories...', 'woocommerce-taxoai' ),
                ),
            ) );
        }

        // Bulk analyzer page.
        if ( 'woocommerce_page_taxoai-bulk-analyzer' === $screen->id ) {
            wp_enqueue_style(
                'taxoai-admin-metabox',
                TAXOAI_PLUGIN_URL . 'assets/css/admin-metabox.css',
                array(),
                TAXOAI_VERSION
            );

            wp_enqueue_script(
                'taxoai-admin-bulk',
                TAXOAI_PLUGIN_URL . 'assets/js/admin-bulk.js',
                array( 'jquery' ),
                TAXOAI_VERSION,
                true
            );

            wp_localize_script( 'taxoai-admin-bulk', 'taxoai_bulk', array(
                'ajax_url' => admin_url( 'admin-ajax.php' ),
                'nonce'    => wp_create_nonce( 'taxoai_nonce' ),
                'i18n'     => array(
                    'processing'    => __( 'Processing...', 'woocommerce-taxoai' ),
                    'completed'     => __( 'Completed', 'woocommerce-taxoai' ),
                    'failed'        => __( 'Failed', 'woocommerce-taxoai' ),
                    'no_selection'  => __( 'Please select at least one product.', 'woocommerce-taxoai' ),
                    'confirm_bulk'  => __( 'Analyze selected products?', 'woocommerce-taxoai' ),
                    'polling'       => __( 'Checking progress...', 'woocommerce-taxoai' ),
                ),
            ) );
        }
    }

    /**
     * Add plugin action links.
     *
     * @param array $links Existing links.
     * @return array
     */
    public function add_action_links( $links ) {
        $settings_link = sprintf(
            '<a href="%s">%s</a>',
            esc_url( admin_url( 'admin.php?page=taxoai-settings' ) ),
            esc_html__( 'Settings', 'woocommerce-taxoai' )
        );
        array_unshift( $links, $settings_link );
        return $links;
    }
}
