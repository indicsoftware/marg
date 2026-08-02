<?php
/**
 * WooCommerce Memberships Scripts
 *
 * @package TenUpTheme
 */

namespace TenUpTheme;

/**
 * WooCommerce Memberships Scripts class.
 *
 * Handles JavaScript for WooCommerce Memberships functionality.
 */
class WooCommerceMembershipsScripts {

	/**
	 * Initialize the class and set up hooks.
	 *
	 * @return void
	 */
	public function __construct() {
		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_scripts' ] );
	}

	/**
	 * Enqueue custom scripts for WooCommerce Memberships.
	 *
	 * @return void
	 */
	public function enqueue_scripts() {
		// Only enqueue on WooCommerce product pages
		if ( ! is_product() ) {
			return;
		}

		$js = $this->get_member_button_script();

		wp_add_inline_script( 'woocommerce', $js );
	}

	/**
	 * Get custom JavaScript for member buttons.
	 *
	 * @return string
	 */
	private function get_member_button_script() {
		return '
		(function($) {
			"use strict";

			$(document).ready(function() {
				// Handle variation selection for member buttons
				$(document).on("found_variation", "form.variations_form", function(event, variation) {
					updateMemberButton(variation);
				});

				// Handle variation reset
				$(document).on("reset_data", "form.variations_form", function() {
					updateMemberButton(null);
				});

				// Initial check on page load
				var currentVariation = $("form.variations_form").find("input[name=variation_id]").val();
				if (currentVariation) {
					var variationData = {
						variation_id: currentVariation,
						is_downloadable: $("form.variations_form").find("input[name=variation_id]").data("is-downloadable")
					};
					updateMemberButton(variationData);
				}
			});

			function updateMemberButton(variation) {
				var $form = $("form.variations_form");
				var $addToCartButton = $form.find(".single_add_to_cart_button");
				var $memberButtonContainer = $(".woocommerce-member-view-button");
				var $memberButton = $(".member-view-btn");
				var $priceContainer = $(".woocommerce-variation-price");
				var $availabilityContainer = $(".woocommerce-variation-availability");
				var $addToCartContainer = $(".woocommerce-variation-add-to-cart");

				// Additional price elements to hide for members
				var $memberDiscountElements = $(".wc-memberships-member-discount-message, .woocommerce-Price-amount, .woocommerce-Price-currencySymbol, .price, .woocommerce-variation-price .price");

				// Check if user is a member
				var isMember = window.woocommerceMemberships && window.woocommerceMemberships.isMember;

				if (isMember && variation && variation.is_downloadable) {
					// Show member button and hide add to cart container for downloadable variations
					$memberButtonContainer.show();
					$addToCartContainer.hide();

					// Hide price and availability for downloadable variations (members get it free)
					$priceContainer.hide();
					$availabilityContainer.hide();
					$memberDiscountElements.hide();

					// Add CSS class to body for additional price hiding
					$("body").addClass("member-has-downloadable-variation");

					// Update member button URL and variation ID
					if (variation.variation_id) {
						$memberButton.attr("data-variation-id", variation.variation_id);

						// Get download URL for this variation
						var downloadUrl = getDownloadUrlForVariation(variation.variation_id);
						if (downloadUrl) {
							$memberButton.attr("href", downloadUrl);
						}
					}
				} else {
					// Hide member button, show add to cart container
					$memberButtonContainer.hide();
					$addToCartContainer.show();

					// Show price and availability for non-downloadable variations
					$priceContainer.show();
					$availabilityContainer.show();
					$memberDiscountElements.show();

					// Remove CSS class from body
					$("body").removeClass("member-has-downloadable-variation");
				}
			}

			function getDownloadUrlForVariation(variationId) {
				// Return null to use the secure PDF viewer instead of direct download
				// The PDF viewer will handle the secure URL generation
				return null;
			}

			// Add member status to window object (this would be set by PHP)
			window.woocommerceMemberships = window.woocommerceMemberships || {};

		})(jQuery);
		';
	}
}
