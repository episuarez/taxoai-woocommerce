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
        add_action( 'wp_ajax_taxoai_undo_analysis', array( $this->ajax_handler, 'undo_analysis' ) );

        // Bulk action on product list: "Analyze with TaxoAI".
        add_filter( 'bulk_actions-edit-product', array( $this, 'register_bulk_action' ) );
        add_filter( 'handle_bulk_actions-edit-product', array( $this, 'handle_bulk_action' ), 10, 3 );

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
                    'analyzing'          => __( 'Analyzing...', 'woocommerce-taxoai' ),
                    'analyze_now'        => __( 'Analyze Now', 'woocommerce-taxoai' ),
                    'error'              => __( 'An error occurred. Please try again.', 'woocommerce-taxoai' ),
                    'limit_reached'      => __( 'Monthly analysis limit reached.', 'woocommerce-taxoai' ),
                    'no_api_key'         => __( 'Please configure your TaxoAI API key in settings.', 'woocommerce-taxoai' ),
                    'search_placeholder' => __( 'Search Google categories...', 'woocommerce-taxoai' ),
                    'undo_confirm'       => __( 'Restore this product to its state before the last TaxoAI analysis?', 'woocommerce-taxoai' ),
                    'undoing'            => __( 'Undoing...', 'woocommerce-taxoai' ),
                    'undo_success'       => __( 'Analysis undone. Reloading...', 'woocommerce-taxoai' ),
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

            // Parse product_ids from URL if redirected from product list bulk action.
            $query_ids = array();
            if ( ! empty( $_GET['product_ids'] ) ) { // phpcs:ignore WordPress.Security.NonceVerification
                $raw_ids   = sanitize_text_field( wp_unslash( $_GET['product_ids'] ) ); // phpcs:ignore
                $query_ids = array_values( array_filter( array_map( 'absint', explode( ',', $raw_ids ) ) ) );
            }

            wp_localize_script( 'taxoai-admin-bulk', 'taxoai_bulk', array(
                'ajax_url'         => admin_url( 'admin-ajax.php' ),
                'nonce'            => wp_create_nonce( 'taxoai_nonce' ),
                'query_product_ids' => $query_ids,
                'i18n'             => array(
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
     * Register "Analyze with TaxoAI" in the product list bulk actions dropdown.
     *
     * @param array $actions Existing bulk actions.
     * @return array
     */
    public function register_bulk_action( $actions ) {
        $actions['taxoai_bulk_analyze'] = __( 'Analyze with TaxoAI', 'woocommerce-taxoai' );
        return $actions;
    }

    /**
     * Handle the "Analyze with TaxoAI" bulk action.
     * Redirects to the TaxoAI Bulk Analyzer page with the selected product IDs.
     *
     * @param string $redirect_to URL to redirect to after the bulk action.
     * @param string $action      The action being taken.
     * @param array  $post_ids    Selected post IDs.
     * @return string
     */
    public function handle_bulk_action( $redirect_to, $action, $post_ids ) {
        if ( 'taxoai_bulk_analyze' !== $action ) {
            return $redirect_to;
        }

        $product_ids = array_map( 'absint', $post_ids );
        $product_ids = array_filter( $product_ids );

        if ( empty( $product_ids ) ) {
            return $redirect_to;
        }

        $url = add_query_arg(
            array(
                'page'        => 'taxoai-bulk-analyzer',
                'product_ids' => implode( ',', $product_ids ),
            ),
            admin_url( 'admin.php' )
        );

        return $url;
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
