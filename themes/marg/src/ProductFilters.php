<?php
/**
 * Product Filters
 *
 * Adds custom dropdown filters to the WordPress admin product list pages.
 * Currently implements Open Access filtering for Magazines.
 *
 * @package TenUpTheme
 */

namespace TenUpTheme;

/**
 * ProductFilters class
 */
class ProductFilters {

	/**
	 * Initialize product filters
	 *
	 * @return void
	 */
	public function init() {
		// Add dropdown filter to admin product list
		add_action( 'restrict_manage_posts', array( $this, 'add_open_access_filter' ), 10, 2 );

		// Modify query based on filter selection
		add_action( 'pre_get_posts', array( $this, 'filter_by_open_access' ), 10, 1 );
	}

	/**
	 * Add Open Access dropdown filter to Magazines admin page
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

		// Only on Magazines category page
		if ( ! isset( $_GET['product_cat'] ) || 'magazines' !== $_GET['product_cat'] ) {
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

		// Add Open Access filter condition
		$meta_query[] = array(
			'key'     => 'magazine_details_open_access',
			'value'   => $open_access_filter,
			'compare' => '=',
			'type'    => 'NUMERIC',
		);

		// Set modified meta_query back to query
		$query->set( 'meta_query', $meta_query );
	}
}
