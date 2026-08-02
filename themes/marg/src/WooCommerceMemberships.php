<?php
/**
 * WooCommerce Memberships Integration
 *
 * @package TenUpTheme
 */

namespace TenUpTheme;

/**
 * WooCommerce Memberships Integration class.
 *
 * Handles custom functionality for WooCommerce Memberships,
 * including showing "View" buttons for members instead of "View Cart".
 */
class WooCommerceMemberships {

	/**
	 * Initialize the class and set up hooks.
	 *
	 * @return void
	 */
	public function __construct() {
		// Only initialize if WooCommerce and WooCommerce Memberships are active
		if ( ! $this->is_woocommerce_memberships_active() ) {
			return;
		}

		$this->init_hooks();
	}

	/**
	 * Check if WooCommerce and WooCommerce Memberships are active.
	 *
	 * @return bool
	 */
	private function is_woocommerce_memberships_active() {
		return class_exists( 'WooCommerce' ) && class_exists( 'WC_Memberships' );
	}

	/**
	 * Initialize WordPress hooks and filters.
	 *
	 * @return void
	 */
	private function init_hooks() {
		// Hook into WooCommerce single product page
		add_action( 'woocommerce_single_product_summary', [ $this, 'modify_single_product_buttons' ], 25 );

		// Hook into WooCommerce product loop
		add_action( 'woocommerce_after_shop_loop_item', [ $this, 'modify_shop_loop_buttons' ], 10 );

		// Filter the add to cart button text
		add_filter( 'woocommerce_product_add_to_cart_text', [ $this, 'modify_add_to_cart_text' ], 10, 2 );

		// Filter the add to cart URL
		add_filter( 'woocommerce_product_add_to_cart_url', [ $this, 'modify_add_to_cart_url' ], 10, 2 );

		// Add JavaScript variables for member status
		add_action( 'wp_footer', [ $this, 'add_member_status_script' ] );
	}

	/**
	 * Modify buttons on single product page for members.
	 *
	 * @return void
	 */
	public function modify_single_product_buttons() {
		global $product;

		if ( ! $product ) {
			return;
		}

		// For variable products, we'll handle this with JavaScript
		// For simple products, check if we should show member button
		if ( ! $product->is_type( 'variable' ) && $this->user_has_active_membership() && $this->should_show_member_button( $product ) ) {
			// Remove default add to cart button
			remove_action( 'woocommerce_single_product_summary', 'woocommerce_template_single_add_to_cart', 30 );

			// Add custom view button
			add_action( 'woocommerce_single_product_summary', [ $this, 'display_member_view_button' ], 30 );
		} elseif ( $product->is_type( 'variable' ) && $this->user_has_active_membership() ) {
			// For variable products, add member button after the add to cart form
			add_action( 'woocommerce_single_product_summary', [ $this, 'display_member_button_for_variable' ], 35 );
		}
	}

	/**
	 * Modify buttons on shop loop for members.
	 *
	 * @return void
	 */
	public function modify_shop_loop_buttons() {
		global $product;

		if ( ! $product ) {
			return;
		}

		// Check if current user has active membership and product should show member button
		if ( $this->user_has_active_membership() && $this->should_show_member_button( $product ) ) {
			// Remove default add to cart button
			remove_action( 'woocommerce_after_shop_loop_item', 'woocommerce_template_loop_add_to_cart', 10 );

			// Add custom view button
			add_action( 'woocommerce_after_shop_loop_item', [ $this, 'display_member_view_button_loop' ], 10 );
		}
	}

	/**
	 * Modify add to cart button text for members.
	 *
	 * @param string $text The button text.
	 * @param object $product The product object.
	 * @return string
	 */
	public function modify_add_to_cart_text( $text, $product ) {
		if ( $this->user_has_active_membership() && $this->should_show_member_button( $product ) ) {
			return __( 'View', 'tenup-theme' );
		}

		return $text;
	}

	/**
	 * Modify add to cart URL for members.
	 *
	 * @param string $url The add to cart URL.
	 * @param object $product The product object.
	 * @return string
	 */
	public function modify_add_to_cart_url( $url, $product ) {
		if ( $this->user_has_active_membership() && $this->should_show_member_button( $product ) ) {
			// Return the product URL for viewing
			return get_permalink( $product->get_id() );
		}

		return $url;
	}

	/**
	 * Display custom view button for members on single product page.
	 *
	 * @return void
	 */
	public function display_member_view_button() {
		global $product;

		if ( ! $product ) {
			return;
		}

		$download_url = $this->get_member_download_url( $product );

		if ( $download_url ) {
			?>
			<div class="woocommerce-member-view-button">
				<a href="<?php echo esc_url( $download_url ); ?>"
				   class="button alt wc-forward member-view-btn"
				   target="_blank">
					<?php esc_html_e( 'View PDF', 'tenup-theme' ); ?>
				</a>
			</div>
			<?php
		} else {
			?>
			<div class="woocommerce-member-view-button">
				<a href="<?php echo esc_url( get_permalink( $product->get_id() ) ); ?>"
				   class="button alt wc-forward member-view-btn">
					<?php esc_html_e( 'View', 'tenup-theme' ); ?>
				</a>
			</div>
			<?php
		}
	}

	/**
	 * Display member button for variable products (initially hidden, shown by JavaScript).
	 *
	 * @return void
	 */
	public function display_member_button_for_variable() {
		global $product;

		if ( ! $product ) {
			return;
		}

		// Get the first downloadable variation for the initial URL
		$download_url = $this->get_member_download_url( $product );

		?>
		<div class="woocommerce-member-view-button" style="display: none;">
			<a href="#"
			   class="button alt wc-forward member-view-btn"
			   data-variation-id="">
				<?php esc_html_e( 'View PDF', 'tenup-theme' ); ?>
			</a>
		</div>
		<?php
	}

	/**
	 * Display custom view button for members on shop loop.
	 *
	 * @return void
	 */
	public function display_member_view_button_loop() {
		global $product;

		if ( ! $product ) {
			return;
		}

		$download_url = $this->get_member_download_url( $product );

		if ( $download_url ) {
			?>
			<a href="<?php echo esc_url( $download_url ); ?>"
			   class="button alt wc-forward member-view-btn"
			   target="_blank">
				<?php esc_html_e( 'View PDF', 'tenup-theme' ); ?>
			</a>
			<?php
		} else {
			?>
			<a href="<?php echo esc_url( get_permalink( $product->get_id() ) ); ?>"
			   class="button alt wc-forward member-view-btn">
				<?php esc_html_e( 'View', 'tenup-theme' ); ?>
			</a>
			<?php
		}
	}

	/**
	 * Check if current user has active membership.
	 *
	 * @return bool
	 */
	private function user_has_active_membership() {
		if ( ! is_user_logged_in() ) {
			return false;
		}

		$user_id = get_current_user_id();

		// Check if user has active WooCommerce Memberships
		if ( function_exists( 'wc_memberships_is_user_active_member' ) ) {
			// Check for "Digital Subscription" membership plan (ID: 667)
			return wc_memberships_is_user_active_member( $user_id, 667 );
		}

		// Fallback: Check database directly
		global $wpdb;
		$membership = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$wpdb->posts}
			 WHERE post_author = %d
			 AND post_type = 'wc_user_membership'
			 AND post_status = 'wcm-active'
			 AND post_parent = 667",
			$user_id
		) );

		return ! empty( $membership );
	}

	/**
	 * Check if a specific product should show member view button.
	 *
	 * @param object $product The product object.
	 * @return bool
	 */
	private function should_show_member_button( $product ) {
		if ( ! $product ) {
			return false;
		}

		// For variable products, check if any variation is downloadable
		if ( $product->is_type( 'variable' ) ) {
			$variations = $product->get_available_variations();
			foreach ( $variations as $variation_data ) {
				$variation = wc_get_product( $variation_data['variation_id'] );
				if ( $variation && $variation->is_downloadable() ) {
					return true;
				}
			}
			return false;
		}

		// Check if this is the Ars Botanica digital variant (ID: 222)
		if ( $product->get_id() == 222 ) {
			return true;
		}

		// Check if this is a downloadable product
		if ( $product->is_downloadable() ) {
			return true;
		}

		// Check if this is a digital product (you can add more conditions here)
		$product_type = $product->get_type();
		if ( $product_type === 'simple' && $product->is_virtual() ) {
			return true;
		}

		return false;
	}

	/**
	 * Get download URL for member if product has downloadable files.
	 *
	 * @param object $product The product object.
	 * @return string|false
	 */
	private function get_member_download_url( $product ) {
		if ( ! $product ) {
			return false;
		}

		// For variable products, try to get the selected variation
		if ( $product->is_type( 'variable' ) ) {
			// Check if there's a selected variation in the URL or form data
			$selected_variation_id = $this->get_selected_variation_id( $product );

			if ( $selected_variation_id ) {
				$variation = wc_get_product( $selected_variation_id );
				if ( $variation && $variation->is_downloadable() ) {
					$downloads = $variation->get_downloads();
					if ( ! empty( $downloads ) ) {
						$first_download = reset( $downloads );
						if ( $first_download && isset( $first_download['file'] ) ) {
							return $first_download['file'];
						}
					}
				}
			}

			// Fallback: get the first downloadable variation
			$variations = $product->get_available_variations();
			foreach ( $variations as $variation_data ) {
				$variation = wc_get_product( $variation_data['variation_id'] );
				if ( $variation && $variation->is_downloadable() ) {
					$downloads = $variation->get_downloads();
					if ( ! empty( $downloads ) ) {
						$first_download = reset( $downloads );
						if ( $first_download && isset( $first_download['file'] ) ) {
							return $first_download['file'];
						}
					}
				}
			}

			return false;
		}

		// For simple products
		if ( ! $product->is_downloadable() ) {
			return false;
		}

		$downloads = $product->get_downloads();

		if ( empty( $downloads ) ) {
			return false;
		}

		// Get the first download URL
		$first_download = reset( $downloads );

		if ( $first_download && isset( $first_download['file'] ) ) {
			return $first_download['file'];
		}

		return false;
	}

	/**
	 * Get the selected variation ID from URL parameters or form data.
	 *
	 * @param object $product The product object.
	 * @return int|false
	 */
	private function get_selected_variation_id( $product ) {
		// Check URL parameters first
		if ( isset( $_GET['variation_id'] ) && ! empty( $_GET['variation_id'] ) ) {
			return intval( $_GET['variation_id'] );
		}

		// Check form data (for AJAX requests)
		if ( isset( $_POST['variation_id'] ) && ! empty( $_POST['variation_id'] ) ) {
			return intval( $_POST['variation_id'] );
		}

		// Check if there's a default variation
		$default_attributes = $product->get_default_attributes();
		if ( ! empty( $default_attributes ) ) {
			$variations = $product->get_available_variations();
			foreach ( $variations as $variation_data ) {
				$variation = wc_get_product( $variation_data['variation_id'] );
				if ( $variation ) {
					$variation_attributes = $variation->get_attributes();
					$match = true;
					foreach ( $default_attributes as $attribute_name => $default_value ) {
						if ( ! isset( $variation_attributes[ $attribute_name ] ) ||
							 $variation_attributes[ $attribute_name ] !== $default_value ) {
							$match = false;
							break;
						}
					}
					if ( $match ) {
						return $variation->get_id();
					}
				}
			}
		}

		return false;
	}

	/**
	 * Add JavaScript variables for member status.
	 *
	 * @return void
	 */
	public function add_member_status_script() {
		if ( ! is_product() ) {
			return;
		}

		$is_member = $this->user_has_active_membership();
		?>
		<script type="text/javascript">
			window.woocommerceMemberships = window.woocommerceMemberships || {};
			window.woocommerceMemberships.isMember = <?php echo $is_member ? 'true' : 'false'; ?>;
		</script>
		<?php
	}
}
