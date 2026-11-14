<?php
/**
 * Magazine archive (WooCommerce template override for the product_cat "magazines" archive).
 *
 * Served at /product-category/magazines/ (ProductTemplates::archive_url()). WooCommerce's
 * template loader picks this file by taxonomy + slug; archive-product.php is deliberately
 * not overridden because the shop page is the site front page.
 *
 * Figma: 848:2819 (desktop 1920), 880:5747 / 864:3637 / 880:4951 (mobile 390).
 * The main query (ProductTemplates::archive_query()) already holds every magazine with
 * numeric volume and issue numbers, sorted by volume then issue, unpaginated.
 *
 * WooCommerce's shop-loop chrome (notices, breadcrumb, result count, ordering,
 * pagination, sidebar) is intentionally not rendered here: the Figma page has none.
 * The header title ("1946–2015 | ARCHIVES") and the Archive footer variant are rendered
 * by header.php / footer.php through ProductTemplates::is_magazine_archive().
 *
 * @package TenUpTheme
 */

use TenUpTheme\ProductPresenter;

defined( 'ABSPATH' ) || exit;

global $wp_query;

// Iterate the queried posts directly: the_post() would make WooCommerce build a WC_Product
// (with term and variation lookups) for each of the ~300 issues, which the rows do not need.
$marg_archive_rows = ProductPresenter::archive_rows( $wp_query instanceof WP_Query ? (array) $wp_query->posts : [] );

get_header( 'shop' );

get_template_part(
	'partials/archive/magazines',
	null,
	[
		'rows' => $marg_archive_rows,
	]
);

get_footer( 'shop' );
