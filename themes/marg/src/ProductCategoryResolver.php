<?php
/**
 * Product category resolver.
 *
 * Product category term IDs are database-local and can differ between the
 * local, staging, and production sites. The product type slugs are the
 * portable identifiers used by the theme; this class resolves them to IDs
 * only where an API (such as ACF conditional logic) requires an ID.
 *
 * @package TenUpTheme
 */

namespace TenUpTheme;

/**
 * Resolve the product type categories without depending on term IDs.
 */
class ProductCategoryResolver {

	/**
	 * Product category taxonomy.
	 *
	 * @var string
	 */
	const TAXONOMY = 'product_cat';

	/**
	 * Product type slugs used by the site.
	 *
	 * @var array<string, string>
	 */
	const TYPE_SLUGS = array(
		'articles'           => 'articles',
		'books'              => 'books',
		'magazines'          => 'magazines',
		'subscription_plans' => 'subscription-plans',
	);

	/**
	 * Cached term IDs for the current request.
	 *
	 * @var array<string, int>
	 */
	private static $term_ids = array();

	/**
	 * Register the ACF portability filter.
	 *
	 * @return void
	 */
	public function init() {
		add_filter( 'acf/load_field', array( $this, 'load_acf_field' ), 20, 1 );
	}

	/**
	 * Return the stable slug for a product type.
	 *
	 * @param string $type Product type key.
	 * @return string
	 */
	public static function slug( $type ) {
		return isset( self::TYPE_SLUGS[ $type ] ) ? self::TYPE_SLUGS[ $type ] : '';
	}

	/**
	 * Resolve a product type to its current term ID.
	 *
	 * @param string $type Product type key.
	 * @return int
	 */
	public static function id( $type ) {
		if ( isset( self::$term_ids[ $type ] ) ) {
			return self::$term_ids[ $type ];
		}

		$slug = self::slug( $type );
		if ( '' === $slug ) {
			self::$term_ids[ $type ] = 0;
			return 0;
		}

		$term = get_term_by( 'slug', $slug, self::TAXONOMY );
		if ( ! $term || is_wp_error( $term ) ) {
			self::$term_ids[ $type ] = 0;
			return 0;
		}

		self::$term_ids[ $type ] = (int) $term->term_id;
		return self::$term_ids[ $type ];
	}

	/**
	 * Resolve multiple product types to their current term IDs.
	 *
	 * Missing terms are omitted rather than represented by zero, which keeps
	 * the result safe to pass to has_term() or other taxonomy APIs.
	 *
	 * @param array $types Product type keys.
	 * @return array<int, int>
	 */
	public static function ids( $types ) {
		$ids = array();

		foreach ( (array) $types as $type ) {
			$id = self::id( $type );
			if ( $id > 0 ) {
				$ids[] = $id;
			}
		}

		return array_values( array_unique( $ids ) );
	}

	/**
	 * Check whether a post has a product type category.
	 *
	 * Uses the stable slug directly so this path never depends on a
	 * database-specific term ID.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $type    Product type key.
	 * @return bool
	 */
	public static function has( $post_id, $type ) {
		$slug = self::slug( $type );
		return '' !== $slug && has_term( $slug, self::TAXONOMY, $post_id );
	}

	/**
	 * Check whether a post has any of the supplied product type categories.
	 *
	 * @param int   $post_id Post ID.
	 * @param array $types   Product type keys.
	 * @return bool
	 */
	public static function has_any( $post_id, $types ) {
		foreach ( (array) $types as $type ) {
			if ( self::has( $post_id, $type ) ) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Replace ACF conditional values with the current site's term IDs.
	 *
	 * ACF taxonomy fields return term IDs, so the conditional rules must use
	 * IDs at runtime. The JSON source stores portable product-type slugs, and
	 * the field definitions identify the product-type groups by stable ACF
	 * keys. The rules are rebuilt from the slugs on every site and are never
	 * written back to the database.
	 *
	 * @param array $field ACF field settings.
	 * @return array
	 */
	public function load_acf_field( $field ) {
		if ( ! is_array( $field ) || empty( $field['key'] ) ) {
			return $field;
		}

		$conditional_types = array(
			'field_book_details_group'      => array( 'books' ),
			'field_magazine_details_group'  => array( 'magazines' ),
			'field_article_details_group'   => array( 'articles' ),
			'field_taxonomies_group'        => array( 'books', 'magazines', 'articles' ),
		);

		if ( ! isset( $conditional_types[ $field['key'] ] ) ) {
			return $field;
		}

		$conditions = array();
		foreach ( $conditional_types[ $field['key'] ] as $type ) {
			$term_id = self::id( $type );
			if ( $term_id < 1 ) {
				// Keep the stored rule if a required category has not been seeded
				// yet. This avoids making all fields visible on an incomplete site.
				return $field;
			}

			$conditions[] = array(
				array(
					'field'    => 'field_68ac2f304ad26',
					'operator' => '==',
					'value'    => (string) $term_id,
				),
			);
		}

		if ( ! empty( $conditions ) ) {
			$field['conditional_logic'] = $conditions;
		}

		return $field;
	}
}
