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
 * toggle variants 881:151..881:175), "header-desktop" 951:2811 (main 951:2810,
 * magazine 951:2862, article 951:2887), "Footer-interim" 897:6385 (mobile Archive
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
	 * Desktop header (>= 1133px), Figma "header-desktop" 951:2811.
	 *
	 * - main     (951:2810): the magazine archive and every non-product page. The
	 *            hover-image container is archive-only (show_hover_image).
	 * - magazine (951:2862): back link + the issue's "Vol. N No. M" (current page).
	 *            Without volume / issue numbers, the issue title instead.
	 * - article  (951:2887): muted back link + "article from Vol. N No. M, Issue
	 *            name", linking to the parent magazine. Unnamed (placeholder) issues
	 *            read "article from Vol. N No. M" (same rule as issue_title()). No
	 *            parent: the back link only (title '').
	 *
	 * @return array{variant:string, archive_url:string, is_archive:bool, show_hover_image:bool, title:string, title_url:string, title_is_label:bool}
	 *   title_is_label: the title is a short "Vol. N No. M" label (kept on one line).
	 */
	public static function header_desktop() {
		return self::memo(
			'header_desktop',
			static function () {
				$context = self::header_context();
				$id      = (int) get_queried_object_id();
				$header  = array(
					'variant'          => 'main',
					'archive_url'      => $context['archive_url'],
					'is_archive'       => 'archive' === $context['type'],
					'show_hover_image' => 'archive' === $context['type'],
					'title'            => '',
					'title_url'        => '',
					'title_is_label'   => false,
				);

				if ( 'magazine' === $context['type'] ) {
					$label                    = ProductPresenter::volume_issue( $id )['label'];
					$header['variant']        = 'magazine';
					$header['title']          = '' !== $label ? $label : self::issue_title( $id );
					$header['title_is_label'] = '' !== $label;
				} elseif ( 'article' === $context['type'] ) {
					$header['variant'] = 'article';
					$parent            = ProductPresenter::parent_magazine( $id );
					if ( $parent ) {
						$header['title']     = self::article_from( (int) $parent['id'] );
						$header['title_url'] = (string) $parent['url'];
					}
				}

				return $header;
			}
		);
	}

	/**
	 * Desktop article header line: "article from Vol. 70 No. 2, Ars Botanica".
	 *
	 * The issue name is dropped when it is a placeholder ("Volume 70 Number 2") or
	 * repeats the label; without volume / issue numbers the issue title stands alone.
	 *
	 * @param int $magazine_id Parent magazine ID.
	 * @return string
	 */
	private static function article_from( $magazine_id ) {
		$label = ProductPresenter::volume_issue( $magazine_id )['label'];
		$name  = ProductPresenter::issue_name( $magazine_id );
		$parts = array_unique( array_filter( array( $label, $name ), 'strlen' ) );
		$ref   = $parts ? implode( ', ', $parts ) : ProductPresenter::title( $magazine_id );

		if ( '' === $ref ) {
			return '';
		}

		/* translators: %s: parent issue, e.g. "Vol. 70 No. 2, Ars Botanica" (lower-case "article" as drawn). */
		return sprintf( __( 'article from %s', 'tenup-theme' ), $ref );
	}

	/**
	 * Cart item count for the desktop header's decorative "Cart(N)", or null
	 * when WooCommerce has no cart on this request (then it reads "Cart").
	 *
	 * @return int|null
	 */
	public static function cart_count() {
		if ( ! function_exists( 'WC' ) ) {
			return null;
		}
		$wc = \WC();
		if ( ! $wc || empty( $wc->cart ) || ! is_object( $wc->cart ) ) {
			return null;
		}
		return (int) $wc->cart->get_cart_contents_count();
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
		 * Default true, except on the magazine archive: Figma 880:5044 "this header on
		 * archive doesn't hide on mobile, stays present and on top of the hover images".
		 *
		 * @param bool $autohide Default true (false on the magazine archive).
		 */
		return (bool) apply_filters( 'tenup_theme_site_header_autohide', ! self::is_archive_page() );
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
