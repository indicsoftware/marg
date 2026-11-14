<?php
/**
 * Product presenter: read-only view data for the Article and Magazine templates.
 *
 * Rules:
 * - Plain static methods, no hooks. Not a ModuleInterface module.
 * - Every ACF field is optional: each method returns an empty string, empty
 *   array, 0 or null when data is missing. Nothing here ever prints.
 * - Values are RAW (unescaped). Templates escape at output: esc_html(),
 *   esc_url(), esc_attr(), and wp_kses_post() for the *_html methods.
 * - All methods accept int|\WP_Post|\WC_Product|null ($p). null = current post.
 * - Results are memoised per request.
 *
 * @package TenUpTheme
 */

namespace TenUpTheme;

/**
 * Product presenter.
 */
final class ProductPresenter {

	/**
	 * Membership plan slugs that grant digital access (filter: marg_access_membership_plans).
	 *
	 * @var string[]
	 */
	const ACCESS_PLAN_SLUGS = array( 'digital-subscription', 'annual-print-digital', 'modern-archive' );

	/**
	 * Taxonomy groups shown in the metadata column, in display order.
	 *
	 * @var array<string, array{taxonomy:string, label:string}>
	 */
	const TAXONOMY_GROUPS = array(
		'subjects'     => array(
			'taxonomy' => 'subject',
			'label'    => 'Subjects',
		),
		'geography'    => array(
			'taxonomy' => 'geographic-area',
			'label'    => 'Geography',
		),
		'time_periods' => array(
			'taxonomy' => 'time-period',
			'label'    => 'Time Period',
		),
		'keywords'     => array(
			'taxonomy' => 'product_tag',
			'label'    => 'Keywords',
		),
	);

	/**
	 * Per-request memo.
	 *
	 * @var array<string, mixed>
	 */
	private static $cache = array();

	/* ---------------------------------------------------------------------
	 * Internal helpers
	 * ------------------------------------------------------------------- */

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

	/**
	 * Clear the request memo (useful after updating products in the same request).
	 *
	 * @return void
	 */
	public static function flush() {
		self::$cache = array();
	}

	/**
	 * Read an ACF field safely.
	 *
	 * @param string     $name Field name.
	 * @param int|string $id   Post ID or 'option'.
	 * @return mixed|null
	 */
	private static function field( $name, $id ) {
		if ( ! function_exists( 'get_field' ) || ( is_int( $id ) && $id < 1 ) ) {
			return null;
		}
		return get_field( $name, $id );
	}

	/**
	 * Read an ACF group field as an array.
	 *
	 * @param int    $id   Post ID.
	 * @param string $name Group name.
	 * @return array
	 */
	private static function group( $id, $name ) {
		$value = self::memo(
			"group:{$id}:{$name}",
			static function () use ( $id, $name ) {
				return self::field( $name, $id );
			}
		);
		return is_array( $value ) ? $value : array();
	}

	/**
	 * Read a nested array key path safely.
	 *
	 * @param array    $data Data.
	 * @param string[] $path Keys.
	 * @return mixed|null
	 */
	private static function dig( $data, $path ) {
		foreach ( $path as $key ) {
			if ( ! is_array( $data ) || ! array_key_exists( $key, $data ) ) {
				return null;
			}
			$data = $data[ $key ];
		}
		return $data;
	}

	/**
	 * Scalar to trimmed string ('' for anything else).
	 *
	 * @param mixed $value Value.
	 * @return string
	 */
	private static function str( $value ) {
		return is_scalar( $value ) && ! is_bool( $value ) ? trim( (string) $value ) : '';
	}

	/**
	 * Numeric value to positive float, or null.
	 *
	 * @param mixed $value Value.
	 * @return float|null
	 */
	private static function amount( $value ) {
		if ( is_numeric( $value ) && (float) $value > 0 ) {
			return (float) $value;
		}
		return null;
	}

	/**
	 * Numeric value to positive int, or null.
	 *
	 * @param mixed $value Value.
	 * @return int|null
	 */
	private static function positive_int( $value ) {
		if ( is_numeric( $value ) && (int) $value > 0 ) {
			return (int) $value;
		}
		return null;
	}

	/**
	 * Format a number without trailing zeros (500, 20.4, 7.5).
	 *
	 * @param float $value Number.
	 * @return string
	 */
	private static function format_number( $value ) {
		$decimals = ( floor( $value ) === (float) $value ) ? 0 : 2;
		$out      = number_format_i18n( $value, $decimals );
		if ( $decimals ) {
			$out = rtrim( rtrim( $out, '0' ), '.,' );
		}
		return $out;
	}

	/**
	 * Normalise an ACF post_object / relationship value to published posts.
	 *
	 * @param mixed  $value     WP_Post|WP_Post[]|int|int[]|false|''.
	 * @param string $post_type Optional post type to enforce.
	 * @return \WP_Post[]
	 */
	private static function posts( $value, $post_type = '' ) {
		if ( empty( $value ) ) {
			return array();
		}
		$items = is_array( $value ) ? $value : array( $value );
		$out   = array();
		foreach ( $items as $item ) {
			$post = $item instanceof \WP_Post ? $item : ( is_numeric( $item ) ? get_post( (int) $item ) : null );
			if ( ! $post instanceof \WP_Post || 'publish' !== $post->post_status ) {
				continue;
			}
			if ( '' !== $post_type && $post_type !== $post->post_type ) {
				continue;
			}
			$out[ $post->ID ] = $post;
		}
		return array_values( $out );
	}

	/**
	 * Normalise an ACF file field (ID, array or URL) to an attachment ID.
	 *
	 * @param mixed $value Field value.
	 * @return int
	 */
	private static function attachment_id( $value ) {
		if ( is_numeric( $value ) ) {
			return max( 0, (int) $value );
		}
		if ( is_array( $value ) ) {
			return isset( $value['ID'] ) ? (int) $value['ID'] : ( isset( $value['id'] ) ? (int) $value['id'] : 0 );
		}
		if ( is_string( $value ) && '' !== $value ) {
			return (int) attachment_url_to_postid( $value );
		}
		return 0;
	}

	/**
	 * Post IDs stored in a raw (unformatted) ACF relationship / post_object meta value.
	 *
	 * Reading the meta directly avoids ACF formatting the whole group (one
	 * WP_Query per relationship field) when looping over many products.
	 *
	 * @param int    $post_id  Post ID.
	 * @param string $meta_key Meta key, e.g. article_details_authors.
	 * @return int[]
	 */
	private static function raw_ids( $post_id, $meta_key ) {
		$raw = get_post_meta( (int) $post_id, $meta_key, true );
		$raw = is_array( $raw ) ? $raw : ( is_numeric( $raw ) ? array( $raw ) : array() );
		return array_values( array_filter( array_map( 'intval', array_filter( $raw, 'is_numeric' ) ) ) );
	}

	/* ---------------------------------------------------------------------
	 * Identity / type
	 * ------------------------------------------------------------------- */

	/**
	 * Resolve a product reference to a post ID.
	 *
	 * @param int|\WP_Post|\WC_Product|null $p Product reference (null = current post).
	 * @return int
	 */
	public static function id( $p = null ) {
		if ( null === $p ) {
			return (int) get_the_ID();
		}
		if ( $p instanceof \WC_Product ) {
			return (int) $p->get_id();
		}
		if ( $p instanceof \WP_Post ) {
			return (int) $p->ID;
		}
		return is_numeric( $p ) ? max( 0, (int) $p ) : 0;
	}

	/**
	 * WooCommerce product object, or null.
	 *
	 * @param int|\WP_Post|\WC_Product|null $p Product reference.
	 * @return \WC_Product|null
	 */
	public static function product( $p = null ) {
		if ( $p instanceof \WC_Product ) {
			return $p;
		}
		$id = self::id( $p );
		if ( $id < 1 || ! function_exists( 'wc_get_product' ) ) {
			return null;
		}
		$product = wc_get_product( $id );
		return $product instanceof \WC_Product ? $product : null;
	}

	/**
	 * Product type key.
	 *
	 * @param int|\WP_Post|\WC_Product|null $p Product reference.
	 * @return string 'article' | 'magazine' | 'book' | ''
	 */
	public static function type( $p = null ) {
		$id = self::id( $p );
		return self::memo(
			"type:{$id}",
			static function () use ( $id ) {
				if ( $id < 1 ) {
					return '';
				}
				if ( ProductCategoryResolver::has( $id, 'articles' ) ) {
					return 'article';
				}
				if ( ProductCategoryResolver::has( $id, 'magazines' ) ) {
					return 'magazine';
				}
				if ( ProductCategoryResolver::has( $id, 'books' ) ) {
					return 'book';
				}
				return '';
			}
		);
	}

	/**
	 * Open-access flag (magazine flag, or the parent magazine's flag for articles).
	 *
	 * @param int|\WP_Post|\WC_Product|null $p Product reference.
	 * @return bool
	 */
	public static function is_open_access( $p = null ) {
		$id   = self::id( $p );
		$type = self::type( $id );
		if ( 'article' === $type ) {
			if ( class_exists( __NAMESPACE__ . '\\TaxonomyInheritance' ) ) {
				return (bool) TaxonomyInheritance::article_open_access( $id );
			}
			return ! empty( self::group( $id, 'article_details' )['open_access'] );
		}
		if ( 'magazine' === $type ) {
			$group = self::group( $id, 'magazine_details' );
			if ( array_key_exists( 'open_access', $group ) ) {
				return (bool) $group['open_access'];
			}
			return '1' === (string) get_post_meta( $id, 'magazine_details_open_access', true );
		}
		return false;
	}

	/**
	 * Product title (raw; falls back to "Untitled").
	 *
	 * @param int|\WP_Post|\WC_Product|null $p Product reference.
	 * @return string
	 */
	public static function title( $p = null ) {
		$id    = self::id( $p );
		$title = $id ? trim( wp_strip_all_tags( get_the_title( $id ) ) ) : '';
		return '' !== $title ? html_entity_decode( $title, ENT_QUOTES, 'UTF-8' ) : __( 'Untitled', 'tenup-theme' );
	}

	/**
	 * Product permalink ('' when unavailable).
	 *
	 * @param int|\WP_Post|\WC_Product|null $p Product reference.
	 * @return string
	 */
	public static function permalink( $p = null ) {
		$id  = self::id( $p );
		$url = $id ? get_permalink( $id ) : '';
		return is_string( $url ) ? $url : '';
	}

	/**
	 * Magazine subtitle (subtitle_mag), only when short enough to be a real subtitle.
	 *
	 * @param int|\WP_Post|\WC_Product|null $p          Magazine reference.
	 * @param int                           $max_length Longer values are treated as blurbs and dropped.
	 * @return string
	 */
	public static function subtitle( $p = null, $max_length = 80 ) {
		$id = self::id( $p );
		if ( 'magazine' !== self::type( $id ) ) {
			return '';
		}
		$subtitle = self::str( self::group( $id, 'magazine_details' )['subtitle_mag'] ?? '' );
		return ( '' !== $subtitle && mb_strlen( $subtitle ) <= $max_length ) ? $subtitle : '';
	}

	/* ---------------------------------------------------------------------
	 * People
	 * ------------------------------------------------------------------- */

	/**
	 * Person view-model.
	 *
	 * @param \WP_Post $person Person post.
	 * @return array{id:int,name:string,url:string,bio:string}
	 */
	private static function person( $person ) {
		$name = trim( wp_strip_all_tags( get_the_title( $person ) ) );
		if ( '' === $name ) {
			$name = trim( self::str( self::field( 'first_name', $person->ID ) ) . ' ' . self::str( self::field( 'last_name', $person->ID ) ) );
		}
		$url = get_permalink( $person );
		return array(
			'id'   => (int) $person->ID,
			'name' => html_entity_decode( $name, ENT_QUOTES, 'UTF-8' ),
			'url'  => is_string( $url ) ? $url : '',
			'bio'  => self::str( self::field( 'bio', $person->ID ) ),
		);
	}

	/**
	 * Convert people posts to view-models, dropping nameless entries.
	 *
	 * @param \WP_Post[] $posts Person posts.
	 * @return array<int, array{id:int,name:string,url:string,bio:string}>
	 */
	private static function people( $posts ) {
		$out = array();
		foreach ( $posts as $post ) {
			$person = self::person( $post );
			if ( '' !== $person['name'] ) {
				$out[] = $person;
			}
		}
		return $out;
	}

	/**
	 * Article authors.
	 *
	 * @param int|\WP_Post|\WC_Product|null $p Article reference.
	 * @return array<int, array{id:int,name:string,url:string,bio:string}>
	 */
	public static function authors( $p = null ) {
		$id = self::id( $p );
		return self::memo(
			"authors:{$id}",
			static function () use ( $id ) {
				$posts = self::posts( self::group( $id, 'article_details' )['authors'] ?? null, 'person' );
				return self::people( $posts );
			}
		);
	}

	/**
	 * Magazine editors: general (magazine_details.editors), guest (guest_editors), all (unique, general first).
	 *
	 * @param int|\WP_Post|\WC_Product|null $p Magazine reference.
	 * @return array{general:array, guest:array, all:array}
	 */
	public static function editors( $p = null ) {
		$id = self::id( $p );
		return self::memo(
			"editors:{$id}",
			static function () use ( $id ) {
				$group   = self::group( $id, 'magazine_details' );
				$general = self::people( self::posts( $group['editors'] ?? null, 'person' ) );
				$guest   = self::people( self::posts( $group['guest_editors'] ?? null, 'person' ) );
				$all     = array();
				foreach ( array_merge( $general, $guest ) as $person ) {
					$all[ $person['id'] ] = $person;
				}
				return array(
					'general' => $general,
					'guest'   => $guest,
					'all'     => array_values( $all ),
				);
			}
		);
	}

	/**
	 * Join people names: "A", "A and B", "A, B and C".
	 *
	 * @param array  $people Person view-models (or plain strings).
	 * @param string $glue   Separator between names.
	 * @param string $last   Separator before the last name ('' = use $glue).
	 * @return string
	 */
	public static function names( array $people, $glue = ', ', $last = ' and ' ) {
		$names = array();
		foreach ( $people as $person ) {
			$name = is_array( $person ) ? self::str( $person['name'] ?? '' ) : self::str( $person );
			if ( '' !== $name ) {
				$names[] = $name;
			}
		}
		if ( count( $names ) < 2 || '' === $last ) {
			return implode( $glue, $names );
		}
		$final = array_pop( $names );
		return implode( $glue, $names ) . $last . $final;
	}

	/**
	 * Authors that have a bio (for "About the author"). Bio is plain text:
	 * output with wpautop( esc_html( $bio ) ).
	 *
	 * @param int|\WP_Post|\WC_Product|null $p Article reference.
	 * @return array<int, array{id:int,name:string,url:string,bio:string}>
	 */
	public static function author_bios( $p = null ) {
		return array_values(
			array_filter(
				self::authors( $p ),
				static function ( $person ) {
					return '' !== $person['bio'];
				}
			)
		);
	}

	/* ---------------------------------------------------------------------
	 * Issue information
	 * ------------------------------------------------------------------- */

	/**
	 * Parent magazine ID of an article (0 when none / unpublished / not a magazine).
	 *
	 * @param int|\WP_Post|\WC_Product|null $p Article reference.
	 * @return int
	 */
	public static function parent_magazine_id( $p = null ) {
		$id = self::id( $p );
		return self::memo(
			"parent:{$id}",
			static function () use ( $id ) {
				if ( 'article' !== self::type( $id ) ) {
					return 0;
				}
				$value = self::group( $id, 'article_details' )['parent_publication'] ?? null;
				if ( empty( $value ) ) {
					$raw   = get_post_meta( $id, 'article_details_parent_publication', true );
					$value = is_array( $raw ) ? $raw : ( is_numeric( $raw ) ? array( $raw ) : array() );
				}
				foreach ( self::posts( $value, 'product' ) as $post ) {
					if ( ProductCategoryResolver::has( $post->ID, 'magazines' ) ) {
						return (int) $post->ID;
					}
				}
				return 0;
			}
		);
	}

	/**
	 * The magazine whose issue data applies: itself for magazines, the parent for articles.
	 *
	 * @param int $id Product ID.
	 * @return int
	 */
	private static function issue_id( $id ) {
		$type = self::type( $id );
		if ( 'magazine' === $type ) {
			return $id;
		}
		return 'article' === $type ? self::parent_magazine_id( $id ) : 0;
	}

	/**
	 * Volume / issue numbers. Articles use their parent magazine.
	 *
	 * @param int|\WP_Post|\WC_Product|null $p Product reference.
	 * @return array{volume:?int, issue:?int, label:string} label e.g. "Vol. 70 No. 2", "Vol. 70" or ''.
	 */
	public static function volume_issue( $p = null ) {
		$mag   = self::issue_id( self::id( $p ) );
		$group = $mag ? self::group( $mag, 'magazine_details' ) : array();
		$vol   = self::positive_int( $group['volume_number'] ?? null );
		$no    = self::positive_int( $group['issue_number'] ?? null );
		$parts = array();
		if ( null !== $vol ) {
			/* translators: %d: volume number. */
			$parts[] = sprintf( __( 'Vol. %d', 'tenup-theme' ), $vol );
		}
		if ( null !== $no ) {
			/* translators: %d: issue number. */
			$parts[] = sprintf( __( 'No. %d', 'tenup-theme' ), $no );
		}
		return array(
			'volume' => $vol,
			'issue'  => $no,
			'label'  => implode( ' ', $parts ),
		);
	}

	/**
	 * Publication date. Articles use their parent magazine.
	 *
	 * @param int|\WP_Post|\WC_Product|null $p Product reference.
	 * @return array{start:string,end:string,label:string,short:string,year:string,ymd:string}
	 *   start "June 2024", end "September 2024" ('' if same/absent),
	 *   label "June 2024 – September 2024" (falls back to the free-text reference),
	 *   short "Jun 2024", year "2024", ymd "20240601".
	 */
	public static function publication_date( $p = null ) {
		$mag = self::issue_id( self::id( $p ) );
		return self::memo(
			"date:{$mag}",
			static function () use ( $mag ) {
				$empty = array(
					'start' => '',
					'end'   => '',
					'label' => '',
					'short' => '',
					'year'  => '',
					'ymd'   => '',
				);
				if ( ! $mag ) {
					return $empty;
				}
				$to_ts = static function ( $ymd ) {
					$ymd = self::str( $ymd );
					if ( ! preg_match( '/^\d{8}$/', $ymd ) ) {
						return 0;
					}
					$date = \DateTime::createFromFormat( '!Ymd', $ymd, new \DateTimeZone( 'UTC' ) );
					return $date ? $date->getTimestamp() : 0;
				};
				$start_ymd = self::str( get_post_meta( $mag, 'magazine_details_publication_month_year_start_date', true ) );
				$start_ts  = $to_ts( $start_ymd );
				$end_ts    = $to_ts( get_post_meta( $mag, 'magazine_details_publication_month_year_end_date', true ) );
				$out       = $empty;

				if ( $start_ts ) {
					$out['start'] = date_i18n( 'F Y', $start_ts, true );
					$out['short'] = date_i18n( 'M Y', $start_ts, true );
					$out['year']  = gmdate( 'Y', $start_ts );
					$out['ymd']   = $start_ymd;
					$out['label'] = $out['start'];
					if ( $end_ts && gmdate( 'Ym', $end_ts ) !== gmdate( 'Ym', $start_ts ) ) {
						$out['end']   = date_i18n( 'F Y', $end_ts, true );
						$out['label'] = $out['start'] . ' – ' . $out['end'];
					}
					return $out;
				}

				$reference = self::str( self::group( $mag, 'magazine_details' )['publication_month_year_reference'] ?? '' );
				if ( '' !== $reference ) {
					$out['label'] = $reference;
					$out['short'] = $reference;
					if ( preg_match( '/\b(1[89]\d{2}|20\d{2})\b/', $reference, $m ) ) {
						$out['year'] = $m[1];
					}
				}
				return $out;
			}
		);
	}

	/**
	 * Article page range.
	 *
	 * @param int|\WP_Post|\WC_Product|null $p Article reference.
	 * @return array{from:?int,to:?int,label:string,range:string} label "pp 14–23" / "p 12", range "14–23" / "12".
	 */
	public static function pages( $p = null ) {
		$group = self::group( self::id( $p ), 'article_details' );
		return self::page_info( $group['page_from'] ?? null, $group['page_to'] ?? null );
	}

	/**
	 * Build the page range array used by pages() from raw page_from / page_to values.
	 *
	 * @param mixed $from_raw page_from value.
	 * @param mixed $to_raw   page_to value.
	 * @return array{from:?int,to:?int,label:string,range:string}
	 */
	private static function page_info( $from_raw, $to_raw ) {
		$from = self::positive_int( $from_raw );
		$to   = self::positive_int( $to_raw );
		$out  = array(
			'from'  => $from,
			'to'    => $to,
			'label' => '',
			'range' => '',
		);
		if ( null !== $from && null !== $to && $from !== $to ) {
			$out['range'] = $from . '–' . $to;
			/* translators: %s: page range, e.g. 14–23. */
			$out['label'] = sprintf( __( 'pp %s', 'tenup-theme' ), $out['range'] );
		} elseif ( null !== $from || null !== $to ) {
			$out['range'] = (string) ( null !== $from ? $from : $to );
			/* translators: %s: page number. */
			$out['label'] = sprintf( __( 'p %s', 'tenup-theme' ), $out['range'] );
		}
		return $out;
	}

	/**
	 * Parent magazine summary for an article.
	 *
	 * @param int|\WP_Post|\WC_Product|null $p Article reference.
	 * @return array{id:int,title:string,url:string,volume_issue:string,date:string,cover_id:int}|null
	 */
	public static function parent_magazine( $p = null ) {
		$parent = self::parent_magazine_id( $p );
		if ( ! $parent ) {
			return null;
		}
		return array(
			'id'           => $parent,
			'title'        => self::title( $parent ),
			'url'          => self::permalink( $parent ),
			'volume_issue' => self::volume_issue( $parent )['label'],
			'date'         => self::publication_date( $parent )['label'],
			'cover_id'     => self::image_id( $parent ),
		);
	}

	/**
	 * Physical specification parts for a magazine, natural case (templates add `uppercase`).
	 *
	 * @param int|\WP_Post|\WC_Product|null $p Magazine reference.
	 * @return string[] e.g. ['28 cm x 20 cm', '124 pages', '110 photographs', '83 illustrations'].
	 */
	public static function physical_specs( $p = null ) {
		$id = self::id( $p );
		if ( 'magazine' !== self::type( $id ) ) {
			return array();
		}
		$group  = self::group( $id, 'magazine_details' );
		$print  = is_array( $group['print_details'] ?? null ) ? $group['print_details'] : array();
		$height = self::amount( $print['height'] ?? null );
		$width  = self::amount( $print['width'] ?? null );
		$parts  = array();

		if ( null !== $height && null !== $width ) {
			$parts[] = sprintf( '%s cm x %s cm', self::format_number( $height ), self::format_number( $width ) );
		}
		$counts = array(
			'print_length'            => _n_noop( '%s page', '%s pages', 'tenup-theme' ),
			'number_of_photographs'   => _n_noop( '%s photograph', '%s photographs', 'tenup-theme' ),
			'number_of_illustrations' => _n_noop( '%s illustration', '%s illustrations', 'tenup-theme' ),
		);
		foreach ( $counts as $key => $noop ) {
			$n = self::positive_int( $group[ $key ] ?? null );
			if ( null !== $n ) {
				$parts[] = sprintf( translate_nooped_plural( $noop, $n, 'tenup-theme' ), number_format_i18n( $n ) );
			}
		}
		return $parts;
	}

	/* ---------------------------------------------------------------------
	 * Text
	 * ------------------------------------------------------------------- */

	/**
	 * Long description / abstract as paragraphs (post_content through wpautop).
	 * NOT escaped: output with wp_kses_post(). '' when empty.
	 *
	 * the_content filters are deliberately not applied (Memberships and other
	 * plugins hook there).
	 *
	 * @param int|\WP_Post|\WC_Product|null $p Product reference.
	 * @return string
	 */
	public static function description_html( $p = null ) {
		$id      = self::id( $p );
		$content = $id ? (string) get_post_field( 'post_content', $id ) : '';
		if ( '' === trim( wp_strip_all_tags( $content ) ) ) {
			return '';
		}
		return wpautop( $content );
	}

	/**
	 * Taxonomy groups for the metadata column; empty groups are removed.
	 *
	 * @param int|\WP_Post|\WC_Product|null $p             Product reference.
	 * @param int                           $keyword_limit Max keywords (0 = no limit).
	 * @return array<string, array{label:string, taxonomy:string, terms:array<int, array{id:int,name:string,url:string}>}>
	 *   Keys (in order): subjects, geography, time_periods, keywords.
	 */
	public static function taxonomy_groups( $p = null, $keyword_limit = 20 ) {
		$id  = self::id( $p );
		$out = array();
		foreach ( self::TAXONOMY_GROUPS as $key => $config ) {
			$terms = $id ? get_the_terms( $id, $config['taxonomy'] ) : false;
			if ( empty( $terms ) || is_wp_error( $terms ) ) {
				continue;
			}
			$items = array();
			foreach ( $terms as $term ) {
				$name = html_entity_decode( trim( $term->name ), ENT_QUOTES, 'UTF-8' );
				if ( '' === $name || preg_match( '/^[\W_]*$/u', $name ) ) {
					continue;
				}
				$link    = get_term_link( $term );
				$items[] = array(
					'id'   => (int) $term->term_id,
					'name' => $name,
					'url'  => is_wp_error( $link ) ? '' : $link,
				);
			}
			if ( 'keywords' === $key && $keyword_limit > 0 ) {
				$items = array_slice( $items, 0, $keyword_limit );
			}
			if ( $items ) {
				$out[ $key ] = array(
					'label'    => $config['label'],
					'taxonomy' => $config['taxonomy'],
					'terms'    => $items,
				);
			}
		}
		return $out;
	}

	/**
	 * Article metadata table rows; rows without a value are dropped.
	 *
	 * @param int|\WP_Post|\WC_Product|null $p Article reference.
	 * @return array<int, array{key:string,label:string,value:string,url:string}>
	 *   keys: title, authors, doi, pages, publication_date, access_type.
	 */
	public static function metadata_rows( $p = null ) {
		$id      = self::id( $p );
		$authors = self::authors( $id );
		$doi     = esc_url_raw( self::str( self::group( $id, 'article_details' )['doi_link'] ?? '' ) );
		$rows    = array(
			array(
				'key'   => 'title',
				'label' => __( 'Title', 'tenup-theme' ),
				'value' => self::title( $id ),
				'url'   => '',
			),
			array(
				'key'   => 'authors',
				'label' => count( $authors ) > 1 ? __( 'Authors', 'tenup-theme' ) : __( 'Author', 'tenup-theme' ),
				'value' => self::names( $authors ),
				'url'   => '',
			),
			array(
				'key'   => 'doi',
				'label' => __( 'DOI', 'tenup-theme' ),
				'value' => $doi,
				'url'   => $doi,
			),
			array(
				'key'   => 'pages',
				'label' => __( 'Pages', 'tenup-theme' ),
				'value' => self::pages( $id )['range'],
				'url'   => '',
			),
			array(
				'key'   => 'publication_date',
				'label' => __( 'Publication date', 'tenup-theme' ),
				'value' => self::publication_date( $id )['short'],
				'url'   => '',
			),
			array(
				'key'   => 'access_type',
				'label' => __( 'Access type', 'tenup-theme' ),
				'value' => self::is_open_access( $id ) ? __( 'Free', 'tenup-theme' ) : __( 'Paid', 'tenup-theme' ),
				'url'   => '',
			),
		);
		return array_values(
			array_filter(
				$rows,
				static function ( $row ) {
					return '' !== $row['value'];
				}
			)
		);
	}

	/**
	 * Citation as plain text (Chicago-like; every segment optional).
	 *
	 * Article:  Authors. "Title." Marg Vol. 70 No. 2 (November 2018): 14–23. DOI-or-URL
	 * Magazine: Editors, ed. Title. Marg Vol. 70 No. 2 (November 2018). URL
	 *
	 * @param int|\WP_Post|\WC_Product|null $p Product reference.
	 * @return string
	 */
	public static function citation( $p = null ) {
		$id    = self::id( $p );
		$type  = self::type( $id );
		$issue = self::volume_issue( $id )['label'];
		$date  = self::publication_date( $id )['label'];
		$url   = self::permalink( $id );
		$marg  = trim( 'Marg ' . $issue );
		if ( '' !== $date ) {
			$marg .= ' (' . $date . ')';
		}

		if ( 'article' === $type ) {
			$parts   = array();
			$authors = self::names( self::authors( $id ) );
			if ( '' !== $authors ) {
				$parts[] = rtrim( $authors, '.' ) . '.';
			}
			$parts[] = '“' . self::title( $id ) . '.”';
			$range   = self::pages( $id )['range'];
			$parts[] = $marg . ( '' !== $range ? ': ' . $range : '' ) . '.';
			$doi     = self::str( self::group( $id, 'article_details' )['doi_link'] ?? '' );
			$link    = '' !== $doi ? $doi : $url;
			if ( '' !== $link ) {
				$parts[] = $link . '.';
			}
			return implode( ' ', $parts );
		}

		$parts   = array();
		$editors = self::editors( $id )['all'];
		if ( $editors ) {
			$parts[] = self::names( $editors ) . ', ' . ( count( $editors ) > 1 ? __( 'eds.', 'tenup-theme' ) : __( 'ed.', 'tenup-theme' ) );
		}
		$parts[] = self::title( $id ) . '.';
		$parts[] = $marg . '.';
		if ( '' !== $url ) {
			$parts[] = $url . '.';
		}
		return implode( ' ', $parts );
	}

	/* ---------------------------------------------------------------------
	 * Images
	 * ------------------------------------------------------------------- */

	/**
	 * Featured image ID (0 when none). No fallback.
	 *
	 * @param int|\WP_Post|\WC_Product|null $p Product reference.
	 * @return int
	 */
	public static function image_id( $p = null ) {
		$id = self::id( $p );
		return $id ? (int) get_post_thumbnail_id( $id ) : 0;
	}

	/**
	 * Cover image: magazine featured image; for articles the parent magazine's cover.
	 *
	 * @param int|\WP_Post|\WC_Product|null $p Product reference.
	 * @return int
	 */
	public static function cover_id( $p = null ) {
		$id = self::id( $p );
		if ( 'article' === self::type( $id ) ) {
			$parent = self::parent_magazine_id( $id );
			return $parent ? self::image_id( $parent ) : 0;
		}
		return self::image_id( $id );
	}

	/**
	 * Attachment caption ('' when none).
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return string
	 */
	public static function caption( $attachment_id ) {
		$attachment_id = (int) $attachment_id;
		$caption       = $attachment_id ? wp_get_attachment_caption( $attachment_id ) : '';
		return is_string( $caption ) ? trim( $caption ) : '';
	}

	/* ---------------------------------------------------------------------
	 * Commerce
	 * ------------------------------------------------------------------- */

	/**
	 * Display currency: the Price Based on Country zone currency, else the store currency.
	 *
	 * @return string e.g. 'INR' or 'USD'.
	 */
	public static function currency() {
		return self::memo(
			'currency',
			static function () {
				if ( function_exists( 'wcpbc_the_zone' ) ) {
					$zone = wcpbc_the_zone();
					if ( is_object( $zone ) && method_exists( $zone, 'get_currency' ) && $zone->get_currency() ) {
						return strtoupper( (string) $zone->get_currency() );
					}
				}
				return function_exists( 'get_woocommerce_currency' ) ? strtoupper( get_woocommerce_currency() ) : 'INR';
			}
		);
	}

	/**
	 * Variation ID per pa_medium slug for a variable product.
	 *
	 * @param int $id Product ID.
	 * @return array<string, int> e.g. ['print' => 8270, 'digital' => 8271].
	 */
	private static function variations( $id ) {
		return self::memo(
			"variations:{$id}",
			static function () use ( $id ) {
				$product = self::product( $id );
				if ( ! $product || ! $product->is_type( 'variable' ) ) {
					return array();
				}
				$out = array();
				foreach ( $product->get_children() as $variation_id ) {
					$variation = wc_get_product( $variation_id );
					if ( ! $variation ) {
						continue;
					}
					// The variation attribute holds the term slug (what add-to-cart expects); get_attribute() returns the name.
					$attrs  = $variation->get_attributes();
					$medium = sanitize_title( (string) ( ! empty( $attrs['pa_medium'] ) ? $attrs['pa_medium'] : $variation->get_attribute( 'pa_medium' ) ) );
					if ( '' !== $medium && ! isset( $out[ $medium ] ) ) {
						$out[ $medium ] = (int) $variation_id;
					}
				}
				return $out;
			}
		);
	}

	/**
	 * Build one price entry.
	 *
	 * @param array      $details  ACF price group (regular_price_inr etc.).
	 * @param array|null $fallback Global option prices ['inr' => , 'usd' => ].
	 * @return array{amount:?float,regular:?float,on_sale:bool}
	 */
	private static function price_from( $details, $fallback ) {
		$suffix  = strtolower( self::currency() );
		$suffix  = in_array( $suffix, array( 'inr', 'usd' ), true ) ? $suffix : 'inr';
		$regular = self::amount( $details[ 'regular_price_' . $suffix ] ?? null );
		$sale    = self::amount( $details[ 'sale_price_' . $suffix ] ?? null );
		if ( null === $regular && null === $sale && is_array( $fallback ) ) {
			$regular = self::amount( $fallback[ $suffix ] ?? null );
		}
		$on_sale = null !== $sale && ( null === $regular || $sale < $regular );
		return array(
			'amount'  => $on_sale ? $sale : $regular,
			'regular' => $regular,
			'on_sale' => $on_sale && null !== $regular,
		);
	}

	/**
	 * Prices per medium (ACF first, Global Settings fallback). WC variations are
	 * used only for purchasability and the add-to-cart URL.
	 *
	 * Magazine: always two entries (print, digital). Article: one 'digital' entry.
	 * Consumers should filter on `available` (has an amount and not out of print).
	 *
	 * @param int|\WP_Post|\WC_Product|null $p Product reference.
	 * @return array<int, array{medium:string,label:string,currency:string,amount:?float,regular:?float,on_sale:bool,
	 *   formatted:string,available:bool,out_of_print:bool,variation_id:int,purchasable:bool,add_to_cart_url:string}>
	 */
	public static function prices( $p = null ) {
		$id = self::id( $p );
		return self::memo(
			"prices:{$id}",
			static function () use ( $id ) {
				$type     = self::type( $id );
				$currency = self::currency();
				$options  = self::field( 'price', 'option' );
				$options  = is_array( $options ) ? $options : array();
				$entries  = array();

				if ( 'magazine' === $type ) {
					$group      = self::group( $id, 'magazine_details' );
					$variations = self::variations( $id );
					$cart_url   = function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : '';
					$media      = array(
						'print'   => array(
							'details'  => is_array( $group['print_details'] ?? null ) ? $group['print_details'] : array(),
							'fallback' => self::dig( $options, array( 'magazines', 'physical' ) ),
							'label'    => __( 'Print copy:', 'tenup-theme' ),
						),
						'digital' => array(
							'details'  => is_array( $group['digital_details'] ?? null ) ? $group['digital_details'] : array(),
							'fallback' => self::dig( $options, array( 'magazines', 'digital' ) ),
							'label'    => __( 'Digital copy:', 'tenup-theme' ),
						),
					);
					foreach ( $media as $medium => $config ) {
						$price        = self::price_from( $config['details'], $config['fallback'] );
						$out_of_print = 'print' === $medium && (
							! empty( $config['details']['out_of_print'] )
							|| 'outofstock' === ( $config['details']['stock_status'] ?? '' )
						);
						$variation_id = $variations[ $medium ] ?? 0;
						$variation    = $variation_id ? wc_get_product( $variation_id ) : null;
						$purchasable  = $variation && $variation->is_purchasable() && $variation->is_in_stock() && ! $out_of_print;
						$entries[]    = array(
							'medium'          => $medium,
							'label'           => $config['label'],
							'currency'        => $currency,
							'amount'          => $price['amount'],
							'regular'         => $price['regular'],
							'on_sale'         => $price['on_sale'],
							'formatted'       => null !== $price['amount'] ? self::format_number( $price['amount'] ) : '',
							'available'       => null !== $price['amount'] && ! $out_of_print,
							'out_of_print'    => $out_of_print,
							'variation_id'    => $variation_id,
							'purchasable'     => (bool) $purchasable,
							'add_to_cart_url' => $purchasable && $cart_url ? add_query_arg(
								array(
									'add-to-cart'         => $id,
									'variation_id'        => $variation_id,
									'attribute_pa_medium' => $medium,
								),
								$cart_url
							) : '',
						);
					}
					return $entries;
				}

				if ( 'article' === $type ) {
					$group       = self::group( $id, 'article_details' );
					$details     = is_array( $group['product_data'] ?? null ) ? $group['product_data'] : array();
					$price       = self::price_from( $details, self::dig( $options, array( 'articles' ) ) );
					$product     = self::product( $id );
					$purchasable = $product && $product->is_purchasable() && $product->is_in_stock();
					$entries[]   = array(
						'medium'          => 'digital',
						'label'           => __( 'Digital copy:', 'tenup-theme' ),
						'currency'        => $currency,
						'amount'          => $price['amount'],
						'regular'         => $price['regular'],
						'on_sale'         => $price['on_sale'],
						'formatted'       => null !== $price['amount'] ? self::format_number( $price['amount'] ) : '',
						'available'       => null !== $price['amount'],
						'out_of_print'    => false,
						'variation_id'    => 0,
						'purchasable'     => (bool) $purchasable,
						'add_to_cart_url' => $purchasable ? (string) $product->add_to_cart_url() : '',
					);
				}
				return $entries;
			}
		);
	}

	/**
	 * Items for partials/components/price-display.php (available prices only).
	 *
	 * @param int|\WP_Post|\WC_Product|null $p Product reference.
	 * @return array<int, array{label:string,currency:string,amount:string,medium:string}>
	 */
	public static function price_items( $p = null ) {
		$items = array();
		foreach ( self::prices( $p ) as $entry ) {
			if ( $entry['available'] ) {
				$items[] = array(
					'label'    => $entry['label'],
					'currency' => $entry['currency'],
					'amount'   => $entry['formatted'],
					'medium'   => $entry['medium'],
				);
			}
		}
		return $items;
	}

	/**
	 * Compact price for cards: the lowest available price, plus the OA flag.
	 *
	 * @param int|\WP_Post|\WC_Product|null $p Product reference.
	 * @return array{open_access:bool,currency:string,amount:string}
	 */
	public static function price_summary( $p = null ) {
		$id     = self::id( $p );
		$lowest = null;
		foreach ( self::prices( $id ) as $entry ) {
			if ( $entry['available'] && ( null === $lowest || $entry['amount'] < $lowest['amount'] ) ) {
				$lowest = $entry;
			}
		}
		return array(
			'open_access' => self::is_open_access( $id ),
			'currency'    => $lowest ? $lowest['currency'] : '',
			'amount'      => $lowest ? $lowest['formatted'] : '',
		);
	}

	/**
	 * PDF attachment ID (article product_data.pdf_file / magazine digital_details.pdf_file), or 0.
	 *
	 * @param int|\WP_Post|\WC_Product|null $p Product reference.
	 * @return int
	 */
	public static function pdf_attachment_id( $p = null ) {
		$id   = self::id( $p );
		$type = self::type( $id );
		if ( 'article' === $type ) {
			return self::attachment_id( self::dig( self::group( $id, 'article_details' ), array( 'product_data', 'pdf_file' ) ) );
		}
		if ( 'magazine' === $type ) {
			return self::attachment_id( self::dig( self::group( $id, 'magazine_details' ), array( 'digital_details', 'pdf_file' ) ) );
		}
		return 0;
	}

	/**
	 * Whether a user can read the digital content: open access, an active
	 * access membership, or a purchase of the product / its digital variation.
	 *
	 * @param int|\WP_Post|\WC_Product|null $p       Product reference.
	 * @param int                           $user_id User ID (0 = current user).
	 * @return bool
	 */
	public static function user_has_access( $p = null, $user_id = 0 ) {
		$id = self::id( $p );
		if ( self::is_open_access( $id ) ) {
			return true;
		}
		$user_id = $user_id ? (int) $user_id : get_current_user_id();
		if ( ! $user_id ) {
			return false;
		}

		if ( function_exists( 'wc_memberships_is_user_active_member' ) ) {
			$plans = (array) apply_filters( 'marg_access_membership_plans', self::ACCESS_PLAN_SLUGS );
			foreach ( $plans as $plan ) {
				if ( wc_memberships_is_user_active_member( $user_id, $plan ) ) {
					return true;
				}
			}
		}

		if ( function_exists( 'wc_customer_bought_product' ) ) {
			$targets = array( $id );
			if ( 'magazine' === self::type( $id ) ) {
				$variations = self::variations( $id );
				if ( ! empty( $variations['digital'] ) ) {
					$targets[] = $variations['digital'];
				}
			}
			foreach ( $targets as $target ) {
				if ( wc_customer_bought_product( '', $user_id, $target ) ) {
					return true;
				}
			}
		}
		return false;
	}

	/**
	 * Whether KEEP is shown. No backend exists yet, so it defaults to false.
	 *
	 * @return bool
	 */
	public static function keep_enabled() {
		return (bool) apply_filters( 'marg_keep_enabled', false );
	}

	/**
	 * Action view-model for the KEEP / VIEW / BUY circles.
	 *
	 * state: 'open' (open access), 'owned' (member / purchased), 'buy' (purchasable), 'none'.
	 * view:  non-null when state is open|owned. `enabled` is false when no PDF or WC
	 *        download exists (true for all content locally); render it disabled.
	 *        `product_id` is the article ID or the magazine's digital variation ID
	 *        (what SecurePDFViewer's AJAX expects); `url` is reserved ('' for now).
	 * buy:   purchasable media, independent of state (templates decide whether to
	 *        show BUY alongside VIEW). Empty when nothing is purchasable.
	 * keep:  ['enabled' => bool] (see keep_enabled()).
	 *
	 * @param int|\WP_Post|\WC_Product|null $p Product reference.
	 * @return array{state:string, view:?array{enabled:bool,product_id:int,pdf_id:int,url:string},
	 *   buy:array<int, array{medium:string,label:string,url:string,variation_id:int,currency:string,amount:string}>,
	 *   keep:array{enabled:bool}}
	 */
	public static function actions( $p = null ) {
		$id  = self::id( $p );
		$buy = array();
		foreach ( self::prices( $id ) as $entry ) {
			if ( $entry['purchasable'] && '' !== $entry['add_to_cart_url'] ) {
				$buy[] = array(
					'medium'       => $entry['medium'],
					'label'        => 'print' === $entry['medium'] ? __( 'Buy print copy', 'tenup-theme' ) : __( 'Buy digital copy', 'tenup-theme' ),
					'url'          => $entry['add_to_cart_url'],
					'variation_id' => $entry['variation_id'],
					'currency'     => $entry['currency'],
					'amount'       => $entry['formatted'],
				);
			}
		}

		if ( self::is_open_access( $id ) ) {
			$state = 'open';
		} elseif ( self::user_has_access( $id ) ) {
			$state = 'owned';
		} elseif ( $buy ) {
			$state = 'buy';
		} else {
			$state = 'none';
		}

		$view = null;
		if ( 'open' === $state || 'owned' === $state ) {
			$target  = $id;
			$pdf_id  = self::pdf_attachment_id( $id );
			$product = self::product( $id );
			if ( 'magazine' === self::type( $id ) ) {
				$variations = self::variations( $id );
				$target     = ! empty( $variations['digital'] ) ? $variations['digital'] : $id;
				$product    = self::product( $target );
			}
			$has_download = $product && $product->is_downloadable() && $product->get_downloads();
			$view         = array(
				'enabled'    => $pdf_id > 0 || (bool) $has_download,
				'product_id' => (int) $target,
				'pdf_id'     => $pdf_id,
				'url'        => '',
			);
		}

		return array(
			'state' => $state,
			'view'  => $view,
			'buy'   => $buy,
			'keep'  => array( 'enabled' => self::keep_enabled() ),
		);
	}

	/* ---------------------------------------------------------------------
	 * Lists
	 * ------------------------------------------------------------------- */

	/**
	 * Articles in a magazine issue, sorted by page_from (no pages last), then page_to, then title.
	 *
	 * @param int|\WP_Post|\WC_Product|null $p Magazine reference.
	 * @return array<int, array{id:int,title:string,url:string,pages:string,page_range:string,authors:string,
	 *   authors_list:array,image_id:int,from:?int,to:?int}>
	 */
	public static function issue_articles( $p = null ) {
		$id = self::id( $p );
		return self::memo(
			"issue_articles:{$id}",
			static function () use ( $id ) {
				if ( 'magazine' !== self::type( $id ) ) {
					return array();
				}
				$query = new \WP_Query(
					array(
						'post_type'              => 'product',
						'post_status'            => 'publish',
						'posts_per_page'         => 200,
						'no_found_rows'          => true,
						'ignore_sticky_posts'    => true,
						'update_post_term_cache' => false,
						'tax_query'              => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
							array(
								'taxonomy' => 'product_cat',
								'field'    => 'slug',
								'terms'    => ProductCategoryResolver::slug( 'articles' ),
							),
						),
						'meta_query'             => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
							'relation' => 'OR',
							array(
								'key'     => 'article_details_parent_publication',
								'value'   => '"' . $id . '"',
								'compare' => 'LIKE',
							),
							array(
								'key'     => 'article_details_parent_publication',
								'value'   => 'i:' . $id . ';',
								'compare' => 'LIKE',
							),
						),
					)
				);
				// Batch-load what every row needs (raw meta, not ACF-formatted groups, to avoid N+1).
				update_post_thumbnail_cache( $query );
				$author_ids = array();
				foreach ( $query->posts as $post ) {
					$author_ids = array_merge( $author_ids, self::raw_ids( $post->ID, 'article_details_authors' ) );
				}
				$author_ids = array_values( array_unique( $author_ids ) );
				if ( $author_ids ) {
					get_posts(
						array(
							'post_type'              => 'person',
							'post__in'               => $author_ids,
							'posts_per_page'         => count( $author_ids ),
							'no_found_rows'          => true,
							'update_post_term_cache' => false,
							'suppress_filters'       => false,
						)
					);
				}

				$rows = array();
				foreach ( $query->posts as $post ) {
					$pages   = self::page_info(
						get_post_meta( $post->ID, 'article_details_page_from', true ),
						get_post_meta( $post->ID, 'article_details_page_to', true )
					);
					$authors = self::people( self::posts( self::raw_ids( $post->ID, 'article_details_authors' ), 'person' ) );
					$rows[]  = array(
						'id'           => (int) $post->ID,
						'title'        => self::title( $post->ID ),
						'url'          => self::permalink( $post->ID ),
						'pages'        => $pages['label'],
						'page_range'   => $pages['range'],
						'authors'      => self::names( $authors ),
						'authors_list' => $authors,
						'image_id'     => self::image_id( $post->ID ),
						'from'         => $pages['from'],
						'to'           => $pages['to'],
					);
				}
				usort(
					$rows,
					static function ( $a, $b ) {
						$af = null === $a['from'] ? PHP_INT_MAX : $a['from'];
						$bf = null === $b['from'] ? PHP_INT_MAX : $b['from'];
						if ( $af !== $bf ) {
							return $af <=> $bf;
						}
						$at = null === $a['to'] ? PHP_INT_MAX : $a['to'];
						$bt = null === $b['to'] ? PHP_INT_MAX : $b['to'];
						if ( $at !== $bt ) {
							return $at <=> $bt;
						}
						return strcasecmp( $a['title'], $b['title'] );
					}
				);
				return $rows;
			}
		);
	}

	/**
	 * Related product IDs of the same type.
	 *
	 * Ranking: candidates sharing subject (weight 3), geography (2) or time
	 * period (1) terms, scored by overlap, newest first on ties. Articles from
	 * the same parent issue are skipped in that pass and used as the first
	 * fallback; the final fallback is the most recent items of the same type.
	 * Cached in the object cache for 12 hours (filter: marg_related_ids).
	 *
	 * @param int|\WP_Post|\WC_Product|null $p     Product reference.
	 * @param int                           $limit Max IDs.
	 * @return int[]
	 */
	public static function related_ids( $p = null, $limit = 3 ) {
		$id    = self::id( $p );
		$limit = max( 1, (int) $limit );
		$type  = self::type( $id );
		if ( ! in_array( $type, array( 'article', 'magazine' ), true ) ) {
			return array();
		}

		$cache_key = "related:{$id}:{$limit}";
		return self::memo(
			$cache_key,
			static function () use ( $id, $limit, $type, $cache_key ) {
				$cached = wp_cache_get( $cache_key, 'marg_presenter' );
				if ( is_array( $cached ) ) {
					return $cached;
				}

				$category = ProductCategoryResolver::slug( 'article' === $type ? 'articles' : 'magazines' );
				$parent   = 'article' === $type ? self::parent_magazine_id( $id ) : 0;
				$weights  = array(
					'subject'         => 3,
					'geographic-area' => 2,
					'time-period'     => 1,
				);
				$own      = array();
				$or       = array( 'relation' => 'OR' );
				foreach ( $weights as $taxonomy => $weight ) {
					$terms = get_the_terms( $id, $taxonomy );
					$ids   = ( $terms && ! is_wp_error( $terms ) ) ? wp_list_pluck( $terms, 'term_id' ) : array();
					if ( $ids ) {
						$own[ $taxonomy ] = array_map( 'intval', $ids );
						$or[]             = array(
							'taxonomy' => $taxonomy,
							'field'    => 'term_id',
							'terms'    => $own[ $taxonomy ],
						);
					}
				}

				$picked = array();

				if ( $own ) {
					$query  = new \WP_Query(
						array(
							'post_type'           => 'product',
							'post_status'         => 'publish',
							'posts_per_page'      => 40,
							'no_found_rows'       => true,
							'ignore_sticky_posts' => true,
							'post__not_in'        => array( $id ), // phpcs:ignore WordPressVIPMinimum.Performance.WPQueryParams.PostNotIn_post__not_in
							'orderby'             => 'date',
							'order'               => 'DESC',
							'tax_query'           => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
								'relation' => 'AND',
								array(
									'taxonomy' => 'product_cat',
									'field'    => 'slug',
									'terms'    => $category,
								),
								$or,
							),
						)
					);
					$scored = array();
					foreach ( $query->posts as $index => $post ) {
						if ( $parent && in_array( $parent, self::raw_ids( $post->ID, 'article_details_parent_publication' ), true ) ) {
							continue;
						}
						$score = 0;
						foreach ( $own as $taxonomy => $ids ) {
							$terms = get_the_terms( $post->ID, $taxonomy );
							if ( $terms && ! is_wp_error( $terms ) ) {
								$score += $weights[ $taxonomy ] * count( array_intersect( $ids, array_map( 'intval', wp_list_pluck( $terms, 'term_id' ) ) ) );
							}
						}
						$scored[] = array(
							'id'    => (int) $post->ID,
							'score' => $score,
							'index' => $index,
						);
					}
					usort(
						$scored,
						static function ( $a, $b ) {
							return ( $b['score'] <=> $a['score'] ) ?: ( $a['index'] <=> $b['index'] );
						}
					);
					foreach ( $scored as $row ) {
						$picked[] = $row['id'];
						if ( count( $picked ) >= $limit ) {
							break;
						}
					}
				}

				if ( count( $picked ) < $limit && $parent ) {
					foreach ( self::issue_articles( $parent ) as $row ) {
						if ( $row['id'] !== $id && ! in_array( $row['id'], $picked, true ) ) {
							$picked[] = $row['id'];
							if ( count( $picked ) >= $limit ) {
								break;
							}
						}
					}
				}

				if ( count( $picked ) < $limit ) {
					$args = array(
						'post_type'           => 'product',
						'post_status'         => 'publish',
						'posts_per_page'      => $limit + count( $picked ) + 1,
						'no_found_rows'       => true,
						'ignore_sticky_posts' => true,
						'fields'              => 'ids',
						'orderby'             => 'date',
						'order'               => 'DESC',
						'tax_query'           => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_tax_query
							array(
								'taxonomy' => 'product_cat',
								'field'    => 'slug',
								'terms'    => $category,
							),
						),
					);
					if ( 'magazine' === $type ) {
						$args['meta_key'] = 'magazine_details_publication_month_year_start_date'; // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
						$args['orderby']  = array(
							'meta_value_num' => 'DESC',
							'date'           => 'DESC',
						);
					}
					$recent = new \WP_Query( $args );
					foreach ( $recent->posts as $recent_id ) {
						$recent_id = (int) $recent_id;
						if ( $recent_id !== $id && ! in_array( $recent_id, $picked, true ) ) {
							$picked[] = $recent_id;
							if ( count( $picked ) >= $limit ) {
								break;
							}
						}
					}
				}

				$picked = array_map( 'intval', (array) apply_filters( 'marg_related_ids', $picked, $id, $limit ) );
				wp_cache_set( $cache_key, $picked, 'marg_presenter', 12 * HOUR_IN_SECONDS );
				return $picked;
			}
		);
	}

	/**
	 * Card view-model for partials/components/card-publication.php.
	 *
	 * Article: type_label "Article", byline "by {authors}", meta_primary "Article",
	 *          meta_secondary "from Vol. 31 No. 4, {Parent title}", image = own → parent cover.
	 * Magazine: type_label "Magazine", byline "edited by {editors}",
	 *          meta_primary "Vol. 75 No. 4", meta_secondary "March 2023".
	 * mobile_meta: "Article, 2023" (type label + year, either part optional).
	 * id is 0 for a missing / non-product post (the card partial then renders nothing).
	 *
	 * @param int|\WP_Post|\WC_Product|null $p Product reference.
	 * @return array{id:int,type:string,type_label:string,title:string,url:string,image_id:int,byline:string,
	 *   meta_primary:string,meta_secondary:string,mobile_meta:string,year:string,
	 *   price:array{open_access:bool,currency:string,amount:string}}
	 */
	public static function card( $p = null ) {
		$id = self::id( $p );
		// related_ids() is object-cached for 12h, so an ID may since have been unpublished / trashed.
		if ( $id < 1 || 'product' !== get_post_type( $id ) || 'publish' !== get_post_status( $id ) ) {
			$id = 0;
		}
		$type = self::type( $id );
		$date = self::publication_date( $id );
		$card = array(
			'id'             => $id,
			'type'           => $type,
			'type_label'     => '',
			'title'          => self::title( $id ),
			'url'            => self::permalink( $id ),
			'image_id'       => self::image_id( $id ),
			'byline'         => '',
			'meta_primary'   => '',
			'meta_secondary' => '',
			'mobile_meta'    => '',
			'year'           => $date['year'],
			'price'          => self::price_summary( $id ),
		);

		if ( 'article' === $type ) {
			$card['type_label']   = __( 'Article', 'tenup-theme' );
			$card['meta_primary'] = $card['type_label'];
			$authors              = self::names( self::authors( $id ) );
			if ( '' !== $authors ) {
				/* translators: %s: author names. */
				$card['byline'] = sprintf( __( 'by %s', 'tenup-theme' ), $authors );
			}
			$parent = self::parent_magazine( $id );
			if ( $parent ) {
				$from = implode( ', ', array_filter( array( $parent['volume_issue'], $parent['title'] ) ) );
				/* translators: %s: "Vol. X No. Y, Magazine title". */
				$card['meta_secondary'] = sprintf( __( 'from %s', 'tenup-theme' ), $from );
			}
			if ( ! $card['image_id'] ) {
				$card['image_id'] = self::cover_id( $id );
			}
		} elseif ( 'magazine' === $type ) {
			$card['type_label'] = __( 'Magazine', 'tenup-theme' );
			$editors            = self::names( self::editors( $id )['all'] );
			if ( '' !== $editors ) {
				/* translators: %s: editor names. */
				$card['byline'] = sprintf( __( 'edited by %s', 'tenup-theme' ), $editors );
			}
			$card['meta_primary']   = self::volume_issue( $id )['label'];
			$card['meta_secondary'] = '' !== $date['start'] ? $date['start'] : $date['label'];
		}

		$card['mobile_meta'] = implode( ', ', array_filter( array( $card['type_label'], $card['year'] ) ) );
		return $card;
	}

	/**
	 * Card view-models for related_ids().
	 *
	 * @param int|\WP_Post|\WC_Product|null $p     Product reference.
	 * @param int                           $limit Max cards.
	 * @return array<int, array> See card().
	 */
	public static function related_cards( $p = null, $limit = 3 ) {
		return array_map( array( __CLASS__, 'card' ), self::related_ids( $p, $limit ) );
	}

	/* ---------------------------------------------------------------------
	 * Issue names / magazine archive
	 * ------------------------------------------------------------------- */

	/**
	 * Whether a title is an imported placeholder ("Volume 12 Number 3") rather than a real issue name.
	 *
	 * @param string $title Title (raw or rendered; tags and entities are handled).
	 * @return bool
	 */
	public static function is_placeholder_title( $title ) {
		$title = html_entity_decode( wp_strip_all_tags( (string) $title ), ENT_QUOTES, 'UTF-8' );
		return 1 === preg_match( '/^\s*Volume\s+\d+\s+Number\s+\d+\s*$/iu', $title );
	}

	/**
	 * Real issue name of a product: its title, or '' when the title is empty or a
	 * "Volume N Number M" placeholder (Figma 311:67 annotation: such issues have no name).
	 *
	 * @param int|\WP_Post|\WC_Product|null $p Product reference.
	 * @return string Raw (unescaped) name or ''.
	 */
	public static function issue_name( $p = null ) {
		$id    = self::id( $p );
		$title = $id ? trim( html_entity_decode( wp_strip_all_tags( get_the_title( $id ) ), ENT_QUOTES, 'UTF-8' ) ) : '';
		return ( '' === $title || self::is_placeholder_title( $title ) ) ? '' : $title;
	}

	/**
	 * Unix timestamp (UTC midnight) of an ACF date_picker raw value (Ymd), or 0.
	 *
	 * @param mixed $ymd Raw value, e.g. "19461001".
	 * @return int
	 */
	private static function ymd_timestamp( $ymd ) {
		$ymd = self::str( $ymd );
		if ( ! preg_match( '/^\d{8}$/', $ymd ) ) {
			return 0;
		}
		$date = \DateTime::createFromFormat( '!Ymd', $ymd, new \DateTimeZone( 'UTC' ) );
		return $date ? $date->getTimestamp() : 0;
	}

	/**
	 * One magazine archive row (Figma 852:157 desktop row, 866:170 mobile row).
	 *
	 * Reads raw post meta only (no ACF formatting, no taxonomy lookups), so a loop
	 * over a main query whose meta cache is primed costs no extra queries.
	 * Returns [] when the product has no positive numeric volume AND issue number.
	 *
	 * @param int|\WP_Post|\WC_Product|null $p Magazine reference.
	 * @return array{id:int,url:string,date:string,year:string,volume:int,issue:int,volume_label:string,
	 *   vol_issue:string,title:string,has_title:bool,annotation:string,image_id:int,sku:string,has_date:bool}|array{}
	 *   date "1946, Oct" (falls back to the free-text publication reference), volume_label is filled
	 *   in by archive_rows() ('' unless the row opens a volume), title '' for unnamed issues.
	 */
	public static function archive_row( $p = null ) {
		$id = self::id( $p );
		return self::memo(
			"archive_row:{$id}",
			static function () use ( $id ) {
				return self::build_archive_row( $id );
			}
		);
	}

	/**
	 * Build archive_row() (unmemoised).
	 *
	 * @param int $id Product ID.
	 * @return array
	 */
	private static function build_archive_row( $id ) {
		if ( $id < 1 ) {
			return array();
		}
		$volume = self::positive_int( get_post_meta( $id, 'magazine_details_volume_number', true ) );
		$issue  = self::positive_int( get_post_meta( $id, 'magazine_details_issue_number', true ) );
		if ( null === $volume || null === $issue ) {
			return array();
		}

		$start = self::ymd_timestamp( get_post_meta( $id, 'magazine_details_publication_month_year_start_date', true ) );
		if ( $start ) {
			$date = date_i18n( _x( 'Y, M', 'magazine archive date format', 'tenup-theme' ), $start, true );
			$year = gmdate( 'Y', $start );
		} else {
			$date = self::str( get_post_meta( $id, 'magazine_details_publication_month_year_reference', true ) );
			$year = preg_match( '/\b(1[89]\d{2}|20\d{2})\b/', $date, $m ) ? $m[1] : '';
		}

		$image_id = self::attachment_id( get_post_meta( $id, 'magazine_details_open_access_hover_image', true ) );
		if ( ! $image_id ) {
			$image_id = (int) get_post_thumbnail_id( $id );
		}

		$title = self::issue_name( $id );

		return array(
			'id'           => $id,
			'url'          => self::permalink( $id ),
			'date'         => $date,
			'year'         => $year,
			'volume'       => $volume,
			'issue'        => $issue,
			'volume_label' => '',
			/* translators: 1: volume number, 2: issue number. */
			'vol_issue'    => sprintf( __( 'Vol. %1$d No. %2$d', 'tenup-theme' ), $volume, $issue ),
			'title'        => $title,
			'has_title'    => '' !== $title,
			'annotation'   => self::str( get_post_meta( $id, 'magazine_details_open_access_featured_text', true ) ),
			'image_id'     => $image_id,
			'sku'          => self::str( get_post_meta( $id, '_sku', true ) ),
			'has_date'     => (bool) $start,
		);
	}

	/**
	 * Magazine archive rows, one per issue, in the order given (the archive query sorts by volume, issue, ID).
	 *
	 * Duplicates: the archive lists each volume/issue once. When several published products
	 * claim the same volume and issue (test fixtures, re-imports), the canonical record wins:
	 * the one whose SKU follows the catalogue convention "margmagv{volume}i{issue}" (every
	 * imported issue has one), then the one with a publication date, then the first in
	 * query order (lowest ID).
	 *
	 * Also fills 'volume_label' ("Vol. 8") on the first row of each volume and primes the
	 * attachment caches of the row images in one query.
	 *
	 * @param array $posts WP_Post objects or IDs (e.g. $wp_query->posts).
	 * @return array<int, array> See archive_row().
	 */
	public static function archive_rows( array $posts ) {
		$picked = array();
		$scores = array();
		foreach ( $posts as $post ) {
			$row = self::archive_row( $post );
			if ( ! $row ) {
				continue;
			}
			$key   = $row['volume'] . '/' . $row['issue'];
			$sku   = sprintf( '/^margmagv0*%di0*%d$/i', $row['volume'], $row['issue'] );
			$score = ( preg_match( $sku, $row['sku'] ) ? 2 : 0 ) + ( $row['has_date'] ? 1 : 0 );
			if ( ! isset( $scores[ $key ] ) || $score > $scores[ $key ] ) {
				$picked[ $key ] = $row;
				$scores[ $key ] = $score;
			}
		}

		$rows = array_values( $picked );
		usort(
			$rows,
			static function ( $a, $b ) {
				return array( $a['volume'], $a['issue'], $a['id'] ) <=> array( $b['volume'], $b['issue'], $b['id'] );
			}
		);

		$previous  = null;
		$image_ids = array();
		foreach ( $rows as $i => $row ) {
			if ( $row['volume'] !== $previous ) {
				/* translators: %d: volume number. */
				$rows[ $i ]['volume_label'] = sprintf( __( 'Vol. %d', 'tenup-theme' ), $row['volume'] );
			}
			$previous = $row['volume'];
			if ( $row['image_id'] ) {
				$image_ids[] = $row['image_id'];
			}
		}

		if ( $image_ids && function_exists( '_prime_post_caches' ) ) {
			_prime_post_caches( array_unique( $image_ids ), false, true );
		}

		return $rows;
	}

	/**
	 * Image data for the mobile archive strip (Figma 880:4949: natural proportions,
	 * top left, max height 160px, max width 100%).
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $size          Image size.
	 * @param int    $max_height    Rendered max height in px (used for the sizes hint).
	 * @return array{src:string,srcset:string,sizes:string,width:int,height:int}|array{} Raw values, [] when unusable.
	 */
	public static function archive_image( $attachment_id, $size = 'medium_large', $max_height = 160 ) {
		$attachment_id = (int) $attachment_id;
		$src           = $attachment_id ? wp_get_attachment_image_src( $attachment_id, $size ) : false;
		if ( ! is_array( $src ) || empty( $src[0] ) || empty( $src[1] ) || empty( $src[2] ) ) {
			return array();
		}
		$srcset = wp_get_attachment_image_srcset( $attachment_id, $size );
		return array(
			'src'    => (string) $src[0],
			'srcset' => is_string( $srcset ) ? $srcset : '',
			// Rendered width at the max height; the CSS max-width (100%) can only make it smaller.
			'sizes'  => (int) ceil( $max_height * (int) $src[1] / (int) $src[2] ) . 'px',
			'width'  => (int) $src[1],
			'height' => (int) $src[2],
		);
	}

	/**
	 * `sizes` hint for an archive image shown in the desktop header's hover-image container
	 * (header-desktop "main", Frame 24 951:2797: cols 14-17 x rows 4-7, 320 x 209.5 at 1920).
	 *
	 * The image is contained (object-fit: contain) in the box, so its rendered width is the
	 * smaller of the box width and the box height times its aspect ratio. The box is never
	 * wider than 320px (the header grid is capped at 1920), so a fixed px upper bound is used.
	 *
	 * @param array     $image      archive_image() result (width and height are read).
	 * @param int|float $box_width  Container max width in px.
	 * @param int|float $box_height Container height in px.
	 * @return string e.g. "155px"; '' when the image has no usable dimensions.
	 */
	public static function archive_hover_sizes( array $image, $box_width = 320, $box_height = 209.5 ) {
		$width  = isset( $image['width'] ) ? (int) $image['width'] : 0;
		$height = isset( $image['height'] ) ? (int) $image['height'] : 0;
		if ( $width < 1 || $height < 1 ) {
			return '';
		}
		return (int) ceil( min( (float) $box_width, (float) $box_height * $width / $height ) ) . 'px';
	}
}
