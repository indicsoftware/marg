<?php
/**
 * Site chrome: read-only view data for header.php and footer.php.
 *
 * Rules (same as ProductPresenter):
 * - Plain static methods. Not a ModuleInterface module, registers no hooks.
 * - Values are RAW (unescaped). Templates escape at output.
 * - Results are memoised per request.
 *
 * Figma: "Header-interim / mobile" 881:183 (static variant 897:6278, archive
 * toggle variants 881:151..881:175), "Footer-interim" 897:6385 (mobile Archive
 * 908:214 / Non-archive 908:230) and "footer-interim/desktop" 922:6730.
 *
 * @package TenUpTheme
 */

namespace TenUpTheme;

/**
 * Site chrome presenter.
 */
final class SiteChrome {

	/**
	 * Current Marg website (footer "Currently at Marg").
	 *
	 * @var string
	 */
	const MARG_ART_URL = 'https://marg-art.org';

	/**
	 * Footer telephone numbers, display form.
	 *
	 * @var string[]
	 */
	const PHONES = array( '+91 22 22842520', '+91 22 22821151', '+91 22 22045947' );

	/**
	 * Per-request memo.
	 *
	 * @var array<string, mixed>
	 */
	private static $cache = array();

	/**
	 * Memoise a computed value for the request.
	 *
	 * @param string   $key Cache key.
	 * @param callable $fn  Producer.
	 * @return mixed
	 */
	private static function memo( $key, $fn ) {
		if ( ! array_key_exists( $key, self::$cache ) ) {
			self::$cache[ $key ] = $fn();
		}
		return self::$cache[ $key ];
	}

	/* ---------------------------------------------------------------------
	 * Context
	 * ------------------------------------------------------------------- */

	/**
	 * Whether the current request is the magazine archive (product_cat "magazines").
	 *
	 * @return bool
	 */
	public static function is_archive_page() {
		return (bool) self::memo(
			'is_archive',
			static function () {
				if ( method_exists( ProductTemplates::class, 'is_magazine_archive' ) ) {
					return (bool) ProductTemplates::is_magazine_archive();
				}
				return function_exists( 'is_product_category' ) && is_product_category( 'magazines' );
			}
		);
	}

	/**
	 * Magazine archive URL (home URL if the category is missing).
	 *
	 * @return string
	 */
	public static function archive_url() {
		return (string) self::memo(
			'archive_url',
			static function () {
				if ( method_exists( ProductTemplates::class, 'archive_url' ) ) {
					$url = (string) ProductTemplates::archive_url();
					if ( '' !== $url ) {
						return $url;
					}
				}
				$url = get_term_link( 'magazines', 'product_cat' );
				return is_string( $url ) ? $url : home_url( '/' );
			}
		);
	}

	/**
	 * Archive year range: "1946–2015" (header) or "1946 – 2015" (footer).
	 *
	 * @param bool $spaced Spaces around the en dash (footer form).
	 * @return string
	 */
	public static function archive_years( $spaced = false ) {
		return $spaced
			/* translators: archive year range as drawn in the footer (en dash with spaces). */
			? __( '1946 – 2015', 'tenup-theme' )
			/* translators: archive year range as drawn in the mobile header (en dash, no spaces). */
			: __( '1946–2015', 'tenup-theme' );
	}

	/**
	 * Display title of a magazine issue: ProductPresenter::title() (no subtitle),
	 * with placeholder names ("Volume 1 Number 1") shown as "Vol. 1 No. 1" (D6).
	 *
	 * @param int $magazine_id Magazine product ID.
	 * @return string '' when the ID is empty.
	 */
	public static function issue_title( $magazine_id ) {
		$magazine_id = (int) $magazine_id;
		if ( $magazine_id < 1 ) {
			return '';
		}

		// Same "no name" rule as card-publication.php / article.php (ProductPresenter::issue_name()).
		$label = ProductPresenter::volume_issue( $magazine_id )['label'];
		if ( '' !== $label && '' === ProductPresenter::issue_name( $magazine_id ) ) {
			return $label;
		}

		return ProductPresenter::title( $magazine_id );
	}

	/* ---------------------------------------------------------------------
	 * Header
	 * ------------------------------------------------------------------- */

	/**
	 * Mobile header context title (Figma 897:6278 static / 881:151 archive).
	 *
	 * - archive:  "1946–2015 | ARCHIVES", all semibold, no links.
	 * - magazine: "Archives" (link) | issue title (current page).
	 * - article:  "Archives" (link) | parent magazine title (link); without a
	 *             parent only "Archives".
	 * - other:    "Archives" (link).
	 *
	 * @return array{type:string, archive_url:string, title:string, title_url:string}
	 *   type 'archive' | 'magazine' | 'article' | 'default'.
	 */
	public static function header_context() {
		return self::memo(
			'header_context',
			static function () {
				$context = array(
					'type'        => 'default',
					'archive_url' => self::archive_url(),
					'title'       => '',
					'title_url'   => '',
				);

				if ( self::is_archive_page() ) {
					$context['type'] = 'archive';
					return $context;
				}

				$template = ProductTemplates::current_template();
				$id       = (int) get_queried_object_id();

				if ( 'magazine' === $template ) {
					$context['type']  = 'magazine';
					$context['title'] = self::issue_title( $id );
				} elseif ( 'article' === $template ) {
					$context['type'] = 'article';
					$parent          = ProductPresenter::parent_magazine( $id );
					if ( $parent ) {
						$context['title']     = self::issue_title( $parent['id'] );
						$context['title_url'] = $parent['url'];
					}
				}

				return $context;
			}
		);
	}

	/**
	 * When the mobile white fade (Figma 897:6351) shows: 'always' on article
	 * pages, 'scrolled' elsewhere (so the magazine cover band stays dark at the top).
	 *
	 * The archive also gets 'scrolled', but header.php hides the fade there while the
	 * archive's own sticky stage sits under the bar (html.js and a viewport at least
	 * 520px tall, the stage.php / archive-list.js condition; the archive frames
	 * 880:5747 / 864:3637 / 880:4951 have no fade). Without JS or on a landscape phone
	 * the stage scrolls away, and the fade keeps list rows from running under the title.
	 *
	 * @return string 'always' | 'scrolled' | 'none'
	 */
	public static function header_fade() {
		$fade = 'article' === ProductTemplates::current_template() ? 'always' : 'scrolled';

		/**
		 * Filter when the mobile header's white fade shows.
		 *
		 * @param string $fade 'always' | 'scrolled' | 'none'.
		 */
		$fade = (string) apply_filters( 'tenup_theme_site_header_fade', $fade );
		return in_array( $fade, array( 'always', 'scrolled', 'none' ), true ) ? $fade : 'scrolled';
	}

	/**
	 * Whether the mobile header hides on scroll down / reveals on scroll up.
	 *
	 * @return bool
	 */
	public static function header_autohide() {
		/**
		 * Filter whether the mobile site header hides on scroll down.
		 *
		 * @param bool $autohide Default true.
		 */
		return (bool) apply_filters( 'tenup_theme_site_header_autohide', true );
	}

	/* ---------------------------------------------------------------------
	 * Footer
	 * ------------------------------------------------------------------- */

	/**
	 * Footer variant: 'archive' (back to top) on the archive, 'non-archive' elsewhere.
	 *
	 * @return string
	 */
	public static function footer_variant() {
		return self::is_archive_page() ? 'archive' : 'non-archive';
	}

	/**
	 * Terms & Conditions URL: the WooCommerce terms page, else a published page
	 * with the slug terms-and-conditions, else '' (render as plain text).
	 *
	 * @return string
	 */
	public static function terms_url() {
		return (string) self::memo(
			'terms_url',
			static function () {
				$page_id = function_exists( 'wc_terms_and_conditions_page_id' ) ? (int) wc_terms_and_conditions_page_id() : 0;
				if ( $page_id < 1 || 'publish' !== get_post_status( $page_id ) ) {
					$page    = get_page_by_path( 'terms-and-conditions' );
					$page_id = ( $page && 'publish' === $page->post_status ) ? (int) $page->ID : 0;
				}
				$url = $page_id ? get_permalink( $page_id ) : '';
				return is_string( $url ) ? $url : '';
			}
		);
	}

	/**
	 * Privacy Policy URL ('' unless the privacy page is published).
	 *
	 * @return string
	 */
	public static function privacy_url() {
		return (string) get_privacy_policy_url();
	}

	/**
	 * Footer address lines.
	 *
	 * @return string[]
	 */
	public static function address_lines() {
		return array(
			__( '3rd Floor, Army & Navy Building,', 'tenup-theme' ),
			__( '148 Mahatma Gandhi Road,', 'tenup-theme' ),
			__( 'Mumbai 400001, India', 'tenup-theme' ),
		);
	}

	/**
	 * Footer phones as [ display, tel: href ].
	 *
	 * @return array<int, array{label:string, href:string}>
	 */
	public static function phones() {
		$out = array();
		foreach ( self::PHONES as $phone ) {
			$out[] = array(
				'label' => $phone,
				'href'  => 'tel:' . preg_replace( '/[^0-9+]/', '', $phone ),
			);
		}
		return $out;
	}

	/**
	 * Copyright line, e.g. "© Marg Foundation 2026".
	 *
	 * @return string
	 */
	public static function copyright() {
		/* translators: %s: current year. */
		return sprintf( __( '© Marg Foundation %s', 'tenup-theme' ), wp_date( 'Y' ) );
	}
}
