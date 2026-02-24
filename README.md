# TaxoAI for WooCommerce

Auto-categorize WooCommerce products and generate SEO content using AI.

[![CI](https://github.com/episuarez/taxoai-woocommerce/actions/workflows/ci.yml/badge.svg)](https://github.com/episuarez/taxoai-woocommerce/actions/workflows/ci.yml)
[![License: GPL v2](https://img.shields.io/badge/License-GPL_v2-blue.svg)](https://www.gnu.org/licenses/gpl-2.0)

---

## What it does

TaxoAI analyzes your WooCommerce products and automatically:

- **Classifies products** into the Google Product Taxonomy with confidence scores
- **Generates SEO content** — optimized titles, meta descriptions, keywords with search volume
- **Detects attributes** — color, material, gender, style, and more
- **Analyzes product images** — detects visual attributes and flags poor backgrounds
- **Integrates with Yoast SEO & Rank Math** — or stores SEO data in its own meta fields
- **Maps Google categories** to WooCommerce categories
- **Creates product attributes** as global WooCommerce attributes (`pa_color`, `pa_material`, etc.)
- **Bulk analysis** — process hundreds of products at once with progress tracking
- **Google Shopping ready** — stores `_google_product_category` compatible with Google Listings & Ads

## Requirements

- WordPress 5.8+
- WooCommerce 5.0+
- PHP 7.4+
- A TaxoAI API key ([get one free](https://app.taxoai.dev))

## Installation

### From GitHub Releases

1. Download the latest `.zip` from [Releases](https://github.com/episuarez/taxoai-woocommerce/releases)
2. In WordPress, go to **Plugins > Add New > Upload Plugin**
3. Upload the zip and activate

### Manual

1. Clone or download this repo into `/wp-content/plugins/woocommerce-taxoai/`
2. Activate from **Plugins** in your WordPress admin

### Setup

1. Go to **WooCommerce > TaxoAI**
2. Enter your API key (get one at [app.taxoai.dev](https://app.taxoai.dev))
3. Choose your language and preferences
4. Start analyzing products

## How it works

```
Product data ──> TaxoAI API ──> Google category + SEO + Attributes
                                       │
                    ┌──────────────────┼──────────────────┐
                    ▼                  ▼                  ▼
              Yoast / Rank Math   WC Categories    WC Attributes
              meta fields         auto-mapped      pa_color, etc.
```

1. When you save a product (or click "Analyze Now"), the plugin sends the product name, description, price, and images to the TaxoAI API
2. The API returns a classification, SEO content, and detected attributes
3. If the confidence score meets your threshold, results are auto-applied
4. Everything is stored in post meta for display in the product metabox

## Free tier

**25 products/month** — no credit card required.

Usage is verified server-side on every analysis. When you need more, upgrade at [taxoai.dev](https://taxoai.dev).

## Configuration

| Setting | Description | Default |
|---|---|---|
| API Key | Your TaxoAI API key | — |
| Language | Analysis language (es/en/pt) | `es` |
| Auto-analyze | Analyze on product save | Off |
| Confidence threshold | Minimum confidence to auto-apply | 0.7 |
| Update title | Replace product title with optimized version | Off |
| Update description | Replace product description | Off |
| Auto-map categories | Create WC categories from Google taxonomy | Off |
| Analyze images | Send product images for visual analysis | Off |

## Development

```bash
# Install dependencies
composer install

# Run tests
composer test
```

## License

GPL-2.0-or-later. See [LICENSE](https://www.gnu.org/licenses/gpl-2.0.html).

---

Built by [TaxoAI](https://taxoai.dev)
