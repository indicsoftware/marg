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
 * Magazine archive: the product_cat "magazines" archive (/product-category/magazines/)
 * is rendered by woocommerce/taxonomy-product_cat-magazines.php, which WooCommerce's
 * template loader picks up by slug. archive_query() makes its main query return every
 * magazine with numeric volume and issue numbers, sorted by volume then issue in SQL.
 * (archive-product.php is not overridden: the shop page is the site front page.)
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
		// After WC_Query::pre_get_posts (10), which sets the catalogue ordering and paging.
		add_action( 'pre_get_posts', array( $this, 'archive_query' ), 20 );
		add_action( 'template_redirect', array( $this, 'redirect_paged_archive' ) );
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
	 * Whether the current request is the magazine archive (product_cat "magazines").
	 *
	 * @return bool
	 */
	public static function is_magazine_archive() {
		$slug = ProductCategoryResolver::slug( 'magazines' );
		return '' !== $slug && function_exists( 'is_product_category' ) && is_product_category( $slug );
	}

	/**
	 * URL of the magazine archive (the "magazines" product_cat term link); home URL as a fallback.
	 *
	 * @return string Raw URL (escape with esc_url() at output).
	 */
	public static function archive_url() {
		static $url = null;
		if ( null === $url ) {
			$slug = ProductCategoryResolver::slug( 'magazines' );
			$link = '' !== $slug ? get_term_link( $slug, ProductCategoryResolver::TAXONOMY ) : '';
			$url  = ( is_string( $link ) && '' !== $link ) ? $link : home_url( '/' );
		}
		return $url;
	}

	/**
	 * Main query of the magazine archive: every published magazine with positive numeric
	 * volume and issue numbers, ordered by volume then issue (named NUMERIC meta clauses,
	 * so the sort happens in SQL), unpaginated.
	 *
	 * WooCommerce's catalogue visibility tax_query and other plugins' restrictions
	 * (e.g. Memberships' post__not_in at priority 999) stay in place. Term caches are
	 * not primed: the archive rows read post meta only (ProductPresenter::archive_row()).
	 * Duplicate volume/issue records are resolved in ProductPresenter::archive_rows().
	 *
	 * @param \WP_Query $query Query.
	 * @return void
	 */
	public function archive_query( $query ) {
		$slug = ProductCategoryResolver::slug( 'magazines' );
		if ( is_admin() || '' === $slug || ! $query instanceof \WP_Query || ! $query->is_main_query() || $query->is_feed() || ! $query->is_tax( ProductCategoryResolver::TAXONOMY, $slug ) ) {
			return;
		}

		$meta_query = $query->get( 'meta_query' );
		$meta_query = is_array( $meta_query ) ? $meta_query : array();

		$meta_query['marg_archive_volume'] = array(
			'key'     => 'magazine_details_volume_number',
			'value'   => 0,
			'compare' => '>',
			'type'    => 'NUMERIC',
		);
		$meta_query['marg_archive_issue']  = array(
			'key'     => 'magazine_details_issue_number',
			'value'   => 0,
			'compare' => '>',
			'type'    => 'NUMERIC',
		);

		$args = array(
			'posts_per_page'         => -1,
			'nopaging'               => true,
			'no_found_rows'          => true,
			'update_post_meta_cache' => true,
			'update_post_term_cache' => false,
			'meta_query'             => $meta_query, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query -- one query for the whole archive.
			'meta_key'               => '',
			'orderby'                => array(
				'marg_archive_volume' => 'ASC',
				'marg_archive_issue'  => 'ASC',
				'ID'                  => 'ASC',
			),
			'order'                  => 'ASC',
		);

		/**
		 * Filter the magazine archive main query vars (e.g. to limit the volume range
		 * or exclude records with post__not_in).
		 *
		 * @param array     $args  Query vars set on the main query.
		 * @param \WP_Query $query The main query.
		 */
		$args = apply_filters( 'tenup_theme_magazine_archive_query_args', $args, $query );

		foreach ( (array) $args as $key => $value ) {
			$query->set( $key, $value );
		}

		// Drop WooCommerce's ?orderby=price|popularity|rating clause filters, if any were added.
		if ( function_exists( 'WC' ) && isset( WC()->query ) && is_object( WC()->query ) && method_exists( WC()->query, 'remove_ordering_args' ) ) {
			WC()->query->remove_ordering_args();
		}
	}

	/**
	 * The magazine archive is one unpaginated page: send /page/N/ requests (stale links
	 * to the old paginated WooCommerce archive, crawlers) to the canonical archive URL
	 * instead of serving the full list again under every page number.
	 *
	 * @return void
	 */
	public function redirect_paged_archive() {
		if ( ! is_paged() || ! self::is_magazine_archive() ) {
			return;
		}

		wp_safe_redirect( self::archive_url(), 301 );
		exit;
	}

	/**
	 * Dequeue WooCommerce's general/layout/smallscreen CSS on Marg product templates
	 * and on the magazine archive.
	 *
	 * Kept on every other WooCommerce screen (cart, checkout, account, books),
	 * which still rely on WooCommerce's default styling.
	 *
	 * @param array $styles WooCommerce stylesheets keyed by handle.
	 * @return array
	 */
	public function filter_woocommerce_styles( $styles ) {
		if ( '' === self::current_template() && ! self::is_magazine_archive() ) {
			return $styles;
		}

		foreach ( array( 'woocommerce-general', 'woocommerce-layout', 'woocommerce-smallscreen' ) as $handle ) {
			unset( $styles[ $handle ] );
		}

		return $styles;
	}

	/**
	 * Add template body classes (marg-product, marg-product--article|magazine;
	 * marg-archive marg-archive--magazines on the magazine archive).
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

		if ( self::is_magazine_archive() ) {
			$classes[] = 'marg-archive';
			$classes[] = 'marg-archive--magazines';
		}

		return $classes;
	}
}
