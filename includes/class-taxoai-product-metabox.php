<?php
/**
 * TaxoAI Product Metabox.
 *
 * @package TaxoAI
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class TaxoAI_Product_Metabox
 *
 * Renders the "TaxoAI Analysis" meta box on the product edit screen.
 */
class TaxoAI_Product_Metabox {

    /**
     * Register the meta box.
     */
    public function register_metabox() {
        add_meta_box(
            'taxoai-analysis',
            __( 'TaxoAI Analysis', 'woocommerce-taxoai' ),
            array( $this, 'render' ),
            'product',
            'side',
            'default'
        );
    }

    /**
     * Render the meta box content.
     *
     * @param WP_Post $post Current post object.
     */
    public function render( $post ) {
        $result     = get_post_meta( $post->ID, '_taxoai_analysis_result', true );
        $analyzed   = get_post_meta( $post->ID, '_taxoai_analyzed_at', true );
        $confidence = get_post_meta( $post->ID, '_taxoai_confidence', true );
        $category   = get_post_meta( $post->ID, '_taxoai_google_category', true );

        wp_nonce_field( 'taxoai_metabox_nonce', 'taxoai_metabox_nonce_field' );
        ?>
        <div id="taxoai-metabox-wrapper" data-product-id="<?php echo esc_attr( $post->ID ); ?>">

            <?php if ( ! empty( $result ) && is_array( $result ) ) : ?>
                <?php $this->render_classification( $result ); ?>
                <?php $this->render_seo( $result ); ?>
                <?php $this->render_attributes( $result ); ?>
                <?php $this->render_image_analysis( $result ); ?>
                <?php $this->render_keywords( $result ); ?>

                <?php if ( ! empty( $analyzed ) ) : ?>
                    <p class="taxoai-timestamp">
                        <em>
                            <?php
                            printf(
                                /* translators: %s: date/time string */
                                esc_html__( 'Last analyzed: %s', 'woocommerce-taxoai' ),
                                esc_html( $analyzed )
                            );
                            ?>
                        </em>
                        <?php if ( isset( $result['processing_time_ms'] ) ) : ?>
                            <span class="taxoai-processing-time">
                                <?php echo esc_html( $result['processing_time_ms'] ); ?>ms
                            </span>
                        <?php endif; ?>
                        <?php if ( ! empty( $result['cached'] ) ) : ?>
                            <span class="taxoai-cached-badge"><?php esc_html_e( 'Cached', 'woocommerce-taxoai' ); ?></span>
                        <?php endif; ?>
                    </p>
                <?php endif; ?>
            <?php else : ?>
                <p class="taxoai-empty">
                    <?php esc_html_e( 'This product has not been analyzed yet.', 'woocommerce-taxoai' ); ?>
                </p>
            <?php endif; ?>

            <div id="taxoai-metabox-results"></div>

            <div class="taxoai-actions" style="margin-top:12px;">
                <button type="button" id="taxoai-analyze-btn" class="button button-primary" style="width:100%;">
                    <?php esc_html_e( 'Analyze Now', 'woocommerce-taxoai' ); ?>
                </button>
                <div id="taxoai-loading" class="taxoai-loading" style="display:none;">
                    <span class="spinner is-active" style="float:none;margin:0 8px 0 0;"></span>
                    <?php esc_html_e( 'Analyzing...', 'woocommerce-taxoai' ); ?>
                </div>
                <div id="taxoai-error" class="taxoai-error" style="display:none;"></div>
            </div>

            <hr style="margin:12px 0;" />

            <div class="taxoai-taxonomy-search">
                <label for="taxoai-taxonomy-query">
                    <strong><?php esc_html_e( 'Search Google Categories', 'woocommerce-taxoai' ); ?></strong>
                </label>
                <input type="text" id="taxoai-taxonomy-query"
                       placeholder="<?php esc_attr_e( 'Search Google categories...', 'woocommerce-taxoai' ); ?>"
                       class="widefat" style="margin-top:4px;" />
                <div id="taxoai-taxonomy-results" class="taxoai-taxonomy-results" style="margin-top:8px;"></div>
            </div>
        </div>
        <?php
    }

    /**
     * Render classification section.
     *
     * @param array $result Analysis result.
     */
    private function render_classification( array $result ) {
        if ( ! isset( $result['classification'] ) ) {
            return;
        }

        $classification = $result['classification'];
        $category       = isset( $classification['google_category'] ) ? $classification['google_category'] : '';
        $category_id    = isset( $classification['google_category_id'] ) ? $classification['google_category_id'] : '';
        $confidence     = isset( $classification['confidence'] ) ? (float) $classification['confidence'] : 0;
        $badge_class    = $this->get_confidence_class( $confidence );
        ?>
        <div class="taxoai-section taxoai-classification">
            <strong><?php esc_html_e( 'Google Category', 'woocommerce-taxoai' ); ?></strong>
            <p style="margin:4px 0;">
                <?php echo esc_html( $category ); ?>
                <?php if ( $category_id ) : ?>
                    <small>(ID: <?php echo esc_html( $category_id ); ?>)</small>
                <?php endif; ?>
            </p>
            <span class="taxoai-confidence-badge <?php echo esc_attr( $badge_class ); ?>">
                <?php
                printf(
                    /* translators: %s: confidence percentage */
                    esc_html__( 'Confidence: %s%%', 'woocommerce-taxoai' ),
                    esc_html( round( $confidence * 100 ) )
                );
                ?>
            </span>
        </div>
        <?php
    }

    /**
     * Render SEO section.
     *
     * @param array $result Analysis result.
     */
    private function render_seo( array $result ) {
        if ( ! isset( $result['seo'] ) ) {
            return;
        }

        $seo = $result['seo'];
        ?>
        <div class="taxoai-section taxoai-seo">
            <strong><?php esc_html_e( 'SEO', 'woocommerce-taxoai' ); ?></strong>

            <?php if ( ! empty( $seo['meta_title'] ) ) : ?>
                <p class="taxoai-seo-item">
                    <span class="taxoai-label"><?php esc_html_e( 'Meta Title:', 'woocommerce-taxoai' ); ?></span>
                    <?php echo esc_html( $seo['meta_title'] ); ?>
                </p>
            <?php endif; ?>

            <?php if ( ! empty( $seo['meta_description'] ) ) : ?>
                <p class="taxoai-seo-item">
                    <span class="taxoai-label"><?php esc_html_e( 'Meta Description:', 'woocommerce-taxoai' ); ?></span>
                    <?php echo esc_html( $seo['meta_description'] ); ?>
                </p>
            <?php endif; ?>

            <?php if ( ! empty( $seo['optimized_title'] ) ) : ?>
                <p class="taxoai-seo-item">
                    <span class="taxoai-label"><?php esc_html_e( 'Optimized Title:', 'woocommerce-taxoai' ); ?></span>
                    <?php echo esc_html( $seo['optimized_title'] ); ?>
                </p>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Render attributes section.
     *
     * @param array $result Analysis result.
     */
    private function render_attributes( array $result ) {
        if ( ! isset( $result['attributes'] ) || empty( $result['attributes'] ) ) {
            return;
        }

        $attrs = $result['attributes'];
        ?>
        <div class="taxoai-section taxoai-attributes">
            <strong><?php esc_html_e( 'Attributes', 'woocommerce-taxoai' ); ?></strong>
            <div class="taxoai-pills" style="margin-top:4px;">
                <?php if ( ! empty( $attrs['color'] ) && is_array( $attrs['color'] ) ) : ?>
                    <?php foreach ( $attrs['color'] as $color ) : ?>
                        <span class="taxoai-pill taxoai-pill-color"><?php echo esc_html( $color ); ?></span>
                    <?php endforeach; ?>
                <?php endif; ?>

                <?php if ( ! empty( $attrs['material'] ) ) : ?>
                    <span class="taxoai-pill taxoai-pill-material"><?php echo esc_html( $attrs['material'] ); ?></span>
                <?php endif; ?>

                <?php if ( ! empty( $attrs['gender'] ) ) : ?>
                    <span class="taxoai-pill taxoai-pill-gender"><?php echo esc_html( $attrs['gender'] ); ?></span>
                <?php endif; ?>

                <?php if ( ! empty( $attrs['style'] ) ) : ?>
                    <span class="taxoai-pill taxoai-pill-style"><?php echo esc_html( $attrs['style'] ); ?></span>
                <?php endif; ?>
            </div>
        </div>
        <?php
    }

    /**
     * Render image analysis section.
     *
     * @param array $result Analysis result.
     */
    private function render_image_analysis( array $result ) {
        if ( ! isset( $result['image_analysis'] ) || empty( $result['image_analysis'] ) ) {
            return;
        }

        $img = $result['image_analysis'];
        ?>
        <div class="taxoai-section taxoai-image-analysis">
            <strong><?php esc_html_e( 'Image Analysis', 'woocommerce-taxoai' ); ?></strong>

            <?php if ( ! empty( $img['detected_colors'] ) && is_array( $img['detected_colors'] ) ) : ?>
                <p class="taxoai-seo-item">
                    <span class="taxoai-label"><?php esc_html_e( 'Colors:', 'woocommerce-taxoai' ); ?></span>
                    <?php echo esc_html( implode( ', ', $img['detected_colors'] ) ); ?>
                </p>
            <?php endif; ?>

            <?php if ( ! empty( $img['detected_material'] ) ) : ?>
                <p class="taxoai-seo-item">
                    <span class="taxoai-label"><?php esc_html_e( 'Material:', 'woocommerce-taxoai' ); ?></span>
                    <?php echo esc_html( $img['detected_material'] ); ?>
                </p>
            <?php endif; ?>

            <?php if ( ! empty( $img['detected_style'] ) ) : ?>
                <p class="taxoai-seo-item">
                    <span class="taxoai-label"><?php esc_html_e( 'Style:', 'woocommerce-taxoai' ); ?></span>
                    <?php echo esc_html( $img['detected_style'] ); ?>
                </p>
            <?php endif; ?>

            <?php if ( ! empty( $img['background_quality'] ) ) : ?>
                <?php
                $quality       = $img['background_quality'];
                $quality_lower = strtolower( $quality );
                $is_poor       = in_array( $quality_lower, array( 'poor', 'low', 'bad' ), true );
                ?>
                <p class="taxoai-seo-item <?php echo $is_poor ? 'taxoai-bg-warning' : ''; ?>">
                    <span class="taxoai-label"><?php esc_html_e( 'Background Quality:', 'woocommerce-taxoai' ); ?></span>
                    <?php echo esc_html( $quality ); ?>
                    <?php if ( $is_poor ) : ?>
                        <span class="dashicons dashicons-warning" style="color:#dba617;"></span>
                    <?php endif; ?>
                </p>
            <?php endif; ?>
        </div>
        <?php
    }

    /**
     * Render keywords section.
     *
     * @param array $result Analysis result.
     */
    private function render_keywords( array $result ) {
        if ( ! isset( $result['seo']['keywords'] ) || empty( $result['seo']['keywords'] ) ) {
            return;
        }

        $keywords = $result['seo']['keywords'];
        ?>
        <div class="taxoai-section taxoai-keywords">
            <strong><?php esc_html_e( 'Keywords', 'woocommerce-taxoai' ); ?></strong>
            <ul class="taxoai-keyword-list" style="margin:4px 0 0;padding:0;list-style:none;">
                <?php foreach ( $keywords as $kw ) : ?>
                    <?php
                    $keyword = isset( $kw['keyword'] ) ? $kw['keyword'] : '';
                    $volume  = isset( $kw['volume'] ) ? $kw['volume'] : '';
                    ?>
                    <li class="taxoai-keyword-item">
                        <span class="taxoai-keyword-text"><?php echo esc_html( $keyword ); ?></span>
                        <?php if ( ! empty( $volume ) ) : ?>
                            <span class="taxoai-volume-indicator taxoai-volume-<?php echo esc_attr( $volume ); ?>">
                                <?php echo esc_html( $volume ); ?>
                            </span>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php
    }

    /**
     * Determine the confidence CSS class.
     *
     * @param float $confidence Confidence score (0-1).
     * @return string CSS class name.
     */
    private function get_confidence_class( $confidence ) {
        if ( $confidence >= 0.8 ) {
            return 'taxoai-confidence-high';
        }
        if ( $confidence >= 0.5 ) {
            return 'taxoai-confidence-medium';
        }
        return 'taxoai-confidence-low';
    }

}
