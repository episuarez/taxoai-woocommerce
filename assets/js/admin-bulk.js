/**
 * TaxoAI Bulk Analyzer JavaScript.
 *
 * Handles product selection, batch submission, and job polling on the bulk
 * analysis admin page.
 *
 * @package TaxoAI
 */

/* global jQuery, taxoai_bulk */
(function ($) {
    'use strict';

    var $selectAll,
        $analyzeBtn,
        $progressWrap,
        $progressBar,
        $progressText,
        pollInterval = null;

    /**
     * Initialise DOM references and bind events.
     */
    function init() {
        $selectAll    = $('#taxoai-select-all');
        $analyzeBtn   = $('#taxoai-bulk-analyze-btn');
        $progressWrap = $('#taxoai-bulk-progress');
        $progressBar  = $('#taxoai-progress-bar');
        $progressText = $('#taxoai-progress-text');

        // Select all / none.
        $selectAll.on('change', function () {
            var checked = $(this).is(':checked');
            $('.taxoai-product-checkbox').prop('checked', checked);
        });

        // Analyze selected.
        $analyzeBtn.on('click', handleBulkAnalyze);
    }

    /**
     * Gather selected product IDs and submit a batch job.
     */
    function handleBulkAnalyze() {
        var ids = [];
        $('.taxoai-product-checkbox:checked').each(function () {
            ids.push($(this).val());
        });

        if (!ids.length) {
            alert(taxoai_bulk.i18n.no_selection);
            return;
        }

        if (!confirm(taxoai_bulk.i18n.confirm_bulk)) {
            return;
        }

        $analyzeBtn.prop('disabled', true).text(taxoai_bulk.i18n.processing);
        showProgress(0, ids.length);

        $.ajax({
            url:      taxoai_bulk.ajax_url,
            type:     'POST',
            dataType: 'json',
            data: {
                action:      'taxoai_bulk_analyze',
                nonce:       taxoai_bulk.nonce,
                product_ids: ids
            },
            success: function (response) {
                if (response.success && response.data && response.data.job_id) {
                    startPolling(response.data.job_id, response.data.total_products || ids.length);
                } else {
                    var msg = response.data && response.data.message
                        ? response.data.message
                        : taxoai_bulk.i18n.failed;
                    alert(msg);
                    resetUI();
                }
            },
            error: function () {
                alert(taxoai_bulk.i18n.failed);
                resetUI();
            }
        });
    }

    /**
     * Begin polling a batch job for progress.
     *
     * @param {string} jobId        Batch job identifier.
     * @param {number} totalProducts Total products in batch.
     */
    function startPolling(jobId, totalProducts) {
        $progressText.text(taxoai_bulk.i18n.polling);

        pollInterval = setInterval(function () {
            $.ajax({
                url:      taxoai_bulk.ajax_url,
                type:     'GET',
                dataType: 'json',
                data: {
                    action: 'taxoai_poll_job',
                    nonce:  taxoai_bulk.nonce,
                    job_id: jobId
                },
                success: function (response) {
                    if (!response.success) {
                        clearInterval(pollInterval);
                        alert(taxoai_bulk.i18n.failed);
                        resetUI();
                        return;
                    }

                    var data      = response.data;
                    var processed = data.processed_products || 0;
                    var total     = data.total_products || totalProducts;

                    updateProgress(processed, total);

                    if ('completed' === data.status || 'failed' === data.status) {
                        clearInterval(pollInterval);
                        pollInterval = null;

                        if ('completed' === data.status) {
                            $progressText.text(taxoai_bulk.i18n.completed + ' (' + processed + '/' + total + ')');
                            // Reload after a short delay so the user sees the completed state.
                            setTimeout(function () {
                                window.location.reload();
                            }, 1500);
                        } else {
                            $progressText.text(taxoai_bulk.i18n.failed);
                            resetUI();
                        }
                    }
                },
                error: function () {
                    clearInterval(pollInterval);
                    pollInterval = null;
                    alert(taxoai_bulk.i18n.failed);
                    resetUI();
                }
            });
        }, 3000); // Poll every 3 seconds.
    }

    /**
     * Show the progress bar.
     *
     * @param {number} processed Number of processed products.
     * @param {number} total     Total products.
     */
    function showProgress(processed, total) {
        $progressWrap.show();
        updateProgress(processed, total);
    }

    /**
     * Update the progress bar.
     *
     * @param {number} processed Number of processed products.
     * @param {number} total     Total products.
     */
    function updateProgress(processed, total) {
        var pct = total > 0 ? Math.round((processed / total) * 100) : 0;
        $progressBar.css('width', pct + '%');
        $progressText.text(processed + ' / ' + total + ' (' + pct + '%)');
    }

    /**
     * Reset the UI to its idle state.
     */
    function resetUI() {
        $analyzeBtn.prop('disabled', false).text(taxoai_bulk.i18n.processing.replace('...', ''));
        // Reset button text to original (best-effort).
        $analyzeBtn.text('Analyze Selected');
    }

    // Initialise on DOM ready.
    $(document).ready(init);

})(jQuery);
