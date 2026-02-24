<?php
/**
 * TaxoAI Settings Page.
 *
 * @package TaxoAI
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class TaxoAI_Settings
 *
 * Registers the admin settings page under WooCommerce menu.
 */
class TaxoAI_Settings {

    /**
     * API client for validating keys and fetching usage.
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
     * Register the submenu page under WooCommerce.
     */
    public function register_menu() {
        add_submenu_page(
            'woocommerce',
            __( 'TaxoAI Settings', 'woocommerce-taxoai' ),
            __( 'TaxoAI', 'woocommerce-taxoai' ),
            'manage_woocommerce',
            'taxoai-settings',
            array( $this, 'render_page' )
        );
    }

    /**
     * Register settings with the WordPress Settings API.
     */
    public function register_settings() {
        // API section.
        add_settings_section(
            'taxoai_api_section',
            __( 'API Configuration', 'woocommerce-taxoai' ),
            array( $this, 'render_api_section' ),
            'taxoai-settings'
        );

        add_settings_field(
            'taxoai_api_key',
            __( 'API Key', 'woocommerce-taxoai' ),
            array( $this, 'render_api_key_field' ),
            'taxoai-settings',
            'taxoai_api_section'
        );

        add_settings_field(
            'taxoai_language',
            __( 'Language', 'woocommerce-taxoai' ),
            array( $this, 'render_language_field' ),
            'taxoai-settings',
            'taxoai_api_section'
        );

        // Automation section.
        add_settings_section(
            'taxoai_automation_section',
            __( 'Automation', 'woocommerce-taxoai' ),
            array( $this, 'render_automation_section' ),
            'taxoai-settings'
        );

        add_settings_field(
            'taxoai_auto_analyze',
            __( 'Auto-Analyze', 'woocommerce-taxoai' ),
            array( $this, 'render_auto_analyze_field' ),
            'taxoai-settings',
            'taxoai_automation_section'
        );

        add_settings_field(
            'taxoai_confidence_threshold',
            __( 'Confidence Threshold', 'woocommerce-taxoai' ),
            array( $this, 'render_confidence_threshold_field' ),
            'taxoai-settings',
            'taxoai_automation_section'
        );

        add_settings_field(
            'taxoai_auto_map_categories',
            __( 'Auto-Map Categories', 'woocommerce-taxoai' ),
            array( $this, 'render_auto_map_categories_field' ),
            'taxoai-settings',
            'taxoai_automation_section'
        );

        add_settings_field(
            'taxoai_analyze_images',
            __( 'Analyze Images', 'woocommerce-taxoai' ),
            array( $this, 'render_analyze_images_field' ),
            'taxoai-settings',
            'taxoai_automation_section'
        );

        // SEO section.
        add_settings_section(
            'taxoai_seo_section',
            __( 'SEO Settings', 'woocommerce-taxoai' ),
            array( $this, 'render_seo_section' ),
            'taxoai-settings'
        );

        add_settings_field(
            'taxoai_update_title',
            __( 'Update Product Title', 'woocommerce-taxoai' ),
            array( $this, 'render_update_title_field' ),
            'taxoai-settings',
            'taxoai_seo_section'
        );

        add_settings_field(
            'taxoai_update_description',
            __( 'Update Product Description', 'woocommerce-taxoai' ),
            array( $this, 'render_update_description_field' ),
            'taxoai-settings',
            'taxoai_seo_section'
        );

        // Register settings.
        register_setting( 'taxoai_settings_group', 'taxoai_api_key', array(
            'type'              => 'string',
            'sanitize_callback' => array( $this, 'sanitize_api_key' ),
            'default'           => '',
        ) );

        register_setting( 'taxoai_settings_group', 'taxoai_language', array(
            'type'              => 'string',
            'sanitize_callback' => array( $this, 'sanitize_language' ),
            'default'           => 'es',
        ) );

        register_setting( 'taxoai_settings_group', 'taxoai_auto_analyze', array(
            'type'              => 'string',
            'sanitize_callback' => array( $this, 'sanitize_checkbox' ),
            'default'           => '0',
        ) );

        register_setting( 'taxoai_settings_group', 'taxoai_confidence_threshold', array(
            'type'              => 'number',
            'sanitize_callback' => array( $this, 'sanitize_confidence_threshold' ),
            'default'           => 0.7,
        ) );

        register_setting( 'taxoai_settings_group', 'taxoai_update_title', array(
            'type'              => 'string',
            'sanitize_callback' => array( $this, 'sanitize_checkbox' ),
            'default'           => '0',
        ) );

        register_setting( 'taxoai_settings_group', 'taxoai_update_description', array(
            'type'              => 'string',
            'sanitize_callback' => array( $this, 'sanitize_checkbox' ),
            'default'           => '0',
        ) );

        register_setting( 'taxoai_settings_group', 'taxoai_auto_map_categories', array(
            'type'              => 'string',
            'sanitize_callback' => array( $this, 'sanitize_checkbox' ),
            'default'           => '0',
        ) );

        register_setting( 'taxoai_settings_group', 'taxoai_analyze_images', array(
            'type'              => 'string',
            'sanitize_callback' => array( $this, 'sanitize_checkbox' ),
            'default'           => '0',
        ) );
    }

    /**
     * Sanitize the API key: trim whitespace and validate against /v1/usage.
     *
     * @param string $value Raw API key input.
     * @return string Sanitized API key.
     */
    public function sanitize_api_key( $value ) {
        $value = sanitize_text_field( trim( $value ) );

        if ( empty( $value ) ) {
            return $value;
        }

        // Temporarily set the option so the API client picks it up.
        $old_key = get_option( 'taxoai_api_key', '' );
        update_option( 'taxoai_api_key', $value );

        $result = $this->api_client->get_usage();

        if ( is_wp_error( $result ) ) {
            // Restore the old key and show an error.
            update_option( 'taxoai_api_key', $old_key );
            add_settings_error(
                'taxoai_api_key',
                'taxoai_invalid_key',
                __( 'The API key could not be validated. Please check it and try again.', 'woocommerce-taxoai' ),
                'error'
            );
            return $old_key;
        }

        // Cache the usage data.
        set_transient( TaxoAI_Usage_Tracker::CACHE_KEY, $result, TaxoAI_Usage_Tracker::CACHE_TTL );

        add_settings_error(
            'taxoai_api_key',
            'taxoai_key_valid',
            __( 'API key validated successfully.', 'woocommerce-taxoai' ),
            'success'
        );

        return $value;
    }

    /**
     * Sanitize language select value.
     *
     * @param string $value Selected language.
     * @return string
     */
    public function sanitize_language( $value ) {
        $allowed = array( 'es', 'en', 'pt' );
        return in_array( $value, $allowed, true ) ? $value : 'es';
    }

    /**
     * Sanitize a checkbox value.
     *
     * @param string $value Checkbox input.
     * @return string "1" or "0".
     */
    public function sanitize_checkbox( $value ) {
        return '1' === $value ? '1' : '0';
    }

    /**
     * Sanitize confidence threshold.
     *
     * @param mixed $value Input value.
     * @return float Clamped between 0 and 1.
     */
    public function sanitize_confidence_threshold( $value ) {
        $value = (float) $value;
        return max( 0.0, min( 1.0, $value ) );
    }

    /**
     * Render the settings page.
     */
    public function render_page() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            return;
        }
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'TaxoAI Settings', 'woocommerce-taxoai' ); ?></h1>

            <?php settings_errors(); ?>

            <?php $this->render_usage_widget(); ?>

            <form method="post" action="options.php">
                <?php
                settings_fields( 'taxoai_settings_group' );
                do_settings_sections( 'taxoai-settings' );
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    /**
     * Render the usage statistics widget.
     */
    private function render_usage_widget() {
        $usage = get_transient( TaxoAI_Usage_Tracker::CACHE_KEY );

        if ( ! is_array( $usage ) ) {
            // Try to fetch fresh data.
            $api_key = get_option( 'taxoai_api_key', '' );
            if ( ! empty( $api_key ) ) {
                $usage = $this->api_client->get_usage();
                if ( is_wp_error( $usage ) ) {
                    $usage = null;
                }
            }
        }

        if ( ! is_array( $usage ) ) {
            return;
        }

        $tier       = isset( $usage['tier'] ) ? $usage['tier'] : 'free';
        $used       = isset( $usage['products_used_this_month'] ) ? (int) $usage['products_used_this_month'] : 0;
        $limit      = isset( $usage['products_limit'] ) ? (int) $usage['products_limit'] : 25;
        $percentage = isset( $usage['percentage_used'] ) ? (float) $usage['percentage_used'] : 0;
        ?>
        <div class="taxoai-usage-widget" style="background:#fff;border:1px solid #ccd0d4;border-left:4px solid #2271b1;padding:16px 20px;margin-bottom:20px;">
            <h3 style="margin-top:0;">
                <?php esc_html_e( 'Usage This Month', 'woocommerce-taxoai' ); ?>
                <span style="font-weight:normal;text-transform:uppercase;background:#2271b1;color:#fff;padding:2px 8px;border-radius:3px;font-size:11px;margin-left:8px;">
                    <?php echo esc_html( $tier ); ?>
                </span>
            </h3>
            <div style="display:flex;align-items:center;gap:12px;">
                <div style="flex:1;background:#f0f0f1;border-radius:4px;height:20px;overflow:hidden;">
                    <div style="height:100%;background:<?php echo $percentage > 90 ? '#d63638' : '#2271b1'; ?>;width:<?php echo esc_attr( min( 100, $percentage ) ); ?>%;border-radius:4px;transition:width .3s;"></div>
                </div>
                <span>
                    <strong><?php echo esc_html( $used ); ?></strong> / <?php echo esc_html( $limit ); ?>
                    <?php esc_html_e( 'products', 'woocommerce-taxoai' ); ?>
                    (<?php echo esc_html( round( $percentage, 1 ) ); ?>%)
                </span>
            </div>
        </div>
        <?php
    }

    // ── Section descriptions ────────────────────────────────────────────

    /**
     * Render the API section description.
     */
    public function render_api_section() {
        echo '<p>' . esc_html__( 'Enter your TaxoAI API key and select your preferred language.', 'woocommerce-taxoai' ) . '</p>';
    }

    /**
     * Render the Automation section description.
     */
    public function render_automation_section() {
        echo '<p>' . esc_html__( 'Configure how TaxoAI automatically processes your products.', 'woocommerce-taxoai' ) . '</p>';
    }

    /**
     * Render the SEO section description.
     */
    public function render_seo_section() {
        echo '<p>' . esc_html__( 'Control how TaxoAI updates product SEO fields.', 'woocommerce-taxoai' ) . '</p>';
    }

    // ── Field renderers ────────────────────────────────────────────────

    /**
     * Render the API key field.
     */
    public function render_api_key_field() {
        $value = get_option( 'taxoai_api_key', '' );
        ?>
        <input type="password" id="taxoai_api_key" name="taxoai_api_key"
               value="<?php echo esc_attr( $value ); ?>"
               class="regular-text" autocomplete="off" />
        <p class="description">
            <?php
            printf(
                /* translators: %s: link to TaxoAI dashboard */
                esc_html__( 'Get your API key from %s.', 'woocommerce-taxoai' ),
                '<a href="https://app.taxoai.dev" target="_blank">app.taxoai.dev</a>'
            );
            ?>
        </p>
        <?php
    }

    /**
     * Render the language select field.
     */
    public function render_language_field() {
        $value = get_option( 'taxoai_language', 'es' );
        ?>
        <select id="taxoai_language" name="taxoai_language">
            <option value="es" <?php selected( $value, 'es' ); ?>><?php esc_html_e( 'Spanish', 'woocommerce-taxoai' ); ?></option>
            <option value="en" <?php selected( $value, 'en' ); ?>><?php esc_html_e( 'English', 'woocommerce-taxoai' ); ?></option>
            <option value="pt" <?php selected( $value, 'pt' ); ?>><?php esc_html_e( 'Portuguese', 'woocommerce-taxoai' ); ?></option>
        </select>
        <p class="description">
            <?php esc_html_e( 'Language used for product analysis and SEO content generation.', 'woocommerce-taxoai' ); ?>
        </p>
        <?php
    }

    /**
     * Render the auto-analyze checkbox.
     */
    public function render_auto_analyze_field() {
        $value = get_option( 'taxoai_auto_analyze', '0' );
        ?>
        <label>
            <input type="checkbox" id="taxoai_auto_analyze" name="taxoai_auto_analyze" value="1" <?php checked( $value, '1' ); ?> />
            <?php esc_html_e( 'Automatically analyze products when they are published or updated.', 'woocommerce-taxoai' ); ?>
        </label>
        <?php
    }

    /**
     * Render the confidence threshold field.
     */
    public function render_confidence_threshold_field() {
        $value = get_option( 'taxoai_confidence_threshold', '0.7' );
        ?>
        <input type="number" id="taxoai_confidence_threshold" name="taxoai_confidence_threshold"
               value="<?php echo esc_attr( $value ); ?>"
               min="0" max="1" step="0.05" class="small-text" />
        <p class="description">
            <?php esc_html_e( 'Minimum confidence score (0-1) required before auto-applying results. Default: 0.7', 'woocommerce-taxoai' ); ?>
        </p>
        <?php
    }

    /**
     * Render the auto-map categories checkbox.
     */
    public function render_auto_map_categories_field() {
        $value = get_option( 'taxoai_auto_map_categories', '0' );
        ?>
        <label>
            <input type="checkbox" id="taxoai_auto_map_categories" name="taxoai_auto_map_categories" value="1" <?php checked( $value, '1' ); ?> />
            <?php esc_html_e( 'Automatically create and assign WooCommerce product categories based on Google taxonomy.', 'woocommerce-taxoai' ); ?>
        </label>
        <?php
    }

    /**
     * Render the analyze images checkbox.
     */
    public function render_analyze_images_field() {
        $value = get_option( 'taxoai_analyze_images', '0' );
        ?>
        <label>
            <input type="checkbox" id="taxoai_analyze_images" name="taxoai_analyze_images" value="1" <?php checked( $value, '1' ); ?> />
            <?php esc_html_e( 'Send product images to TaxoAI for visual analysis (colors, materials, style).', 'woocommerce-taxoai' ); ?>
        </label>
        <?php
    }

    /**
     * Render the update title checkbox.
     */
    public function render_update_title_field() {
        $value = get_option( 'taxoai_update_title', '0' );
        ?>
        <label>
            <input type="checkbox" id="taxoai_update_title" name="taxoai_update_title" value="1" <?php checked( $value, '1' ); ?> />
            <?php esc_html_e( 'Replace product title with TaxoAI optimized title.', 'woocommerce-taxoai' ); ?>
        </label>
        <p class="description" style="color:#d63638;">
            <?php esc_html_e( 'Warning: This will overwrite your existing product titles.', 'woocommerce-taxoai' ); ?>
        </p>
        <?php
    }

    /**
     * Render the update description checkbox.
     */
    public function render_update_description_field() {
        $value = get_option( 'taxoai_update_description', '0' );
        ?>
        <label>
            <input type="checkbox" id="taxoai_update_description" name="taxoai_update_description" value="1" <?php checked( $value, '1' ); ?> />
            <?php esc_html_e( 'Replace product description with TaxoAI optimized description.', 'woocommerce-taxoai' ); ?>
        </label>
        <p class="description" style="color:#d63638;">
            <?php esc_html_e( 'Warning: This will overwrite your existing product descriptions.', 'woocommerce-taxoai' ); ?>
        </p>
        <?php
    }
}
