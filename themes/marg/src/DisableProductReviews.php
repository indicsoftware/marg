<?php
/**
 * Disable Product Reviews
 *
 * Completely disables and removes all product reviews functionality from WooCommerce.
 *
 * @package TenUpTheme
 */

namespace TenUpTheme;

/**
 * DisableProductReviews class
 */
class DisableProductReviews {

	/**
	 * Initialize the product reviews disabler
	 *
	 * @return void
	 */
	public function init() {
		// Redirect product reviews admin page
		add_action( 'admin_init', array( $this, 'redirect_product_reviews_page' ) );

		// Disable reviews support for products
		add_action( 'init', array( $this, 'remove_reviews_support' ) );
		add_action( 'wp_loaded', array( $this, 'remove_reviews_support' ), 99 );

		// Remove reviews tab from product page
		add_filter( 'woocommerce_product_tabs', array( $this, 'remove_reviews_tab' ), 98 );

		// Disable star ratings
		add_filter( 'woocommerce_product_get_rating_html', '__return_empty_string', 100 );
		add_filter( 'woocommerce_product_review_count', '__return_zero', 100 );

		// Remove reviews from admin
		add_action( 'admin_menu', array( $this, 'remove_reviews_admin_menu' ), 999 );
		add_filter( 'manage_edit-product_columns', array( $this, 'remove_reviews_column' ), 999 );

		// Disable comment/review posting for products
		add_filter( 'comments_open', array( $this, 'disable_product_reviews' ), 20, 2 );
		add_filter( 'comments_array', array( $this, 'remove_product_reviews_from_list' ), 10, 2 );

		// Remove rating from structured data
		add_filter( 'woocommerce_structured_data_product', array( $this, 'remove_rating_from_structured_data' ), 10, 2 );

		// Remove reviews from WooCommerce status
		add_filter( 'woocommerce_admin_status_tabs', array( $this, 'remove_reviews_from_status' ) );

		// Remove review-related widgets
		add_action( 'widgets_init', array( $this, 'remove_review_widgets' ), 99 );

		// Remove review meta boxes
		add_action( 'add_meta_boxes', array( $this, 'remove_review_meta_boxes' ), 999 );

		// Hide review settings in product data
		add_action( 'admin_head', array( $this, 'hide_review_settings_css' ) );

		// Remove review-related WooCommerce scripts
		add_action( 'wp_enqueue_scripts', array( $this, 'remove_review_scripts' ), 999 );

		// Disable review settings in WooCommerce settings
		add_filter( 'woocommerce_enable_reviews', '__return_false', 100 );
		add_filter( 'woocommerce_review_ratings_enabled', '__return_false', 100 );
		add_filter( 'woocommerce_review_gravatar_enabled', '__return_false', 100 );

		// Remove reviews from blocks
		add_filter( 'woocommerce_blocks_product_grid_item_html', array( $this, 'remove_reviews_from_blocks' ), 10, 3 );

		// Hide reviews option in product settings
		add_filter( 'woocommerce_product_data_tabs', array( $this, 'remove_reviews_product_data_tab' ), 98 );

		// Disable review verification badge
		add_filter( 'woocommerce_review_verification_label', '__return_empty_string', 100 );

		// Remove review sorting
		add_filter( 'woocommerce_get_catalog_ordering_args', array( $this, 'remove_review_sorting' ), 20 );
	}

	/**
	 * Redirect product reviews admin page
	 *
	 * @return void
	 */
	public function redirect_product_reviews_page() {
		global $pagenow;

		// Check if we're on the product reviews page
		if ( 'edit.php' === $pagenow &&
			 isset( $_GET['post_type'] ) && 'product' === $_GET['post_type'] &&
			 isset( $_GET['page'] ) && 'product-reviews' === $_GET['page'] ) {
			wp_safe_redirect( admin_url( 'edit.php?post_type=product' ) );
			exit;
		}
	}

	/**
	 * Remove reviews support from products
	 *
	 * @return void
	 */
	public function remove_reviews_support() {
		remove_post_type_support( 'product', 'comments' );
		remove_post_type_support( 'product', 'trackbacks' );
	}

	/**
	 * Remove reviews tab from product page
	 *
	 * @param array $tabs Product tabs.
	 * @return array
	 */
	public function remove_reviews_tab( $tabs ) {
		if ( isset( $tabs['reviews'] ) ) {
			unset( $tabs['reviews'] );
		}
		return $tabs;
	}

	/**
	 * Remove reviews from admin menu
	 *
	 * @return void
	 */
	public function remove_reviews_admin_menu() {
		global $submenu;

		// Remove Reviews from WooCommerce menu
		if ( isset( $submenu['woocommerce'] ) ) {
			foreach ( $submenu['woocommerce'] as $key => $item ) {
				if ( isset( $item[2] ) && ( strpos( $item[2], 'edit-comments.php' ) !== false || strpos( $item[2], 'product-reviews' ) !== false ) ) {
					unset( $submenu['woocommerce'][$key] );
				}
			}
		}

		// Remove Reviews from Products menu (including product-reviews page)
		if ( isset( $submenu['edit.php?post_type=product'] ) ) {
			foreach ( $submenu['edit.php?post_type=product'] as $key => $item ) {
				if ( isset( $item[2] ) && ( strpos( $item[2], 'edit-comments.php' ) !== false || strpos( $item[2], 'product-reviews' ) !== false ) ) {
					unset( $submenu['edit.php?post_type=product'][$key] );
				}
			}
		}

		// Directly remove the product-reviews submenu page
		remove_submenu_page( 'edit.php?post_type=product', 'product-reviews' );

		// Remove product reviews from Comments menu if it exists
		remove_menu_page( 'edit-comments.php?comment_type=review' );
	}

	/**
	 * Remove reviews column from products list
	 *
	 * @param array $columns Product columns.
	 * @return array
	 */
	public function remove_reviews_column( $columns ) {
		if ( isset( $columns['comments'] ) ) {
			unset( $columns['comments'] );
		}
		return $columns;
	}

	/**
	 * Disable product reviews
	 *
	 * @param bool $open Whether comments are open.
	 * @param int  $post_id Post ID.
	 * @return bool
	 */
	public function disable_product_reviews( $open, $post_id ) {
		$post = get_post( $post_id );
		if ( $post && 'product' === $post->post_type ) {
			return false;
		}
		return $open;
	}

	/**
	 * Remove product reviews from comments list
	 *
	 * @param array $comments Comments array.
	 * @param int   $post_id Post ID.
	 * @return array
	 */
	public function remove_product_reviews_from_list( $comments, $post_id ) {
		$post = get_post( $post_id );
		if ( $post && 'product' === $post->post_type ) {
			return array();
		}
		return $comments;
	}

	/**
	 * Remove rating from structured data
	 *
	 * @param array  $data Structured data.
	 * @param object $product Product object.
	 * @return array
	 */
	public function remove_rating_from_structured_data( $data, $product ) {
		if ( isset( $data['aggregateRating'] ) ) {
			unset( $data['aggregateRating'] );
		}
		if ( isset( $data['review'] ) ) {
			unset( $data['review'] );
		}
		return $data;
	}

	/**
	 * Remove reviews from WooCommerce status
	 *
	 * @param array $tabs Status tabs.
	 * @return array
	 */
	public function remove_reviews_from_status( $tabs ) {
		if ( isset( $tabs['reviews'] ) ) {
			unset( $tabs['reviews'] );
		}
		return $tabs;
	}

	/**
	 * Remove review widgets
	 *
	 * @return void
	 */
	public function remove_review_widgets() {
		unregister_widget( 'WC_Widget_Recent_Reviews' );
		unregister_widget( 'WC_Widget_Top_Rated_Products' );
	}

	/**
	 * Remove review meta boxes
	 *
	 * @return void
	 */
	public function remove_review_meta_boxes() {
		remove_meta_box( 'commentsdiv', 'product', 'normal' );
		remove_meta_box( 'commentstatusdiv', 'product', 'normal' );
	}

	/**
	 * Hide review settings with CSS
	 *
	 * @return void
	 */
	public function hide_review_settings_css() {
		global $post_type;

		if ( 'product' === $post_type ) {
			echo '<style>
				#postcustom .acf-field[data-name="rating"],
				.reviews_tab,
				#woocommerce-product-data ul.wc-tabs li.reviews_options,
				#woocommerce-product-data .reviews_panel,
				.options_group.reviews,
				#average-rating,
				.star-rating,
				.woocommerce-review-link,
				.woocommerce-Reviews,
				#reviews,
				.related.products .star-rating {
					display: none !important;
				}
			</style>';
		}
	}

	/**
	 * Remove review-related scripts
	 *
	 * @return void
	 */
	public function remove_review_scripts() {
		// Dequeue star rating scripts
		wp_dequeue_script( 'wc-single-product' );
		wp_dequeue_script( 'jquery-rating' );
	}

	/**
	 * Remove reviews from product blocks
	 *
	 * @param string     $html Product grid item HTML.
	 * @param array      $data Product data.
	 * @param WC_Product $product Product object.
	 * @return string
	 */
	public function remove_reviews_from_blocks( $html, $data, $product ) {
		// Remove rating HTML from blocks
		$html = preg_replace( '/<div class="wc-block-grid__product-rating">.*?<\/div>/s', '', $html );
		return $html;
	}

	/**
	 * Remove reviews tab from product data
	 *
	 * @param array $tabs Product data tabs.
	 * @return array
	 */
	public function remove_reviews_product_data_tab( $tabs ) {
		if ( isset( $tabs['reviews'] ) ) {
			unset( $tabs['reviews'] );
		}
		return $tabs;
	}

	/**
	 * Remove review sorting options
	 *
	 * @param array $args Ordering args.
	 * @return array
	 */
	public function remove_review_sorting( $args ) {
		// Remove rating sorting option
		if ( isset( $args['meta_key'] ) && '_wc_average_rating' === $args['meta_key'] ) {
			unset( $args['meta_key'] );
			$args['orderby'] = 'date';
		}
		return $args;
	}
}