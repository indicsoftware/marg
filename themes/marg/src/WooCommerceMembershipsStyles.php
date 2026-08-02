<?php
/**
 * WooCommerce Memberships Styles
 *
 * @package TenUpTheme
 */

namespace TenUpTheme;

/**
 * WooCommerce Memberships Styles class.
 *
 * Handles custom CSS for WooCommerce Memberships functionality.
 */
class WooCommerceMembershipsStyles {

	/**
	 * Initialize the class and set up hooks.
	 *
	 * @return void
	 */
	public function __construct() {
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_styles' ] );
	}

	/**
	 * Enqueue custom styles for WooCommerce Memberships.
	 *
	 * @return void
	 */
	public function enqueue_styles() {
		// Only enqueue on WooCommerce pages
		if ( ! is_woocommerce() && ! is_cart() && ! is_checkout() && ! is_account_page() ) {
			return;
		}

		$css = $this->get_member_button_styles();

		wp_add_inline_style( 'woocommerce-general', $css );
	}

	/**
	 * Get custom CSS for member buttons.
	 *
	 * @return string
	 */
	private function get_member_button_styles() {
		return '
		/* Member View Button Styles */
		.member-view-btn {
			background-color: #0073aa !important;
			color: #ffffff !important;
			border: 1px solid #0073aa !important;
			padding: 12px 24px !important;
			font-weight: 600 !important;
			text-decoration: none !important;
			display: inline-block !important;
			text-align: center !important;
			border-radius: 4px !important;
			transition: all 0.3s ease !important;
		}

		.member-view-btn:hover {
			background-color: #005a87 !important;
			border-color: #005a87 !important;
			color: #ffffff !important;
			text-decoration: none !important;
			transform: translateY(-1px) !important;
			box-shadow: 0 2px 4px rgba(0, 115, 170, 0.3) !important;
		}

		.member-view-btn:focus {
			outline: 2px solid #0073aa !important;
			outline-offset: 2px !important;
		}

		/* Single Product Page Member Button */
		.woocommerce-member-view-button {
			margin-top: 1em;
		}

		/* Shop Loop Member Button */
		.woocommerce-loop-product__link + .member-view-btn {
			margin-top: 0.5em;
		}

		/* Responsive adjustments */
		@media (max-width: 768px) {
			.member-view-btn {
				padding: 10px 20px !important;
				font-size: 14px !important;
			}
		}

		/* Hide price elements for members on downloadable variations */
		body.member-has-downloadable-variation .woocommerce-variation-price,
		body.member-has-downloadable-variation .wc-memberships-member-discount-message,
		body.member-has-downloadable-variation .woocommerce-Price-amount,
		body.member-has-downloadable-variation .woocommerce-Price-currencySymbol,
		body.member-has-downloadable-variation .price,
		body.member-has-downloadable-variation .woocommerce-variation-price .price {
			display: none !important;
		}
		';
	}
}
