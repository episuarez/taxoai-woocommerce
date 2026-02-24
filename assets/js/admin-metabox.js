/**
 * TaxoAI Admin Metabox JavaScript.
 *
 * Handles single-product analysis and taxonomy search on the product edit screen.
 *
 * @package TaxoAI
 */

/* global jQuery, taxoai_metabox */
(function ($) {
    'use strict';

    var $wrapper,
        $analyzeBtn,
        $loading,
        $errorDiv,
        $resultsDiv,
        $taxonomyInput,
        $taxonomyResults,
        searchTimer = null;

    /**
     * Initialise DOM references and bind events.
     */
    function init() {
        $wrapper          = $('#taxoai-metabox-wrapper');
        $analyzeBtn       = $('#taxoai-analyze-btn');
        $loading          = $('#taxoai-loading');
        $errorDiv         = $('#taxoai-error');
        $resultsDiv       = $('#taxoai-metabox-results');
        $taxonomyInput    = $('#taxoai-taxonomy-query');
        $taxonomyResults  = $('#taxoai-taxonomy-results');

        // Analyze button.
        $analyzeBtn.on('click', handleAnalyze);

        // Taxonomy search with debounce.
        $taxonomyInput.on('input', function () {
            clearTimeout(searchTimer);
            searchTimer = setTimeout(handleTaxonomySearch, 400);
        });

        // Quick-analyze links on the product list table.
        $(document).on('click', '.taxoai-quick-analyze', function (e) {
            e.preventDefault();
            var productId = $(this).data('product-id');
            if (productId) {
                analyzeProduct(productId, $(this));
            }
        });
    }

    /**
     * Handle the "Analyze Now" button click.
     */
    function handleAnalyze() {
        var productId = $wrapper.data('product-id');
        if (!productId) {
            showError(taxoai_metabox.i18n.error);
            return;
        }
        analyzeProduct(productId);
    }

    /**
     * Send an AJAX request to analyze a product.
     *
     * @param {number}      productId  WooCommerce product ID.
     * @param {jQuery|null} $trigger   Optional trigger element for inline feedback.
     */
    function analyzeProduct(productId, $trigger) {
        hideError();

        if ($trigger) {
            $trigger.text(taxoai_metabox.i18n.analyzing);
            $trigger.prop('disabled', true);
        } else {
            $analyzeBtn.prop('disabled', true).text(taxoai_metabox.i18n.analyzing);
            $loading.show();
        }

        $.ajax({
            url:      taxoai_metabox.ajax_url,
            type:     'POST',
            dataType: 'json',
            data: {
                action:     'taxoai_analyze_product',
                nonce:      taxoai_metabox.nonce,
                product_id: productId
            },
            success: function (response) {
                if (response.success) {
                    if ($trigger) {
                        // On the list page, reload to update columns.
                        window.location.reload();
                    } else {
                        renderResult(response.data);
                    }
                } else {
                    var msg = response.data && response.data.message
                        ? response.data.message
                        : taxoai_metabox.i18n.error;
                    showError(msg);
                }
            },
            error: function () {
                showError(taxoai_metabox.i18n.error);
            },
            complete: function () {
                if ($trigger) {
                    $trigger.prop('disabled', false).text(taxoai_metabox.i18n.analyze_now);
                } else {
                    $analyzeBtn.prop('disabled', false).text(taxoai_metabox.i18n.analyze_now);
                    $loading.hide();
                }
            }
        });
    }

    /**
     * Render the analysis result inside the metabox.
     *
     * @param {Object} data Response data.
     */
    function renderResult(data) {
        var result = data.result || {};
        var html   = '';

        // Classification.
        if (result.classification) {
            var cls        = result.classification;
            var confidence = cls.confidence || 0;
            var badgeClass = confidence >= 0.8 ? 'taxoai-confidence-high'
                           : confidence >= 0.5 ? 'taxoai-confidence-medium'
                           : 'taxoai-confidence-low';

            html += '<div class="taxoai-section taxoai-classification">';
            html += '<strong>Google Category</strong>';
            html += '<p style="margin:4px 0;">' + escHtml(cls.google_category || '');
            if (cls.google_category_id) {
                html += ' <small>(ID: ' + escHtml(String(cls.google_category_id)) + ')</small>';
            }
            html += '</p>';
            html += '<span class="taxoai-confidence-badge ' + badgeClass + '">';
            html += 'Confidence: ' + Math.round(confidence * 100) + '%';
            html += '</span></div>';
        }

        // SEO.
        if (result.seo) {
            html += '<div class="taxoai-section taxoai-seo">';
            html += '<strong>SEO</strong>';
            if (result.seo.meta_title) {
                html += '<p class="taxoai-seo-item"><span class="taxoai-label">Meta Title:</span> ' + escHtml(result.seo.meta_title) + '</p>';
            }
            if (result.seo.meta_description) {
                html += '<p class="taxoai-seo-item"><span class="taxoai-label">Meta Description:</span> ' + escHtml(result.seo.meta_description) + '</p>';
            }
            html += '</div>';
        }

        // Attributes.
        if (result.attributes) {
            var attrs = result.attributes;
            html += '<div class="taxoai-section taxoai-attributes"><strong>Attributes</strong><div class="taxoai-pills" style="margin-top:4px;">';
            if (attrs.color && Array.isArray(attrs.color)) {
                attrs.color.forEach(function (c) {
                    html += '<span class="taxoai-pill taxoai-pill-color">' + escHtml(c) + '</span>';
                });
            }
            if (attrs.material) {
                html += '<span class="taxoai-pill taxoai-pill-material">' + escHtml(attrs.material) + '</span>';
            }
            if (attrs.gender) {
                html += '<span class="taxoai-pill taxoai-pill-gender">' + escHtml(attrs.gender) + '</span>';
            }
            if (attrs.style) {
                html += '<span class="taxoai-pill taxoai-pill-style">' + escHtml(attrs.style) + '</span>';
            }
            html += '</div></div>';
        }

        // Keywords.
        if (result.seo && result.seo.keywords && result.seo.keywords.length) {
            html += '<div class="taxoai-section taxoai-keywords"><strong>Keywords</strong>';
            html += '<ul class="taxoai-keyword-list" style="margin:4px 0 0;padding:0;list-style:none;">';
            result.seo.keywords.forEach(function (kw) {
                var vol   = kw.volume || 0;
                var level = vol >= 10000 ? 'high' : (vol >= 1000 ? 'medium' : 'low');
                var volDisplay = vol >= 1000 ? (Math.round(vol / 100) / 10) + 'K' : String(vol);
                html += '<li class="taxoai-keyword-item">';
                html += '<span class="taxoai-keyword-text">' + escHtml(kw.keyword || '') + '</span>';
                html += '<span class="taxoai-volume-indicator taxoai-volume-' + level + '" title="' + vol + ' searches/mo">' + volDisplay + '</span>';
                html += '</li>';
            });
            html += '</ul></div>';
        }

        // Timestamp.
        if (data.analyzed_at) {
            html += '<p class="taxoai-timestamp"><em>Last analyzed: ' + escHtml(data.analyzed_at) + '</em></p>';
        }

        $resultsDiv.html(html);
    }

    /**
     * Handle the taxonomy search input.
     */
    function handleTaxonomySearch() {
        var query = $.trim($taxonomyInput.val());
        if (query.length < 2) {
            $taxonomyResults.empty();
            return;
        }

        $taxonomyResults.html('<span class="spinner is-active" style="float:none;"></span>');

        $.ajax({
            url:      taxoai_metabox.ajax_url,
            type:     'GET',
            dataType: 'json',
            data: {
                action: 'taxoai_search_taxonomy',
                nonce:  taxoai_metabox.nonce,
                query:  query
            },
            success: function (response) {
                if (response.success && response.data && response.data.categories) {
                    renderTaxonomyResults(response.data.categories);
                } else {
                    $taxonomyResults.html('<p class="description">' + taxoai_metabox.i18n.error + '</p>');
                }
            },
            error: function () {
                $taxonomyResults.html('<p class="description">' + taxoai_metabox.i18n.error + '</p>');
            }
        });
    }

    /**
     * Render taxonomy search results.
     *
     * @param {Array} categories Array of category objects.
     */
    function renderTaxonomyResults(categories) {
        if (!categories.length) {
            $taxonomyResults.html('<p class="description">No categories found.</p>');
            return;
        }

        var html = '<ul class="taxoai-taxonomy-list" style="margin:0;padding:0;list-style:none;">';
        categories.forEach(function (cat) {
            html += '<li class="taxoai-taxonomy-item" style="padding:4px 0;border-bottom:1px solid #f0f0f1;">';
            html += '<strong>' + escHtml(cat.full_path || '') + '</strong>';
            if (cat.id) {
                html += ' <small>(ID: ' + escHtml(String(cat.id)) + ')</small>';
            }
            if (cat.relevance) {
                html += ' <span class="taxoai-relevance" style="color:#999;font-size:11px;">relevance: ' + escHtml(String(Math.round(cat.relevance * 100))) + '%</span>';
            }
            html += '</li>';
        });
        html += '</ul>';
        $taxonomyResults.html(html);
    }

    /**
     * Show an error message.
     *
     * @param {string} msg Error message.
     */
    function showError(msg) {
        $errorDiv.html('<p style="color:#d63638;">' + escHtml(msg) + '</p>').show();
    }

    /**
     * Hide the error message.
     */
    function hideError() {
        $errorDiv.hide().empty();
    }

    /**
     * Simple HTML escaping.
     *
     * @param {string} str Raw string.
     * @return {string} Escaped string.
     */
    function escHtml(str) {
        var div = document.createElement('div');
        div.appendChild(document.createTextNode(str));
        return div.innerHTML;
    }

    // Initialise on DOM ready.
    $(document).ready(init);

})(jQuery);
