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
 * Magazine archive: the product_cat "magazines" archive is rendered by
 * woocommerce/taxonomy-product_cat-magazines.php, which WooCommerce's template loader
 * picks up by slug. archive_query() makes its main query return every magazine with
 * numeric volume and issue numbers, sorted by volume then issue in SQL.
 *
 * Archive on the front page (filter tenup_theme_archive_on_front, default true): the
 * site root "/" serves the magazine archive without touching the page_on_front option
 * (which stays the WooCommerce shop page). front_page_request() turns the front-page
 * request into the "magazines" product_cat request, so the main query, template, body
 * classes and style dequeue above all apply at "/". The term URL
 * (/product-category/magazines/) 301-redirects to "/", and term links point at "/", so
 * the archive has one canonical URL. The shop stays at /shop/ (its post type archive), and
 * the shop page's permalink points there (filter_shop_page_link()).
 * (archive-product.php is not overridden.)
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
	 * Last volume listed on the magazine archive ("1946–2015": Vol. 67, the final
	 * issue being Jun 2016). Later volumes are current issues, not the archive.
	 *
	 * @var int
	 */
	const ARCHIVE_LAST_VOLUME = 67;

	/**
	 * Query vars allowed on a front-page request that is still served as the archive.
	 * Mirrors WP_Query's static-front-page detection (paging vars), plus WooCommerce's
	 * catalogue sort vars from old front-page shop links, which the archive ignores.
	 *
	 * @var string[]
	 */
	const FRONT_REQUEST_VARS = array( 'paged', 'page', 'cpage', 'orderby', 'order' );

	/**
	 * Whether the current main request is the front page served as the magazine archive.
	 *
	 * @var bool
	 */
	private static $is_front_archive = false;

	/**
	 * Whether the current front-page archive request carried FRONT_REQUEST_VARS
	 * (e.g. ?orderby=, ?order=, ?cpage=); redirect_paged_archive() sends it to the bare URL.
	 *
	 * @var bool
	 */
	private static $front_request_had_vars = false;

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
		add_action( 'wp_head', array( $this, 'archive_canonical' ) );

		// Archive on the front page.
		add_filter( 'request', array( $this, 'front_page_request' ) );
		add_filter( 'term_link', array( $this, 'filter_term_link' ), 10, 3 );
		add_filter( 'taxonomy_feed_link', array( $this, 'filter_term_feed_link' ), 10, 3 );
		add_filter( 'redirect_canonical', array( $this, 'filter_redirect_canonical' ) );
		add_filter( 'document_title_parts', array( $this, 'front_page_title' ) );
		add_filter( 'page_link', array( $this, 'filter_shop_page_link' ), 10, 3 );
	}

	/**
	 * Whether the site root serves the magazine archive.
	 *
	 * Requires the "magazines" product_cat term to exist (ProductCategoryResolver::id()
	 * caches the lookup), so a missing or renamed term leaves "/" and the archive links on
	 * the Settings > Reading front page instead of a 404.
	 *
	 * @return bool
	 */
	public static function archive_on_front() {
		// Before the taxonomy is registered the lookup would fail and cache 0.
		if ( ! taxonomy_exists( ProductCategoryResolver::TAXONOMY ) || ProductCategoryResolver::id( 'magazines' ) < 1 ) {
			return false;
		}

		/**
		 * Filter whether the site root ("/") renders the magazine archive.
		 *
		 * Return false to serve the front page set in Settings > Reading again and to
		 * use the magazines product_cat term URL as the archive URL.
		 *
		 * @param bool $on_front Default true.
		 */
		return (bool) apply_filters( 'tenup_theme_archive_on_front', true );
	}

	/**
	 * Whether the current request is the front page served as the magazine archive.
	 *
	 * @return bool
	 */
	public static function is_front_archive() {
		return self::$is_front_archive && self::is_magazine_archive();
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
	 * URL of the magazine archive: the home URL while the archive is on the front page,
	 * otherwise the "magazines" product_cat term link (home URL as a fallback).
	 *
	 * @return string Raw URL (escape with esc_url() at output).
	 */
	public static function archive_url() {
		if ( self::archive_on_front() ) {
			return home_url( '/' );
		}

		$slug = ProductCategoryResolver::slug( 'magazines' );
		$link = '' !== $slug ? get_term_link( $slug, ProductCategoryResolver::TAXONOMY ) : '';
		return ( is_string( $link ) && '' !== $link ) ? $link : home_url( '/' );
	}

	/**
	 * Serve the front page as the magazine archive: turn the main front-end request for
	 * "/" (and "/page/N/", which redirect_paged_archive() sends back to "/") into the
	 * "magazines" product_cat request.
	 *
	 * Only a request whose query vars are empty or limited to FRONT_REQUEST_VARS is
	 * changed: searches, feeds, embeds, previews, REST (rest_route), sitemaps and every
	 * other URL that WordPress or a plugin maps to query vars pass through untouched, as
	 * do admin, AJAX, REST and cron requests. The Customizer preview of "/" is an ordinary
	 * front-end request and gets the archive, as visitors do.
	 *
	 * @param array $query_vars Query vars parsed from the request.
	 * @return array
	 */
	public function front_page_request( $query_vars ) {
		self::$is_front_archive       = false;
		self::$front_request_had_vars = false;

		if ( ! is_array( $query_vars ) || is_admin() || wp_doing_ajax() || wp_doing_cron() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return $query_vars;
		}

		// WP::parse_request() can leave an empty pagename for the root URL.
		if ( isset( $query_vars['pagename'] ) && '' === $query_vars['pagename'] ) {
			unset( $query_vars['pagename'] );
		}

		if ( array_diff( array_keys( $query_vars ), self::FRONT_REQUEST_VARS ) || ! self::archive_on_front() ) {
			return $query_vars;
		}

		$paged = max(
			isset( $query_vars['paged'] ) ? absint( $query_vars['paged'] ) : 0,
			isset( $query_vars['page'] ) ? absint( $query_vars['page'] ) : 0
		);

		// Any non-empty paging/sort var: the archive ignores them, so serve it at the bare URL only.
		$had_vars = (bool) array_filter(
			$query_vars,
			static function ( $value ) {
				return '' !== $value && null !== $value;
			}
		);

		$query_vars = array(
			ProductCategoryResolver::TAXONOMY => ProductCategoryResolver::slug( 'magazines' ),
		);
		if ( $paged > 1 ) {
			$query_vars['paged'] = $paged;
		}

		self::$is_front_archive       = true;
		self::$front_request_had_vars = $had_vars;

		return $query_vars;
	}

	/**
	 * Point the "magazines" product_cat term link at the front page while the archive is
	 * served there, so menus, admin "View" links, sitemaps and redirect_canonical() all
	 * use the one canonical archive URL.
	 *
	 * @param string   $termlink Term link.
	 * @param \WP_Term $term     Term.
	 * @param string   $taxonomy Taxonomy.
	 * @return string
	 */
	public function filter_term_link( $termlink, $term, $taxonomy ) {
		if ( ProductCategoryResolver::TAXONOMY !== $taxonomy || ! $term instanceof \WP_Term || ProductCategoryResolver::slug( 'magazines' ) !== $term->slug || ! self::archive_on_front() ) {
			return $termlink;
		}

		return home_url( '/' );
	}

	/**
	 * Keep the "magazines" term feed at its own URL (/product-category/magazines/feed/).
	 *
	 * get_term_feed_link() builds pretty feed URLs from get_term_link(), which
	 * filter_term_link() points at "/", so the result would be the site's main feed. A
	 * product_cat feed link under the home URL's /feed can only be that term's: rebuild it
	 * from the unfiltered term link, keeping the feed part core appended.
	 *
	 * @param string $link     Feed link.
	 * @param string $feed     Feed type.
	 * @param string $taxonomy Taxonomy.
	 * @return string
	 */
	public function filter_term_feed_link( $link, $feed, $taxonomy ) {
		if ( ProductCategoryResolver::TAXONOMY !== $taxonomy || ! is_string( $link ) || ! self::archive_on_front() ) {
			return $link;
		}

		$home = trailingslashit( home_url( '/' ) );
		if ( 0 !== strpos( $link, $home . 'feed' ) ) {
			return $link;
		}

		remove_filter( 'term_link', array( $this, 'filter_term_link' ), 10 );
		$term_link = get_term_link( ProductCategoryResolver::slug( 'magazines' ), ProductCategoryResolver::TAXONOMY );
		add_filter( 'term_link', array( $this, 'filter_term_link' ), 10, 3 );

		if ( ! is_string( $term_link ) || '' === $term_link ) {
			return $link;
		}

		return trailingslashit( $term_link ) . substr( $link, strlen( $home ) );
	}

	/**
	 * Keep redirect_canonical() from sending the front-page archive to the term URL.
	 *
	 * With filter_term_link() in place the canonical URL already is the home URL; this
	 * guards against another term_link filter running later and changing it back.
	 *
	 * @param string|false $redirect_url Redirect URL.
	 * @return string|false
	 */
	public function filter_redirect_canonical( $redirect_url ) {
		if ( ! $redirect_url || ! self::is_front_archive() ) {
			return $redirect_url;
		}

		// Allow redirects that land on the front page itself (e.g. /index.php to /); drop the rest.
		$redirect_path = untrailingslashit( (string) wp_parse_url( $redirect_url, PHP_URL_PATH ) );
		$home_path     = untrailingslashit( (string) wp_parse_url( home_url( '/' ), PHP_URL_PATH ) );

		return $redirect_path === $home_path ? $redirect_url : false;
	}

	/**
	 * Give the front-page archive the front page's document title (site name and tagline)
	 * instead of the term archive title.
	 *
	 * @param array $parts Title parts.
	 * @return array
	 */
	public function front_page_title( $parts ) {
		if ( ! self::is_front_archive() ) {
			return $parts;
		}

		return array(
			'title'   => get_bloginfo( 'name', 'display' ),
			'tagline' => get_bloginfo( 'description', 'display' ),
		);
	}

	/**
	 * Point the shop page's permalink at the shop's own URL (/shop/, the product post type
	 * archive) while the archive is on the front page.
	 *
	 * The shop page is still the page_on_front option, so get_permalink() returns the home
	 * URL for it, which now serves the magazine archive. Everything WooCommerce builds from
	 * it (wc_get_page_permalink( 'shop' ): "Return to shop", "Continue shopping",
	 * breadcrumbs, My Account "Browse products"; the blocks' storePages.shop) and the admin
	 * "View page" link then lead to the shop instead.
	 *
	 * @param string $link    Page permalink.
	 * @param int    $post_id Page ID.
	 * @param bool   $sample  Whether this is a sample permalink (editor).
	 * @return string
	 */
	public function filter_shop_page_link( $link, $post_id, $sample = false ) {
		if ( $sample || ! is_string( $link ) || ! function_exists( 'wc_get_page_id' ) ) {
			return $link;
		}

		$post_id = (int) $post_id;
		if ( $post_id < 1 || (int) wc_get_page_id( 'shop' ) !== $post_id || (int) get_option( 'page_on_front' ) !== $post_id || ! self::archive_on_front() ) {
			return $link;
		}

		$home = untrailingslashit( home_url( '/' ) );
		$shop = get_post_type_archive_link( 'product' );

		return ( is_string( $shop ) && '' !== $shop && untrailingslashit( $shop ) !== $home ) ? $shop : $link;
	}

	/**
	 * Main query of the magazine archive: every published magazine in volumes
	 * 1–ARCHIVE_LAST_VOLUME with a positive issue number, ordered by volume then issue (named NUMERIC meta clauses,
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
			'value'   => array( 1, self::ARCHIVE_LAST_VOLUME ),
			'compare' => 'BETWEEN',
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
	 * The magazine archive is one unpaginated page at one URL: send /page/N/ requests
	 * (stale links to the old paginated WooCommerce archive, crawlers) and, while the
	 * archive is on the front page, the old term URL (/product-category/magazines/) to
	 * the canonical archive URL instead of serving the full list again under each URL.
	 * Front-page requests carrying ?orderby=, ?order=, ?cpage= (ignored by the archive)
	 * go to the bare URL for the same reason. In the Customizer preview core keeps the
	 * changeset query vars on the redirect (WP_Customize_Manager::add_state_query_params()).
	 *
	 * @return void
	 */
	public function redirect_paged_archive() {
		if ( ! self::is_magazine_archive() || is_feed() ) {
			return;
		}

		$is_old_term_url   = ! self::$is_front_archive && self::archive_on_front();
		$has_ignored_query = self::$is_front_archive && self::$front_request_had_vars;
		if ( ! is_paged() && ! $is_old_term_url && ! $has_ignored_query ) {
			return;
		}

		// Keep the view (?view=covers) so shared Covers links still open in Covers.
		$url = self::archive_url();
		if ( 'covers' === ProductPresenter::archive_view() ) {
			$url = add_query_arg( 'view', 'covers', $url );
		}

		wp_safe_redirect( $url, 301 );
		exit;
	}

	/**
	 * Print the archive's canonical URL.
	 *
	 * Core prints rel=canonical on singular views only, and no SEO plugin is active, so
	 * the archive and its view variant (?view=covers, the same rows) would be indexable as
	 * duplicates. Both point at the one archive URL.
	 *
	 * @return void
	 */
	public function archive_canonical() {
		if ( ! self::is_magazine_archive() || is_paged() ) {
			return;
		}

		printf( '<link rel="canonical" href="%s" />' . "\n", esc_url( self::archive_url() ) );
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
