<?php
/**
 * Product Filters
 *
 * Adds custom dropdown filters and admin columns to WordPress product list pages.
 * - Open Access filtering for Magazines and Articles
 * - Parent Magazine column for Articles (with sorting)
 *
 * @package TenUpTheme
 */

namespace TenUpTheme;

/**
 * ProductFilters class
 */
class ProductFilters {

	/**
	 * Initialize product filters and custom columns
	 *
	 * @return void
	 */
	public function init() {
		// Add dropdown filter to admin product list
		add_action( 'restrict_manage_posts', array( $this, 'add_open_access_filter' ), 10, 2 );

		// Modify query based on filter selection and column sorting
		add_action( 'pre_get_posts', array( $this, 'filter_by_open_access' ), 10, 1 );

		// Add Magazine column to Articles admin page
		add_filter( 'manage_product_posts_columns', array( $this, 'add_magazine_column' ) );
		add_action( 'manage_product_posts_custom_column', array( $this, 'populate_magazine_column' ), 10, 2 );
		add_filter( 'manage_edit-product_sortable_columns', array( $this, 'make_magazine_column_sortable' ) );
	}

	/**
	 * Add Open Access dropdown filter to Magazines and Articles admin pages
	 *
	 * @param string $post_type Current post type.
	 * @param string $which     Position ('top' or 'bottom').
	 * @return void
	 */
	public function add_open_access_filter( $post_type, $which ) {
		// Only on product list page
		if ( 'product' !== $post_type ) {
			return;
		}

		// Only on Magazines or Articles category page
		$allowed_categories = array( 'magazines', 'articles' );
		if ( ! isset( $_GET['product_cat'] ) || ! in_array( $_GET['product_cat'], $allowed_categories, true ) ) {
			return;
		}

		// Only show at top of table (avoid duplicate)
		if ( 'top' !== $which ) {
			return;
		}

		// Get current filter value
		$current_filter = isset( $_GET['open_access_filter'] ) ? sanitize_text_field( $_GET['open_access_filter'] ) : '';

		?>
		<select name="open_access_filter">
			<option value=""><?php esc_html_e( 'All Open Access Status', 'tenup-theme' ); ?></option>
			<option value="1" <?php selected( $current_filter, '1' ); ?>>
				<?php esc_html_e( 'Open Access', 'tenup-theme' ); ?>
			</option>
			<option value="0" <?php selected( $current_filter, '0' ); ?>>
				<?php esc_html_e( 'Not Open Access', 'tenup-theme' ); ?>
			</option>
		</select>
		<?php
	}

	/**
	 * Filter products by Open Access status
	 * Handles both Magazines (boolean field) and Articles (synced string field)
	 *
	 * @param WP_Query $query The WordPress query object.
	 * @return void
	 */
	public function filter_by_open_access( $query ) {
		// Only in admin area
		if ( ! is_admin() ) {
			return;
		}

		// Only for main query
		if ( ! $query->is_main_query() ) {
			return;
		}

		// Only for products
		if ( ! isset( $query->query_vars['post_type'] ) || 'product' !== $query->query_vars['post_type'] ) {
			return;
		}

		// Check if filter is active
		if ( ! isset( $_GET['open_access_filter'] ) || '' === $_GET['open_access_filter'] ) {
			return;
		}

		// Sanitize filter value
		$open_access_filter = sanitize_text_field( $_GET['open_access_filter'] );

		// Only allow '0' or '1'
		if ( ! in_array( $open_access_filter, array( '0', '1' ), true ) ) {
			return;
		}

		// Get existing meta_query (preserve other filters)
		$meta_query = $query->get( 'meta_query' );
		if ( ! is_array( $meta_query ) ) {
			$meta_query = array();
		}

		// Determine current category
		$current_category = isset( $_GET['product_cat'] ) ? sanitize_text_field( $_GET['product_cat'] ) : '';

		// Add Open Access filter condition based on category
		if ( 'magazines' === $current_category ) {
			// Magazine filter: Query boolean field (0/1)
			$meta_query[] = array(
				'key'     => 'magazine_details_open_access',
				'value'   => $open_access_filter,
				'compare' => '=',
				'type'    => 'NUMERIC',
			);
		} elseif ( 'articles' === $current_category ) {
			// Article filter: Query string field ("Yes"/"No")
			if ( '1' === $open_access_filter ) {
				// Open Access articles
				$meta_query[] = array(
					'key'     => 'article_details_open_access',
					'value'   => 'Yes',
					'compare' => '=',
					'type'    => 'CHAR',
				);
			} else {
				// Not Open Access: includes "No" and missing values
				$meta_query[] = array(
					'relation' => 'OR',
					array(
						'key'     => 'article_details_open_access',
						'value'   => 'No',
						'compare' => '=',
						'type'    => 'CHAR',
					),
					array(
						'key'     => 'article_details_open_access',
						'compare' => 'NOT EXISTS',
					),
				);
			}
		}

		// Set modified meta_query back to query
		$query->set( 'meta_query', $meta_query );

		// Handle Magazine column sorting for Articles
		$this->handle_magazine_column_sorting( $query );
	}

	/**
	 * Add Magazine column to Articles admin page
	 *
	 * @param array $columns Existing columns.
	 * @return array Modified columns array.
	 */
	public function add_magazine_column( $columns ) {
		// Only on Articles category page
		if ( ! isset( $_GET['product_cat'] ) || 'articles' !== $_GET['product_cat'] ) {
			return $columns;
		}

		// Insert Magazine column after title
		$new_columns = array();
		foreach ( $columns as $key => $label ) {
			$new_columns[ $key ] = $label;

			// Add Magazine column after title
			if ( 'title' === $key ) {
				$new_columns['magazine'] = __( 'Magazine', 'tenup-theme' );
			}
		}

		return $new_columns;
	}

	/**
	 * Populate Magazine column data for each Article
	 *
	 * @param string $column_key Column identifier.
	 * @param int    $post_id    Post ID.
	 * @return void
	 */
	public function populate_magazine_column( $column_key, $post_id ) {
		// Only handle our Magazine column
		if ( 'magazine' !== $column_key ) {
			return;
		}

		// Get article details
		$article_details = get_field( 'article_details', $post_id );

		// Check if parent publication exists
		if ( ! $article_details || ! isset( $article_details['parent_publication'] ) ) {
			echo '<span style="color: #999;">—</span>';
			return;
		}

		$parent_publication = $article_details['parent_publication'];

		// Handle empty parent
		if ( empty( $parent_publication ) ) {
			echo '<span style="color: #999;">—</span>';
			return;
		}

		// Handle array format (ACF relationship with max:1 may return array)
		if ( is_array( $parent_publication ) ) {
			$parent_publication = isset( $parent_publication[0] ) ? $parent_publication[0] : null;
		}

		// Validate we have a post object
		if ( ! $parent_publication || ! is_object( $parent_publication ) ) {
			echo '<span style="color: #999;">—</span>';
			return;
		}

		// Check if Magazine post still exists
		$magazine_id = $parent_publication->ID;
		if ( ! get_post_status( $magazine_id ) ) {
			echo '<span style="color: #dc3232;">Deleted Magazine</span>';
			return;
		}

		// Display Magazine title with link to edit
		$magazine_title = get_the_title( $magazine_id );
		$edit_link      = get_edit_post_link( $magazine_id );

		if ( $edit_link ) {
			printf(
				'<a href="%s">%s</a>',
				esc_url( $edit_link ),
				esc_html( $magazine_title )
			);
		} else {
			echo esc_html( $magazine_title );
		}
	}

	/**
	 * Make Magazine column sortable
	 *
	 * @param array $columns Sortable columns.
	 * @return array Modified sortable columns.
	 */
	public function make_magazine_column_sortable( $columns ) {
		// Only on Articles category page
		if ( ! isset( $_GET['product_cat'] ) || 'articles' !== $_GET['product_cat'] ) {
			return $columns;
		}

		// Register magazine column as sortable
		$columns['magazine'] = 'magazine';

		return $columns;
	}

	/**
	 * Handle Magazine column sorting in query
	 *
	 * @param WP_Query $query The WordPress query object.
	 * @return void
	 */
	private function handle_magazine_column_sorting( $query ) {
		// Check if sorting by magazine column
		$orderby = $query->get( 'orderby' );

		if ( 'magazine' !== $orderby ) {
			return;
		}

		// Sort by parent_publication meta value
		// This groups Articles by: has parent vs no parent
		$query->set( 'meta_key', 'article_details_parent_publication' );
		$query->set( 'orderby', 'meta_value' );
	}
}
