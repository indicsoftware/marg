<?php
/**
 * Product template routing helpers.
 *
 * Decides which Marg single-product template (article / magazine) a product
 * uses and keeps WooCommerce's default front-end CSS off those templates.
 * The actual routing happens in woocommerce/single-product.php, which
 * WooCommerce loads through template_include once the theme declares
 * add_theme_support( 'woocommerce' ) (see ThemeCore::theme_setup()).
 *
 * @package TenUpTheme
 */

namespace TenUpTheme;

/**
 * Product template routing helpers.
 */
class ProductTemplates {

	/**
	 * Map of template key => product type key understood by ProductCategoryResolver.
	 *
	 * @var array<string, string>
	 */
	const TEMPLATES = array(
		'article'  => 'articles',
		'magazine' => 'magazines',
	);

	/**
	 * Register hooks.
	 *
	 * @return void
	 */
	public function init() {
		add_filter( 'woocommerce_enqueue_styles', array( $this, 'filter_woocommerce_styles' ), 99 );
		add_filter( 'body_class', array( $this, 'body_class' ) );
	}

	/**
	 * Return the Marg template key for a product, or '' for the WooCommerce default.
	 *
	 * Articles win over magazines if a product is (incorrectly) in both categories.
	 *
	 * @param int $post_id Product ID.
	 * @return string 'article' | 'magazine' | ''
	 */
	public static function template_for( $post_id ) {
		$post_id = (int) $post_id;
		if ( $post_id < 1 || 'product' !== get_post_type( $post_id ) ) {
			return '';
		}

		foreach ( self::TEMPLATES as $template => $type ) {
			if ( ProductCategoryResolver::has( $post_id, $type ) ) {
				return $template;
			}
		}

		return '';
	}

	/**
	 * Template key for the current request ('' unless viewing an article/magazine product).
	 *
	 * @return string
	 */
	public static function current_template() {
		if ( ! function_exists( 'is_product' ) || ! is_product() ) {
			return '';
		}

		return self::template_for( (int) get_queried_object_id() );
	}

	/**
	 * Dequeue WooCommerce's general/layout/smallscreen CSS on Marg product templates.
	 *
	 * Kept on every other WooCommerce screen (cart, checkout, account, books),
	 * which still rely on WooCommerce's default styling.
	 *
	 * @param array $styles WooCommerce stylesheets keyed by handle.
	 * @return array
	 */
	public function filter_woocommerce_styles( $styles ) {
		if ( '' === self::current_template() ) {
			return $styles;
		}

		foreach ( array( 'woocommerce-general', 'woocommerce-layout', 'woocommerce-smallscreen' ) as $handle ) {
			unset( $styles[ $handle ] );
		}

		return $styles;
	}

	/**
	 * Add template body classes (marg-product, marg-product--article|magazine).
	 *
	 * @param array $classes Body classes.
	 * @return array
	 */
	public function body_class( $classes ) {
		$template = self::current_template();
		if ( '' !== $template ) {
			$classes[] = 'marg-product';
			$classes[] = 'marg-product--' . $template;
		}

		return $classes;
	}
}
