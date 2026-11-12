<?php
/**
 * Single product router (WooCommerce template override).
 *
 * WooCommerce loads this file through template_include for every product
 * (the theme declares add_theme_support( 'woocommerce' )).
 *  - product_cat 'articles'  -> partials/product/article.php
 *  - product_cat 'magazines' -> partials/product/magazine.php
 *  - anything else           -> WooCommerce's default single product markup.
 *
 * WooCommerce integration kept for the Marg templates:
 *  - woocommerce_before_single_product (notices; Memberships restriction, which
 *    password-protects restricted products at priority 999) and
 *    woocommerce_after_single_product are fired around the partial.
 *  - post_password_required() is honoured like content-single-product.php.
 *  - Product structured data (normally added on woocommerce_single_product_summary,
 *    which these templates do not fire) is generated explicitly.
 *
 * Partials receive $args = [ 'product_id' => int, 'product' => WC_Product ].
 *
 * @package TenUpTheme
 */

use TenUpTheme\ProductTemplates;

defined( 'ABSPATH' ) || exit;

get_header( 'shop' );

while ( have_posts() ) :
	the_post();

	$marg_product_id = (int) get_the_ID();
	$marg_template   = ProductTemplates::template_for( $marg_product_id );
	$marg_product    = wc_get_product( $marg_product_id );

	if ( '' === $marg_template || ! $marg_product ) {
		/** This action is documented in woocommerce/templates/single-product.php */
		do_action( 'woocommerce_before_main_content' );
		wc_get_template_part( 'content', 'single-product' );
		/** This action is documented in woocommerce/templates/single-product.php */
		do_action( 'woocommerce_after_main_content' );
		/** This action is documented in woocommerce/templates/single-product.php */
		do_action( 'woocommerce_sidebar' );
		continue;
	}

	$GLOBALS['product'] = $marg_product; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited -- WC expects the global product.

	/** This action is documented in woocommerce/templates/content-single-product.php */
	do_action( 'woocommerce_before_single_product' );

	if ( post_password_required() ) {
		echo get_the_password_form(); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- core markup.
	} else {
		?>
		<div id="product-<?php echo esc_attr( $marg_product_id ); ?>" <?php wc_product_class( 'marg-product-template', $marg_product ); ?>>
			<?php
			get_template_part(
				'partials/product/' . $marg_template,
				null,
				[
					'product_id' => $marg_product_id,
					'product'    => $marg_product,
				]
			);
			?>
		</div>
		<?php
		if ( function_exists( 'WC' ) && isset( WC()->structured_data ) && is_object( WC()->structured_data ) ) {
			WC()->structured_data->generate_product_data( $marg_product );
		}
	}

	/** This action is documented in woocommerce/templates/content-single-product.php */
	do_action( 'woocommerce_after_single_product' );

endwhile;

get_footer( 'shop' );
