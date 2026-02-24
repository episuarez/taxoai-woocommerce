=== TaxoAI for WooCommerce ===
Contributors: taxoai
Tags: woocommerce, product categorization, google taxonomy, seo, ai
Requires at least: 5.8
Tested up to: 6.7
Requires PHP: 7.4
WC requires at least: 5.0
WC tested up to: 9.0
Stable tag: 1.0.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Auto-categorize WooCommerce products using the TaxoAI API. Generates Google taxonomy classifications, SEO content, and product attributes powered by AI.

== Description ==

**TaxoAI for WooCommerce** automatically analyzes your WooCommerce products and:

* **Classifies products** using the Google Product Taxonomy with confidence scores
* **Generates SEO content** including optimized titles, meta descriptions, and keywords with search volume data
* **Detects product attributes** such as color, material, gender, and style
* **Analyzes product images** to detect visual attributes and background quality
* **Integrates with SEO plugins** (Yoast SEO and Rank Math) or stores SEO data in its own meta fields
* **Maps categories** to WooCommerce product categories automatically
* **Creates product attributes** as global WooCommerce attributes (pa_color, pa_material, etc.)
* **Bulk analysis** for processing multiple products at once with progress tracking
* **Google Shopping feed compatible** - stores `_google_product_category` and `_google_product_category_id` meta

= How It Works =

1. Install the plugin and enter your TaxoAI API key
2. Products are analyzed individually or in bulk
3. The AI classifies each product, generates SEO content, and detects attributes
4. Results are stored and optionally auto-applied based on your confidence threshold

= Features =

* **Single product analysis** via the metabox on the product edit screen
* **Bulk analysis** with batch processing and progress polling
* **Auto-analyze on publish** - optionally analyze products when they are saved
* **Confidence threshold** - only auto-apply results above your chosen confidence level
* **Multi-language support** - analyze in Spanish, English, or Portuguese
* **Image analysis** - optional visual attribute detection from product photos
* **Taxonomy search** - search the Google Product Taxonomy from the product editor
* **Usage tracking** - monitor your API usage with server-side verification

== Installation ==

1. Upload the `woocommerce-taxoai` folder to `/wp-content/plugins/`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Go to **WooCommerce > TaxoAI** to configure your API key and settings
4. Get your API key from [app.taxoai.dev](https://app.taxoai.dev)

= Requirements =

* WordPress 5.8 or higher
* WooCommerce 5.0 or higher
* PHP 7.4 or higher
* A TaxoAI API key (free tier includes 25 products/month)

== Frequently Asked Questions ==

= Where do I get an API key? =

Sign up at [app.taxoai.dev](https://app.taxoai.dev) to get your free API key. The free tier includes 25 product analyses per month.

= Does this plugin work with Yoast SEO? =

Yes. When Yoast SEO is active, TaxoAI writes to the standard Yoast meta fields (`_yoast_wpseo_title`, `_yoast_wpseo_metadesc`, `_yoast_wpseo_focuskw`).

= Does this plugin work with Rank Math? =

Yes. When Rank Math is active, TaxoAI writes to the standard Rank Math meta fields (`rank_math_title`, `rank_math_description`, `rank_math_focus_keyword`).

= Will this overwrite my existing product data? =

By default, TaxoAI does **not** overwrite product titles or descriptions. You can enable this in Settings if desired. SEO meta fields and attributes are always updated when analysis results meet your confidence threshold.

= What happens when I reach the free tier limit? =

The plugin checks your usage against the TaxoAI server before every analysis. If you have reached 25 products for the month on the free tier, analysis requests will be blocked. Upgrade your plan at [app.taxoai.dev](https://app.taxoai.dev) for higher limits.

= Is my data sent to external servers? =

Yes. Product names, descriptions, prices, and image URLs are sent to the TaxoAI API (`api.taxoai.dev`) for analysis. No customer data or order information is sent.

== Changelog ==

= 1.0.0 =
* Initial release
* Single product analysis with metabox UI
* Bulk product analysis with batch processing
* Google Product Taxonomy classification
* SEO content generation (titles, descriptions, keywords)
* Attribute detection (color, material, gender, style)
* Image analysis support
* Yoast SEO and Rank Math integration
* Auto-category mapping
* Usage tracking with server-side verification
* Taxonomy search widget
* Product list column with status indicators

== Upgrade Notice ==

= 1.0.0 =
Initial release of TaxoAI for WooCommerce.
