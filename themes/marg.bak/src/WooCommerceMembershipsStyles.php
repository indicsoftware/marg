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
		/* Open Access Read Button */
		.woocommerce-open-access-read {
			margin-top: 1em;
		}
		.open-access-read-btn {
			background-color: #2e7d32 !important;
			border-color: #2e7d32 !important;
			font-size: 16px !important;
		}
		.open-access-read-btn:hover {
			background-color: #1b5e20 !important;
			border-color: #1b5e20 !important;
		}

		/* Secure PDF Viewer Modal */
		.secure-pdf-modal {
			position: fixed;
			top: 0;
			left: 0;
			width: 100%;
			height: 100%;
			z-index: 999999;
			display: none;
		}
		.secure-pdf-modal-overlay {
			position: absolute;
			top: 0;
			left: 0;
			width: 100%;
			height: 100%;
			background: rgba(0, 0, 0, 0.7);
		}
		.secure-pdf-modal-content {
			position: relative;
			width: 90%;
			max-width: 900px;
			height: 90vh;
			margin: 5vh auto;
			background: #fff;
			border-radius: 8px;
			display: flex;
			flex-direction: column;
			overflow: hidden;
			box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
		}
		.secure-pdf-modal-header {
			display: flex;
			justify-content: space-between;
			align-items: center;
			padding: 12px 20px;
			background: #333;
			color: #fff;
		}
		.secure-pdf-modal-header h3 {
			margin: 0;
			font-size: 16px;
			color: #fff;
		}
		.secure-pdf-close {
			background: none;
			border: none;
			color: #fff;
			font-size: 28px;
			cursor: pointer;
			line-height: 1;
			padding: 0 4px;
		}
		.secure-pdf-close:hover {
			color: #ccc;
		}
		.secure-pdf-modal-body {
			flex: 1;
			overflow: auto;
			position: relative;
			background: #525659;
		}
		.pdf-controls {
			display: flex;
			align-items: center;
			justify-content: center;
			gap: 8px;
			padding: 8px 12px;
			background: #404040;
			flex-wrap: wrap;
		}
		.pdf-control-btn {
			background: #555;
			color: #fff;
			border: 1px solid #666;
			padding: 4px 12px;
			border-radius: 3px;
			cursor: pointer;
			font-size: 13px;
		}
		.pdf-control-btn:hover {
			background: #666;
		}
		#pdf-page-info {
			color: #ddd;
			font-size: 13px;
			margin: 0 8px;
		}
		.pdf-viewer-container {
			display: flex;
			justify-content: center;
			padding: 16px;
			min-height: 200px;
		}
		#pdf-canvas {
			max-width: 100%;
			box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
		}
		.pdf-loading {
			display: flex;
			flex-direction: column;
			align-items: center;
			justify-content: center;
			padding: 40px;
			color: #fff;
		}
		.pdf-loading .spinner {
			width: 40px;
			height: 40px;
			border: 4px solid rgba(255, 255, 255, 0.3);
			border-top-color: #fff;
			border-radius: 50%;
			animation: pdf-spin 0.8s linear infinite;
		}
		@keyframes pdf-spin {
			to { transform: rotate(360deg); }
		}
		.pdf-loading p {
			margin-top: 12px;
			color: #ccc;
		}
		@media (max-width: 768px) {
			.secure-pdf-modal-content {
				width: 98%;
				height: 95vh;
				margin: 2.5vh auto;
			}
			.pdf-controls {
				gap: 4px;
				padding: 6px 8px;
			}
			.pdf-control-btn {
				padding: 3px 8px;
				font-size: 12px;
			}
		}

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
