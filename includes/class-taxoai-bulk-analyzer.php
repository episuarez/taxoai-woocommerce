<?php
/**
 * TaxoAI Bulk Analyzer.
 *
 * @package TaxoAI
 */

defined( 'ABSPATH' ) || exit;

/**
 * Class TaxoAI_Bulk_Analyzer
 *
 * Admin page for bulk-analyzing WooCommerce products.
 */
class TaxoAI_Bulk_Analyzer {

    /**
     * Register the submenu page under WooCommerce.
     */
    public function register_menu() {
        add_submenu_page(
            'woocommerce',
            __( 'TaxoAI Bulk Analyzer', 'woocommerce-taxoai' ),
            __( 'TaxoAI Bulk', 'woocommerce-taxoai' ),
            'manage_woocommerce',
            'taxoai-bulk-analyzer',
            array( $this, 'render_page' )
        );
    }

    /**
     * Render the bulk analyzer page.
     */
    public function render_page() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
            return;
        }

        // Determine the current filter.
        $filter = isset( $_GET['filter'] ) ? sanitize_text_field( wp_unslash( $_GET['filter'] ) ) : 'all';
        $paged  = isset( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
        $per_page = 25;

        $products_query = $this->get_products_query( $filter, $paged, $per_page );
        $products       = $products_query->get_posts();
        $total          = $products_query->found_posts;
        $total_pages    = ceil( $total / $per_page );
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'TaxoAI Bulk Analyzer', 'woocommerce-taxoai' ); ?></h1>

            <div id="taxoai-bulk-progress" class="taxoai-bulk-progress" style="display:none;">
                <div class="taxoai-progress-bar-container" style="background:#f0f0f1;border-radius:4px;height:24px;overflow:hidden;margin-bottom:8px;">
                    <div id="taxoai-progress-bar" class="taxoai-progress-bar" style="height:100%;background:#2271b1;width:0%;border-radius:4px;transition:width .3s;"></div>
                </div>
                <p id="taxoai-progress-text"></p>
            </div>

            <ul class="subsubsub">
                <li>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=taxoai-bulk-analyzer&filter=all' ) ); ?>"
                       class="<?php echo 'all' === $filter ? 'current' : ''; ?>">
                        <?php esc_html_e( 'All', 'woocommerce-taxoai' ); ?>
                    </a> |
                </li>
                <li>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=taxoai-bulk-analyzer&filter=unanalyzed' ) ); ?>"
                       class="<?php echo 'unanalyzed' === $filter ? 'current' : ''; ?>">
                        <?php esc_html_e( 'Unanalyzed', 'woocommerce-taxoai' ); ?>
                    </a> |
                </li>
                <li>
                    <a href="<?php echo esc_url( admin_url( 'admin.php?page=taxoai-bulk-analyzer&filter=low-confidence' ) ); ?>"
                       class="<?php echo 'low-confidence' === $filter ? 'current' : ''; ?>">
                        <?php esc_html_e( 'Low Confidence', 'woocommerce-taxoai' ); ?>
                    </a>
                </li>
            </ul>

            <form id="taxoai-bulk-form" method="post">
                <div class="tablenav top">
                    <div class="alignleft actions bulkactions">
                        <button type="button" id="taxoai-bulk-analyze-btn" class="button action">
                            <?php esc_html_e( 'Analyze Selected', 'woocommerce-taxoai' ); ?>
                        </button>
                    </div>
                    <?php if ( $total_pages > 1 ) : ?>
                        <div class="tablenav-pages">
                            <?php
                            echo paginate_links( array(
                                'base'      => add_query_arg( 'paged', '%#%' ),
                                'format'    => '',
                                'prev_text' => '&laquo;',
                                'next_text' => '&raquo;',
                                'total'     => $total_pages,
                                'current'   => $paged,
                            ) );
                            ?>
                        </div>
                    <?php endif; ?>
                </div>

                <table class="wp-list-table widefat fixed striped" id="taxoai-bulk-table">
                    <thead>
                        <tr>
                            <td class="manage-column column-cb check-column">
                                <input type="checkbox" id="taxoai-select-all" />
                            </td>
                            <th class="manage-column"><?php esc_html_e( 'Product', 'woocommerce-taxoai' ); ?></th>
                            <th class="manage-column"><?php esc_html_e( 'Status', 'woocommerce-taxoai' ); ?></th>
                            <th class="manage-column"><?php esc_html_e( 'Google Category', 'woocommerce-taxoai' ); ?></th>
                            <th class="manage-column"><?php esc_html_e( 'Confidence', 'woocommerce-taxoai' ); ?></th>
                            <th class="manage-column"><?php esc_html_e( 'Last Analyzed', 'woocommerce-taxoai' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ( empty( $products ) ) : ?>
                            <tr>
                                <td colspan="6"><?php esc_html_e( 'No products found.', 'woocommerce-taxoai' ); ?></td>
                            </tr>
                        <?php else : ?>
                            <?php foreach ( $products as $product_post ) : ?>
                                <?php $this->render_row( $product_post ); ?>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </form>
        </div>
        <?php
    }

    /**
     * Render a single product row.
     *
     * @param WP_Post $product_post Product post object.
     */
    private function render_row( $product_post ) {
        $id         = $product_post->ID;
        $analyzed   = get_post_meta( $id, '_taxoai_analyzed_at', true );
        $confidence = get_post_meta( $id, '_taxoai_confidence', true );
        $category   = get_post_meta( $id, '_taxoai_google_category', true );
        $threshold  = (float) get_option( 'taxoai_confidence_threshold', 0.7 );

        // Determine status.
        if ( empty( $analyzed ) ) {
            $status_label = __( 'Pending', 'woocommerce-taxoai' );
            $status_class = 'taxoai-status-pending';
        } elseif ( $confidence !== '' && (float) $confidence < $threshold ) {
            $status_label = __( 'Low Confidence', 'woocommerce-taxoai' );
            $status_class = 'taxoai-status-low';
        } else {
            $status_label = __( 'Analyzed', 'woocommerce-taxoai' );
            $status_class = 'taxoai-status-analyzed';
        }

        $confidence_display = '';
        if ( $confidence !== '' && $confidence !== false ) {
            $confidence_display = round( (float) $confidence * 100 ) . '%';
        }
        ?>
        <tr data-product-id="<?php echo esc_attr( $id ); ?>">
            <th class="check-column">
                <input type="checkbox" name="product_ids[]" value="<?php echo esc_attr( $id ); ?>" class="taxoai-product-checkbox" />
            </th>
            <td>
                <a href="<?php echo esc_url( get_edit_post_link( $id ) ); ?>">
                    <?php echo esc_html( $product_post->post_title ); ?>
                </a>
            </td>
            <td>
                <span class="taxoai-status <?php echo esc_attr( $status_class ); ?>">
                    <?php echo esc_html( $status_label ); ?>
                </span>
            </td>
            <td><?php echo esc_html( $category ); ?></td>
            <td>
                <?php if ( $confidence_display ) : ?>
                    <span class="taxoai-confidence-badge <?php echo esc_attr( $this->get_confidence_class( (float) $confidence ) ); ?>">
                        <?php echo esc_html( $confidence_display ); ?>
                    </span>
                <?php else : ?>
                    &mdash;
                <?php endif; ?>
            </td>
            <td><?php echo $analyzed ? esc_html( $analyzed ) : '&mdash;'; ?></td>
        </tr>
        <?php
    }

    /**
     * Build a WP_Query for the products table.
     *
     * @param string $filter   Filter type: all, unanalyzed, low-confidence.
     * @param int    $paged    Current page.
     * @param int    $per_page Items per page.
     * @return WP_Query
     */
    private function get_products_query( $filter, $paged, $per_page ) {
        $args = array(
            'post_type'      => 'product',
            'post_status'    => 'publish',
            'posts_per_page' => $per_page,
            'paged'          => $paged,
            'orderby'        => 'title',
            'order'          => 'ASC',
        );

        if ( 'unanalyzed' === $filter ) {
            $args['meta_query'] = array(
                array(
                    'key'     => '_taxoai_analyzed_at',
                    'compare' => 'NOT EXISTS',
                ),
            );
        } elseif ( 'low-confidence' === $filter ) {
            $threshold = (float) get_option( 'taxoai_confidence_threshold', 0.7 );
            $args['meta_query'] = array(
                'relation' => 'AND',
                array(
                    'key'     => '_taxoai_confidence',
                    'compare' => 'EXISTS',
                ),
                array(
                    'key'     => '_taxoai_confidence',
                    'value'   => $threshold,
                    'type'    => 'DECIMAL(3,2)',
                    'compare' => '<',
                ),
            );
        }

        return new WP_Query( $args );
    }

    /**
     * Get CSS class for a confidence value.
     *
     * @param float $confidence Confidence score.
     * @return string CSS class.
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
